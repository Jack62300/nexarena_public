<?php

namespace App\Repository;

use App\Entity\Plugin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Plugin>
 */
class PluginRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Plugin::class);
    }

    /**
     * @return Plugin[]
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isActive = true')
            ->orderBy('p.category', 'ASC')
            ->addOrderBy('p.platform', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Plugin[]
     */
    public function findAllForAdmin(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.category', 'ASC')
            ->addOrderBy('p.platform', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findBySlug(string $slug): ?Plugin
    {
        return $this->findOneBy(['slug' => $slug, 'isActive' => true]);
    }
}
