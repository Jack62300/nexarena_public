<?php

namespace App\Service;

use App\Entity\Badge;
use App\Entity\User;
use App\Entity\UserBadge;
use App\Repository\BadgeRepository;
use App\Repository\CommentRepository;
use App\Repository\TransactionRepository;
use App\Repository\UserBadgeRepository;
use App\Repository\VoteRepository;
use Doctrine\ORM\EntityManagerInterface;

class BadgeService
{
    public function __construct(
        private EntityManagerInterface $em,
        private BadgeRepository $badgeRepo,
        private UserBadgeRepository $userBadgeRepo,
        private VoteRepository $voteRepo,
        private CommentRepository $commentRepo,
        private TransactionRepository $transactionRepo,
    ) {
    }

    /** @return Badge[] newly awarded badges */
    public function checkAndAwardBadges(User $user): array
    {
        $awarded = [];
        $activeBadges = $this->badgeRepo->findAllActive();

        foreach ($activeBadges as $badge) {
            if ($this->userBadgeRepo->userHasBadge($user, $badge)) {
                continue;
            }
            if ($this->evaluateCriteria($user, $badge->getCriteria())) {
                $ub = new UserBadge();
                $ub->setUser($user);
                $ub->setBadge($badge);
                $this->em->persist($ub);
                $awarded[] = $badge;
            }
        }

        if (!empty($awarded)) {
            $this->em->flush();
        }

        return $awarded;
    }

    public function awardBadge(User $user, Badge $badge): bool
    {
        if ($this->userBadgeRepo->userHasBadge($user, $badge)) {
            return false;
        }

        $ub = new UserBadge();
        $ub->setUser($user);
        $ub->setBadge($badge);
        $this->em->persist($ub);
        $this->em->flush();

        return true;
    }

    public function revokeBadge(User $user, Badge $badge): void
    {
        $this->em->createQueryBuilder()
            ->delete(UserBadge::class, 'ub')
            ->where('ub.user = :user')
            ->andWhere('ub.badge = :badge')
            ->setParameter('user', $user)
            ->setParameter('badge', $badge)
            ->getQuery()
            ->execute();
    }

    private function evaluateCriteria(User $user, ?array $criteria): bool
    {
        if (!$criteria || !isset($criteria['type'])) {
            return false;
        }

        $type = $criteria['type'];
        $threshold = (int) ($criteria['threshold'] ?? 0);

        return match ($type) {
            'vote_count' => $this->voteRepo->countTotalVotesForUserServers($user) >= $threshold,
            'server_count' => $user->getServers()->count() >= $threshold,
            'account_age' => $user->getCreatedAt()
                ? $user->getCreatedAt()->diff(new \DateTimeImmutable())->days >= $threshold
                : false,
            'comment_count' => $this->commentRepo->countByAuthor($user) >= $threshold,
            'premium_purchase' => $this->transactionRepo->hasAnyPurchase($user),
            'custom' => false,
            default => false,
        };
    }
}
