<?php

namespace App\Repository;

use App\Entity\MonthlyBattle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MonthlyBattle>
 */
class MonthlyBattleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MonthlyBattle::class);
    }

    public function findByMonthYear(int $month, int $year): ?MonthlyBattle
    {
        return $this->findOneBy(['month' => $month, 'year' => $year]);
    }
}
