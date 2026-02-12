<?php

namespace App\Repository;

use App\Entity\LivePromotion;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<LivePromotion> */
class LivePromotionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LivePromotion::class);
    }

    /** @return LivePromotion[] */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return LivePromotion[] */
    public function findCurrentlyActive(): array
    {
        $now = new \DateTimeImmutable();
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = true')
            ->andWhere('p.startDate <= :now')
            ->andWhere('p.endDate > :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }
}
