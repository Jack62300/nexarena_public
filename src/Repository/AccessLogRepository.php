<?php

namespace App\Repository;

use App\Entity\AccessLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AccessLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccessLog::class);
    }

    /**
     * Retourne [logs[], total] avec filtres.
     *
     * @return array{0: AccessLog[], 1: int}
     */
    public function findFiltered(
        string $filter   = 'all',
        string $search   = '',
        int    $page     = 1,
        int    $perPage  = 60,
        string $country  = '',
        string $reason   = '',
    ): array {
        $qb = $this->createQueryBuilder('l')
            ->orderBy('l.createdAt', 'DESC');

        match ($filter) {
            'blocked' => $qb->andWhere('l.blocked = :b')->setParameter('b', true),
            'allowed' => $qb->andWhere('l.blocked = :b')->setParameter('b', false),
            default   => null,
        };

        if ($search !== '') {
            $qb->andWhere('l.ip LIKE :s OR l.path LIKE :s')
               ->setParameter('s', '%' . $search . '%');
        }

        if ($country !== '') {
            $qb->andWhere('l.countryCode = :cc')
               ->setParameter('cc', strtoupper($country));
        }

        if ($reason !== '') {
            $qb->andWhere('l.blockReason = :r')->setParameter('r', $reason);
        }

        $total = (int) (clone $qb)->select('COUNT(l.id)')->getQuery()->getSingleScalarResult();

        $logs = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return [$logs, $total];
    }

    /** Nombre d'accès bloqués dans les dernières 24h (pour le badge sidebar). */
    public function countBlockedLast24h(): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.blocked = :b')
            ->andWhere('l.createdAt >= :since')
            ->setParameter('b', true)
            ->setParameter('since', new \DateTimeImmutable('-24 hours'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Supprime les entrées plus vieilles que $days jours. Retourne le nombre supprimé. */
    public function deleteOlderThan(int $days): int
    {
        return (int) $this->createQueryBuilder('l')
            ->delete()
            ->andWhere('l.createdAt < :before')
            ->setParameter('before', new \DateTimeImmutable("-{$days} days"))
            ->getQuery()
            ->execute();
    }

    /** Stats rapides pour le dashboard (last 24h). */
    public function getStats24h(): array
    {
        $since = new \DateTimeImmutable('-24 hours');

        $rows = $this->createQueryBuilder('l')
            ->select('l.blocked, COUNT(l.id) as cnt')
            ->andWhere('l.createdAt >= :since')
            ->setParameter('since', $since)
            ->groupBy('l.blocked')
            ->getQuery()
            ->getResult();

        $stats = ['total' => 0, 'blocked' => 0, 'allowed' => 0];
        foreach ($rows as $row) {
            $stats['total'] += (int) $row['cnt'];
            $row['blocked'] ? ($stats['blocked'] += (int) $row['cnt']) : ($stats['allowed'] += (int) $row['cnt']);
        }

        return $stats;
    }
}
