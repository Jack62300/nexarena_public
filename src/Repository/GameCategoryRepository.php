<?php

namespace App\Repository;

use App\Entity\Category;
use App\Entity\GameCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GameCategory>
 */
class GameCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameCategory::class);
    }

    /**
     * @return GameCategory[]
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('g.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return GameCategory[]
     */
    public function findByCategory(Category $category): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.category = :category')
            ->andWhere('g.isActive = :active')
            ->setParameter('category', $category)
            ->setParameter('active', true)
            ->orderBy('g.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
