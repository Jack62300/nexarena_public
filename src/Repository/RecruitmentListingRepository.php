<?php

namespace App\Repository;

use App\Entity\Category;
use App\Entity\RecruitmentListing;
use App\Entity\Server;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RecruitmentListing>
 */
class RecruitmentListingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RecruitmentListing::class);
    }

    /**
     * @return RecruitmentListing[]
     */
    public function findPubliclyVisible(?Category $category = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.server', 's')
            ->leftJoin('s.category', 'c')
            ->leftJoin('s.gameCategory', 'gc')
            ->leftJoin('r.author', 'a')
            ->addSelect('s', 'c', 'gc', 'a')
            ->where('r.status = :status')
            ->andWhere('r.isActive = true')
            ->setParameter('status', RecruitmentListing::STATUS_APPROVED)
            ->orderBy('r.createdAt', 'DESC');

        if ($category) {
            $qb->andWhere('s.category = :category')
                ->setParameter('category', $category);
        }

        $results = $qb->getQuery()->getResult();

        // Filter out listings with empty formFields (can't do JSON check in DQL)
        return array_filter($results, fn(RecruitmentListing $r) => !empty($r->getFormFields()));
    }

    /**
     * @return RecruitmentListing[]
     */
    public function findByAuthor(User $user): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.server', 's')
            ->addSelect('s')
            ->where('r.author = :user')
            ->setParameter('user', $user)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return RecruitmentListing[]
     */
    public function findByServer(Server $server): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.server = :server')
            ->setParameter('server', $server)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return RecruitmentListing[]
     */
    public function findForAdmin(?string $status = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.server', 's')
            ->leftJoin('r.author', 'a')
            ->addSelect('s', 'a')
            ->orderBy('r.createdAt', 'DESC');

        if ($status) {
            $qb->andWhere('r.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.status = :status')
            ->setParameter('status', RecruitmentListing::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
