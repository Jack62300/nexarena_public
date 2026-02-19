<?php

namespace App\Repository;

use App\Entity\PluginSubmission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PluginSubmissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PluginSubmission::class);
    }

    public function findAllForAdmin(): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.submitterUser', 'u')
            ->leftJoin('s.reviewedBy', 'r')
            ->addSelect('u', 'r')
            ->orderBy('CASE WHEN s.status = \'pending\' THEN 0 ELSE 1 END', 'ASC')
            ->addOrderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.status = :status')
            ->setParameter('status', PluginSubmission::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
