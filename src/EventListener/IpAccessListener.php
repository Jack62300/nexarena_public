<?php

namespace App\EventListener;

use App\Service\IpSecurityService;
use App\Service\SettingsService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Listener global : vérifie VPN/proxy et pays pour CHAQUE visiteur.
 * S'applique à toutes les routes du site sauf les exclusions techniques.
 * Priority 9 → s'exécute avant MaintenanceListener (5) et AdminVpnBlockListener (8).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 9)]
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

        $path = $event->getRequest()->getPathInfo();

        foreach (self::SKIP_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        $ip = $event->getRequest()->getClientIp() ?? '0.0.0.0';

        // ── 1. Blocage VPN / Proxy / Tor (site entier) ──────────────────────
        if ($this->settings->getBool('vpn_block_enabled', false)) {
            if ($this->ipSecurity->isVpnOrProxy($ip)) {
                $event->setResponse($this->buildResponse('vpn', $ip));
                return;
            }
        }

        // ── 2. Blocage par pays ──────────────────────────────────────────────
        if ($this->settings->getBool('country_block_enabled', false)) {
            if (!$this->ipSecurity->isCountryAllowed($ip)) {
                $event->setResponse($this->buildResponse('country', $ip, $this->ipSecurity->getCountryCode($ip)));
                return;
            }
        }
    }

    private function buildResponse(string $reason, string $ip, string $country = ''): Response
    {
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
