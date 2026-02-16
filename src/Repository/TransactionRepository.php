<?php

namespace App\Repository;

use App\Entity\Server;
use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    /**
     * @return Transaction[]
     */
    public function findForAdmin(?string $type = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.user', 'u')
            ->leftJoin('t.plan', 'p')
            ->addSelect('u', 'p')
            ->orderBy('t.createdAt', 'DESC');

        if ($type) {
            $qb->andWhere('t.type = :type')
                ->setParameter('type', $type);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Transaction[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.plan', 'p')
            ->addSelect('p')
            ->where('t.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Transaction[]
     */
    public function findByServer(Server $server): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.user', 'u')
            ->addSelect('u')
            ->where('t.server = :server')
            ->setParameter('server', $server)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByPaypalOrderId(string $orderId): ?Transaction
    {
        return $this->createQueryBuilder('t')
            ->where('t.paypalOrderId = :orderId')
            ->setParameter('orderId', $orderId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getTotalRevenue(): float
    {
        $result = $this->createQueryBuilder('t')
            ->select('SUM(t.amount)')
            ->where('t.type = :type')
            ->setParameter('type', Transaction::TYPE_PURCHASE)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    public function getMonthlyPurchaseCount(): int
    {
        $start = new \DateTimeImmutable('first day of this month midnight');

        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.type = :type')
            ->andWhere('t.createdAt >= :start')
            ->setParameter('type', Transaction::TYPE_PURCHASE)
            ->setParameter('start', $start)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getMonthlyRevenue(): float
    {
        $start = new \DateTimeImmutable('first day of this month midnight');

        $result = $this->createQueryBuilder('t')
            ->select('SUM(t.amount)')
            ->where('t.type = :type')
            ->andWhere('t.createdAt >= :start')
            ->setParameter('type', Transaction::TYPE_PURCHASE)
            ->setParameter('start', $start)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * @return Transaction[]
     */
    public function findRecent(int $limit = 8): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.user', 'u')
            ->leftJoin('t.plan', 'p')
            ->addSelect('u', 'p')
            ->where('t.type = :type')
            ->setParameter('type', Transaction::TYPE_PURCHASE)
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, array{month: string, total: float, count: int}>
     */
    public function getRevenueByMonth(int $months = 12): array
    {
        $since = new \DateTimeImmutable("-{$months} months");
        $since = $since->modify('first day of this month midnight');

        $rows = $this->createQueryBuilder('t')
            ->select('t.amount, t.createdAt')
            ->where('t.type = :type')
            ->andWhere('t.createdAt >= :since')
            ->setParameter('type', Transaction::TYPE_PURCHASE)
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();

        $totals = [];
        foreach ($rows as $row) {
            $key = $row['createdAt']->format('Y-m');
            $totals[$key] = ($totals[$key] ?? 0) + (float) $row['amount'];
        }

        // Fill empty months
        $result = [];
        $date = $since;
        for ($i = 0; $i <= $months; $i++) {
            $key = $date->format('Y-m');
            $result[] = ['month' => $key, 'total' => round($totals[$key] ?? 0, 2)];
            $date = $date->modify('+1 month');
        }

        return $result;
    }
}
