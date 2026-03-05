<?php

namespace App\EventListener;

use App\Entity\IpBan;
use App\Repository\IpBanRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Bloque les IPs bannies avant tout traitement.
 * Priority 10 → s'exécute après IpAccessListener (2048), avant Maintenance (5).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
class IpBanListener
{
    private const SKIP_PREFIXES = [
        '/_profiler',
        '/_wdt',
        '/_dev',
        '/widget/',
        '/robots.txt',
        '/sitemap.xml',
    ];

    /** User-Agent des bots de moteurs de recherche (ne pas bannir) */
    private const BOT_PATTERNS = [
        'Googlebot',
        'Google-InspectionTool',
        'AdsBot-Google',
        'Bingbot',
        'Applebot',
        'YandexBot',
        'DuckDuckBot',
    ];

    public function __construct(
        private IpBanRepository $ipBanRepo,
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

        // Ne pas bloquer les bots de moteurs de recherche
        $userAgent = $request->headers->get('User-Agent', '');
        foreach (self::BOT_PATTERNS as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                return;
            }
        }

        $ip = $request->getClientIp() ?? '0.0.0.0';

        $ban = $this->ipBanRepo->findActiveBanForIp($ip);

        if ($ban === null) {
            return;
        }

        $event->setResponse($this->buildResponse($ban, $ip));
    }

    private function buildResponse(IpBan $ban, string $ip): Response
    {
        $safeIp     = htmlspecialchars($ip, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeReason = $ban->getReason() !== null
            ? htmlspecialchars($ban->getReason(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            : 'Violation des conditions d\'utilisation.';

        $accentColor = '#dc2626';

        if ($ban->getType() === IpBan::TYPE_PERMANENT) {
            $badgeHtml  = '<div class="block-badge block-badge--red">Ban permanent</div>';
            $expireHtml = '';
        } else {
            $duration   = htmlspecialchars($ban->getFormattedDuration(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $badgeHtml  = '<div class="block-badge block-badge--orange">Ban temporaire &middot; ' . $duration . '</div>';
            $expireHtml = $ban->getExpiresAt() !== null
                ? '<div class="block-expire">Levee le <strong>' . $ban->getExpiresAt()->format('d/m/Y a H\hi') . '</strong></div>'
                : '';
        }

        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>Acces refuse — Nexarena</title>
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
                    background: radial-gradient(circle, rgba(220,38,38,.06) 0%, transparent 70%);
                }
                .block-logo {
                    position: relative; z-index: 1;
                    margin-bottom: 40px;
                    display: flex; align-items: center; gap: 10px;
                    text-decoration: none;
                }
                .block-logo img { width: 36px; height: 36px; border-radius: 8px; }
                .block-logo__text { font-size: 18px; font-weight: 700; color: #fff; letter-spacing: -.3px; }
                .block-card {
                    position: relative; z-index: 1;
                    max-width: 520px; width: 100%;
                    background: linear-gradient(165deg, rgba(255,255,255,.04) 0%, rgba(255,255,255,.01) 100%);
                    border: 1px solid rgba(255,255,255,.06);
                    border-radius: 20px;
                    overflow: hidden;
                    backdrop-filter: blur(20px);
                    box-shadow: 0 1px 0 0 rgba(255,255,255,.03) inset, 0 32px 80px rgba(0,0,0,.4);
                }
                .block-card__accent {
                    height: 3px;
                    background: linear-gradient(90deg, transparent, {$accentColor}, transparent);
                    opacity: .6;
                }
                .block-card__body { padding: 44px 40px 40px; text-align: center; }
                .block-icon {
                    width: 72px; height: 72px;
                    background: linear-gradient(145deg, rgba(220,38,38,.16), rgba(220,38,38,.04));
                    border: 1px solid rgba(220,38,38,.25);
                    border-radius: 18px;
                    display: flex; align-items: center; justify-content: center;
                    margin: 0 auto 28px;
                    box-shadow: 0 0 40px rgba(220,38,38,.08);
                }
                .block-subtitle {
                    font-size: 12px; font-weight: 600; color: {$accentColor};
                    text-transform: uppercase; letter-spacing: 1.5px;
                    margin-bottom: 20px;
                }
                .block-title {
                    font-size: 22px; font-weight: 800; color: #fff;
                    margin-bottom: 8px; letter-spacing: -.4px;
                }
                .block-reason {
                    font-size: 14px; color: #94a3b8; line-height: 1.75;
                    margin-bottom: 28px;
                }
                .block-reason em { color: #cbd5e1; font-style: normal; font-weight: 500; }
                .block-divider {
                    height: 1px;
                    background: linear-gradient(90deg, transparent, rgba(255,255,255,.06), transparent);
                    margin: 0 0 20px;
                }
                .block-badge {
                    display: inline-block;
                    padding: 7px 18px;
                    border-radius: 99px;
                    font-size: 11px; font-weight: 700; letter-spacing: .8px; text-transform: uppercase;
                    margin-bottom: 12px;
                }
                .block-badge--red { background: rgba(220,38,38,.1); border: 1px solid rgba(220,38,38,.25); color: #f87171; }
                .block-badge--orange { background: rgba(251,146,60,.1); border: 1px solid rgba(251,146,60,.25); color: #fb923c; }
                .block-expire { font-size: 13px; color: #64748b; margin-top: 8px; margin-bottom: 16px; }
                .block-expire strong { color: #94a3b8; }
                .block-contact {
                    font-size: 12px; color: #475569; margin-bottom: 16px;
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
                }
                .block-footer a { color: #45f882; text-decoration: none; font-weight: 500; }
                .block-footer a:hover { text-decoration: underline; }
                @media (max-width: 540px) {
                    .block-card__body { padding: 32px 24px 28px; }
                    .block-title { font-size: 19px; }
                }
            </style>
        </head>
        <body>
            <div class="bg-grid"></div>
            <div class="bg-glow"></div>
            <a href="/" class="block-logo">
                <img src="/assets/img/logo/new_logo_hd.png" alt="Nexarena">
                <span class="block-logo__text">Nexarena</span>
            </a>
            <div class="block-card">
                <div class="block-card__accent"></div>
                <div class="block-card__body">
                    <div class="block-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                    </div>
                    <div class="block-subtitle">Adresse IP bannie</div>
                    <h1 class="block-title">Acces refuse</h1>
                    <p class="block-reason">Votre adresse IP a ete bannie de cette plateforme.<br><em>{$safeReason}</em></p>
                    <div class="block-divider"></div>
                    {$badgeHtml}
                    {$expireHtml}
                    <div class="block-contact">Si vous pensez qu'il s'agit d'une erreur, contactez le support.</div>
                    <div class="block-ip">{$safeIp}</div>
                </div>
            </div>
            <div class="block-footer">&copy; Nexarena — <a href="/">Retour a l'accueil</a></div>
        </body>
        </html>
        HTML;

        return new Response($html, Response::HTTP_FORBIDDEN, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
