<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Transaction;
use App\Entity\User;
use App\Entity\WheelSpin;
use App\Repository\WheelSpinRepository;
use Doctrine\ORM\EntityManagerInterface;

class WheelService
{
    public const PRIZES = [
        ['index' => 0,  'label' => '1 NexBit',    'nexbits' => 1,    'nexboost' => 0,   'weight' => 2500],
        ['index' => 1,  'label' => '2 NexBits',   'nexbits' => 2,    'nexboost' => 0,   'weight' => 2000],
        ['index' => 2,  'label' => '5 NexBits',   'nexbits' => 5,    'nexboost' => 0,   'weight' => 1500],
        ['index' => 3,  'label' => '10 NexBits',  'nexbits' => 10,   'nexboost' => 0,   'weight' => 1000],
        ['index' => 4,  'label' => '15 NexBits',  'nexbits' => 15,   'nexboost' => 0,   'weight' => 500],
        ['index' => 5,  'label' => '25 NexBits',  'nexbits' => 25,   'nexboost' => 0,   'weight' => 300],
        ['index' => 6,  'label' => '50 NexBits',  'nexbits' => 50,   'nexboost' => 0,   'weight' => 150],
        ['index' => 7,  'label' => '100 NexBits', 'nexbits' => 100,  'nexboost' => 0,   'weight' => 50],
        ['index' => 8,  'label' => '250 NexBits', 'nexbits' => 250,  'nexboost' => 0,   'weight' => 20],
        ['index' => 9,  'label' => '500 NexBits', 'nexbits' => 500,  'nexboost' => 0,   'weight' => 10],
        ['index' => 10, 'label' => 'Perdu',       'nexbits' => 0,    'nexboost' => 0,   'weight' => 1960],
        ['index' => 11, 'label' => 'JACKPOT',     'nexbits' => 1000, 'nexboost' => 100, 'weight' => 10],
    ];

    private const TOTAL_WEIGHT = 10000;

    public function __construct(
        private EntityManagerInterface $em,
        private WheelSpinRepository $wheelSpinRepo,
        private SettingsService $settings,
        private NotificationService $notificationService,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->settings->get('wheel_enabled', '1') === '1';
    }

    public function getSpinCost(): int
    {
        return max(1, (int) $this->settings->get('wheel_spin_cost', '10'));
    }

    public function getMaxPaidSpinsPerDay(): int
    {
        return max(1, (int) $this->settings->get('wheel_max_paid_spins_per_day', '10'));
    }

    public function getPrizes(): array
    {
        return self::PRIZES;
    }

    /**
     * @return array{can: bool, reason: ?string, type: ?string}
     */
    public function canSpin(User $user): array
    {
        if (!$this->isEnabled()) {
            return ['can' => false, 'reason' => 'La roue communautaire est desactivee.', 'type' => null];
        }

        // Check free spins first
        if ($user->getFreeSpins() > 0) {
            return ['can' => true, 'reason' => null, 'type' => WheelSpin::TYPE_FREE];
        }

        // Check paid spin
        $cost = $this->getSpinCost();
        if (!$user->hasEnoughTokens($cost)) {
            return ['can' => false, 'reason' => 'NexBits insuffisants. Il vous faut ' . $cost . ' NexBits.', 'type' => null];
        }

        // Check daily cap
        $paidToday = $this->wheelSpinRepo->countPaidByUserToday($user);
        if ($paidToday >= $this->getMaxPaidSpinsPerDay()) {
            return ['can' => false, 'reason' => 'Vous avez atteint la limite de tours payants pour aujourd\'hui.', 'type' => null];
        }

        return ['can' => true, 'reason' => null, 'type' => WheelSpin::TYPE_PAID];
    }

    /**
     * @return array{sectionIndex: int, prize: array, spin: WheelSpin}
     */
    public function spin(User $user): array
    {
        $canResult = $this->canSpin($user);
        if (!$canResult['can']) {
            throw new \RuntimeException($canResult['reason']);
        }

        $type = $canResult['type'];
        $prize = $this->selectPrize();

        // Debit
        if ($type === WheelSpin::TYPE_FREE) {
            $user->removeFreeSpins(1);
        } else {
            $user->removeTokens($this->getSpinCost());
        }

        // Credit gains
        if ($prize['nexbits'] > 0) {
            $user->addTokens($prize['nexbits']);
        }
        if ($prize['nexboost'] > 0) {
            $user->addBoostTokens($prize['nexboost']);
        }

        // Create WheelSpin record
        $spin = new WheelSpin();
        $spin->setUser($user);
        $spin->setType($type);
        $spin->setSectionIndex($prize['index']);
        $spin->setPrizeLabel($prize['label']);
        $spin->setNexbitsWon($prize['nexbits']);
        $spin->setNexboostWon($prize['nexboost']);
        $spin->setIsJackpot($prize['index'] === 11);
        $this->em->persist($spin);

        // Create Transaction if there was a gain
        if ($prize['nexbits'] > 0 || $prize['nexboost'] > 0) {
            $tx = new Transaction();
            $tx->setUser($user);
            $tx->setType(Transaction::TYPE_WHEEL_REWARD);
            $tx->setTokensAmount($prize['nexbits']);
            $tx->setBoostTokensAmount($prize['nexboost']);
            $tx->setDescription('Roue communautaire : ' . $prize['label']);
            $tx->setIsCredited(true);
            $tx->setCreditedAt(new \DateTimeImmutable());
            $this->em->persist($tx);
        }

        // Notify on jackpot
        if ($prize['index'] === 11) {
            $this->notificationService->create(
                $user,
                Notification::TYPE_REWARD,
                'JACKPOT ! Roue communautaire',
                sprintf(
                    'Incroyable ! Vous avez remporte le JACKPOT : %d NexBits + %d NexBoost !',
                    $prize['nexbits'],
                    $prize['nexboost']
                ),
                '/profil/modifier'
            );
        }

        $this->em->flush();

        return [
            'sectionIndex' => $prize['index'],
            'prize' => $prize,
            'spin' => $spin,
        ];
    }

    public function selectPrize(): array
    {
        $roll = random_int(1, self::TOTAL_WEIGHT);
        $cumulative = 0;

        foreach (self::PRIZES as $prize) {
            $cumulative += $prize['weight'];
            if ($roll <= $cumulative) {
                return $prize;
            }
        }

        // Fallback (should never happen)
        return self::PRIZES[10]; // "Perdu"
    }
}
