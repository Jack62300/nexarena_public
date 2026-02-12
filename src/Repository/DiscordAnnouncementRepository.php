<?php

namespace App\Repository;

use App\Entity\DiscordAnnouncement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<DiscordAnnouncement> */
class DiscordAnnouncementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DiscordAnnouncement::class);
    }

    /** @return DiscordAnnouncement[] */
    public function findAllForAdmin(): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return DiscordAnnouncement[] */
    public function findScheduledReady(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.sentAt IS NULL')
            ->andWhere('a.isActive = true')
            ->andWhere('a.scheduledAt IS NOT NULL')
            ->andWhere('a.scheduledAt <= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }
}
