<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\WheelSpin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WheelSpin>
 */
class WheelSpinRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WheelSpin::class);
    }

    /**
     * @return WheelSpin[]
     */
    public function findByUser(User $user, int $limit = 20): array
    {
        return $this->createQueryBuilder('ws')
            ->andWhere('ws.user = :user')
            ->setParameter('user', $user)
            ->orderBy('ws.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countPaidByUserToday(User $user): int
    {
        $today = new \DateTimeImmutable('today midnight');

        return (int) $this->createQueryBuilder('ws')
            ->select('COUNT(ws.id)')
            ->andWhere('ws.user = :user')
            ->andWhere('ws.type = :type')
            ->andWhere('ws.createdAt >= :today')
            ->setParameter('user', $user)
            ->setParameter('type', WheelSpin::TYPE_PAID)
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
