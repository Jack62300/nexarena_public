<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class IpSecurityService
{
    public function __construct(
        private SettingsService $settings,
        private CacheInterface $cache,
        private LoggerInterface $logger,
    ) {
    }

    public function checkIp(string $ip): array
    {
        $cacheKey = 'ip_security_' . md5($ip);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($ip) {
            $item->expiresAfter(3600);

            $url = 'https://api.ipgeolocation.io/v2/security?' . http_build_query([
                'apiKey' => $this->settings->get('ipgeolocation_api_key'),
                'ip' => $ip,
            ]);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error || $httpCode !== 200 || !$response) {
                $this->logger->error('IpSecurityService: API error', [
                    'ip' => $ip,
                    'http_code' => $httpCode,
                    'error' => $error,
                ]);

                return ['is_vpn' => false, 'is_proxy' => false, 'is_tor' => false, 'error' => true];
            }

            $data = json_decode($response, true);
            if (!is_array($data)) {
                $this->logger->error('IpSecurityService: Invalid JSON response', ['ip' => $ip]);

                return ['is_vpn' => false, 'is_proxy' => false, 'is_tor' => false, 'error' => true];
            }

            return $data;
        });
    }

    public function isVpnOrProxy(string $ip): bool
    {
        $data = $this->checkIp($ip);

        if (!empty($data['error'])) {
            return false;
        }

        return !empty($data['is_vpn']) || !empty($data['is_proxy']) || !empty($data['is_tor']);
    }
}
