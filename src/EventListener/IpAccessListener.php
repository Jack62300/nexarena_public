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
        '/robots.txt',
        '/sitemap.xml',
    ];

    /** User-Agent des bots de moteurs de recherche (bypass VPN/country) */
    private const BOT_PATTERNS = [
        'Googlebot',
        'Google-InspectionTool',
        'AdsBot-Google',
        'Mediapartners-Google',
        'APIs-Google',
        'Google-Site-Verification',
        'Storebot-Google',
        'GoogleOther',
        'Google Favicon',
        'Bingbot',
        'Slurp',
        'DuckDuckBot',
        'Baiduspider',
        'YandexBot',
        'Applebot',
        'facebookexternalhit',
        'Twitterbot',
        'LinkedInBot',
        'Pinterestbot',
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

        $ip         = $this->ipSecurity->normalizeIp($request->getClientIp() ?? '0.0.0.0');
        $remoteAddr = $request->server->get('REMOTE_ADDR');
        $method     = $request->getMethod();
        $userAgent  = $request->headers->get('User-Agent');

        // ── IP de confiance → bypass total (pas de log) ─────────────────────
        if ($this->ipSecurity->isTrustedIp($ip)) {
            $this->logger->debug('IpAccessListener: IP de confiance, bypass', ['ip' => $ip]);
            return;
        }

        // ── Bot moteur de recherche → bypass pour permettre l'indexation ──
        // Vérification 1 : User-Agent (rapide)
        // Vérification 2 : DNS inversé (fallback si UA modifié par proxy/CDN, résultat caché 24h)
        if ($this->isSearchEngineBot($userAgent) || $this->isVerifiedSearchEngineBotByIp($ip)) {
            $this->logger->debug('IpAccessListener: bot SEO détecté, bypass', [
                'ip' => $ip,
                'ua' => $userAgent,
            ]);
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

    /**
     * Vérifie si l'IP appartient à un moteur de recherche via DNS inversé (méthode recommandée par Google).
     * Résultat caché 24h pour éviter les lookups DNS répétés.
     */
    private function isVerifiedSearchEngineBotByIp(string $ip): bool
    {
        $cacheKey = 'seo_bot_ip_' . str_replace([':', '.'], '_', $ip);

        try {
            $item = $this->logCache->getItem($cacheKey);
            if ($item->isHit()) {
                return (bool) $item->get();
            }

            $isBot = $this->verifySearchEngineDns($ip);
            $item->set($isBot)->expiresAfter(86400); // 24h
            $this->logCache->save($item);

            if ($isBot) {
                $this->logger->info('IpAccessListener: bot vérifié par DNS', ['ip' => $ip]);
            }

            return $isBot;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * DNS inversé + vérification avant : confirme que l'IP appartient à Google ou Bing.
     * @see https://developers.google.com/search/docs/crawling-indexing/verifying-googlebot
     */
    private function verifySearchEngineDns(string $ip): bool
    {
        $hostname = @gethostbyaddr($ip);
        if (!$hostname || $hostname === $ip) {
            return false;
        }

        $isKnownBot = str_ends_with($hostname, '.googlebot.com')
            || str_ends_with($hostname, '.google.com')
            || str_ends_with($hostname, '.search.msn.com');

        if (!$isKnownBot) {
            return false;
        }

        // Vérification avant : le hostname doit résoudre vers la même IP
        $resolved = gethostbyname($hostname);

        return $resolved === $ip;
    }

    private function isSearchEngineBot(?string $userAgent): bool
    {
        if ($userAgent === null || $userAgent === '') {
            return false;
        }

        foreach (self::BOT_PATTERNS as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                return true;
            }
        }

        return false;
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
        $siteName    = $this->settings->get('site_name', 'Nexarena');

        if ($reason === 'vpn') {
            $title       = 'Connexion bloquee';
            $subtitle    = 'VPN / Proxy / Tor detecte';
            $message     = 'Les connexions via <strong>VPN, proxy ou reseau Tor</strong> ne sont pas autorisees sur cette plateforme. Veuillez desactiver votre VPN et recharger la page.';
            $iconSvg     = '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#e74a3b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></svg>';
            $accentColor = '#e74a3b';
            $detail      = '';
        } else {
            $title       = 'Region non autorisee';
            $subtitle    = 'Restriction geographique';
            $message     = 'Ce service n\'est pas disponible dans votre region. L\'acces est restreint a certains pays uniquement.';
            $iconSvg     = '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>';
            $accentColor = '#f59e0b';
            $detail      = $safeCountry ? "<div class=\"block-detail\"><span class=\"block-detail__label\">Pays detecte</span><span class=\"block-detail__value\">{$safeCountry}</span></div>" : '';
        }

        $safeSiteName = htmlspecialchars($siteName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>{$title} — {$safeSiteName}</title>
            <link rel="icon" type="image/png" href="/assets/img/logo/new_logo_hd.png">
            <style>
                *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
                body {
                    min-height: 100vh;
                    background: #080c14;
                    color: #e2e8f0;
                    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    padding: 24px;
                    position: relative;
                    overflow: hidden;
                }
                .bg-grid {
                    position: fixed; inset: 0; pointer-events: none;
                    background-image:
                        linear-gradient(rgba(255,255,255,.015) 1px, transparent 1px),
                        linear-gradient(90deg, rgba(255,255,255,.015) 1px, transparent 1px);
                    background-size: 60px 60px;
                }
                .bg-glow {
                    position: fixed; pointer-events: none;
                    width: 600px; height: 600px;
                    top: -200px; left: 50%; transform: translateX(-50%);
                    background: radial-gradient(circle, {$accentColor}08 0%, transparent 70%);
                }
                .bg-glow-2 {
                    position: fixed; pointer-events: none;
                    width: 400px; height: 400px;
                    bottom: -100px; right: -100px;
                    background: radial-gradient(circle, rgba(69,248,130,.03) 0%, transparent 70%);
                    border-radius: 50%;
                }
                .block-logo {
                    position: relative; z-index: 1;
                    margin-bottom: 40px;
                    display: flex; align-items: center; gap: 10px;
                    text-decoration: none;
                }
                .block-logo img {
                    width: 36px; height: 36px; border-radius: 8px;
                }
                .block-logo__text {
                    font-size: 18px; font-weight: 700; color: #fff;
                    letter-spacing: -.3px;
                }
                .block-card {
                    position: relative; z-index: 1;
                    max-width: 520px; width: 100%;
                    background: linear-gradient(165deg, rgba(255,255,255,.04) 0%, rgba(255,255,255,.01) 100%);
                    border: 1px solid rgba(255,255,255,.06);
                    border-radius: 20px;
                    padding: 0;
                    overflow: hidden;
                    backdrop-filter: blur(20px);
                    box-shadow: 0 1px 0 0 rgba(255,255,255,.03) inset, 0 32px 80px rgba(0,0,0,.4);
                }
                .block-card__accent {
                    height: 3px;
                    background: linear-gradient(90deg, transparent, {$accentColor}, transparent);
                    opacity: .6;
                }
                .block-card__body {
                    padding: 44px 40px 40px;
                    text-align: center;
                }
                .block-icon {
                    width: 72px; height: 72px;
                    background: linear-gradient(145deg, {$accentColor}18, {$accentColor}06);
                    border: 1px solid {$accentColor}30;
                    border-radius: 18px;
                    display: flex; align-items: center; justify-content: center;
                    margin: 0 auto 28px;
                    box-shadow: 0 0 40px {$accentColor}0a;
                }
                .block-title {
                    font-size: 22px; font-weight: 800; color: #fff;
                    margin-bottom: 6px; letter-spacing: -.4px;
                }
                .block-subtitle {
                    font-size: 12px; font-weight: 600; color: {$accentColor};
                    text-transform: uppercase; letter-spacing: 1.5px;
                    margin-bottom: 20px;
                }
                .block-message {
                    font-size: 14px; color: #94a3b8; line-height: 1.75;
                    margin-bottom: 28px;
                }
                .block-message strong { color: #cbd5e1; font-weight: 600; }
                .block-divider {
                    height: 1px;
                    background: linear-gradient(90deg, transparent, rgba(255,255,255,.06), transparent);
                    margin: 0 0 20px;
                }
                .block-detail {
                    display: inline-flex; align-items: center; gap: 8px;
                    background: rgba(255,255,255,.03);
                    border: 1px solid rgba(255,255,255,.06);
                    border-radius: 10px;
                    padding: 10px 18px;
                    margin-bottom: 16px;
                }
                .block-detail__label {
                    font-size: 11px; color: #64748b;
                    text-transform: uppercase; letter-spacing: .5px; font-weight: 600;
                }
                .block-detail__value {
                    font-size: 13px; font-weight: 700; color: #fff;
                    font-family: 'SF Mono', 'Fira Code', monospace;
                }
                .block-ip {
                    display: inline-flex; align-items: center; gap: 6px;
                    font-size: 11px; color: #475569;
                    font-family: 'SF Mono', 'Fira Code', monospace;
                }
                .block-ip::before {
                    content: ''; display: block;
                    width: 6px; height: 6px; border-radius: 50%;
                    background: {$accentColor}; opacity: .5;
                }
                .block-footer {
                    position: relative; z-index: 1;
                    margin-top: 32px;
                    font-size: 12px; color: #334155;
                    text-align: center;
                }
                .block-footer a {
                    color: #45f882; text-decoration: none; font-weight: 500;
                }
                .block-footer a:hover { text-decoration: underline; }
                @media (max-width: 540px) {
                    .block-card__body { padding: 32px 24px 28px; }
                    .block-title { font-size: 19px; }
                    .block-message { font-size: 13px; }
                }
            </style>
        </head>
        <body>
            <div class="bg-grid"></div>
            <div class="bg-glow"></div>
            <div class="bg-glow-2"></div>
            <a href="/" class="block-logo">
                <img src="/assets/img/logo/new_logo_hd.png" alt="{$safeSiteName}">
                <span class="block-logo__text">{$safeSiteName}</span>
            </a>
            <div class="block-card">
                <div class="block-card__accent"></div>
                <div class="block-card__body">
                    <div class="block-icon">{$iconSvg}</div>
                    <div class="block-subtitle">{$subtitle}</div>
                    <h1 class="block-title">{$title}</h1>
                    <p class="block-message">{$message}</p>
                    <div class="block-divider"></div>
                    {$detail}
                    <div class="block-ip">{$safeIp}</div>
                </div>
            </div>
            <div class="block-footer">
                &copy; {$safeSiteName} — <a href="/">Retour a l'accueil</a>
            </div>
        </body>
        </html>
        HTML;

        return new Response($html, Response::HTTP_FORBIDDEN, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
