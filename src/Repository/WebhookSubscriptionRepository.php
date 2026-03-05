<?php

namespace App\Repository;

use App\Entity\Server;
use App\Entity\WebhookSubscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WebhookSubscription>
 */
class WebhookSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebhookSubscription::class);
    }

    public function findByServer(Server $server): ?WebhookSubscription
    {
        return $this->findOneBy(['server' => $server]);
    }

    /**
     * @return WebhookSubscription[]
     */
    public function findExpired(): array
    {
        return $this->createQueryBuilder('ws')
            ->where('ws.status = :status')
            ->andWhere('ws.expiresAt <= :now')
            ->setParameter('status', WebhookSubscription::STATUS_ACTIVE)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }
}
