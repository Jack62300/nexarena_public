<?php

namespace App\Service;

use App\Entity\Server;
use Psr\Log\LoggerInterface;

class GameServerQueryService
{
    public const TYPE_CFX = 'cfx';         // FiveM, RedM
    public const TYPE_SOURCE = 'source';    // Rust, CS2, GMod, ARK, DayZ, etc.
    public const TYPE_MINECRAFT = 'minecraft';

    public const TYPES = [
        self::TYPE_CFX => 'FiveM / RedM (CFX)',
        self::TYPE_SOURCE => 'Source Engine (Rust, CS2, GMod, ARK, DayZ...)',
        self::TYPE_MINECRAFT => 'Minecraft',
    ];

    public function __construct(
        private LoggerInterface $logger,
        private NetworkValidationService $networkValidator,
    ) {
    }

    /**
     * Auto-detect query protocol from server's category/subcategory names.
     * Admin override via category.queryType takes priority if set.
     */
    public function detectQueryType(Server $server): ?string
    {
        // 1. Admin override: if category has explicit queryType, use it
        $adminOverride = $server->getCategory()?->getQueryType();
        if ($adminOverride) {
            return $adminOverride;
        }

        // 2. Auto-detect from category + gameCategory + serverType names
        $parts = array_filter([
            $server->getCategory()?->getName(),
            $server->getGameCategory()?->getName(),
            $server->getServerType()?->getName(),
        ]);
        $names = mb_strtolower(implode(' ', $parts));

        // CFX (FiveM, RedM, GTA RP)
        if (preg_match('/fivem|five.?m|redm|red.?m|cfx|gta.?rp|gta.?online|gta.?v/i', $names)) {
            return self::TYPE_CFX;
        }

        // Minecraft
        if (preg_match('/minecraft|mc.?server|spigot|paper|bukkit|bungeecord|velocity/i', $names)) {
            return self::TYPE_MINECRAFT;
        }

        // Source Engine games
        if (preg_match('/rust|counter.?strike|cs\.?go|cs\.?2|garry|gmod|g.?mod|ark\b|ark.?survival|dayz|day.?z|unturned|arma|terraria|space.?engineer|team.?fortress|tf\.?2|left.?4.?dead|l4d|starbound|7.?days|conan|valheim|satisfactory|squad/i', $names)) {
            return self::TYPE_SOURCE;
        }

        return null;
    }

    /**
     * Query a game server and return status + player list.
     *
     * @return array{online: bool, players: int, maxPlayers: int, playerList: array, serverName: ?string, map: ?string}
     */
    public function query(string $type, string $ip, int $port): array
    {
        $empty = [
            'online' => false,
            'players' => 0,
            'maxPlayers' => 0,
            'playerList' => [],
            'serverName' => null,
            'map' => null,
        ];

        // SSRF protection: block private/reserved IP ranges
        if (!$this->networkValidator->resolveAndValidateHost($ip, $port)) {
            $this->logger->warning('Game server query blocked: private/reserved IP', ['ip' => $ip, 'port' => $port]);
            return $empty;
        }

        try {
            return match ($type) {
                self::TYPE_CFX => $this->queryCfx($ip, $port),
                self::TYPE_SOURCE => $this->querySource($ip, $port),
                self::TYPE_MINECRAFT => $this->queryMinecraft($ip, $port),
                default => $empty,
            };
        } catch (\Throwable $e) {
            $this->logger->warning('Game server query failed', [
                'type' => $type,
                'ip' => $ip,
                'port' => $port,
                'error' => $e->getMessage(),
            ]);

            return $empty;
        }
    }

    /**
     * FiveM / RedM (CFX) - HTTP JSON API
     */
    private function queryCfx(string $ip, int $port): array
    {
        $base = "http://{$ip}:{$port}";
        $timeout = 5;

        // Fetch server info
        $infoJson = $this->httpGet("{$base}/info.json", $timeout);
        $info = $infoJson ? json_decode($infoJson, true) : null;

        // Fetch players
        $playersJson = $this->httpGet("{$base}/players.json", $timeout);
        $players = $playersJson ? json_decode($playersJson, true) : null;

        if ($info === null && $players === null) {
            return [
                'online' => false,
                'players' => 0,
                'maxPlayers' => 0,
                'playerList' => [],
                'serverName' => null,
                'map' => null,
            ];
        }

        $maxPlayers = 0;
        $serverName = null;
        if (is_array($info)) {
            $maxPlayers = (int) ($info['vars']['sv_maxClients'] ?? $info['vars']['sv_maxclients'] ?? 0);
            $serverName = $info['vars']['sv_projectName'] ?? $info['vars']['sv_hostname'] ?? null;
        }

        $playerList = [];
        if (is_array($players)) {
            foreach ($players as $i => $p) {
                $playerList[] = [
                    'id' => $p['id'] ?? ($i + 1),
                    'name' => $p['name'] ?? 'Inconnu',
                    'ping' => $p['ping'] ?? 0,
                ];
            }
        }

        return [
            'online' => true,
            'players' => count($playerList),
            'maxPlayers' => $maxPlayers,
            'playerList' => $playerList,
            'serverName' => $serverName,
            'map' => null,
        ];
    }

    /**
     * Source Engine (A2S) - UDP protocol
     * Supports: Rust, CS2, Garry's Mod, ARK, DayZ, Unturned, Space Engineers, Terraria, Arma
     */
    private function querySource(string $ip, int $port): array
    {
        $timeout = 5;
        $socket = @fsockopen('udp://' . $ip, $port, $errno, $errstr, $timeout);
        if (!$socket) {
            return $this->offlineResult();
        }

        stream_set_timeout($socket, $timeout);

        // A2S_INFO query
        $info = $this->sourceA2sInfo($socket, $ip, $port, $timeout);

        // A2S_PLAYER query
        $playerList = $this->sourceA2sPlayer($socket, $ip, $port, $timeout);

        fclose($socket);

        if ($info === null) {
            return $this->offlineResult();
        }

        return [
            'online' => true,
            'players' => $info['players'],
            'maxPlayers' => $info['maxPlayers'],
            'playerList' => $playerList,
            'serverName' => $info['serverName'],
            'map' => $info['map'],
        ];
    }

    private function sourceA2sInfo($socket, string $ip, int $port, int $timeout): ?array
    {
        // Reopen socket for clean state
        fclose($socket);
        $socket = @fsockopen('udp://' . $ip, $port, $errno, $errstr, $timeout);
        if (!$socket) {
            return null;
        }
        stream_set_timeout($socket, $timeout);

        // A2S_INFO request: \xFF\xFF\xFF\xFF\x54Source Engine Query\x00
        $request = "\xFF\xFF\xFF\xFF\x54Source Engine Query\x00";
        fwrite($socket, $request);

        $response = fread($socket, 4096);
        fclose($socket);

        if (empty($response) || strlen($response) < 6) {
            return null;
        }

        // Check if it's a challenge response
        if ($response[4] === "\x41") {
            // Challenge response - resend with challenge
            $challenge = substr($response, 5, 4);
            $socket = @fsockopen('udp://' . $ip, $port, $errno, $errstr, $timeout);
            if (!$socket) {
                return null;
            }
            stream_set_timeout($socket, $timeout);
            fwrite($socket, "\xFF\xFF\xFF\xFF\x54Source Engine Query\x00" . $challenge);
            $response = fread($socket, 4096);
            fclose($socket);

            if (empty($response) || strlen($response) < 6) {
                return null;
            }
        }

        // Skip 4-byte header \xFF\xFF\xFF\xFF
        $data = substr($response, 4);
        if (empty($data)) {
            return null;
        }

        $type = ord($data[0]);
        $data = substr($data, 1);

        if ($type === 0x49) {
            // Source Engine response
            return $this->parseSourceInfo($data);
        } elseif ($type === 0x6D) {
            // GoldSource response (older games)
            return $this->parseGoldSourceInfo($data);
        }

        return null;
    }

    private function parseSourceInfo(string $data): ?array
    {
        $offset = 0;

        // Protocol
        $offset += 1;

        // Server name (null-terminated)
        $serverName = $this->readString($data, $offset);

        // Map (null-terminated)
        $map = $this->readString($data, $offset);

        // Game dir (null-terminated)
        $this->readString($data, $offset);

        // Game description (null-terminated)
        $this->readString($data, $offset);

        // App ID (2 bytes)
        $offset += 2;

        // Players, maxPlayers
        if ($offset + 2 > strlen($data)) {
            return null;
        }
        $players = ord($data[$offset++]);
        $maxPlayers = ord($data[$offset++]);

        return [
            'serverName' => $serverName,
            'map' => $map,
            'players' => $players,
            'maxPlayers' => $maxPlayers,
        ];
    }

    private function parseGoldSourceInfo(string $data): ?array
    {
        $offset = 0;

        // IP address (null-terminated)
        $this->readString($data, $offset);

        // Server name
        $serverName = $this->readString($data, $offset);

        // Map
        $map = $this->readString($data, $offset);

        // Game dir
        $this->readString($data, $offset);

        // Game description
        $this->readString($data, $offset);

        // Players, maxPlayers
        if ($offset + 2 > strlen($data)) {
            return null;
        }
        $players = ord($data[$offset++]);
        $maxPlayers = ord($data[$offset++]);

        return [
            'serverName' => $serverName,
            'map' => $map,
            'players' => $players,
            'maxPlayers' => $maxPlayers,
        ];
    }

    private function sourceA2sPlayer($socket, string $ip, int $port, int $timeout): array
    {
        // Step 1: Send challenge request
        $socket = @fsockopen('udp://' . $ip, $port, $errno, $errstr, $timeout);
        if (!$socket) {
            return [];
        }
        stream_set_timeout($socket, $timeout);

        $request = "\xFF\xFF\xFF\xFF\x55\xFF\xFF\xFF\xFF";
        fwrite($socket, $request);
        $response = fread($socket, 4096);

        if (empty($response) || strlen($response) < 9) {
            fclose($socket);
            return [];
        }

        // Challenge response should start with \xFF\xFF\xFF\xFF\x41
        if ($response[4] !== "\x41") {
            // Some servers respond directly with player data
            if ($response[4] === "\x44") {
                fclose($socket);
                return $this->parsePlayerResponse(substr($response, 5));
            }
            fclose($socket);
            return [];
        }

        $challenge = substr($response, 5, 4);
        fclose($socket);

        // Step 2: Send actual request with challenge
        $socket = @fsockopen('udp://' . $ip, $port, $errno, $errstr, $timeout);
        if (!$socket) {
            return [];
        }
        stream_set_timeout($socket, $timeout);

        fwrite($socket, "\xFF\xFF\xFF\xFF\x55" . $challenge);
        $response = fread($socket, 4096);
        fclose($socket);

        if (empty($response) || strlen($response) < 6 || $response[4] !== "\x44") {
            return [];
        }

        return $this->parsePlayerResponse(substr($response, 5));
    }

    private function parsePlayerResponse(string $data): array
    {
        $offset = 0;
        $playerCount = ord($data[$offset++]);
        $players = [];

        for ($i = 0; $i < $playerCount && $offset < strlen($data); $i++) {
            // Index
            $index = ord($data[$offset++]);

            // Name (null-terminated)
            $name = $this->readString($data, $offset);

            // Score (4 bytes, int32)
            if ($offset + 4 > strlen($data)) {
                break;
            }
            $offset += 4; // skip score

            // Duration (4 bytes, float)
            if ($offset + 4 > strlen($data)) {
                break;
            }
            $offset += 4; // skip duration

            if (!empty($name)) {
                $players[] = [
                    'id' => $index + 1,
                    'name' => $name,
                    'ping' => null, // Source Query A2S_PLAYER doesn't return ping
                ];
            }
        }

        return $players;
    }

    /**
     * Minecraft Server List Ping (SLP) - TCP
     */
    private function queryMinecraft(string $ip, int $port): array
    {
        $timeout = 5;
        $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);
        if (!$socket) {
            return $this->offlineResult();
        }

        stream_set_timeout($socket, $timeout);

        // Build handshake packet
        $handshake = $this->mcPacket(0x00, $this->mcVarInt(-1) . $this->mcString($ip) . pack('n', $port) . $this->mcVarInt(1));
        fwrite($socket, $handshake);

        // Status request
        fwrite($socket, $this->mcPacket(0x00, ''));

        // Read response
        $length = $this->mcReadVarInt($socket);
        if ($length <= 0) {
            fclose($socket);
            return $this->offlineResult();
        }

        $data = '';
        $remaining = $length;
        while ($remaining > 0) {
            $chunk = fread($socket, $remaining);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $data .= $chunk;
            $remaining -= strlen($chunk);
        }
        fclose($socket);

        // Skip packet ID
        $offset = 0;
        $this->mcDecodeVarInt($data, $offset);

        // Read JSON string length + content
        $jsonLength = $this->mcDecodeVarInt($data, $offset);
        $json = substr($data, $offset, $jsonLength);

        $status = json_decode($json, true);
        if (!is_array($status)) {
            return $this->offlineResult();
        }

        $playerList = [];
        if (isset($status['players']['sample']) && is_array($status['players']['sample'])) {
            foreach ($status['players']['sample'] as $i => $p) {
                $playerList[] = [
                    'id' => $i + 1,
                    'name' => $p['name'] ?? 'Inconnu',
                    'ping' => null,
                ];
            }
        }

        return [
            'online' => true,
            'players' => (int) ($status['players']['online'] ?? 0),
            'maxPlayers' => (int) ($status['players']['max'] ?? 0),
            'playerList' => $playerList,
            'serverName' => isset($status['description']) ? $this->mcCleanMotd($status['description']) : null,
            'map' => null,
        ];
    }

    // --- Helpers ---

    private function offlineResult(): array
    {
        return [
            'online' => false,
            'players' => 0,
            'maxPlayers' => 0,
            'playerList' => [],
            'serverName' => null,
            'map' => null,
        ];
    }

    private function httpGet(string $url, int $timeout): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Nexarena/1.0',
        ]);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($result === false || $httpCode !== 200) {
            return null;
        }

        return $result;
    }

    private function readString(string $data, int &$offset): string
    {
        $end = strpos($data, "\x00", $offset);
        if ($end === false) {
            $str = substr($data, $offset);
            $offset = strlen($data);
            return $str;
        }
        $str = substr($data, $offset, $end - $offset);
        $offset = $end + 1;
        return $str;
    }

    // Minecraft protocol helpers
    private function mcVarInt(int $value): string
    {
        $result = '';
        $value &= 0xFFFFFFFF;
        for ($i = 0; $i < 5; $i++) {
            $byte = $value & 0x7F;
            $value >>= 7;
            if ($value !== 0) {
                $byte |= 0x80;
            }
            $result .= chr($byte);
            if ($value === 0) {
                break;
            }
        }
        return $result;
    }

    private function mcString(string $str): string
    {
        return $this->mcVarInt(strlen($str)) . $str;
    }

    private function mcPacket(int $id, string $data): string
    {
        $body = $this->mcVarInt($id) . $data;
        return $this->mcVarInt(strlen($body)) . $body;
    }

    private function mcReadVarInt($socket): int
    {
        $value = 0;
        $shift = 0;
        for ($i = 0; $i < 5; $i++) {
            $byte = fread($socket, 1);
            if ($byte === false || $byte === '') {
                return -1;
            }
            $b = ord($byte);
            $value |= ($b & 0x7F) << $shift;
            if (($b & 0x80) === 0) {
                break;
            }
            $shift += 7;
        }
        return $value;
    }

    private function mcDecodeVarInt(string $data, int &$offset): int
    {
        $value = 0;
        $shift = 0;
        for ($i = 0; $i < 5; $i++) {
            if ($offset >= strlen($data)) {
                break;
            }
            $b = ord($data[$offset++]);
            $value |= ($b & 0x7F) << $shift;
            if (($b & 0x80) === 0) {
                break;
            }
            $shift += 7;
        }
        return $value;
    }

    private function mcCleanMotd(mixed $description): string
    {
        if (is_string($description)) {
            return preg_replace('/§[0-9a-fk-or]/i', '', $description);
        }
        if (is_array($description) && isset($description['text'])) {
            $text = $description['text'];
            if (isset($description['extra']) && is_array($description['extra'])) {
                foreach ($description['extra'] as $extra) {
                    $text .= $extra['text'] ?? '';
                }
            }
            return preg_replace('/§[0-9a-fk-or]/i', '', $text);
        }
        return '';
    }
}
