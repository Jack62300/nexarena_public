<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\VoteReward;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VoteReward>
 */
class VoteRewardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VoteReward::class);
    }

    public function sumTokensEarnedByUserInMonth(User $user, int $month, int $year): float
    {
        $startDate = new \DateTimeImmutable("$year-$month-01 00:00:00");
        $endDate = $startDate->modify('first day of next month');

        $result = $this->createQueryBuilder('vr')
            ->select('SUM(vr.tokensEarned)')
            ->where('vr.user = :user')
            ->andWhere('vr.createdAt >= :start')
            ->andWhere('vr.createdAt < :end')
            ->setParameter('user', $user)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    public function countByUserInDay(User $user): int
    {
        $startOfDay = new \DateTimeImmutable('today 00:00:00');

        return (int) $this->createQueryBuilder('vr')
            ->select('COUNT(vr.id)')
            ->where('vr.user = :user')
            ->andWhere('vr.createdAt >= :start')
            ->setParameter('user', $user)
            ->setParameter('start', $startOfDay)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
