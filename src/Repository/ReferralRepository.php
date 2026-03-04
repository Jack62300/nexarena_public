<?php

namespace App\Repository;

use App\Entity\Referral;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Referral>
 */
class ReferralRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Referral::class);
    }

    public function findByReferrer(User $referrer): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.referred', 'u')
            ->addSelect('u')
            ->where('r.referrer = :referrer')
            ->setParameter('referrer', $referrer)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByReferred(User $referred): ?Referral
    {
        return $this->findOneBy(['referred' => $referred]);
    }
}
