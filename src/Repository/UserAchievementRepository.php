<?php

namespace App\Repository;

use App\Entity\Achievement;
use App\Entity\User;
use App\Entity\UserAchievement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserAchievementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserAchievement::class);
    }

    /** @return UserAchievement[] */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('ua')
            ->join('ua.achievement', 'a')
            ->andWhere('ua.user = :user')
            ->andWhere('a.isActive = true')
            ->setParameter('user', $user)
            ->orderBy('ua.awardedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return UserAchievement[] */
    public function findByAchievement(Achievement $achievement): array
    {
        return $this->createQueryBuilder('ua')
            ->join('ua.user', 'u')
            ->andWhere('ua.achievement = :achievement')
            ->setParameter('achievement', $achievement)
            ->orderBy('ua.awardedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function userHasAchievement(User $user, Achievement $achievement): bool
    {
        return $this->count(['user' => $user, 'achievement' => $achievement]) > 0;
    }

    public function countUnviewed(User $user): int
    {
        return $this->count(['user' => $user, 'isViewed' => false]);
    }
}
