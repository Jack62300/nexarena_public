<?php

namespace App\Repository;

use App\Entity\Role;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Role>
 */
class RoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Role::class);
    }

    /**
     * @param string[] $names
     * @return Role[]
     */
    public function findByTechnicalNames(array $names): array
    {
        if (empty($names)) {
            return [];
        }

        return $this->createQueryBuilder('r')
            ->leftJoin('r.permissions', 'p')
            ->addSelect('p')
            ->where('r.technicalName IN (:names)')
            ->setParameter('names', $names)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Role[]
     */
    public function findAllOrderedByPosition(): array
    {
        return $this->findBy([], ['position' => 'DESC']);
    }
}
