<?php

namespace App\Repository;

use App\Entity\Server;
use App\Entity\ServerFavorite;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ServerFavorite>
 */
class ServerFavoriteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServerFavorite::class);
    }

    public function findOneByServerAndUser(Server $server, User $user): ?ServerFavorite
    {
        return $this->findOneBy(['server' => $server, 'user' => $user]);
    }

    /**
     * @return array{favorites: ServerFavorite[], total: int, pages: int}
     */
    public function findByUserPaginated(User $user, int $page = 1, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('f')
            ->leftJoin('f.server', 's')
            ->leftJoin('s.category', 'c')
            ->leftJoin('s.gameCategory', 'gc')
            ->addSelect('s', 'c', 'gc')
            ->where('f.user = :user')
            ->andWhere('s.isActive = true')
            ->andWhere('s.isApproved = true')
            ->setParameter('user', $user)
            ->orderBy('f.createdAt', 'DESC');

        $total = (int) $this->createQueryBuilder('f2')
            ->select('COUNT(f2.id)')
            ->leftJoin('f2.server', 's2')
            ->where('f2.user = :user')
            ->andWhere('s2.isActive = true')
            ->andWhere('s2.isApproved = true')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        $pages = max(1, (int) ceil($total / $limit));
        $page = max(1, min($page, $pages));

        $favorites = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['favorites' => $favorites, 'total' => $total, 'pages' => $pages];
    }

    public function countByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
