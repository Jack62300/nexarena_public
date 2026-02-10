<?php

namespace App\Repository;

use App\Entity\Permission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Permission>
 */
class PermissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Permission::class);
    }

    /**
     * @return array<string, Permission[]>
     */
    public function findAllGroupedByCategory(): array
    {
        $permissions = $this->findBy([], ['category' => 'ASC', 'code' => 'ASC']);
        $grouped = [];
        foreach ($permissions as $perm) {
            $grouped[$perm->getCategory()][] = $perm;
        }

        return $grouped;
    }
}
