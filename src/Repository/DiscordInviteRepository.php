<?php

namespace App\Repository;

use App\Entity\DiscordInvite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<DiscordInvite> */
class DiscordInviteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DiscordInvite::class);
    }

    public function getLeaderboard(int $limit = 20): array
    {
        return $this->createQueryBuilder('i')
            ->select('i.inviterDiscordId, i.inviterUsername, COUNT(i.id) AS inviteCount')
            ->groupBy('i.inviterDiscordId, i.inviterUsername')
            ->orderBy('inviteCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return DiscordInvite[] */
    public function findRecent(int $limit = 50): array
    {
        return $this->createQueryBuilder('i')
            ->orderBy('i.joinedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
