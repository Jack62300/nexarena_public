<?php

namespace App\EventListener;

use App\Service\SettingsService;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
class ApiRateLimiterListener
{
    public function __construct(
        private SettingsService $settings,
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $limit = $this->settings->getInt('api_rate_limit', 60);
        if ($limit <= 0) {
            return;
        }

        // Rate limit by API token (from URL) or by client IP
        $pathParts = explode('/', trim($request->getPathInfo(), '/'));
        $rawKey = $pathParts[3] ?? $request->getClientIp() ?? 'unknown';
        $key = 'api_rl_' . md5($rawKey);

        $item = $this->cache->getItem($key);
        $data = $item->isHit() ? $item->get() : null;

        $now = time();

        // Fixed-window: reset counter each minute
        if (!is_array($data) || $now >= ($data['reset'] ?? 0)) {
            $data = ['count' => 0, 'reset' => $now + 60];
        }

        $data['count']++;
        $remaining = max(0, $limit - $data['count']);
        $retryAfter = max(1, $data['reset'] - $now);

        // Save counter
        $item->set($data);
        $item->expiresAfter($retryAfter + 1);
        $this->cache->save($item);

        if ($data['count'] > $limit) {
            $event->setResponse(new JsonResponse([
                'error' => 'Rate limit exceeded.',
                'retry_after' => $retryAfter,
            ], 429, [
                'X-RateLimit-Limit' => (string) $limit,
                'X-RateLimit-Remaining' => '0',
                'Retry-After' => (string) $retryAfter,
            ]));
        }
    }
}
