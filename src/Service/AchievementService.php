<?php

namespace App\Service;

use App\Entity\Achievement;
use App\Entity\User;
use App\Entity\UserAchievement;
use App\Entity\Vote;
use App\Repository\AchievementRepository;
use App\Repository\CommentRepository;
use App\Repository\TransactionRepository;
use App\Repository\UserAchievementRepository;
use App\Repository\VoteRepository;
use Doctrine\ORM\EntityManagerInterface;

class AchievementService
{
    public function __construct(
        private EntityManagerInterface $em,
        private AchievementRepository $achievementRepo,
        private UserAchievementRepository $userAchievementRepo,
        private VoteRepository $voteRepo,
        private CommentRepository $commentRepo,
        private TransactionRepository $transactionRepo,
    ) {
    }

    /**
     * Check all active achievements for a user and award those whose criteria are met.
     *
     * @return Achievement[] newly awarded achievements
     */
    public function checkAndAwardAchievements(User $user): array
    {
        $awarded = [];
        $active = $this->achievementRepo->findAllActive();

        foreach ($active as $achievement) {
            if ($this->userAchievementRepo->userHasAchievement($user, $achievement)) {
                continue;
            }
            if ($this->evaluateCriteria($user, $achievement->getCriteria())) {
                $ua = new UserAchievement();
                $ua->setUser($user);
                $ua->setAchievement($achievement);
                $this->em->persist($ua);
                $awarded[] = $achievement;
            }
        }

        if (!empty($awarded)) {
            $this->em->flush();
        }

        return $awarded;
    }

    /**
     * Manually award an achievement to a user.
     */
    public function awardAchievement(User $user, Achievement $achievement): bool
    {
        if ($this->userAchievementRepo->userHasAchievement($user, $achievement)) {
            return false;
        }

        $ua = new UserAchievement();
        $ua->setUser($user);
        $ua->setAchievement($achievement);
        $this->em->persist($ua);
        $this->em->flush();

        return true;
    }

    /**
     * Revoke an achievement from a user.
     */
    public function revokeAchievement(User $user, Achievement $achievement): void
    {
        $this->em->createQueryBuilder()
            ->delete(UserAchievement::class, 'ua')
            ->where('ua.user = :user')
            ->andWhere('ua.achievement = :achievement')
            ->setParameter('user', $user)
            ->setParameter('achievement', $achievement)
            ->getQuery()
            ->execute();
    }

    /**
     * Mark all unviewed achievements for a user as viewed (for toast notifications).
     */
    public function markAllViewed(User $user): void
    {
        $this->em->createQueryBuilder()
            ->update(UserAchievement::class, 'ua')
            ->set('ua.isViewed', 'true')
            ->where('ua.user = :user')
            ->andWhere('ua.isViewed = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    private function evaluateCriteria(User $user, ?array $criteria): bool
    {
        if (!$criteria || !isset($criteria['type'])) {
            return false;
        }

        $type      = $criteria['type'];
        $threshold = (int) ($criteria['threshold'] ?? 0);

        return match ($type) {
            'vote_count'  => $this->voteRepo->countTotalVotesForUserServers($user) >= $threshold,
            'server_count' => $user->getServers()->count() >= $threshold,
            'account_age' => $user->getCreatedAt()
                ? $user->getCreatedAt()->diff(new \DateTimeImmutable())->days >= $threshold
                : false,
            'comment_count' => $this->commentRepo->countByAuthor($user) >= $threshold,
            'premium_purchase' => $this->transactionRepo->hasAnyPurchase($user),
            'votes_given' => $this->countVotesGivenByUser($user) >= $threshold,
            'custom' => false,
            default => false,
        };
    }

    private function countVotesGivenByUser(User $user): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(v.id)')
            ->from(Vote::class, 'v')
            ->where('v.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
