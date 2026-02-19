<?php

namespace App\EventListener;

use App\Service\CspNonceProvider;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::RESPONSE, priority: -10)]
class SecurityHeadersListener
{
    public function __construct(private CspNonceProvider $nonceProvider)
    {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();

        // ── HSTS (1 year + subdomains) ──
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');

        // ── X-Content-Type-Options ──
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // ── X-Frame-Options (skip for widget routes that set their own CSP) ──
        if (!$response->headers->has('Content-Security-Policy')) {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        // ── Referrer-Policy ──
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // ── Permissions-Policy ──
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(self)');

        // ── Hide server version ──
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('server');

        // ── Content-Security-Policy (skip if already set, e.g. widget routes) ──
        if (!$response->headers->has('Content-Security-Policy')) {
            $nonce = $this->nonceProvider->getNonce();

            $csp = implode('; ', [
                "default-src 'self'",
                // Nonce replaces 'unsafe-inline' for inline scripts.
                // External CDN scripts are allowed by domain.
                "script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net https://www.paypal.com https://www.sandbox.paypal.com",
                // Inline event handlers (onclick="…") are allowed — nonce-protected <script> blocks handle XSS risk.
                "script-src-attr 'unsafe-inline'",
                // Styles: keep unsafe-inline (inline styles are widespread and lower risk).
                "style-src 'self' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net 'unsafe-inline'",
                "img-src 'self' https: data:",
                "font-src 'self' https://cdnjs.cloudflare.com data:",
                "frame-src 'self' https://player.twitch.tv https://www.paypal.com https://www.sandbox.paypal.com https://discord.com",
                "connect-src 'self' https://www.paypal.com https://www.sandbox.paypal.com",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self' https://www.paypal.com https://www.sandbox.paypal.com https://steamcommunity.com",
                "frame-ancestors 'self'",
                "upgrade-insecure-requests",
            ]);
            $response->headers->set('Content-Security-Policy', $csp);
        }
    }
}
