<?php

namespace App\Repository;

use App\Entity\RecruitmentApplication;
use App\Entity\RecruitmentMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RecruitmentMessage>
 */
class RecruitmentMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RecruitmentMessage::class);
    }

    /**
     * @return RecruitmentMessage[]
     */
    public function findByApplication(RecruitmentApplication $application): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.sender', 's')
            ->addSelect('s')
            ->where('m.application = :app')
            ->setParameter('app', $application)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return RecruitmentMessage[]
     */
    public function findNewMessages(RecruitmentApplication $application, int $afterId): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.sender', 's')
            ->addSelect('s')
            ->where('m.application = :app')
            ->andWhere('m.id > :afterId')
            ->setParameter('app', $application)
            ->setParameter('afterId', $afterId)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
