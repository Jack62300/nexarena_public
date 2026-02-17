<?php

namespace App\Repository;

use App\Entity\Category;
use App\Entity\GameCategory;
use App\Entity\Server;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Server>
 */
class ServerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Server::class);
    }

    /**
     * @return Server[]
     */
    public function findByOwner(User $user): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.owner = :user')
            ->setParameter('user', $user)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByToken(string $token): ?Server
    {
        return $this->findOneBy(['apiToken' => $token]);
    }

    /**
     * @return Server[]
     */
    public function findTopByMonthlyVotes(int $limit = 3): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.isActive = true')
            ->andWhere('s.isApproved = true')
            ->orderBy('s.monthlyVotes', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Server[]
     */
    public function findByGameCategory(GameCategory $gc, int $limit = 5): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.gameCategory = :gc')
            ->andWhere('s.isActive = true')
            ->andWhere('s.isApproved = true')
            ->setParameter('gc', $gc)
            ->orderBy('s.monthlyVotes', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Server[]
     */
    public function findAllApprovedActive(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.isActive = true')
            ->andWhere('s.isApproved = true')
            ->orderBy('s.monthlyVotes', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Server[]
     */
    public function findAllForAdmin(?Category $category = null, ?bool $isApproved = null, ?bool $isActive = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.category', 'c')
            ->leftJoin('s.gameCategory', 'gc')
            ->leftJoin('s.owner', 'o')
            ->addSelect('c', 'gc', 'o')
            ->orderBy('s.createdAt', 'DESC');

        if ($category !== null) {
            $qb->andWhere('s.category = :category')->setParameter('category', $category);
        }
        if ($isApproved !== null) {
            $qb->andWhere('s.isApproved = :approved')->setParameter('approved', $isApproved);
        }
        if ($isActive !== null) {
            $qb->andWhere('s.isActive = :active')->setParameter('active', $isActive);
        }

        return $qb->getQuery()->getResult();
    }

    public function resetAllMonthlyVotes(): int
    {
        return $this->createQueryBuilder('s')
            ->update()
            ->set('s.monthlyVotes', 0)
            ->getQuery()
            ->execute();
    }

    /**
     * @return Server[]
     */
    public function findByCategory(Category $category, int $limit = 50): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.category = :category')
            ->andWhere('s.isActive = true')
            ->andWhere('s.isApproved = true')
            ->setParameter('category', $category)
            ->orderBy('s.monthlyVotes', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{servers: Server[], total: int, pages: int}
     */
    public function findByCategoryPaginated(Category $category, int $page = 1, int $perPage = 10): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.tags', 'tg')
            ->addSelect('tg')
            ->where('s.category = :category')
            ->andWhere('s.isActive = true')
            ->andWhere('s.isApproved = true')
            ->setParameter('category', $category)
            ->orderBy('s.monthlyVotes', 'DESC');

        $total = (int) $this->createQueryBuilder('s2')
            ->select('COUNT(s2.id)')
            ->where('s2.category = :category')
            ->andWhere('s2.isActive = true')
            ->andWhere('s2.isApproved = true')
            ->setParameter('category', $category)
            ->getQuery()
            ->getSingleScalarResult();

        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));

        $servers = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return ['servers' => $servers, 'total' => $total, 'pages' => $pages];
    }

    /**
     * @return array{servers: Server[], total: int, pages: int}
     */
    public function findByGameCategoryPaginated(GameCategory $gc, int $page = 1, int $perPage = 10): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.tags', 'tg')
            ->addSelect('tg')
            ->where('s.gameCategory = :gc')
            ->andWhere('s.isActive = true')
            ->andWhere('s.isApproved = true')
            ->setParameter('gc', $gc)
            ->orderBy('s.monthlyVotes', 'DESC');

        $total = (int) $this->createQueryBuilder('s2')
            ->select('COUNT(s2.id)')
            ->where('s2.gameCategory = :gc')
            ->andWhere('s2.isActive = true')
            ->andWhere('s2.isApproved = true')
            ->setParameter('gc', $gc)
            ->getQuery()
            ->getSingleScalarResult();

        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));

        $servers = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return ['servers' => $servers, 'total' => $total, 'pages' => $pages];
    }

    /**
     * @return Server[]
     */
    public function findTopByMonthlyVotesWithDetails(int $limit = 10): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.category', 'c')
            ->leftJoin('s.gameCategory', 'gc')
            ->addSelect('c', 'gc')
            ->where('s.isActive = true')
            ->andWhere('s.isApproved = true')
            ->orderBy('s.monthlyVotes', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Server[]
     */
    public function findFeatured(): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.category', 'c')
            ->leftJoin('s.gameCategory', 'gc')
            ->addSelect('c', 'gc')
            ->where('s.isFeatured = true')
            ->andWhere('s.isActive = true')
            ->andWhere('s.isApproved = true')
            ->orderBy('s.featuredPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Server[]
     */
    public function findFeaturedForAdmin(): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.category', 'c')
            ->leftJoin('s.gameCategory', 'gc')
            ->leftJoin('s.owner', 'o')
            ->addSelect('c', 'gc', 'o')
            ->where('s.isFeatured = true')
            ->orderBy('s.featuredPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Server[]
     */
    public function searchByName(string $query, int $limit = 10): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.category', 'c')
            ->leftJoin('s.owner', 'o')
            ->addSelect('c', 'o')
            ->where('s.name LIKE :q')
            ->andWhere('s.isActive = true')
            ->andWhere('s.isApproved = true')
            ->setParameter('q', '%' . $query . '%')
            ->orderBy('s.monthlyVotes', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, int> gameCategoryId => server count
     */
    public function countActiveByGameCategory(): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('IDENTITY(s.gameCategory) AS gcId, COUNT(s.id) AS cnt')
            ->where('s.isActive = true')
            ->andWhere('s.isApproved = true')
            ->andWhere('s.gameCategory IS NOT NULL')
            ->groupBy('s.gameCategory')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['gcId']] = (int) $row['cnt'];
        }

        return $map;
    }

    /** @return Server[] */
    public function findActiveByOwner(User $user): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.category', 'c')
            ->leftJoin('s.gameCategory', 'gc')
            ->addSelect('c', 'gc')
            ->where('s.owner = :user')
            ->andWhere('s.isActive = true')
            ->andWhere('s.isApproved = true')
            ->setParameter('user', $user)
            ->orderBy('s.monthlyVotes', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByOwner(User $user): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.owner = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
