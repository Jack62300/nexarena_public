<?php

namespace App\Repository;

use App\Entity\DiscordModerationLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<DiscordModerationLog> */
class DiscordModerationLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DiscordModerationLog::class);
    }

    /** @return DiscordModerationLog[] */
    public function findAllForAdmin(?string $action = null, int $limit = 50, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('l')
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($action) {
            $qb->andWhere('l.action = :action')->setParameter('action', $action);
        }

        return $qb->getQuery()->getResult();
    }

    public function countAll(?string $action = null): int
    {
        $qb = $this->createQueryBuilder('l')
            ->select('COUNT(l.id)');

        if ($action) {
            $qb->andWhere('l.action = :action')->setParameter('action', $action);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function countToday(): int
    {
        $today = new \DateTimeImmutable('today midnight');

        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.createdAt >= :today')
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return array<string, int> */
    public function countByActionToday(): array
    {
        $today = new \DateTimeImmutable('today midnight');

        $rows = $this->createQueryBuilder('l')
            ->select('l.action, COUNT(l.id) as cnt')
            ->andWhere('l.createdAt >= :today')
            ->setParameter('today', $today)
            ->groupBy('l.action')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['action']] = (int) $row['cnt'];
        }

        return $result;
    }
}
