<?php

namespace App\Repository;

use App\Entity\Category;
use App\Entity\ServerType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ServerType>
 */
class ServerTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServerType::class);
    }

    /**
     * @return ServerType[]
     */
    public function findByCategory(Category $category): array
    {
        return $this->createQueryBuilder('st')
            ->where('st.category = :category')
            ->andWhere('st.isActive = true')
            ->setParameter('category', $category)
            ->orderBy('st.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ServerType[]
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('st')
            ->where('st.isActive = true')
            ->orderBy('st.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
