<?php

namespace App\Service;

use App\Entity\Server;
use App\Entity\User;
use App\Entity\Vote;
use App\Repository\VoteRepository;
use Doctrine\ORM\EntityManagerInterface;

class VoteService
{
    public function __construct(
        private VoteRepository $voteRepo,
        private EntityManagerInterface $em,
        private SettingsService $settings,
        private WebhookService $webhookService,
        private IpSecurityService $ipSecurity,
        private AntiBotService $antiBotService,
    ) {
    }

    /**
     * @return array{allowed: bool, reason: string, cooldown: int, captcha_required?: bool}
     */
    public function canVote(Server $server, string $ip, ?string $discordId = null, ?string $steamId = null, ?string $fingerprint = null, ?User $user = null): array
    {
        if (!$server->isActive() || !$server->isApproved()) {
            return ['allowed' => false, 'reason' => 'Ce serveur n\'est pas disponible.', 'cooldown' => 0];
        }

        $interval = $this->settings->getInt('vote_interval', 120);

        // Check IP cooldown
        if ($this->voteRepo->hasIpVotedRecently($server, $ip, $interval)) {
            $cooldown = $this->getCooldownRemainingByIp($server, $ip);
            return ['allowed' => false, 'reason' => 'Un vote a deja ete enregistre depuis cette adresse IP.', 'cooldown' => $cooldown];
        }

        // Check Discord ID cooldown
        if ($discordId && $this->voteRepo->hasDiscordIdVotedRecently($server, $discordId, $interval)) {
            $cooldown = $this->getCooldownRemainingByDiscordId($server, $discordId);
            return ['allowed' => false, 'reason' => 'Vous avez deja vote recemment avec ce compte Discord.', 'cooldown' => $cooldown];
        }

        // Check Steam ID cooldown
        if ($steamId && $this->voteRepo->hasSteamIdVotedRecently($server, $steamId, $interval)) {
            $cooldown = $this->getCooldownRemainingBySteamId($server, $steamId);
            return ['allowed' => false, 'reason' => 'Vous avez deja vote recemment avec ce compte Steam.', 'cooldown' => $cooldown];
        }

        // Anti-fraud: max IP per interval
        if ($this->settings->getBool('vote_antifraud_enabled', true)) {
            $maxIp = $this->settings->getInt('vote_antifraud_max_ip', 3);
            $ipVotes = $this->voteRepo->countVotesByIpInInterval($ip, $interval);
            if ($ipVotes >= $maxIp) {
                return ['allowed' => false, 'reason' => 'Nombre maximum de votes atteint pour cette adresse IP.', 'cooldown' => 0];
            }
        }

        // VPN check
        if ($this->settings->getBool('vote_vpn_check_enabled', true)) {
            if ($this->ipSecurity->isVpnOrProxy($ip)) {
                return ['allowed' => false, 'reason' => 'Les votes via VPN ou proxy ne sont pas autorises.', 'cooldown' => 0];
            }
        }

        // Anti-bot: fingerprint + pattern detection
        if ($this->settings->getBool('vote_antifraud_enabled', true)) {
            $pattern = $this->antiBotService->detectSuspiciousPattern($ip, $fingerprint, $user);
            if ($pattern['suspicious']) {
                if ($pattern['require_captcha']) {
                    return ['allowed' => false, 'reason' => $pattern['reason'], 'cooldown' => 0, 'captcha_required' => true];
                }
                return ['allowed' => false, 'reason' => $pattern['reason'], 'cooldown' => 0];
            }

            // Check if captcha should be required based on volume
            if ($this->antiBotService->shouldRequireCaptcha($ip, $user)) {
                return ['allowed' => false, 'reason' => 'Verification requise avant de voter.', 'cooldown' => 0, 'captcha_required' => true];
            }
        }

        return ['allowed' => true, 'reason' => '', 'cooldown' => 0];
    }

    public function castVote(Server $server, string $ip, ?string $username, ?string $discordId, ?string $steamId, string $provider, ?string $fingerprint = null, ?User $user = null): Vote
    {
        $vote = new Vote();
        $vote->setServer($server);
        $vote->setVoterIp($ip);
        $vote->setVoterUsername($username);
        $vote->setDiscordId($discordId);
        $vote->setSteamId($steamId);
        $vote->setVoteProvider($provider);
        $vote->setVpnChecked($this->settings->getBool('vote_vpn_check_enabled', true));
        $vote->setBrowserFingerprint($fingerprint);

        if ($user) {
            $vote->setUser($user);
        }

        $server->incrementTotalVotes();
        $server->incrementMonthlyVotes();

        $this->em->persist($vote);
        $this->em->flush();

        // Send server-level webhook
        if ($server->isWebhookEnabled() && $server->getWebhookUrl()) {
            $this->webhookService->sendVoteWebhook($server, $vote);
        }

        // Admin Discord webhook
        $this->webhookService->dispatch('vote.cast', [
            'title' => 'Vote enregistre',
            'fields' => [
                ['name' => 'Serveur', 'value' => $server->getName(), 'inline' => true],
                ['name' => 'Votant', 'value' => $username ?: 'Anonyme', 'inline' => true],
                ['name' => 'Methode', 'value' => ucfirst($provider), 'inline' => true],
            ],
        ]);

        return $vote;
    }

    public function getCooldownRemainingByIp(Server $server, string $ip): int
    {
        $interval = $this->settings->getInt('vote_interval', 120);
        $lastVote = $this->voteRepo->getLastVoteTimeByIp($server, $ip);

        return $this->computeRemaining($lastVote, $interval);
    }

    public function getCooldownRemainingByDiscordId(Server $server, string $discordId): int
    {
        $interval = $this->settings->getInt('vote_interval', 120);
        $lastVote = $this->voteRepo->getLastVoteTimeByDiscordId($server, $discordId);

        return $this->computeRemaining($lastVote, $interval);
    }

    public function getCooldownRemainingBySteamId(Server $server, string $steamId): int
    {
        $interval = $this->settings->getInt('vote_interval', 120);
        $lastVote = $this->voteRepo->getLastVoteTimeBySteamId($server, $steamId);

        return $this->computeRemaining($lastVote, $interval);
    }

    private function computeRemaining(?\DateTimeImmutable $lastVote, int $interval): int
    {
        if (!$lastVote) {
            return 0;
        }

        $nextVoteAt = $lastVote->modify("+{$interval} minutes");
        $remaining = $nextVoteAt->getTimestamp() - time();

        return max(0, $remaining);
    }
}
