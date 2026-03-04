<?php

namespace App\Service;

use App\Entity\Referral;
use App\Entity\User;
use App\Repository\ReferralRepository;
use Doctrine\ORM\EntityManagerInterface;

class ReferralService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ReferralRepository $referralRepo,
        private SettingsService $settings,
    ) {}

    public function processReferral(User $newUser, ?string $code): void
    {
        if (!$code || !$this->settings->getBool('referral_enabled', true)) {
            return;
        }

        $code = trim($code);
        if ($code === '') {
            return;
        }

        $referrer = $this->em->getRepository(User::class)->findOneBy(['referralCode' => $code]);
        if (!$referrer || $referrer->getId() === $newUser->getId()) {
            return;
        }

        // Already referred
        if ($this->referralRepo->findByReferred($newUser)) {
            return;
        }

        $reward = (int) $this->settings->get('referral_reward_nexbits', '50');

        $referral = new Referral();
        $referral->setReferrer($referrer);
        $referral->setReferred($newUser);
        $referral->setRewardAmount($reward);

        $referrer->addTokens($reward);
        $referrer->incrementReferralCount();
        $newUser->setReferredBy($referrer);

        $this->em->persist($referral);
        $this->em->flush();
    }

    public function generateCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $existing = $this->em->getRepository(User::class)->findOneBy(['referralCode' => $code]);
        } while ($existing !== null);

        return $code;
    }

    public function ensureReferralCode(User $user): void
    {
        if ($user->getReferralCode() === null) {
            $user->setReferralCode($this->generateCode());
            $this->em->flush();
        }
    }
}
