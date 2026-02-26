<?php

namespace App\Repository;

use App\Entity\ActivityLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityLog>
 */
class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    /**
     * Returns [ActivityLog[], totalCount] with optional filters.
     *
     * @return array{0: ActivityLog[], 1: int}
     */
    public function findFiltered(
        ?string $category = null,
        ?string $search = null,
        int $page = 1,
        int $limit = 50
    ): array {
        $qb = $this->createQueryBuilder('l')
            ->leftJoin('l.user', 'u')
            ->addSelect('u')
            ->orderBy('l.createdAt', 'DESC');

        if ($category) {
            $qb->andWhere('l.category = :cat')
               ->setParameter('cat', $category);
        }

        if ($search) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('l.username', ':s'),
                    $qb->expr()->like('l.action', ':s'),
                    $qb->expr()->like('l.objectLabel', ':s'),
                    $qb->expr()->like('l.objectType', ':s')
                )
            )->setParameter('s', '%' . $search . '%');
        }

        $total = (int) (clone $qb)
            ->select('COUNT(l.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $logs = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [$logs, $total];
    }

    /** Delete logs older than $days days. Returns count deleted. */
    public function deleteOlderThan(int $days): int
    {
        $cutoff = new \DateTimeImmutable("-{$days} days");
        return (int) $this->createQueryBuilder('l')
            ->delete()
            ->where('l.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }
}
