<?php

namespace App\Service;

class NetworkValidationService
{
    private const PRIVATE_RANGES = [
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '127.0.0.0/8',
        '0.0.0.0/8',
        '169.254.0.0/16',
        '100.64.0.0/10',    // CGN
        '192.0.0.0/24',
        '192.0.2.0/24',     // TEST-NET-1
        '198.51.100.0/24',  // TEST-NET-2
        '203.0.113.0/24',   // TEST-NET-3
        '224.0.0.0/4',      // Multicast
        '240.0.0.0/4',      // Reserved
        '::1/128',
        'fc00::/7',
        'fe80::/10',
    ];

    public function isPrivateIp(string $ip): bool
    {
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    public function isValidPublicIp(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        return !$this->isPrivateIp($ip);
    }

    public function isValidWebhookUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['scheme'], $parsed['host'])) {
            return false;
        }

        // Must be HTTPS
        if (strtolower($parsed['scheme']) !== 'https') {
            return false;
        }

        $host = $parsed['host'];

        // Reject IP addresses in URLs (must use hostnames)
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        // Reject localhost variants
        $lower = strtolower($host);
        if ($lower === 'localhost' || str_ends_with($lower, '.local') || str_ends_with($lower, '.internal')) {
            return false;
        }

        // Resolve hostname and check IP
        $ips = gethostbynamel($host);
        if ($ips === false) {
            return false;
        }

        foreach ($ips as $ip) {
            if ($this->isPrivateIp($ip)) {
                return false;
            }
        }

        return true;
    }

    public function resolveAndValidateHost(string $host, int $port): bool
    {
        // For game server queries — allow IPs but block private ranges
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->isValidPublicIp($host);
        }

        $ips = gethostbynamel($host);
        if ($ips === false) {
            return false;
        }

        foreach ($ips as $ip) {
            if ($this->isPrivateIp($ip)) {
                return false;
            }
        }

        return true;
    }
}
