<?php

namespace App\Repository;

use App\Entity\Server;
use App\Entity\ServerCollaborator;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ServerCollaborator>
 */
class ServerCollaboratorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServerCollaborator::class);
    }

    public function findByServerAndUser(Server $server, User $user): ?ServerCollaborator
    {
        return $this->createQueryBuilder('sc')
            ->andWhere('sc.server = :server')
            ->andWhere('sc.user = :user')
            ->setParameter('server', $server)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return ServerCollaborator[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('sc')
            ->join('sc.server', 's')
            ->addSelect('s')
            ->andWhere('sc.user = :user')
            ->setParameter('user', $user)
            ->orderBy('sc.addedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ServerCollaborator[]
     */
    public function findByServer(Server $server): array
    {
        return $this->createQueryBuilder('sc')
            ->join('sc.user', 'u')
            ->addSelect('u')
            ->andWhere('sc.server = :server')
            ->setParameter('server', $server)
            ->orderBy('sc.addedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
