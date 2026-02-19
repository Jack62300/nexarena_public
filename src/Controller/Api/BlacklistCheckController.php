<?php

namespace App\Controller\Api;

use App\Entity\BlacklistEntry;
use App\Service\BlacklistService;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class BlacklistCheckController extends AbstractController
{
    #[Route('/api/blacklist/check', name: 'api_blacklist_check', methods: ['GET'])]
    public function check(
        Request $request,
        BlacklistService $blacklist,
        CacheItemPoolInterface $cache,
    ): JsonResponse {
        // Rate limit: 10 req/min per IP
        $ip = $request->getClientIp();
        $cacheKey = 'bl_check_' . hash('sha256', (string) $ip);
        $item = $cache->getItem($cacheKey);
        $hits = $item->isHit() ? (int) $item->get() : 0;

        if ($hits >= 10) {
            return $this->json(['allowed' => true, 'message' => ''], 429);
        }

        $item->set($hits + 1);
        $item->expiresAfter(60);
        $cache->save($item);

        $type  = $request->query->get('type', '');
        $value = trim((string) $request->query->get('value', ''));

        if ($value === '') {
            return $this->json(['allowed' => true, 'message' => '']);
        }

        if ($type === BlacklistEntry::TYPE_USERNAME) {
            if ($blacklist->isUsernameBlacklisted($value)) {
                return $this->json(['allowed' => false, 'message' => "Ce pseudo n'est pas autorisé sur Nexarena."]);
            }
        } elseif ($type === BlacklistEntry::TYPE_EMAIL_DOMAIN) {
            if ($blacklist->isEmailDomainBlacklisted($value)) {
                return $this->json(['allowed' => false, 'message' => "Les inscriptions avec ce domaine email ne sont pas acceptées."]);
            }
        }

        return $this->json(['allowed' => true, 'message' => '']);
    }
}
