<?php

namespace App\Repository;

use App\Entity\BlacklistEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BlacklistEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlacklistEntry::class);
    }

    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.type = :type')
            ->setParameter('type', $type)
            ->orderBy('b.value', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function isValueBlacklisted(string $type, string $value): bool
    {
        $result = $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.type = :type')
            ->andWhere('LOWER(b.value) = LOWER(:value)')
            ->setParameter('type', $type)
            ->setParameter('value', $value)
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }

    public function findAllForAdmin(): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.createdBy', 'u')
            ->addSelect('u')
            ->orderBy('b.type', 'ASC')
            ->addOrderBy('b.value', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
