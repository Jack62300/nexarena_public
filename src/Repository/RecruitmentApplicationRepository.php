<?php

namespace App\Repository;

use App\Entity\RecruitmentApplication;
use App\Entity\RecruitmentListing;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RecruitmentApplication>
 */
class RecruitmentApplicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RecruitmentApplication::class);
    }

    /**
     * @return RecruitmentApplication[]
     */
    public function findByListing(RecruitmentListing $listing): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.applicantUser', 'u')
            ->addSelect('u')
            ->where('a.listing = :listing')
            ->setParameter('listing', $listing)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByListing(RecruitmentListing $listing): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.listing = :listing')
            ->setParameter('listing', $listing)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countUnreadByListing(RecruitmentListing $listing): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.listing = :listing')
            ->andWhere('a.isRead = false')
            ->setParameter('listing', $listing)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByListingAndUser(RecruitmentListing $listing, User $user): ?RecruitmentApplication
    {
        return $this->createQueryBuilder('a')
            ->where('a.listing = :listing')
            ->andWhere('a.applicantUser = :user')
            ->setParameter('listing', $listing)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
