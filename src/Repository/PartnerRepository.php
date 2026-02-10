<?php

namespace App\Repository;

use App\Entity\Partner;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Partner>
 */
class PartnerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Partner::class);
    }

    /**
     * @return Partner[]
     */
    public function findActiveByType(string $type): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = true')
            ->andWhere('p.type = :type')
            ->setParameter('type', $type)
            ->orderBy('p.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Partner[]
     */
    public function findAllForAdmin(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.type', 'ASC')
            ->addOrderBy('p.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
