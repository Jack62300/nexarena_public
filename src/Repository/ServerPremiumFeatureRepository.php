<?php

namespace App\Repository;

use App\Entity\Server;
use App\Entity\ServerPremiumFeature;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ServerPremiumFeature>
 */
class ServerPremiumFeatureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServerPremiumFeature::class);
    }

    public function findByServerAndFeature(Server $server, string $feature): ?ServerPremiumFeature
    {
        return $this->createQueryBuilder('f')
            ->where('f.server = :server')
            ->andWhere('f.feature = :feature')
            ->setParameter('server', $server)
            ->setParameter('feature', $feature)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function hasFeature(Server $server, string $feature): bool
    {
        return $this->findByServerAndFeature($server, $feature) !== null;
    }
}
