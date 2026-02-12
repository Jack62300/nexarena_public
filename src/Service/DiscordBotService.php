<?php

namespace App\Service;

class DiscordBotService
{
    public function __construct(
        private SettingsService $settings,
    ) {
    }

    private function getBotUrl(): string
    {
        return rtrim($this->settings->get('discord_bot_url', 'http://localhost:3050'), '/');
    }

    private function getApiKey(): string
    {
        return $this->settings->get('discord_bot_api_key', '');
    }

    private function request(string $method, string $path, ?array $body = null): ?array
    {
        $apiKey = $this->getApiKey();
        $url = $this->getBotUrl() . '/api' . $path;

        $opts = [
            'http' => [
                'method' => $method,
                'header' => "X-Api-Key: {$apiKey}\r\nContent-Type: application/json\r\nAccept: application/json\r\n",
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ];

        if ($body !== null) {
            $opts['http']['content'] = json_encode($body);
        }

        $context = stream_context_create($opts);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return null;
        }

        $statusCode = 200;
        if (isset($http_response_header[0])) {
            preg_match('/\d{3}/', $http_response_header[0], $matches);
            $statusCode = (int) ($matches[0] ?? 200);
        }

        $data = json_decode($response, true);

        if (!is_array($data) || $statusCode >= 400) {
            return null;
        }

        return $data;
    }

    public function isAvailable(): bool
    {
        $url = $this->getBotUrl() . '/health';
        $response = @file_get_contents($url, false, stream_context_create([
            'http' => ['timeout' => 3, 'ignore_errors' => true],
        ]));
        if ($response === false) {
            return false;
        }
        $data = json_decode($response, true);
        return isset($data['status']) && $data['status'] === 'ok';
    }

    // ===== GUILD =====

    public function getGuildInfo(): ?array
    {
        return $this->request('GET', '/guild');
    }

    public function isGuildMember(string $discordId): bool
    {
        $apiKey = $this->getApiKey();
        $url = $this->getBotUrl() . '/api/guild/members/' . $discordId;

        $response = @file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "X-Api-Key: {$apiKey}\r\nAccept: application/json\r\n",
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]));

        if ($response === false) {
            return false;
        }

        $data = json_decode($response, true);
        return $data['isMember'] ?? false;
    }

    // ===== MODERATION =====

    public function refreshBannedWords(): ?array
    {
        return $this->request('POST', '/moderation/refresh');
    }

    // ===== ANNOUNCEMENTS =====

    public function sendAnnouncement(string $channelId, array $embedData): ?array
    {
        return $this->request('POST', '/announcements/send', array_merge(
            ['channelId' => $channelId],
            $embedData
        ));
    }
}
