<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Transaction;
use App\Entity\User;
use App\Entity\WheelPrize;
use App\Entity\WheelSpin;
use App\Repository\WheelPrizeRepository;
use App\Repository\WheelSpinRepository;
use Doctrine\ORM\EntityManagerInterface;

class WheelService
{
    public const DEFAULT_PRIZES = [
        ['position' => 0,  'label' => '1 NexBit',    'nexbits' => 1,    'nexboost' => 0,   'weight' => 2500, 'color' => '#2d8a4e', 'isJackpot' => false],
        ['position' => 1,  'label' => '2 NexBits',   'nexbits' => 2,    'nexboost' => 0,   'weight' => 2000, 'color' => '#1a5c3a', 'isJackpot' => false],
        ['position' => 2,  'label' => '5 NexBits',   'nexbits' => 5,    'nexboost' => 0,   'weight' => 1500, 'color' => '#45f882', 'isJackpot' => false],
        ['position' => 3,  'label' => '10 NexBits',  'nexbits' => 10,   'nexboost' => 0,   'weight' => 1000, 'color' => '#1e7a42', 'isJackpot' => false],
        ['position' => 4,  'label' => '15 NexBits',  'nexbits' => 15,   'nexboost' => 0,   'weight' => 500,  'color' => '#33a55d', 'isJackpot' => false],
        ['position' => 5,  'label' => '25 NexBits',  'nexbits' => 25,   'nexboost' => 0,   'weight' => 300,  'color' => '#0f4a2e', 'isJackpot' => false],
        ['position' => 6,  'label' => '50 NexBits',  'nexbits' => 50,   'nexboost' => 0,   'weight' => 150,  'color' => '#3dd672', 'isJackpot' => false],
        ['position' => 7,  'label' => '100 NexBits', 'nexbits' => 100,  'nexboost' => 0,   'weight' => 50,   'color' => '#165e38', 'isJackpot' => false],
        ['position' => 8,  'label' => '250 NexBits', 'nexbits' => 250,  'nexboost' => 0,   'weight' => 20,   'color' => '#28944f', 'isJackpot' => false],
        ['position' => 9,  'label' => '500 NexBits', 'nexbits' => 500,  'nexboost' => 0,   'weight' => 10,   'color' => '#0d3f26', 'isJackpot' => false],
        ['position' => 10, 'label' => 'Perdu',       'nexbits' => 0,    'nexboost' => 0,   'weight' => 1960, 'color' => '#4afc8e', 'isJackpot' => false],
        ['position' => 11, 'label' => 'JACKPOT',     'nexbits' => 1000, 'nexboost' => 100, 'weight' => 10,   'color' => '#ffd700', 'isJackpot' => true],
    ];

    /** @var array[]|null */
    private ?array $cachedPrizes = null;

    public function __construct(
        private EntityManagerInterface $em,
        private WheelSpinRepository $wheelSpinRepo,
        private WheelPrizeRepository $wheelPrizeRepo,
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
        if ($this->cachedPrizes !== null) {
            return $this->cachedPrizes;
        }

        $entities = $this->wheelPrizeRepo->findAllOrdered();

        if (empty($entities)) {
            // Fallback to defaults if table is empty
            $this->cachedPrizes = array_map(function (array $d) {
                return [
                    'index' => $d['position'],
                    'label' => $d['label'],
                    'nexbits' => $d['nexbits'],
                    'nexboost' => $d['nexboost'],
                    'weight' => $d['weight'],
                    'color' => $d['color'],
                    'isJackpot' => $d['isJackpot'],
                ];
            }, self::DEFAULT_PRIZES);
            return $this->cachedPrizes;
        }

        $this->cachedPrizes = array_map(function (WheelPrize $p) {
            return [
                'index' => $p->getPosition(),
                'label' => $p->getLabel(),
                'nexbits' => $p->getNexbits(),
                'nexboost' => $p->getNexboost(),
                'weight' => $p->getWeight(),
                'color' => $p->getColor(),
                'isJackpot' => $p->isJackpot(),
            ];
        }, $entities);

        return $this->cachedPrizes;
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
        $spin->setIsJackpot($prize['isJackpot'] ?? false);
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
        if ($prize['isJackpot'] ?? false) {
            $this->notificationService->create(
                $user,
                Notification::TYPE_REWARD,
                'JACKPOT ! Roue communautaire',
                sprintf(
                    'Incroyable ! Vous avez remporte le JACKPOT : %d NexBits + %d NexBoost !',
                    $prize['nexbits'],
                    $prize['nexboost']
                ),
                '/profil/parametres#roue'
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
        $prizes = $this->getPrizes();
        $totalWeight = array_sum(array_column($prizes, 'weight'));

        if ($totalWeight <= 0) {
            // Fallback
            return $prizes[0] ?? ['index' => 0, 'label' => 'Perdu', 'nexbits' => 0, 'nexboost' => 0, 'weight' => 1, 'color' => '#4afc8e', 'isJackpot' => false];
        }

        $roll = random_int(1, $totalWeight);
        $cumulative = 0;

        foreach ($prizes as $prize) {
            $cumulative += $prize['weight'];
            if ($roll <= $cumulative) {
                return $prize;
            }
        }

        // Fallback (should never happen)
        return $prizes[array_key_last($prizes)];
    }

    public function initDefaultPrizes(EntityManagerInterface $em): int
    {
        $existing = $this->wheelPrizeRepo->count([]);
        if ($existing > 0) {
            return 0;
        }

        foreach (self::DEFAULT_PRIZES as $data) {
            $prize = new WheelPrize();
            $prize->setPosition($data['position']);
            $prize->setLabel($data['label']);
            $prize->setNexbits($data['nexbits']);
            $prize->setNexboost($data['nexboost']);
            $prize->setWeight($data['weight']);
            $prize->setColor($data['color']);
            $prize->setIsJackpot($data['isJackpot']);
            $em->persist($prize);
        }

        $em->flush();
        $this->cachedPrizes = null;

        return count(self::DEFAULT_PRIZES);
    }

    public function resetToDefaults(EntityManagerInterface $em): void
    {
        // Delete all existing
        $existing = $this->wheelPrizeRepo->findAll();
        foreach ($existing as $prize) {
            $em->remove($prize);
        }
        $em->flush();

        // Re-create defaults
        foreach (self::DEFAULT_PRIZES as $data) {
            $prize = new WheelPrize();
            $prize->setPosition($data['position']);
            $prize->setLabel($data['label']);
            $prize->setNexbits($data['nexbits']);
            $prize->setNexboost($data['nexboost']);
            $prize->setWeight($data['weight']);
            $prize->setColor($data['color']);
            $prize->setIsJackpot($data['isJackpot']);
            $em->persist($prize);
        }

        $em->flush();
        $this->cachedPrizes = null;
    }
}
