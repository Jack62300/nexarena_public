<?php

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;

class TwitchService
{
    public function __construct(
        private SettingsService $settings,
        private CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * Get full Twitch data for a channel (cached 2 min).
     */
    public function getChannelData(string $channelName): ?array
    {
        $channelName = strtolower(trim($channelName));
        if ($channelName === '') {
            return null;
        }

        $cacheKey = 'twitch_channel_' . preg_replace('/[^a-z0-9_]/', '', $channelName);
        $item = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            return $item->get();
        }

        $token = $this->getAppAccessToken();
        if (!$token) {
            return null;
        }

        $clientId = $this->settings->get('twitch_client_id');
        if (!$clientId) {
            return null;
        }

        // Get user info
        $user = $this->apiGet('https://api.twitch.tv/helix/users?login=' . urlencode($channelName), $token, $clientId);
        if (!$user || empty($user['data'])) {
            return null;
        }

        $userData = $user['data'][0];
        $broadcasterId = $userData['id'];

        // Get stream (live status)
        $stream = $this->apiGet('https://api.twitch.tv/helix/streams?user_login=' . urlencode($channelName), $token, $clientId);
        $isLive = false;
        $streamData = null;
        if ($stream && !empty($stream['data'])) {
            $isLive = true;
            $s = $stream['data'][0];
            $streamData = [
                'title' => $s['title'] ?? '',
                'viewer_count' => $s['viewer_count'] ?? 0,
                'game_name' => $s['game_name'] ?? '',
                'thumbnail_url' => str_replace(['{width}', '{height}'], ['440', '248'], $s['thumbnail_url'] ?? ''),
                'started_at' => $s['started_at'] ?? '',
            ];
        }

        // Get followers
        $followers = $this->apiGet('https://api.twitch.tv/helix/channels/followers?broadcaster_id=' . $broadcasterId . '&first=1', $token, $clientId);
        $followerCount = $followers['total'] ?? 0;

        // Get recent VODs
        $videos = $this->apiGet('https://api.twitch.tv/helix/videos?user_id=' . $broadcasterId . '&type=archive&first=6', $token, $clientId);
        $vods = [];
        if ($videos && !empty($videos['data'])) {
            foreach ($videos['data'] as $v) {
                $vods[] = [
                    'id' => $v['id'],
                    'title' => $v['title'] ?? '',
                    'url' => $v['url'] ?? '',
                    'thumbnail_url' => str_replace(['%{width}', '%{height}'], ['320', '180'], $v['thumbnail_url'] ?? ''),
                    'duration' => $v['duration'] ?? '',
                    'view_count' => $v['view_count'] ?? 0,
                    'created_at' => $v['created_at'] ?? '',
                ];
            }
        }

        $data = [
            'channel' => [
                'id' => $broadcasterId,
                'login' => $userData['login'],
                'display_name' => $userData['display_name'] ?? $userData['login'],
                'profile_image_url' => $userData['profile_image_url'] ?? '',
                'description' => $userData['description'] ?? '',
            ],
            'is_live' => $isLive,
            'stream' => $streamData,
            'followers' => $followerCount,
            'vods' => $vods,
        ];

        $item->set($data);
        $item->expiresAfter(120); // 2 minutes cache
        $this->cache->save($item);

        return $data;
    }

    /**
     * Get an App Access Token (cached until expiry).
     */
    private function getAppAccessToken(): ?string
    {
        $cacheItem = $this->cache->getItem('twitch_app_token');
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $clientId = $this->settings->get('twitch_client_id', '');
        $clientSecret = $this->settings->get('twitch_client_secret', '');

        if ($clientId === '' || $clientSecret === '') {
            return null;
        }

        $ch = curl_init('https://id.twitch.tv/oauth2/token');
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'client_credentials',
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !is_string($response) || $response === '') {
            return null;
        }

        $json = json_decode($response, true);
        if (!$json || empty($json['access_token'])) {
            return null;
        }

        $token = $json['access_token'];
        $expiresIn = ($json['expires_in'] ?? 3600) - 60; // margin

        $cacheItem->set($token);
        $cacheItem->expiresAfter(max($expiresIn, 60));
        $this->cache->save($cacheItem);

        return $token;
    }

    /**
     * Generic Twitch Helix API GET request.
     */
    private function apiGet(string $url, string $token, string $clientId): ?array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Client-Id: ' . $clientId,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !is_string($response) || $response === '') {
            return null;
        }

        return json_decode($response, true);
    }
}
