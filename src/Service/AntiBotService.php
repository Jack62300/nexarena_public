<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\VoteRepository;

class AntiBotService
{
    public function __construct(
        private VoteRepository $voteRepo,
        private SettingsService $settings,
    ) {
    }

    public function validateFingerprint(?string $fingerprint): bool
    {
        if ($fingerprint === null) {
            return true;
        }

        return (bool) preg_match('/^[a-f0-9]{64}$/', $fingerprint);
    }

    public function detectSuspiciousPattern(string $ip, ?string $fingerprint, ?User $user): array
    {
        $maxFingerprintIps = $this->settings->getInt('vote_antibot_max_fingerprint_ips', 3);
        $maxIpFingerprints = $this->settings->getInt('vote_antibot_max_ip_fingerprints', 5);

        // Check: same fingerprint used by different IPs (multi-IP bot)
        if ($fingerprint) {
            $distinctIps = $this->voteRepo->countDistinctIpsByFingerprint($fingerprint, 24);
            if ($distinctIps >= $maxFingerprintIps) {
                return [
                    'suspicious' => true,
                    'reason' => 'Empreinte navigateur detectee sur trop d\'adresses IP differentes.',
                    'require_captcha' => true,
                ];
            }
        }

        // Check: same IP with different fingerprints (multi-browser bot)
        $distinctFingerprints = $this->voteRepo->countDistinctFingerprintsByIp($ip, 24);
        if ($distinctFingerprints >= $maxIpFingerprints) {
            return [
                'suspicious' => true,
                'reason' => 'Trop d\'empreintes navigateur differentes pour cette IP.',
                'require_captcha' => true,
            ];
        }

        return ['suspicious' => false, 'reason' => '', 'require_captcha' => false];
    }

    public function shouldRequireCaptcha(string $ip, ?User $user): bool
    {
        $threshold = $this->settings->getInt('vote_captcha_threshold', 10);

        // Count votes from this IP in last 24h
        $ipVotes24h = $this->voteRepo->countVotesByIpInInterval($ip, 1440);
        if ($ipVotes24h >= $threshold) {
            return true;
        }

        // If user is logged in, check their votes too
        if ($user) {
            $userVotes = $this->voteRepo->countByUserInDay($user);
            if ($userVotes >= $threshold) {
                return true;
            }
        }

        return false;
    }

    public function generateCaptcha(): array
    {
        $a = random_int(1, 20);
        $b = random_int(1, 20);
        $op = random_int(0, 1) === 0 ? '+' : '-';

        if ($op === '-' && $b > $a) {
            [$a, $b] = [$b, $a];
        }

        $answer = $op === '+' ? $a + $b : $a - $b;
        $question = "$a $op $b = ?";

        return ['question' => $question, 'answer' => $answer];
    }

    public function verifyCaptcha(int $userAnswer, int $expectedAnswer): bool
    {
        return $userAnswer === $expectedAnswer;
    }
}
