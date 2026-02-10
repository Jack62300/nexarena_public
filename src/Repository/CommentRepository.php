<?php

namespace App\Repository;

use App\Entity\Comment;
use App\Entity\Server;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Comment>
 */
class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    /**
     * @return Comment[]
     */
    public function findVisibleByServer(Server $server): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.author', 'a')
            ->addSelect('a')
            ->where('c.server = :server')
            ->andWhere('c.isDeleted = false')
            ->setParameter('server', $server)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countVisibleByServer(Server $server): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.server = :server')
            ->andWhere('c.isDeleted = false')
            ->setParameter('server', $server)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Comment[]
     */
    public function findFlagged(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.server', 's')
            ->leftJoin('c.author', 'a')
            ->leftJoin('c.flaggedBy', 'fb')
            ->addSelect('s', 'a', 'fb')
            ->where('c.isFlagged = true')
            ->andWhere('c.isDeleted = false')
            ->orderBy('c.flaggedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Comment[]
     */
    public function findForAdminList(?Server $server = null, int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.server', 's')
            ->leftJoin('c.author', 'a')
            ->addSelect('s', 'a')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($server !== null) {
            $qb->andWhere('c.server = :server')->setParameter('server', $server);
        }

        return $qb->getQuery()->getResult();
    }

    public function countFlagged(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.isFlagged = true')
            ->andWhere('c.isDeleted = false')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
