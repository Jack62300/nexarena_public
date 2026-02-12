<?php

namespace App\Repository;

use App\Entity\DiscordSanction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<DiscordSanction> */
class DiscordSanctionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DiscordSanction::class);
    }

    /** @return DiscordSanction[] */
    public function findByDiscordUserId(string $discordUserId): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.discordUserId = :uid')
            ->setParameter('uid', $discordUserId)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countActiveWarnsByDiscordUserId(string $discordUserId): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.discordUserId = :uid')
            ->andWhere('s.type = :type')
            ->andWhere('s.isRevoked = false')
            ->setParameter('uid', $discordUserId)
            ->setParameter('type', DiscordSanction::TYPE_WARN)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return DiscordSanction[] */
    public function findAllForAdmin(?string $type = null, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->orderBy('s.createdAt', 'DESC');

        if ($type) {
            $qb->andWhere('s.type = :type')->setParameter('type', $type);
        }

        if ($status === 'active') {
            $qb->andWhere('s.isRevoked = false')
                ->andWhere('(s.expiresAt IS NULL OR s.expiresAt > :now)')
                ->setParameter('now', new \DateTimeImmutable());
        } elseif ($status === 'revoked') {
            $qb->andWhere('s.isRevoked = true');
        } elseif ($status === 'expired') {
            $qb->andWhere('s.isRevoked = false')
                ->andWhere('s.expiresAt IS NOT NULL')
                ->andWhere('s.expiresAt <= :now')
                ->setParameter('now', new \DateTimeImmutable());
        }

        return $qb->getQuery()->getResult();
    }

    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.isRevoked = false')
            ->andWhere('(s.expiresAt IS NULL OR s.expiresAt > :now)')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult();
    }
}
