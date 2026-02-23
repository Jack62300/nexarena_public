<?php

namespace App\Repository;

use App\Entity\IpBan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IpBan>
 */
class IpBanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IpBan::class);
    }

    /**
     * Vérifie si une IP est actuellement bannie (ban actif non expiré).
     */
    public function findActiveBanForIp(string $ip): ?IpBan
    {
        $now = new \DateTimeImmutable();

        // Ban permanent actif
        $permanent = $this->createQueryBuilder('b')
            ->where('b.ipAddress = :ip')
            ->andWhere('b.isActive = true')
            ->andWhere('b.type = :permanent')
            ->setParameter('ip', $ip)
            ->setParameter('permanent', IpBan::TYPE_PERMANENT)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($permanent !== null) {
            return $permanent;
        }

        // Ban temporaire actif non expiré
        return $this->createQueryBuilder('b')
            ->where('b.ipAddress = :ip')
            ->andWhere('b.isActive = true')
            ->andWhere('b.type = :temporary')
            ->andWhere('b.expiresAt > :now')
            ->setParameter('ip', $ip)
            ->setParameter('temporary', IpBan::TYPE_TEMPORARY)
            ->setParameter('now', $now)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Liste tous les bans pour l'admin, avec filtre optionnel.
     *
     * @return IpBan[]
     */
    public function findAllForAdmin(?string $filter = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->leftJoin('b.bannedBy', 'u')
            ->addSelect('u')
            ->orderBy('b.createdAt', 'DESC');

        if ($filter === 'active') {
            $now = new \DateTimeImmutable();
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->andX('b.isActive = true', 'b.type = :perm'),
                    $qb->expr()->andX('b.isActive = true', 'b.type = :temp', 'b.expiresAt > :now')
                )
            )
            ->setParameter('perm', IpBan::TYPE_PERMANENT)
            ->setParameter('temp', IpBan::TYPE_TEMPORARY)
            ->setParameter('now', $now);
        } elseif ($filter === 'expired') {
            $now = new \DateTimeImmutable();
            $qb->andWhere(
                $qb->expr()->orX(
                    'b.isActive = false',
                    $qb->expr()->andX('b.type = :temp', 'b.expiresAt <= :now')
                )
            )
            ->setParameter('temp', IpBan::TYPE_TEMPORARY)
            ->setParameter('now', $now);
        } elseif ($filter === 'permanent') {
            $qb->andWhere('b.type = :perm')->andWhere('b.isActive = true')
               ->setParameter('perm', IpBan::TYPE_PERMANENT);
        } elseif ($filter === 'temporary') {
            $qb->andWhere('b.type = :temp')->andWhere('b.isActive = true')
               ->setParameter('temp', IpBan::TYPE_TEMPORARY);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Nombre de bans actifs (pour badge sidebar).
     */
    public function countActive(): int
    {
        $now = new \DateTimeImmutable();

        return (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where(
                $this->getEntityManager()->createQueryBuilder()->expr()->orX(
                    $this->getEntityManager()->createQueryBuilder()->expr()->andX('b.isActive = true', 'b.type = :perm'),
                    $this->getEntityManager()->createQueryBuilder()->expr()->andX('b.isActive = true', 'b.type = :temp', 'b.expiresAt > :now')
                )
            )
            ->setParameter('perm', IpBan::TYPE_PERMANENT)
            ->setParameter('temp', IpBan::TYPE_TEMPORARY)
            ->setParameter('now', $now)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Expire automatiquement les bans temporaires dépassés (pour la commande cron ou le listener).
     */
    public function deactivateExpired(\Doctrine\ORM\EntityManagerInterface $em): int
    {
        $count = $this->createQueryBuilder('b')
            ->update()
            ->set('b.isActive', ':false')
            ->where('b.type = :temp')
            ->andWhere('b.isActive = true')
            ->andWhere('b.expiresAt <= :now')
            ->setParameter('false', false)
            ->setParameter('temp', IpBan::TYPE_TEMPORARY)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();

        return (int) $count;
    }
}
