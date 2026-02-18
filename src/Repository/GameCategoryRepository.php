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
     * Returns the most active game categories as a flat list, ordered by server count DESC.
     *
     * @return GameCategory[]
     */
    public function findMostActive(int $limit = 10): array
    {
        return $this->createQueryBuilder('gc')
            ->select('gc, COUNT(s.id) AS HIDDEN serverCount')
            ->join('gc.category', 'c')
            ->addSelect('c')
            ->leftJoin('App\Entity\Server', 's', 'WITH', 's.gameCategory = gc AND s.isActive = true AND s.isApproved = true')
            ->where('gc.isActive = :active')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('gc.id')
            ->orderBy('serverCount', 'DESC')
            ->addOrderBy('gc.position', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns the most active game categories (by active+approved server count), max $limit.
     * Grouped by parent category for display purposes.
     *
     * @return array<int, array{parent: \App\Entity\Category, games: GameCategory[]}>
     */
    public function findMostActiveGrouped(int $limit = 10): array
    {
        $results = $this->createQueryBuilder('gc')
            ->select('gc, COUNT(s.id) AS HIDDEN serverCount')
            ->join('gc.category', 'c')
            ->addSelect('c')
            ->leftJoin('App\Entity\Server', 's', 'WITH', 's.gameCategory = gc AND s.isActive = true AND s.isApproved = true')
            ->where('gc.isActive = :active')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('gc.id')
            ->orderBy('serverCount', 'DESC')
            ->addOrderBy('gc.position', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        // Group by parent category, preserving server-count order
        $groups = [];
        foreach ($results as $gc) {
            $parentId = $gc->getCategory()->getId();
            if (!isset($groups[$parentId])) {
                $groups[$parentId] = ['parent' => $gc->getCategory(), 'games' => []];
            }
            $groups[$parentId]['games'][] = $gc;
        }

        return array_values($groups);
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
