<?php

namespace App\Repository;

use App\Entity\DailyRandomBoost;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DailyRandomBoost>
 */
class DailyRandomBoostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DailyRandomBoost::class);
    }

    public function findByDate(\DateTimeInterface $date): ?DailyRandomBoost
    {
        return $this->createQueryBuilder('d')
            ->where('d.date = :date')
            ->setParameter('date', $date->format('Y-m-d'))
            ->getQuery()
            ->getOneOrNullResult();
    }
}
