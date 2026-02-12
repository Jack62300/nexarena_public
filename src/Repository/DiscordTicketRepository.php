<?php

namespace App\Repository;

use App\Entity\DiscordTicket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<DiscordTicket> */
class DiscordTicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DiscordTicket::class);
    }

    /** @return DiscordTicket[] */
    public function findAllForAdmin(?string $status = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->orderBy('t.createdAt', 'DESC');

        if ($status) {
            $qb->andWhere('t.status = :status')->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    public function countByStatus(string $status): int
    {
        return $this->count(['status' => $status]);
    }

    public function countOpen(): int
    {
        return $this->countByStatus(DiscordTicket::STATUS_OPEN);
    }
}
