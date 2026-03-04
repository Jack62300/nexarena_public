<?php

namespace App\Repository;

use App\Entity\Server;
use App\Entity\ServerRating;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ServerRating>
 */
class ServerRatingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServerRating::class);
    }

    public function findByServerAndUser(Server $server, User $user): ?ServerRating
    {
        return $this->findOneBy(['server' => $server, 'user' => $user]);
    }

    /**
     * @return array{avg: float, count: int}
     */
    public function computeAverageForServer(Server $server): array
    {
        $result = $this->createQueryBuilder('r')
            ->select('AVG(r.rating) AS avg, COUNT(r.id) AS cnt')
            ->where('r.server = :server')
            ->setParameter('server', $server)
            ->getQuery()
            ->getSingleResult();

        return [
            'avg' => $result['avg'] ? round((float) $result['avg'], 2) : 0,
            'count' => (int) $result['cnt'],
        ];
    }
}
