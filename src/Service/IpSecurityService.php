<?php

namespace App\Service;

use App\Util\CurlHelper;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class IpSecurityService
{
    private const API_BASE = 'https://ipqualityscore.com/api/json/ip/';
    private const CACHE_TTL = 3600;

    // Résultat fail-open : on laisse passer en cas d'erreur API
    private const FAIL_OPEN_RESULT = [
        'success'    => false,
        'error'      => true,
        'vpn'        => false,
        'tor'        => false,
        'active_vpn' => false,
        'active_tor' => false,
        'proxy'      => false,
        'country_code' => '',
        'fraud_score' => 0,
    ];

    public function __construct(
        private SettingsService $settings,
        private CacheInterface $cache,
        private LoggerInterface $logger,
    ) {}

    /**
     * Appelle l'API IPQualityScore et retourne les données brutes.
     * Le résultat est mis en cache pendant 1 heure.
     */
    public function checkIp(string $ip): array
    {
        $cacheKey = 'ipqs_' . md5($ip);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($ip) {
            $item->expiresAfter(self::CACHE_TTL);

            $apiKey = $this->settings->get('ipqs_api_key', '');

            if (empty(trim($apiKey))) {
                $this->logger->warning('IpSecurityService: ipqs_api_key non configurée — VPN/country check désactivé.');
                return self::FAIL_OPEN_RESULT;
            }

            $url = self::API_BASE . urlencode($apiKey) . '/' . urlencode($ip);

            $ch = CurlHelper::createSecure($url);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error    = curl_error($ch);
            curl_close($ch);

            if ($error || $httpCode !== 200 || !$response) {
                $this->logger->error('IpSecurityService: IPQS API error', [
                    'ip'        => $ip,
                    'http_code' => $httpCode,
                    'error'     => $error,
                ]);
                return self::FAIL_OPEN_RESULT;
            }

            $data = json_decode($response, true);
            if (!is_array($data) || empty($data['success'])) {
                $this->logger->error('IpSecurityService: IPQS réponse invalide', [
                    'ip'       => $ip,
                    'response' => substr((string) $response, 0, 200),
                ]);
                return self::FAIL_OPEN_RESULT;
            }

            return $data;
        });
    }

    /**
     * Retourne true si l'IP est un VPN, proxy ou Tor (selon IPQS).
     */
    public function isVpnOrProxy(string $ip): bool
    {
        $data = $this->checkIp($ip);

        if (!empty($data['error'])) {
            return false; // fail-open
        }

        // On bloque uniquement les VPN/Tor actifs confirmés.
        // Le champ "proxy" est volontairement exclu : IPQS le retourne true
        // pour certains FAI grand public (Free, SFR…) générant des faux positifs.
        return !empty($data['active_vpn'])
            || !empty($data['active_tor'])
            || (!empty($data['vpn']) && !empty($data['tor']));
    }

    /**
     * Retourne le code pays ISO à 2 lettres de l'IP (ex: "FR", "US").
     */
    public function getCountryCode(string $ip): string
    {
        $data = $this->checkIp($ip);
        return strtoupper((string) ($data['country_code'] ?? ''));
    }

    /**
     * Retourne true si le pays de l'IP est dans la liste des pays autorisés.
     * Si la liste est vide → tous les pays sont autorisés.
     */
    public function isCountryAllowed(string $ip): bool
    {
        $allowedStr = $this->settings->get('allowed_countries', '');

        if (empty(trim($allowedStr))) {
            return true; // pas de restriction configurée
        }

        $allowed = array_filter(array_map(
            fn(string $c) => strtoupper(trim($c)),
            explode(',', $allowedStr)
        ));

        if (empty($allowed)) {
            return true;
        }

        $country = $this->getCountryCode($ip);

        if ($country === '') {
            // Pays indéterminable (API fail) → fail-open
            return true;
        }

        return in_array($country, $allowed, true);
    }

    /**
     * Retourne true si l'IP est dans la liste blanche de confiance.
     * Supporte les IPs exactes (ex: 82.66.56.201) et les plages CIDR (ex: 82.66.0.0/16).
     * Liste lue depuis le setting `trusted_ips` (une entrée par ligne).
     */
    public function isTrustedIp(string $ip): bool
    {
        $raw = $this->settings->get('trusted_ips', '');
        if (empty(trim($raw))) {
            return false;
        }

        $entries = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $raw)));

        foreach ($entries as $entry) {
            if ($this->ipMatchesEntry($ip, $entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vérifie si une IP correspond à une entrée (IP exacte ou CIDR).
     */
    private function ipMatchesEntry(string $ip, string $entry): bool
    {
        $entry = trim($entry);
        if ($entry === '') {
            return false;
        }

        // Plage CIDR (ex: 82.66.0.0/16)
        if (str_contains($entry, '/')) {
            return $this->ipInCidr($ip, $entry);
        }

        // IP exacte
        return $ip === $entry;
    }

    /**
     * Vérifie si une IP est dans une plage CIDR (IPv4 uniquement).
     */
    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr, 2);
        $bits = (int) $bits;

        $ipLong     = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false || $bits < 0 || $bits > 32) {
            return false;
        }

        $mask = $bits === 0 ? 0 : (~0 << (32 - $bits));

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }

    /**
     * Retourne les données complètes pour le diagnostic (commande app:check-ip).
     */
    public function getFullReport(string $ip): array
    {
        return $this->checkIp($ip);
    }

    /**
     * Supprime le cache pour une IP donnée.
     */
    public function clearCache(string $ip): void
    {
        $this->cache->delete('ipqs_' . md5($ip));
    }
}
