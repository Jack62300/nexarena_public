<?php

namespace App\EventListener;

use App\Service\IpSecurityService;
use App\Service\SettingsService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Bloque l'accès à l'administration depuis un VPN, proxy ou réseau Tor.
 * S'applique à toutes les routes commençant par /admin.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 8)]
class AdminVpnBlockListener
{
    private const ADMIN_PREFIX = '/admin';

    private const SKIP_PREFIXES = [
        '/_profiler',
        '/_wdt',
        '/_dev',
    ];

    public function __construct(
        private IpSecurityService $ipSecurity,
        private SettingsService $settings,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!$this->settings->getBool('admin_vpn_block_enabled', false)) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();

        if (!str_starts_with($path, self::ADMIN_PREFIX)) {
            return;
        }

        foreach (self::SKIP_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        $ip = $event->getRequest()->getClientIp() ?? '0.0.0.0';

        // IP de confiance → bypass
        if ($this->ipSecurity->isTrustedIp($ip)) {
            return;
        }

        if ($this->ipSecurity->isVpnOrProxy($ip)) {
            $event->setResponse(new Response(
                $this->buildBlockPage($ip),
                Response::HTTP_FORBIDDEN,
                ['Content-Type' => 'text/html; charset=UTF-8']
            ));
        }
    }

    private function buildBlockPage(string $ip): string
    {
        $safeIp = htmlspecialchars($ip, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $accentColor = '#e74a3b';

        return <<<HTML
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>Acces refuse — Administration — Nexarena</title>
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
                    background: linear-gradient(145deg, {$accentColor}18, {$accentColor}06);
                    border: 1px solid {$accentColor}30;
                    border-radius: 18px;
                    display: flex; align-items: center; justify-content: center;
                    margin: 0 auto 28px;
                    box-shadow: 0 0 40px {$accentColor}0a;
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
                .block-hint {
                    font-size: 13px; color: #64748b; margin-bottom: 16px;
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
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="{$accentColor}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></svg>
                    </div>
                    <div class="block-subtitle">VPN / Proxy / Tor detecte</div>
                    <h1 class="block-title">Administration bloquee</h1>
                    <p class="block-message">L'acces a l'administration est <strong>interdit depuis un VPN, proxy ou reseau Tor</strong>. Cette restriction est active pour proteger le panneau d'administration.</p>
                    <div class="block-divider"></div>
                    <div class="block-hint">Desactivez votre VPN et rechargez la page.</div>
                    <div class="block-ip">{$safeIp}</div>
                </div>
            </div>
            <div class="block-footer">&copy; Nexarena — <a href="/">Retour a l'accueil</a></div>
        </body>
        </html>
        HTML;
    }
}
