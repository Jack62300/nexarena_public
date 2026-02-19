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

        if (!$this->settings->getBool('admin_vpn_block_enabled', true)) {
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

        return <<<HTML
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Accès refusé — VPN détecté</title>
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
                }
                .card {
                    max-width: 440px;
                    width: 100%;
                    background: rgba(255,255,255,.03);
                    border: 1px solid rgba(231,74,59,.25);
                    border-radius: 20px;
                    padding: 44px 36px 40px;
                    text-align: center;
                    box-shadow: 0 0 60px rgba(231,74,59,.08), 0 24px 64px rgba(0,0,0,.5);
                }
                .icon {
                    width: 72px; height: 72px;
                    background: linear-gradient(135deg, rgba(231,74,59,.15), rgba(231,74,59,.05));
                    border: 1px solid rgba(231,74,59,.3);
                    border-radius: 18px;
                    display: flex; align-items: center; justify-content: center;
                    font-size: 34px;
                    margin: 0 auto 24px;
                }
                h1 { font-size: 22px; font-weight: 800; margin-bottom: 10px; letter-spacing: -.3px; }
                p { font-size: 14px; color: #9ca3af; line-height: 1.65; margin-bottom: 6px; }
                .badge {
                    display: inline-flex; align-items: center; gap: 6px;
                    margin-top: 24px;
                    padding: 8px 16px;
                    border-radius: 99px;
                    background: rgba(231,74,59,.1);
                    border: 1px solid rgba(231,74,59,.3);
                    color: #e74a3b;
                    font-size: 11px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase;
                }
                .ip {
                    margin-top: 16px;
                    font-size: 12px;
                    color: #4b5563;
                }
            </style>
        </head>
        <body>
            <div class="card">
                <div class="icon">🛡️</div>
                <h1>Accès refusé</h1>
                <p>L'accès à l'administration est <strong>interdit depuis un VPN, proxy ou réseau Tor</strong>.</p>
                <p>Désactivez votre VPN et réessayez.</p>
                <div class="badge">
                    <span>⚠</span> VPN / PROXY / TOR DÉTECTÉ
                </div>
                <div class="ip">IP : {$safeIp}</div>
            </div>
        </body>
        </html>
        HTML;
    }
}
