<?php

namespace App\Repository;

use App\Entity\Server;
use App\Entity\TwitchSubscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TwitchSubscription>
 */
class TwitchSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TwitchSubscription::class);
    }

    public function findByServer(Server $server): ?TwitchSubscription
    {
        return $this->findOneBy(['server' => $server]);
    }

    public function findActiveByServer(Server $server): ?TwitchSubscription
    {
        $sub = $this->findByServer($server);
        if ($sub && $sub->isActive()) {
            return $sub;
        }
        return null;
    }

    /**
     * @return TwitchSubscription[]
     */
    public function findExpiringSoon(\DateTimeImmutable $before): array
    {
        return $this->createQueryBuilder('ts')
            ->where('ts.status = :status')
            ->andWhere('ts.expiresAt <= :before')
            ->andWhere('ts.autoRenew = true')
            ->setParameter('status', TwitchSubscription::STATUS_ACTIVE)
            ->setParameter('before', $before)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return int[] Server IDs with active Twitch subscriptions
     */
    public function findActiveServerIds(): array
    {
        $rows = $this->createQueryBuilder('ts')
            ->select('IDENTITY(ts.server) AS sid')
            ->where('ts.status = :status')
            ->andWhere('ts.expiresAt > :now')
            ->setParameter('status', TwitchSubscription::STATUS_ACTIVE)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getScalarResult();

        return array_map(fn($r) => (int) $r['sid'], $rows);
    }

    /**
     * @return TwitchSubscription[]
     */
    public function findExpired(): array
    {
        return $this->createQueryBuilder('ts')
            ->where('ts.status = :status')
            ->andWhere('ts.expiresAt <= :now')
            ->setParameter('status', TwitchSubscription::STATUS_ACTIVE)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }
}
