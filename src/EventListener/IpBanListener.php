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
 * Priority 10 → s'exécute en premier (avant IpAccessListener: 9, Maintenance: 5).
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

    public function __construct(
        private IpBanRepository $ipBanRepo,
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

        if ($ban->getType() === IpBan::TYPE_PERMANENT) {
            $durationText = '<span class="tag tag--red">Ban permanent</span>';
            $expireText   = '';
        } else {
            $durationText = '<span class="tag tag--orange">Ban temporaire · ' . htmlspecialchars($ban->getFormattedDuration(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
            $expireText   = $ban->getExpiresAt() !== null
                ? '<div class="expire">Levée le <strong>' . $ban->getExpiresAt()->format('d/m/Y à H\hi') . '</strong></div>'
                : '';
        }

        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Accès refusé — IP bannie</title>
            <style>
                *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
                body {
                    min-height: 100vh;
                    background: #0a0e14;
                    color: #fff;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 24px;
                }
                .bg-glow {
                    position: fixed; inset: 0; pointer-events: none;
                    background: radial-gradient(ellipse at 50% 0%, rgba(220,38,38,.07) 0%, transparent 65%);
                }
                .card {
                    position: relative; z-index: 1;
                    max-width: 480px; width: 100%;
                    background: rgba(255,255,255,.03);
                    border: 1px solid rgba(220,38,38,.25);
                    border-radius: 22px;
                    padding: 48px 40px 40px;
                    text-align: center;
                    box-shadow: 0 0 0 1px rgba(220,38,38,.05), 0 24px 64px rgba(0,0,0,.6);
                }
                .icon {
                    width: 80px; height: 80px;
                    background: linear-gradient(135deg, rgba(220,38,38,.18), rgba(220,38,38,.05));
                    border: 1px solid rgba(220,38,38,.3);
                    border-radius: 20px;
                    display: flex; align-items: center; justify-content: center;
                    font-size: 38px;
                    margin: 0 auto 24px;
                    box-shadow: 0 0 40px rgba(220,38,38,.12);
                }
                h1 { font-size: 22px; font-weight: 800; margin-bottom: 10px; letter-spacing: -.3px; }
                .reason { font-size: 14px; color: #9ca3af; line-height: 1.7; margin-bottom: 24px; }
                .tag {
                    display: inline-block;
                    padding: 6px 16px;
                    border-radius: 99px;
                    font-size: 12px; font-weight: 700; letter-spacing: .8px; text-transform: uppercase;
                    margin-bottom: 12px;
                }
                .tag--red { background: rgba(220,38,38,.12); border: 1px solid rgba(220,38,38,.3); color: #f87171; }
                .tag--orange { background: rgba(251,146,60,.12); border: 1px solid rgba(251,146,60,.3); color: #fb923c; }
                .expire { font-size: 13px; color: #6b7280; margin-top: 8px; }
                .ip { margin-top: 20px; font-size: 11px; color: #374151; }
                .separator { height: 1px; background: rgba(255,255,255,.06); margin: 24px 0; }
                .contact { font-size: 12px; color: #4b5563; }
            </style>
        </head>
        <body>
            <div class="bg-glow"></div>
            <div class="card">
                <div class="icon">🚫</div>
                <h1>Accès refusé</h1>
                <p class="reason">Votre adresse IP a été bannie de cette plateforme.<br><em>{$safeReason}</em></p>
                {$durationText}
                {$expireText}
                <div class="separator"></div>
                <div class="contact">Si vous pensez qu'il s'agit d'une erreur, contactez le support.</div>
                <div class="ip">{$safeIp}</div>
            </div>
        </body>
        </html>
        HTML;

        return new Response($html, Response::HTTP_FORBIDDEN, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
