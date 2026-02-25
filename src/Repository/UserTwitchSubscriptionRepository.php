<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserTwitchSubscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserTwitchSubscription>
 */
class UserTwitchSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserTwitchSubscription::class);
    }

    public function findByUser(User $user): ?UserTwitchSubscription
    {
        return $this->findOneBy(['user' => $user]);
    }

    /**
     * @return UserTwitchSubscription[]
     */
    public function findExpired(): array
    {
        return $this->createQueryBuilder('uts')
            ->where('uts.status = :status')
            ->andWhere('uts.expiresAt <= :now')
            ->setParameter('status', UserTwitchSubscription::STATUS_ACTIVE)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }
}
