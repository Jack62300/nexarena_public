<?php

namespace App\Repository;

use App\Entity\AdminWebhook;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdminWebhook>
 */
class AdminWebhookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminWebhook::class);
    }

    public function findByEventType(string $eventType): ?AdminWebhook
    {
        return $this->findOneBy(['eventType' => $eventType]);
    }

    /**
     * @return array<string, AdminWebhook[]>
     */
    public function findAllGroupedByCategory(): array
    {
        $webhooks = $this->findBy([], ['category' => 'ASC', 'eventType' => 'ASC']);
        $grouped = [];

        foreach ($webhooks as $webhook) {
            $grouped[$webhook->getCategory()][] = $webhook;
        }

        return $grouped;
    }
}
