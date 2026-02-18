<?php

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * @return Category[]
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('c.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Category[]
     */
    public function findAllActiveWithGameCategories(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.gameCategories', 'gc', 'WITH', 'gc.isActive = true')
            ->addSelect('gc')
            ->where('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('gc.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns the most active categories (by number of active+approved servers), max $limit.
     *
     * @return Category[]
     */
    public function findMostActiveWithGameCategories(int $limit = 10): array
    {
        // Step 1: get IDs ordered by server count DESC
        $rows = $this->createQueryBuilder('c')
            ->select('c.id AS id, COUNT(s.id) AS serverCount')
            ->leftJoin('App\Entity\Server', 's', 'WITH', 's.category = c AND s.isActive = true AND s.isApproved = true')
            ->where('c.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('c.id')
            ->orderBy('serverCount', 'DESC')
            ->addOrderBy('c.position', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getScalarResult();

        $ids = array_column($rows, 'id');

        if (empty($ids)) {
            return [];
        }

        // Step 2: fetch full objects with game categories, preserving the server-count order
        $categories = $this->createQueryBuilder('c')
            ->leftJoin('c.gameCategories', 'gc', 'WITH', 'gc.isActive = true')
            ->addSelect('gc')
            ->where('c.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->addOrderBy('gc.name', 'ASC')
            ->getQuery()
            ->getResult();

        // Re-order results to match the server-count order from step 1
        $ordered = [];
        foreach ($ids as $id) {
            foreach ($categories as $cat) {
                if ($cat->getId() === $id) {
                    $ordered[] = $cat;
                    break;
                }
            }
        }

        return $ordered;
    }
}
