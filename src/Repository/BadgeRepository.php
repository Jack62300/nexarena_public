<?php

namespace App\Repository;

use App\Entity\Badge;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Badge>
 */
class BadgeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Badge::class);
    }

    /** @return Badge[] */
    public function findAllForAdmin(): array
    {
        return $this->createQueryBuilder('b')
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return Badge[] */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.isActive = true')
            ->orderBy('b.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findBySlug(string $slug): ?Badge
    {
        return $this->findOneBy(['slug' => $slug]);
    }
}
