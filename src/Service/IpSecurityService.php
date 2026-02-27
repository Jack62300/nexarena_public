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
     * Normalise une adresse IP :
     * - IPv4-mapped IPv6 (::ffff:x.x.x.x) → IPv4 pure (x.x.x.x)
     * - Supprime les crochets IPv6 ([::1] → ::1)
     * - Retourne l'IP inchangée si elle est déjà propre.
     */
    public function normalizeIp(string $ip): string
    {
        $ip = trim($ip, '[] ');

        // ::ffff:x.x.x.x  ou  ::FFFF:x.x.x.x  → IPv4
        if (preg_match('/^::(?:ffff|FFFF):(\d{1,3}(?:\.\d{1,3}){3})$/i', $ip, $m)) {
            return $m[1];
        }

        return $ip;
    }

    /**
     * Appelle l'API IPQualityScore et retourne les données brutes.
     * Le résultat est mis en cache pendant 1 heure.
     */
    public function checkIp(string $ip): array
    {
        $ip       = $this->normalizeIp($ip);
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

            if ($error || $httpCode !== 200 || !is_string($response) || $response === '') {
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
     *
     * Champs vérifiés (du plus fiable au plus permissif) :
     *  - active_vpn / active_tor : confirmés actifs (IPQS premium, souvent false sur plan gratuit)
     *  - vpn / tor               : détectés VPN/Tor (disponible sur plan gratuit)
     *  - fraud_score             : score >= seuil configurable (défaut 85/100)
     *
     * Le champ "proxy" reste exclu : IPQS retourne true pour certains FAI
     * grand public (Free, SFR…) et génère de faux positifs.
     */
    public function isVpnOrProxy(string $ip): bool
    {
        $ip   = $this->normalizeIp($ip);
        $data = $this->checkIp($ip);

        if (!empty($data['error'])) {
            return false; // fail-open
        }

        // Seuil fraud_score configurable (0 = désactivé)
        $threshold = (int) $this->settings->get('vpn_fraud_score_threshold', '85');

        return !empty($data['active_vpn'])
            || !empty($data['active_tor'])
            || !empty($data['vpn'])
            || !empty($data['tor'])
            || ($threshold > 0 && ($data['fraud_score'] ?? 0) >= $threshold);
    }

    /**
     * Retourne le code pays ISO à 2 lettres de l'IP (ex: "FR", "US").
     */
    public function getCountryCode(string $ip): string
    {
        $ip   = $this->normalizeIp($ip);
        $data = $this->checkIp($ip);
        return strtoupper((string) ($data['country_code'] ?? ''));
    }

    /**
     * Retourne true si le pays de l'IP est dans la liste des pays autorisés.
     * Si la liste est vide → tous les pays sont autorisés.
     */
    public function isCountryAllowed(string $ip): bool
    {
        $ip         = $this->normalizeIp($ip);
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
        $ip  = $this->normalizeIp($ip);
        $raw = $this->settings->get('trusted_ips', '') ?? '';
        if (empty(trim($raw))) {
            return false;
        }

        $split = preg_split('/[\r\n,]+/', $raw);
        $entries = array_filter(array_map('trim', $split !== false ? $split : []));

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
     * Vérifie si une IP est dans une plage CIDR (IPv4 et IPv6).
     */
    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $prefixLen] = explode('/', $cidr, 2) + [1 => null];

        $isIpv6 = str_contains($ip, ':') || str_contains($subnet, ':');

        if ($isIpv6) {
            return $this->ipv6InCidr($ip, $subnet, (int) ($prefixLen ?? 128));
        }

        // ── IPv4 ─────────────────────────────────────────────────────────────
        $bits       = (int) ($prefixLen ?? 32);
        $ipLong     = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false || $bits < 0 || $bits > 32) {
            return false;
        }

        $mask = $bits === 0 ? 0 : (~0 << (32 - $bits));

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }

    /**
     * Vérifie si une adresse IPv6 est dans une plage CIDR IPv6.
     * Utilise inet_pton() pour une comparaison binaire correcte.
     */
    private function ipv6InCidr(string $ip, string $subnet, int $bits): bool
    {
        $ipBin     = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false || $bits < 0 || $bits > 128) {
            return false;
        }

        // Construire le masque binaire (16 octets)
        $fullBytes = intdiv($bits, 8);
        $remBits   = $bits % 8;

        $mask = str_repeat("\xff", $fullBytes);
        if ($remBits > 0) {
            $mask .= chr((0xff << (8 - $remBits)) & 0xff);
        }
        $mask = str_pad($mask, 16, "\x00");

        return ($ipBin & $mask) === ($subnetBin & $mask);
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

    /**
     * Vide tout le cache IPQS (pool dédié cache.ipqs).
     */
    public function clearAllCache(): bool
    {
        return $this->cache->clear();
    }
}
