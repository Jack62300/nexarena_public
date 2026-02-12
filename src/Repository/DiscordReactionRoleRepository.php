<?php

namespace App\Repository;

use App\Entity\DiscordReactionRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<DiscordReactionRole> */
class DiscordReactionRoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DiscordReactionRole::class);
    }

    /** @return DiscordReactionRole[] */
    public function findByMessageId(string $messageId): array
    {
        return $this->findBy(['messageId' => $messageId]);
    }

    /** @return DiscordReactionRole[] */
    public function findAll(): array
    {
        return $this->createQueryBuilder('r')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
