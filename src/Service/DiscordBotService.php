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

    private function isValidBotUrl(string $url): bool
    {
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['scheme'], $parsed['host'])) {
            return false;
        }

        return in_array($parsed['scheme'], ['http', 'https'], true);
    }

    private function request(string $method, string $path, ?array $body = null): ?array
    {
        $apiKey = $this->getApiKey();
        $baseUrl = $this->getBotUrl();

        if (!$this->isValidBotUrl($baseUrl)) {
            return null;
        }

        $url = $baseUrl . '/api' . $path;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'X-Api-Key: ' . $apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            return null;
        }

        $data = json_decode($response, true);

        return is_array($data) ? $data : null;
    }

    public function isAvailable(): bool
    {
        $baseUrl = $this->getBotUrl();
        if (!$this->isValidBotUrl($baseUrl)) {
            return false;
        }

        $url = $baseUrl . '/health';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

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
        if (!preg_match('/^\d{1,20}$/', $discordId)) {
            return false;
        }

        $data = $this->request('GET', '/guild/members/' . $discordId);
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
