<?php

namespace App\Service;

use App\Entity\Server;
use App\Entity\Transaction;
use App\Entity\User;
use App\Entity\Vote;
use App\Entity\VoteReward;
use App\Repository\VoteRepository;
use App\Repository\VoteRewardRepository;
use Doctrine\ORM\EntityManagerInterface;

class VoteRewardService
{
    public function __construct(
        private EntityManagerInterface $em,
        private SettingsService $settings,
        private VoteRepository $voteRepo,
        private VoteRewardRepository $rewardRepo,
    ) {
    }

    public function calculateReward(User $user, Server $server, Vote $vote): ?VoteReward
    {
        if (!$this->settings->getBool('vote_reward_enabled', true)) {
            return null;
        }

        // Check daily user limit
        $maxUserDay = $this->settings->getInt('vote_reward_max_user_day', 8);
        $userVotesToday = $this->rewardRepo->countByUserInDay($user);
        if ($userVotesToday >= $maxUserDay) {
            return null;
        }

        // Check daily server limit
        $maxServerDay = $this->settings->getInt('vote_reward_max_server_day', 150);
        $serverVotesToday = $this->voteRepo->countByServerInDay($server);
        if ($serverVotesToday > $maxServerDay) {
            return null;
        }

        // Check monthly token limit
        $maxTokensMonth = (float) $this->settings->get('vote_reward_max_tokens_month', '200');
        $now = new \DateTimeImmutable();
        $monthlyEarned = $this->rewardRepo->sumTokensEarnedByUserInMonth($user, (int) $now->format('n'), (int) $now->format('Y'));
        if ($monthlyEarned >= $maxTokensMonth) {
            return null;
        }

        // Determine tier based on user's votes today
        $totalUserVotesToday = $this->voteRepo->countByUserInDay($user);
        $tier1Max = $this->settings->getInt('vote_reward_tier1_max', 5);
        $tier2Max = $this->settings->getInt('vote_reward_tier2_max', 8);
        $tier1Amount = (float) $this->settings->get('vote_reward_tier1_amount', '1.0');
        $tier2Amount = (float) $this->settings->get('vote_reward_tier2_amount', '0.5');
        $tier3Amount = (float) $this->settings->get('vote_reward_tier3_amount', '0.25');

        if ($totalUserVotesToday <= $tier1Max) {
            $baseAmount = $tier1Amount;
            $tier = 1;
        } elseif ($totalUserVotesToday <= $tier2Max) {
            $baseAmount = $tier2Amount;
            $tier = 2;
        } else {
            $baseAmount = $tier3Amount;
            $tier = 3;
        }

        $multiplier = 1.0;
        $reason = 'Vote palier ' . $tier;

        // New server penalty: servers < X days old give half rewards
        $newServerDays = $this->settings->getInt('vote_reward_new_server_days', 7);
        if ($server->getCreatedAt() && $server->getCreatedAt() > new \DateTimeImmutable("-{$newServerDays} days")) {
            $multiplier = 0.5;
            $reason .= ' (nouveau serveur x0.5)';
        }

        $tokensEarned = $baseAmount * $multiplier;

        // Cap to not exceed monthly limit
        $remaining = $maxTokensMonth - $monthlyEarned;
        if ($tokensEarned > $remaining) {
            $tokensEarned = $remaining;
        }

        if ($tokensEarned <= 0) {
            return null;
        }

        // Create reward
        $reward = new VoteReward();
        $reward->setUser($user);
        $reward->setVote($vote);
        $reward->setServer($server);
        $reward->setTokensEarned($tokensEarned);
        $reward->setMultiplier($multiplier);
        $reward->setReason($reason);

        $this->em->persist($reward);

        // Accumulate pending tokens
        $user->addPendingVoteTokens($tokensEarned);

        // Convert when pending >= 1.0
        $pending = $user->getPendingVoteTokens();
        if ($pending >= 1.0) {
            $wholeTokens = (int) floor($pending);
            $user->addTokens($wholeTokens);
            $user->setPendingVoteTokens($pending - (float) $wholeTokens);

            // Create transaction for the conversion
            $tx = new Transaction();
            $tx->setUser($user);
            $tx->setType(Transaction::TYPE_VOTE_REWARD);
            $tx->setTokensAmount($wholeTokens);
            $tx->setDescription('NexBits gagnes via votes (' . $wholeTokens . ' NexBit' . ($wholeTokens > 1 ? 's' : '') . ')');
            $this->em->persist($tx);
        }

        $this->em->flush();

        return $reward;
    }
}
