<?php

namespace App\Twig;

use App\Repository\AccessLogRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AccessLogExtension extends AbstractExtension
{
    private ?int $cachedCount = null;

    public function __construct(
        private AccessLogRepository $accessLogRepo,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('blocked_access_count', $this->blockedAccessCount(...)),
        ];
    }

    /**
     * Retourne le nombre d'accès bloqués dans les 24 dernières heures.
     * Mis en cache en mémoire pour ne faire qu'une seule requête par rendu de page.
     */
    public function blockedAccessCount(): int
    {
        if ($this->cachedCount === null) {
            try {
                $this->cachedCount = $this->accessLogRepo->countBlockedLast24h();
            } catch (\Throwable) {
                $this->cachedCount = 0;
            }
        }

        return $this->cachedCount;
    }
}
