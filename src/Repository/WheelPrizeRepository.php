<?php

namespace App\Repository;

use App\Entity\WheelPrize;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WheelPrize>
 */
class WheelPrizeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WheelPrize::class);
    }

    /**
     * @return WheelPrize[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('wp')
            ->orderBy('wp.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
