<?php

namespace App\EventListener;

use App\Entity\AccessLog;
use App\Service\IpSecurityService;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Listener global : vérifie VPN/proxy et pays pour CHAQUE visiteur.
 * S'applique à toutes les routes du site sauf les exclusions techniques.
 * Priority 2048 → s'exécute avant RouterListener (32), SecurityFirewall (8), scheb/2fa-bundle.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 2048)]
class IpAccessListener
{
    /** Routes qui ne doivent jamais être bloquées */
    private const SKIP_PREFIXES = [
        '/_profiler',
        '/_wdt',
        '/_dev',
        '/maintenance',
        '/deconnexion',
        '/widget/',
        '/oauth/',
        '/premium/crypto/webhook',
    ];

    /**
     * Déduplication des logs :
     * - Bloqués   → 1 entrée par IP max toutes les 2 min
     * - Autorisés → 1 entrée par IP max toutes les 30 min
     */
    private const DEDUP_BLOCKED_TTL = 120;   // 2 minutes
    private const DEDUP_ALLOWED_TTL = 1800;  // 30 minutes

    public function __construct(
        private IpSecurityService  $ipSecurity,
        private SettingsService    $settings,
        private LoggerInterface    $logger,
        private EntityManagerInterface $em,
        #[Autowire(service: 'cache.app')]
        private CacheInterface $logCache,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path    = $request->getPathInfo();

        foreach (self::SKIP_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        $ip         = $request->getClientIp() ?? '0.0.0.0';
        $remoteAddr = $request->server->get('REMOTE_ADDR');
        $method     = $request->getMethod();
        $userAgent  = $request->headers->get('User-Agent');

        // ── IP de confiance → bypass total (pas de log) ─────────────────────
        if ($this->ipSecurity->isTrustedIp($ip)) {
            $this->logger->debug('IpAccessListener: IP de confiance, bypass', ['ip' => $ip]);
            return;
        }

        $vpnBlockEnabled     = $this->settings->getBool('vpn_block_enabled', false);
        $countryBlockEnabled = $this->settings->getBool('country_block_enabled', false);

        // Si aucune règle active → pas de check, pas de log
        if (!$vpnBlockEnabled && !$countryBlockEnabled) {
            return;
        }

        $this->logger->debug('IpAccessListener check', [
            'ip'                    => $ip,
            'path'                  => $path,
            'vpn_block_enabled'     => $vpnBlockEnabled,
            'country_block_enabled' => $countryBlockEnabled,
        ]);

        // ── Récupérer les données IPQS (cache Redis, pas d'appel API supplémentaire) ──
        $raw         = $this->ipSecurity->getFullReport($ip);
        $apiError    = !empty($raw['error']);
        $vpnDetected = null;
        $countryCode = null;
        $fraudScore  = null;

        if (!$apiError) {
            $vpnDetected = !empty($raw['vpn']) || !empty($raw['active_vpn'])
                         || !empty($raw['tor']) || !empty($raw['active_tor']);
            $countryCode = isset($raw['country_code']) ? strtoupper((string) $raw['country_code']) : null;
            $fraudScore  = isset($raw['fraud_score']) ? (int) $raw['fraud_score'] : null;

            // Appliquer le seuil fraud_score
            $threshold = (int) $this->settings->get('vpn_fraud_score_threshold', '85');
            if ($threshold > 0 && $fraudScore !== null && $fraudScore >= $threshold) {
                $vpnDetected = true;
            }
        }

        $blocked     = false;
        $blockReason = null;

        // ── 1. Blocage VPN / Proxy / Tor ────────────────────────────────────
        if ($vpnBlockEnabled && $vpnDetected) {
            $this->logger->warning('IpAccessListener: VPN bloqué', ['ip' => $ip, 'path' => $path]);
            $blocked     = true;
            $blockReason = AccessLog::REASON_VPN;
            $event->setResponse($this->buildResponse('vpn', $ip, '', $request));
        }

        // ── 2. Blocage par pays ──────────────────────────────────────────────
        if (!$blocked && $countryBlockEnabled) {
            $allowed = $this->ipSecurity->isCountryAllowed($ip);
            $this->logger->debug('IpAccessListener country check', [
                'ip'      => $ip,
                'country' => $countryCode,
                'allowed' => $allowed,
            ]);
            if (!$allowed) {
                $this->logger->warning('IpAccessListener: Pays bloqué', [
                    'ip'      => $ip,
                    'country' => $countryCode,
                    'path'    => $path,
                ]);
                $blocked     = true;
                $blockReason = AccessLog::REASON_COUNTRY;
                $event->setResponse($this->buildResponse('country', $ip, $countryCode ?? '', $request));
            }
        }

        // ── Persistance du log (avec déduplication) ─────────────────────────
        $this->writeLog(
            ip:          $ip,
            remoteAddr:  $remoteAddr,
            path:        $path,
            method:      $method,
            userAgent:   $userAgent,
            countryCode: $countryCode,
            vpnDetected: $vpnDetected,
            fraudScore:  $fraudScore,
            blocked:     $blocked,
            blockReason: $blockReason,
        );
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function writeLog(
        string  $ip,
        ?string $remoteAddr,
        string  $path,
        string  $method,
        ?string $userAgent,
        ?string $countryCode,
        ?bool   $vpnDetected,
        ?int    $fraudScore,
        bool    $blocked,
        ?string $blockReason,
    ): void {
        // Déduplication via cache Redis
        $cacheKey = 'al_' . ($blocked ? 'b' : 'a') . '_' . md5($ip);
        $ttl      = $blocked ? self::DEDUP_BLOCKED_TTL : self::DEDUP_ALLOWED_TTL;

        try {
            $item = $this->logCache->getItem($cacheKey);
            if ($item->isHit()) {
                return; // déjà loggué récemment
            }
            $item->set(1)->expiresAfter($ttl);
            $this->logCache->save($item);
        } catch (\Throwable) {
            // Cache indisponible → on logue quand même
        }

        try {
            $log = (new AccessLog())
                ->setIp($ip)
                ->setRemoteAddr($remoteAddr)
                ->setPath(substr($path, 0, 255))
                ->setMethod(strtoupper(substr($method, 0, 10)))
                ->setCountryCode($countryCode ? substr($countryCode, 0, 2) : null)
                ->setVpnDetected($vpnDetected)
                ->setFraudScore($fraudScore)
                ->setBlocked($blocked)
                ->setBlockReason($blockReason)
                ->setUserAgent($userAgent ? substr($userAgent, 0, 500) : null);

            $this->em->persist($log);
            $this->em->flush();
        } catch (\Throwable $e) {
            $this->logger->error('IpAccessListener: échec écriture AccessLog', [
                'ip'    => $ip,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function isApiOrJsonRequest(\Symfony\Component\HttpFoundation\Request $request): bool
    {
        if (str_starts_with($request->getPathInfo(), '/api/')) {
            return true;
        }
        $accept = $request->headers->get('Accept', '');
        return str_contains($accept, 'application/json') || str_contains($accept, 'application/ld+json');
    }

    private function buildResponse(string $reason, string $ip, string $country = '', ?\Symfony\Component\HttpFoundation\Request $request = null): Response
    {
        // Routes API ou requêtes JSON → retourner JSON au lieu d'une page HTML
        if ($request !== null && $this->isApiOrJsonRequest($request)) {
            $message = match ($reason) {
                'vpn'     => 'Connexions via VPN, proxy ou Tor non autorisées.',
                'country' => 'Ce service n\'est pas disponible dans votre région' . ($country ? " ({$country})" : '') . '.',
                default   => 'Accès refusé.',
            };
            return new \Symfony\Component\HttpFoundation\JsonResponse(
                ['error' => 'access_denied', 'reason' => $reason, 'message' => $message],
                Response::HTTP_FORBIDDEN,
            );
        }

        $safeIp      = htmlspecialchars($ip, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeCountry = htmlspecialchars($country, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        if ($reason === 'vpn') {
            $title   = 'Connexion refusée';
            $message = 'Les connexions via <strong>VPN, proxy ou Tor</strong> ne sont pas autorisées sur ce site.';
            $badge   = '⚠ VPN / PROXY / TOR DÉTECTÉ';
            $detail  = '';
        } else {
            $title   = 'Accès non disponible';
            $message = 'Ce service n\'est pas disponible dans votre région.';
            $badge   = '⚠ PAYS NON AUTORISÉ';
            $detail  = $safeCountry ? "<div class=\"detail\">Pays détecté : <strong>{$safeCountry}</strong></div>" : '';
        }

        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{$title}</title>
            <style>
                *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
                body {
                    min-height: 100vh;
                    background: #0a1018;
                    color: #fff;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 24px;
                    position: relative;
                    overflow: hidden;
                }
                .bg-glow {
                    position: fixed; inset: 0; pointer-events: none;
                    background: radial-gradient(ellipse at 50% 0%, rgba(231,74,59,.06) 0%, transparent 65%);
                }
                .card {
                    position: relative; z-index: 1;
                    max-width: 460px; width: 100%;
                    background: rgba(255,255,255,.03);
                    border: 1px solid rgba(231,74,59,.2);
                    border-radius: 22px;
                    padding: 48px 40px 40px;
                    text-align: center;
                    box-shadow: 0 0 0 1px rgba(231,74,59,.04), 0 24px 64px rgba(0,0,0,.5);
                }
                .icon {
                    width: 76px; height: 76px;
                    background: linear-gradient(135deg, rgba(231,74,59,.15), rgba(231,74,59,.04));
                    border: 1px solid rgba(231,74,59,.25);
                    border-radius: 20px;
                    display: flex; align-items: center; justify-content: center;
                    font-size: 36px;
                    margin: 0 auto 24px;
                    box-shadow: 0 0 32px rgba(231,74,59,.1);
                }
                h1 { font-size: 24px; font-weight: 800; margin-bottom: 12px; letter-spacing: -.4px; }
                p { font-size: 14px; color: #9ca3af; line-height: 1.7; }
                .badge {
                    display: inline-flex; align-items: center; gap: 7px;
                    margin-top: 28px;
                    padding: 8px 18px;
                    border-radius: 99px;
                    background: rgba(231,74,59,.1);
                    border: 1px solid rgba(231,74,59,.25);
                    color: #e74a3b;
                    font-size: 11px; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase;
                }
                .detail { margin-top: 12px; font-size: 12px; color: #6b7280; }
                .ip { margin-top: 8px; font-size: 11px; color: #374151; }
            </style>
        </head>
        <body>
            <div class="bg-glow"></div>
            <div class="card">
                <div class="icon">🛡️</div>
                <h1>{$title}</h1>
                <p>{$message}</p>
                <div class="badge">{$badge}</div>
                {$detail}
                <div class="ip">{$safeIp}</div>
            </div>
        </body>
        </html>
        HTML;

        return new Response($html, Response::HTTP_FORBIDDEN, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
