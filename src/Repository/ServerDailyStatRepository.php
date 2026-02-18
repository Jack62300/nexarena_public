<?php

namespace App\Repository;

use App\Entity\Server;
use App\Entity\ServerDailyStat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ServerDailyStat>
 */
class ServerDailyStatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServerDailyStat::class);
    }

    public function findByServerAndDate(Server $server, \DateTimeInterface $date): ?ServerDailyStat
    {
        $dateStr = $date instanceof \DateTimeImmutable
            ? $date->format('Y-m-d')
            : (new \DateTimeImmutable($date->format('Y-m-d')))->format('Y-m-d');

        return $this->createQueryBuilder('s')
            ->where('s.server = :server')
            ->andWhere('s.statDate = :date')
            ->setParameter('server', $server)
            ->setParameter('date', new \DateTimeImmutable($dateStr))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Returns daily view counts for range, filling missing dates with 0.
     * @return array<array{date: string, views: int}>
     */
    public function getDailyViewsForRange(Server $server, \DateTime $from, \DateTime $to): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('s.statDate as date, s.pageViews as views')
            ->where('s.server = :server')
            ->andWhere('s.statDate >= :from')
            ->andWhere('s.statDate <= :to')
            ->setParameter('server', $server)
            ->setParameter('from', new \DateTimeImmutable($from->format('Y-m-d')))
            ->setParameter('to', new \DateTimeImmutable($to->format('Y-m-d')))
            ->orderBy('s.statDate', 'ASC')
            ->getQuery()
            ->getResult();

        // Index by date string
        $indexed = [];
        foreach ($rows as $row) {
            $dateKey = $row['date'] instanceof \DateTimeInterface
                ? $row['date']->format('Y-m-d')
                : (string) $row['date'];
            $indexed[$dateKey] = (int) $row['views'];
        }

        // Fill missing dates with 0
        $result = [];
        $current = clone $from;
        while ($current <= $to) {
            $key = $current->format('Y-m-d');
            $result[] = ['date' => $key, 'views' => $indexed[$key] ?? 0];
            $current->modify('+1 day');
        }

        return $result;
    }
}
