<?php

namespace App\Repository;

use App\Entity\Badge;
use App\Entity\User;
use App\Entity\UserBadge;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserBadge>
 */
class UserBadgeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserBadge::class);
    }

    /** @return UserBadge[] */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('ub')
            ->join('ub.badge', 'b')
            ->where('ub.user = :user')
            ->andWhere('b.isActive = true')
            ->setParameter('user', $user)
            ->orderBy('ub.awardedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return UserBadge[] */
    public function findByBadge(Badge $badge): array
    {
        return $this->createQueryBuilder('ub')
            ->where('ub.badge = :badge')
            ->setParameter('badge', $badge)
            ->orderBy('ub.awardedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function userHasBadge(User $user, Badge $badge): bool
    {
        return (int) $this->createQueryBuilder('ub')
            ->select('COUNT(ub.id)')
            ->where('ub.user = :user')
            ->andWhere('ub.badge = :badge')
            ->setParameter('user', $user)
            ->setParameter('badge', $badge)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}
