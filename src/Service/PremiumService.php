<?php

namespace App\Service;

use App\Entity\FeaturedBooking;
use App\Entity\GameCategory;
use App\Entity\PremiumPlan;
use App\Entity\Server;
use App\Entity\ServerPremiumFeature;
use App\Entity\Transaction;
use App\Entity\TwitchSubscription;
use App\Entity\User;
use App\Repository\FeaturedBookingRepository;
use App\Repository\RecruitmentListingRepository;
use App\Repository\ServerPremiumFeatureRepository;
use App\Repository\TransactionRepository;
use App\Repository\TwitchSubscriptionRepository;
use App\Service\WebhookService;
use Doctrine\ORM\EntityManagerInterface;

class PremiumService
{
    public function __construct(
        private EntityManagerInterface $em,
        private SettingsService $settings,
        private ServerPremiumFeatureRepository $featureRepo,
        private FeaturedBookingRepository $bookingRepo,
        private TransactionRepository $transactionRepo,
        private RecruitmentListingRepository $recruitmentRepo,
        private TwitchSubscriptionRepository $twitchSubRepo,
        private WebhookService $webhookService,
    ) {
    }

    public function isPremiumEnabled(): bool
    {
        return $this->settings->getBool('premium_enabled', true);
    }

    public function isFeatureGated(string $feature): bool
    {
        if (!$this->isPremiumEnabled()) {
            return false;
        }

        return match ($feature) {
            'theme' => $this->settings->getBool('premium_theme_gate_enabled', true),
            'widget' => $this->settings->getBool('premium_widget_gate_enabled', true),
            'recruitment' => $this->settings->getBool('premium_recruitment_gate_enabled', true),
            'twitch_live' => $this->settings->getBool('premium_twitch_live_gate_enabled', true),
            'stats' => $this->settings->getBool('premium_stats_gate_enabled', true),
            default => true,
        };
    }

    public function getFeatureCost(string $feature): int
    {
        return match ($feature) {
            ServerPremiumFeature::FEATURE_THEME => $this->settings->getInt('premium_theme_cost', 50),
            ServerPremiumFeature::FEATURE_WIDGET => $this->settings->getInt('premium_widget_cost', 50),
            ServerPremiumFeature::FEATURE_STATS => $this->settings->getInt('premium_stats_cost', 100),
            default => 0,
        };
    }

    public function hasServerFeature(Server $server, string $feature): bool
    {
        if (!$this->isFeatureGated($feature)) {
            return true;
        }

        return $this->featureRepo->hasFeature($server, $feature);
    }

    public function unlockFeature(Server $server, User $user, string $feature): bool
    {
        if (!$this->isFeatureGated($feature)) {
            return true;
        }

        if ($this->featureRepo->hasFeature($server, $feature)) {
            return true;
        }

        $cost = $this->getFeatureCost($feature);
        if ($cost > 0 && !$server->hasEnoughTokens($cost)) {
            return false;
        }

        if ($cost > 0) {
            $server->removeTokens($cost);
        }

        $unlock = new ServerPremiumFeature();
        $unlock->setServer($server);
        $unlock->setFeature($feature);
        $unlock->setUnlockedBy($user);
        $unlock->setTokensSpent($cost);

        $this->em->persist($unlock);

        $featureLabel = match ($feature) {
            ServerPremiumFeature::FEATURE_THEME => 'Theme personnalise',
            ServerPremiumFeature::FEATURE_WIDGET => 'Widget personnalise',
            default => $feature,
        };

        $tx = new Transaction();
        $tx->setUser($user);
        $tx->setServer($server);
        $tx->setType(Transaction::TYPE_SPEND);
        $tx->setTokensAmount(-$cost);
        $tx->setDescription('Deblocage ' . $featureLabel . ' - ' . $server->getName());
        $this->em->persist($tx);

        $this->em->flush();

        $this->webhookService->dispatch('premium.feature_unlocked', [
            'title' => 'Fonctionnalite debloquee',
            'fields' => [
                ['name' => 'Fonctionnalite', 'value' => $featureLabel, 'inline' => true],
                ['name' => 'Serveur', 'value' => $server->getName(), 'inline' => true],
                ['name' => 'Utilisateur', 'value' => $user->getUsername(), 'inline' => true],
                ['name' => 'Cout', 'value' => $cost . ' NexBits', 'inline' => true],
            ],
        ]);

        return true;
    }

    public function purchaseWithNexbits(User $user, PremiumPlan $plan): bool
    {
        $cost = $plan->getNexbitsPrice();
        if ($cost <= 0) {
            return false;
        }

        if (!$user->hasEnoughTokens($cost)) {
            return false;
        }

        $user->removeTokens($cost);
        $user->addTokens($plan->getTokensGiven());
        $user->addBoostTokens($plan->getBoostTokensGiven());

        $tx = new Transaction();
        $tx->setUser($user);
        $tx->setPlan($plan);
        $tx->setType(Transaction::TYPE_SPEND);
        $tx->setTokensAmount($plan->getTokensGiven() - $cost);
        $tx->setBoostTokensAmount($plan->getBoostTokensGiven());
        $tx->setDescription('Achat du plan ' . $plan->getName() . ' (NexBits)');

        $this->em->persist($tx);
        $this->em->flush();

        return true;
    }

    public function purchaseWithServerNexbits(User $user, Server $server, PremiumPlan $plan): bool
    {
        $cost = $plan->getNexbitsPrice();
        if ($cost <= 0) {
            return false;
        }

        if (!$server->hasEnoughTokens($cost)) {
            return false;
        }

        $server->removeTokens($cost);
        $server->addTokens($plan->getTokensGiven());
        $server->addBoostTokens($plan->getBoostTokensGiven());

        $tx = new Transaction();
        $tx->setUser($user);
        $tx->setServer($server);
        $tx->setPlan($plan);
        $tx->setType(Transaction::TYPE_SPEND);
        $tx->setTokensAmount($plan->getTokensGiven() - $cost);
        $tx->setBoostTokensAmount($plan->getBoostTokensGiven());
        $tx->setDescription('Achat du plan ' . $plan->getName() . ' pour ' . $server->getName() . ' (NexBits serveur)');

        $this->em->persist($tx);
        $this->em->flush();

        return true;
    }

    public function creditTokensFromPurchase(User $user, PremiumPlan $plan, string $paypalOrderId, string $paypalStatus): Transaction
    {
        $isPending = $paypalStatus === Transaction::PAYPAL_STATUS_PENDING;

        if (!$isPending) {
            $user->addTokens($plan->getTokensGiven());
            $user->addBoostTokens($plan->getBoostTokensGiven());
        }

        $tx = new Transaction();
        $tx->setUser($user);
        $tx->setPlan($plan);
        $tx->setType(Transaction::TYPE_PURCHASE);
        $tx->setAmount($plan->getPrice());
        $tx->setCurrency($plan->getCurrency());
        $tx->setTokensAmount($plan->getTokensGiven());
        $tx->setBoostTokensAmount($plan->getBoostTokensGiven());
        $tx->setPaypalOrderId($paypalOrderId);
        $tx->setPaypalStatus($paypalStatus);
        $tx->setIsCredited(!$isPending);
        $tx->setCreditedAt($isPending ? null : new \DateTimeImmutable());
        $tx->setDescription('Achat du plan ' . $plan->getName() . ($isPending ? ' (virement en attente)' : ''));

        $this->em->persist($tx);
        $this->em->flush();

        return $tx;
    }

    public function completePendingTransaction(Transaction $tx): bool
    {
        if ($tx->isCredited()) {
            return false;
        }

        $user = $tx->getUser();
        if (!$user) {
            return false;
        }

        $user->addTokens($tx->getTokensAmount());
        $user->addBoostTokens($tx->getBoostTokensAmount());

        $tx->setIsCredited(true);
        $tx->setCreditedAt(new \DateTimeImmutable());
        $tx->setPaypalStatus(Transaction::PAYPAL_STATUS_COMPLETED);

        $this->em->flush();

        return true;
    }

    public function isOrderAlreadyCaptured(string $paypalOrderId): bool
    {
        $existing = $this->transactionRepo->findByPaypalOrderId($paypalOrderId);

        return $existing !== null && $existing->getType() === Transaction::TYPE_PURCHASE;
    }

    public function getRecruitmentFreeLimit(): int
    {
        return $this->settings->getInt('premium_recruitment_free_limit', 2);
    }

    public function getRecruitmentCost(): int
    {
        return $this->settings->getInt('premium_recruitment_cost', 50);
    }

    public function countRecruitmentsByServer(Server $server): int
    {
        return count($this->recruitmentRepo->findByServer($server));
    }

    public function canCreateRecruitment(Server $server, User $user): array
    {
        if (!$this->isFeatureGated('recruitment')) {
            return ['allowed' => true, 'cost' => 0, 'reason' => null];
        }

        $count = $this->countRecruitmentsByServer($server);
        $freeLimit = $this->getRecruitmentFreeLimit();

        if ($count < $freeLimit) {
            return ['allowed' => true, 'cost' => 0, 'reason' => null];
        }

        $cost = $this->getRecruitmentCost();
        if ($server->hasEnoughTokens($cost)) {
            return ['allowed' => true, 'cost' => $cost, 'reason' => null];
        }

        return [
            'allowed' => false,
            'cost' => $cost,
            'reason' => 'NexBits insuffisants sur le serveur. Vous avez atteint la limite gratuite de ' . $freeLimit . ' annonces par serveur.',
        ];
    }

    public function chargeRecruitmentExtra(User $user, Server $server): void
    {
        $cost = $this->getRecruitmentCost();
        if ($cost <= 0) {
            return;
        }

        $server->removeTokens($cost);

        $tx = new Transaction();
        $tx->setUser($user);
        $tx->setServer($server);
        $tx->setType(Transaction::TYPE_SPEND);
        $tx->setTokensAmount(-$cost);
        $tx->setDescription('Annonce recrutement supplementaire - ' . $server->getName());
        $this->em->persist($tx);

        $this->em->flush();
    }

    public function getBoostCost(): int
    {
        return $this->settings->getInt('premium_boost_cost', 1);
    }

    public function getMaxFeaturedPerDay(): int
    {
        return $this->settings->getInt('premium_max_featured_per_day', 5);
    }

    public function canBookFeaturedSlot(Server $server, \DateTimeInterface $startsAt, int $durationHours = 12): array
    {
        $now = new \DateTime();
        if ($startsAt < $now && $startsAt->format('Y-m-d H') !== $now->format('Y-m-d H')) {
            return ['available' => false, 'reason' => 'La date selectionnee est passee.'];
        }

        $endsAt = \DateTimeImmutable::createFromInterface($startsAt)->modify("+{$durationHours} hours");

        if ($this->bookingRepo->hasActiveBookingForServer($server, $startsAt, $endsAt)) {
            return ['available' => false, 'reason' => 'Ce serveur est deja mis en avant sur ce creneau.'];
        }

        $max = $this->getMaxFeaturedPerDay();
        $overlapping = $this->bookingRepo->countOverlapping($startsAt, $endsAt);
        if ($overlapping >= $max) {
            return ['available' => false, 'reason' => 'Tous les emplacements sont reserves pour ce creneau (' . $max . '/' . $max . ').'];
        }

        $cost = (int) ceil($durationHours / 12) * $this->getBoostCost();

        return ['available' => true, 'reason' => null, 'remaining' => $max - $overlapping, 'cost' => $cost];
    }

    public function bookFeaturedSlot(Server $server, User $user, \DateTimeInterface $startsAt, int $durationHours = 12): bool
    {
        $cost = (int) ceil($durationHours / 12) * $this->getBoostCost();
        if (!$server->hasEnoughBoostTokens($cost)) {
            return false;
        }

        $check = $this->canBookFeaturedSlot($server, $startsAt, $durationHours);
        if (!$check['available']) {
            return false;
        }

        $endsAt = \DateTimeImmutable::createFromInterface($startsAt)->modify("+{$durationHours} hours");

        $server->removeBoostTokens($cost);

        $booking = new FeaturedBooking();
        $booking->setServer($server);
        $booking->setUser($user);
        $booking->setStartsAt(\DateTimeImmutable::createFromInterface($startsAt));
        $booking->setEndsAt(\DateTimeImmutable::createFromInterface($endsAt));
        $booking->setBoostTokensUsed($cost);

        $this->em->persist($booking);

        $tx = new Transaction();
        $tx->setUser($user);
        $tx->setServer($server);
        $tx->setType(Transaction::TYPE_SPEND);
        $tx->setBoostTokensAmount(-$cost);
        $tx->setDescription('Mise en avant ' . $server->getName() . ' du ' . $startsAt->format('d/m/Y H:i') . ' au ' . $endsAt->format('d/m/Y H:i'));
        $this->em->persist($tx);

        $this->em->flush();

        $this->webhookService->dispatch('premium.boost_booked', [
            'title' => 'Boost reserve',
            'fields' => [
                ['name' => 'Serveur', 'value' => $server->getName(), 'inline' => true],
                ['name' => 'Utilisateur', 'value' => $user->getUsername(), 'inline' => true],
                ['name' => 'Creneau', 'value' => $startsAt->format('d/m/Y H:i') . ' - ' . $endsAt->format('d/m/Y H:i'), 'inline' => false],
                ['name' => 'Cout', 'value' => $cost . ' NexBoost', 'inline' => true],
            ],
        ]);

        return true;
    }

    // ──────────────────────────────────────────────
    // Twitch Live Subscription
    // ──────────────────────────────────────────────

    public function getTwitchLiveMonthlyTokenCost(): int
    {
        return $this->settings->getInt('premium_twitch_live_cost_tokens', 100);
    }

    public function getTwitchLiveMonthlyEurPrice(): string
    {
        return $this->settings->get('premium_twitch_live_cost_eur', '4.99') ?? '4.99';
    }

    public function hasTwitchLiveActive(Server $server): bool
    {
        if (!$this->isFeatureGated('twitch_live')) {
            return true;
        }

        $sub = $this->twitchSubRepo->findByServer($server);
        return $sub !== null && $sub->isActive();
    }

    public function getTwitchSubscription(Server $server): ?TwitchSubscription
    {
        return $this->twitchSubRepo->findByServer($server);
    }

    public function subscribeTwitchLiveWithTokens(Server $server, User $user): bool
    {
        $cost = $this->getTwitchLiveMonthlyTokenCost();
        if (!$server->hasEnoughTokens($cost)) {
            return false;
        }

        $server->removeTokens($cost);

        $sub = $this->twitchSubRepo->findByServer($server);
        if ($sub) {
            // Renew: extend from current expiry or now
            $expiresAt = $sub->isActive() ? $sub->getExpiresAt() : null;
            $base = $expiresAt instanceof \DateTimeImmutable ? $expiresAt : new \DateTimeImmutable();
            $sub->setExpiresAt($base->modify('+30 days'));
            $sub->setStatus(TwitchSubscription::STATUS_ACTIVE);
            $sub->setRenewedAt(new \DateTimeImmutable());
            $sub->setPaymentMethod('nexbits');
        } else {
            $sub = new TwitchSubscription();
            $sub->setServer($server);
            $sub->setSubscribedBy($user);
            $sub->setExpiresAt((new \DateTimeImmutable())->modify('+30 days'));
            $sub->setPaymentMethod('nexbits');
            $this->em->persist($sub);
        }

        $tx = new Transaction();
        $tx->setUser($user);
        $tx->setServer($server);
        $tx->setType(Transaction::TYPE_SPEND);
        $tx->setTokensAmount(-$cost);
        $tx->setDescription('Abonnement Twitch Live (30j) - ' . $server->getName());
        $this->em->persist($tx);

        $this->em->flush();

        $this->webhookService->dispatch('premium.twitch_live_subscribed', [
            'title' => 'Abonnement Twitch Live',
            'fields' => [
                ['name' => 'Serveur', 'value' => $server->getName(), 'inline' => true],
                ['name' => 'Utilisateur', 'value' => $user->getUsername(), 'inline' => true],
                ['name' => 'Methode', 'value' => 'NexBits (' . $cost . ')', 'inline' => true],
                ['name' => 'Expire le', 'value' => $sub->getExpiresAt()?->format('d/m/Y') ?? '', 'inline' => true],
            ],
        ]);

        return true;
    }

    public function subscribeTwitchLiveWithPaypal(Server $server, User $user, string $paypalOrderId): bool
    {
        $price = $this->getTwitchLiveMonthlyEurPrice();

        $sub = $this->twitchSubRepo->findByServer($server);
        if ($sub) {
            $expiresAt = $sub->isActive() ? $sub->getExpiresAt() : null;
            $base = $expiresAt instanceof \DateTimeImmutable ? $expiresAt : new \DateTimeImmutable();
            $sub->setExpiresAt($base->modify('+30 days'));
            $sub->setStatus(TwitchSubscription::STATUS_ACTIVE);
            $sub->setRenewedAt(new \DateTimeImmutable());
            $sub->setPaymentMethod('paypal');
        } else {
            $sub = new TwitchSubscription();
            $sub->setServer($server);
            $sub->setSubscribedBy($user);
            $sub->setExpiresAt((new \DateTimeImmutable())->modify('+30 days'));
            $sub->setPaymentMethod('paypal');
            $this->em->persist($sub);
        }

        $tx = new Transaction();
        $tx->setUser($user);
        $tx->setType(Transaction::TYPE_PURCHASE);
        $tx->setAmount($price);
        $tx->setCurrency('EUR');
        $tx->setPaypalOrderId($paypalOrderId);
        $tx->setPaypalStatus('COMPLETED');
        $tx->setDescription('Abonnement Twitch Live (30j) - ' . $server->getName());
        $this->em->persist($tx);

        $this->em->flush();

        $this->webhookService->dispatch('premium.twitch_live_subscribed', [
            'title' => 'Abonnement Twitch Live (PayPal)',
            'fields' => [
                ['name' => 'Serveur', 'value' => $server->getName(), 'inline' => true],
                ['name' => 'Utilisateur', 'value' => $user->getUsername(), 'inline' => true],
                ['name' => 'Montant', 'value' => $price . ' EUR', 'inline' => true],
                ['name' => 'Expire le', 'value' => $sub->getExpiresAt()?->format('d/m/Y') ?? '', 'inline' => true],
            ],
        ]);

        return true;
    }

    public function cancelTwitchLive(Server $server): void
    {
        $sub = $this->twitchSubRepo->findByServer($server);
        if ($sub) {
            $sub->setAutoRenew(false);
            $sub->setStatus(TwitchSubscription::STATUS_CANCELLED);
            $this->em->flush();
        }
    }

    /**
     * Expire overdue subscriptions + auto-renew with NexBits.
     * Called by cron command.
     */
    public function processExpiredTwitchSubscriptions(): array
    {
        $results = ['expired' => 0, 'renewed' => 0];

        $expired = $this->twitchSubRepo->findExpired();
        foreach ($expired as $sub) {
            if ($sub->isAutoRenew() && $sub->getPaymentMethod() === 'nexbits') {
                $server = $sub->getServer();
                $user = $sub->getSubscribedBy();
                $cost = $this->getTwitchLiveMonthlyTokenCost();

                if ($server && $server->hasEnoughTokens($cost)) {
                    $server->removeTokens($cost);
                    $sub->setExpiresAt((new \DateTimeImmutable())->modify('+30 days'));
                    $sub->setStatus(TwitchSubscription::STATUS_ACTIVE);
                    $sub->setRenewedAt(new \DateTimeImmutable());

                    $tx = new Transaction();
                    $tx->setUser($user);
                    $tx->setServer($server);
                    $tx->setType(Transaction::TYPE_SPEND);
                    $tx->setTokensAmount(-$cost);
                    $tx->setDescription('Renouvellement Twitch Live (auto) - ' . $server->getName());
                    $this->em->persist($tx);

                    $results['renewed']++;
                    continue;
                }
            }

            $sub->setStatus(TwitchSubscription::STATUS_EXPIRED);
            $results['expired']++;
        }

        $this->em->flush();
        return $results;
    }

    // ──────────────────────────────────────────────
    // Position-based Booking (Selection Premium)
    // ──────────────────────────────────────────────

    public function getPositionCost(string $scope, int $position): int
    {
        $key = 'premium_' . $scope . '_pos' . $position . '_cost';
        $defaults = [
            'premium_homepage_pos1_cost' => 10,
            'premium_homepage_pos2_cost' => 8,
            'premium_homepage_pos3_cost' => 6,
            'premium_homepage_pos4_cost' => 4,
            'premium_homepage_pos5_cost' => 2,
            'premium_game_pos1_cost' => 5,
            'premium_game_pos2_cost' => 4,
            'premium_game_pos3_cost' => 3,
            'premium_game_pos4_cost' => 2,
            'premium_game_pos5_cost' => 1,
        ];

        return $this->settings->getInt($key, $defaults[$key] ?? 1);
    }

    /**
     * @return array<int, int> position => cost per 12h
     */
    public function getAllPositionCosts(string $scope): array
    {
        $costs = [];
        for ($i = 1; $i <= 5; $i++) {
            $costs[$i] = $this->getPositionCost($scope, $i);
        }
        return $costs;
    }

    public function calculatePositionBookingCost(string $scope, int $position, int $durationHours): int
    {
        return (int) ceil($durationHours / 12) * $this->getPositionCost($scope, $position);
    }

    public function canBookPosition(Server $server, string $scope, int $position, \DateTimeInterface $startsAt, int $durationHours, ?GameCategory $gc = null): array
    {
        if ($position < 1 || $position > 5) {
            return ['available' => false, 'reason' => 'Position invalide.'];
        }

        $now = new \DateTime();
        if ($startsAt < $now && $startsAt->format('Y-m-d H') !== $now->format('Y-m-d H')) {
            return ['available' => false, 'reason' => 'La date selectionnee est passee.'];
        }

        if ($scope === FeaturedBooking::SCOPE_GAME && !$gc) {
            return ['available' => false, 'reason' => 'Veuillez selectionner un jeu.'];
        }

        $endsAt = \DateTimeImmutable::createFromInterface($startsAt)->modify("+{$durationHours} hours");

        if (!$this->bookingRepo->isPositionAvailable($scope, $position, $startsAt, $endsAt, $gc)) {
            return ['available' => false, 'reason' => 'Cette position est deja reservee pour ce creneau.'];
        }

        $cost = $this->calculatePositionBookingCost($scope, $position, $durationHours);

        return ['available' => true, 'reason' => null, 'cost' => $cost];
    }

    public function bookPosition(Server $server, User $user, string $scope, int $position, \DateTimeInterface $startsAt, int $durationHours, ?GameCategory $gc = null): bool
    {
        $check = $this->canBookPosition($server, $scope, $position, $startsAt, $durationHours, $gc);
        if (!$check['available']) {
            return false;
        }

        $cost = $check['cost'];
        if (!$server->hasEnoughBoostTokens($cost)) {
            return false;
        }

        $endsAt = \DateTimeImmutable::createFromInterface($startsAt)->modify("+{$durationHours} hours");

        $server->removeBoostTokens($cost);

        $booking = new FeaturedBooking();
        $booking->setServer($server);
        $booking->setUser($user);
        $booking->setScope($scope);
        $booking->setPosition($position);
        $booking->setGameCategory($gc);
        $booking->setStartsAt(\DateTimeImmutable::createFromInterface($startsAt));
        $booking->setEndsAt(\DateTimeImmutable::createFromInterface($endsAt));
        $booking->setBoostTokensUsed($cost);

        $this->em->persist($booking);

        $scopeLabel = $scope === FeaturedBooking::SCOPE_HOMEPAGE ? 'Accueil' : ('Jeu: ' . ($gc ? $gc->getName() : '?'));
        $tx = new Transaction();
        $tx->setUser($user);
        $tx->setServer($server);
        $tx->setType(Transaction::TYPE_SPEND);
        $tx->setBoostTokensAmount(-$cost);
        $tx->setDescription('Selection premium #' . $position . ' (' . $scopeLabel . ') - ' . $server->getName() . ' du ' . $startsAt->format('d/m/Y H:i') . ' au ' . $endsAt->format('d/m/Y H:i'));
        $this->em->persist($tx);

        $this->em->flush();

        $this->webhookService->dispatch('premium.boost_booked', [
            'title' => 'Selection premium reservee',
            'fields' => [
                ['name' => 'Serveur', 'value' => $server->getName(), 'inline' => true],
                ['name' => 'Position', 'value' => '#' . $position, 'inline' => true],
                ['name' => 'Scope', 'value' => $scopeLabel, 'inline' => true],
                ['name' => 'Utilisateur', 'value' => $user->getUsername(), 'inline' => true],
                ['name' => 'Creneau', 'value' => $startsAt->format('d/m/Y H:i') . ' - ' . $endsAt->format('d/m/Y H:i'), 'inline' => false],
                ['name' => 'Cout', 'value' => $cost . ' NexBoost', 'inline' => true],
            ],
        ]);

        return true;
    }

    /**
     * Public donation from any logged-in user to a server.
     * Enforces daily per-user limits from settings.
     *
     * @return array{success: bool, error: ?string}
     */
    public function donateToServer(User $user, Server $server, int $nexbits, int $nexboost): array
    {
        if ($nexbits < 0 || $nexboost < 0) {
            return ['success' => false, 'error' => 'Les montants ne peuvent pas etre negatifs.'];
        }

        if ($nexbits === 0 && $nexboost === 0) {
            return ['success' => false, 'error' => 'Vous devez donner au moins 1 NexBit ou 1 NexBoost.'];
        }

        // Check user balance
        if ($nexbits > 0 && !$user->hasEnoughTokens($nexbits)) {
            return ['success' => false, 'error' => 'Vous n\'avez pas assez de NexBits. Solde : ' . $user->getTokenBalance() . ' NexBits.'];
        }
        if ($nexboost > 0 && !$user->hasEnoughBoostTokens($nexboost)) {
            return ['success' => false, 'error' => 'Vous n\'avez pas assez de NexBoost. Solde : ' . $user->getBoostTokenBalance() . ' NexBoost.'];
        }

        // Check daily limits
        $limitNexbits = $this->settings->getInt('donation_daily_limit_nexbits', 1000);
        $limitNexboost = $this->settings->getInt('donation_daily_limit_nexboost', 10);
        $today = $this->transactionRepo->getSumDonationsByUserToday($user);

        if ($limitNexbits > 0 && $nexbits > 0) {
            $remaining = $limitNexbits - $today['nexbits'];
            if ($nexbits > $remaining) {
                return ['success' => false, 'error' => sprintf(
                    'Limite quotidienne de dons NexBits atteinte. Il vous reste %d NexBits a donner aujourd\'hui.',
                    max(0, $remaining)
                )];
            }
        }

        if ($limitNexboost > 0 && $nexboost > 0) {
            $remaining = $limitNexboost - $today['nexboost'];
            if ($nexboost > $remaining) {
                return ['success' => false, 'error' => sprintf(
                    'Limite quotidienne de dons NexBoost atteinte. Il vous reste %d NexBoost a donner aujourd\'hui.',
                    max(0, $remaining)
                )];
            }
        }

        $result = $this->depositToServer($user, $server, $nexbits, $nexboost);
        if (!$result) {
            return ['success' => false, 'error' => 'Le don a echoue. Verifiez votre solde.'];
        }

        return ['success' => true, 'error' => null];
    }

    public function depositToServer(User $user, Server $server, int $nexbits, int $nexboost): bool
    {
        if ($nexbits < 0 || $nexboost < 0) {
            return false;
        }
        if ($nexbits === 0 && $nexboost === 0) {
            return false;
        }
        if ($nexbits > 0 && !$user->hasEnoughTokens($nexbits)) {
            return false;
        }
        if ($nexboost > 0 && !$user->hasEnoughBoostTokens($nexboost)) {
            return false;
        }

        if ($nexbits > 0) {
            $user->removeTokens($nexbits);
            $server->addTokens($nexbits);
        }
        if ($nexboost > 0) {
            $user->removeBoostTokens($nexboost);
            $server->addBoostTokens($nexboost);
        }

        $tx = new Transaction();
        $tx->setUser($user);
        $tx->setServer($server);
        $tx->setType(Transaction::TYPE_DEPOSIT);
        $tx->setTokensAmount($nexbits);
        $tx->setBoostTokensAmount($nexboost);

        $parts = [];
        if ($nexbits > 0) {
            $parts[] = $nexbits . ' NexBits';
        }
        if ($nexboost > 0) {
            $parts[] = $nexboost . ' NexBoost';
        }
        $tx->setDescription('Depot sur ' . $server->getName() . ' : ' . implode(' + ', $parts));

        $this->em->persist($tx);
        $this->em->flush();

        $this->webhookService->dispatch('premium.deposit', [
            'title' => 'Depot sur serveur',
            'fields' => [
                ['name' => 'Serveur', 'value' => $server->getName(), 'inline' => true],
                ['name' => 'Utilisateur', 'value' => $user->getUsername(), 'inline' => true],
                ['name' => 'NexBits', 'value' => (string) $nexbits, 'inline' => true],
                ['name' => 'NexBoost', 'value' => (string) $nexboost, 'inline' => true],
            ],
        ]);

        return true;
    }

    // =============================================
    // CRYPTO.COM PAY
    // =============================================

    public function creditTokensFromCryptoPurchase(
        User $user,
        PremiumPlan $plan,
        string $cryptoPaymentId,
        string $cryptoStatus,
    ): Transaction {
        $isCaptured = $cryptoStatus === Transaction::CRYPTO_STATUS_CAPTURED;

        if ($isCaptured) {
            $user->addTokens($plan->getTokensGiven());
            $user->addBoostTokens($plan->getBoostTokensGiven());
        }

        $tx = new Transaction();
        $tx->setUser($user);
        $tx->setPlan($plan);
        $tx->setType(Transaction::TYPE_PURCHASE);
        $tx->setAmount($plan->getPrice());
        $tx->setCurrency($plan->getCurrency());
        $tx->setTokensAmount($plan->getTokensGiven());
        $tx->setBoostTokensAmount($plan->getBoostTokensGiven());
        $tx->setCryptoPaymentId($cryptoPaymentId);
        $tx->setCryptoStatus($cryptoStatus);
        $tx->setIsCredited($isCaptured);
        $tx->setCreditedAt($isCaptured ? new \DateTimeImmutable() : null);
        $tx->setDescription('Achat du plan ' . $plan->getName() . ' (Crypto.com Pay)' . ($isCaptured ? '' : ' — en attente'));

        $this->em->persist($tx);
        $this->em->flush();

        return $tx;
    }

    public function completePendingCryptoTransaction(Transaction $tx): bool
    {
        if ($tx->isCredited()) {
            return false;
        }

        $user = $tx->getUser();
        if (!$user) {
            return false;
        }

        $user->addTokens($tx->getTokensAmount());
        $user->addBoostTokens($tx->getBoostTokensAmount());

        $tx->setIsCredited(true);
        $tx->setCreditedAt(new \DateTimeImmutable());
        $tx->setCryptoStatus(Transaction::CRYPTO_STATUS_CAPTURED);

        $this->em->flush();

        return true;
    }

    public function isCryptoPaymentAlreadyCaptured(string $cryptoPaymentId): bool
    {
        $existing = $this->transactionRepo->findByCryptoPaymentId($cryptoPaymentId);

        return $existing !== null && $existing->isCredited();
    }

    // =============================================
    // STRIPE
    // =============================================

    public function creditTokensFromStripePurchase(
        User $user,
        PremiumPlan $plan,
        string $stripeSessionId,
        string $stripeStatus,
    ): Transaction {
        $isPaid = $stripeStatus === Transaction::STRIPE_STATUS_COMPLETE;

        if ($isPaid) {
            $user->addTokens($plan->getTokensGiven());
            $user->addBoostTokens($plan->getBoostTokensGiven());
        }

        $tx = new Transaction();
        $tx->setUser($user);
        $tx->setPlan($plan);
        $tx->setType(Transaction::TYPE_PURCHASE);
        $tx->setAmount($plan->getPrice());
        $tx->setCurrency($plan->getCurrency());
        $tx->setTokensAmount($plan->getTokensGiven());
        $tx->setBoostTokensAmount($plan->getBoostTokensGiven());
        $tx->setStripeSessionId($stripeSessionId);
        $tx->setStripeStatus($stripeStatus);
        $tx->setIsCredited($isPaid);
        $tx->setCreditedAt($isPaid ? new \DateTimeImmutable() : null);
        $tx->setDescription('Achat du plan ' . $plan->getName() . ' (Stripe)' . ($isPaid ? '' : ' — en attente'));

        $this->em->persist($tx);
        $this->em->flush();

        return $tx;
    }

    public function completePendingStripeTransaction(Transaction $tx): bool
    {
        if ($tx->isCredited()) {
            return false;
        }

        $user = $tx->getUser();
        if (!$user) {
            return false;
        }

        $user->addTokens($tx->getTokensAmount());
        $user->addBoostTokens($tx->getBoostTokensAmount());

        $tx->setIsCredited(true);
        $tx->setCreditedAt(new \DateTimeImmutable());
        $tx->setStripeStatus(Transaction::STRIPE_STATUS_COMPLETE);

        $this->em->flush();

        return true;
    }

    public function isStripeSessionAlreadyCaptured(string $stripeSessionId): bool
    {
        $existing = $this->transactionRepo->findByStripeSessionId($stripeSessionId);

        return $existing !== null && $existing->isCredited();
    }

    public function processRefund(User $user, string $paypalOrderId, string $description): void
    {
        $tokens = 0;
        $boostTokens = 0;

        $originalTx = $this->transactionRepo->findByPaypalOrderId($paypalOrderId);
        if ($originalTx) {
            $tokens = $originalTx->getTokensAmount();
            $boostTokens = $originalTx->getBoostTokensAmount();

            if ($user->getTokenBalance() >= $tokens) {
                $user->removeTokens($tokens);
            } else {
                $user->setTokenBalance(0);
            }
            if ($user->getBoostTokenBalance() >= $boostTokens) {
                $user->removeBoostTokens($boostTokens);
            } else {
                $user->setBoostTokenBalance(0);
            }
        }

        $tx = new Transaction();
        $tx->setUser($user);
        $tx->setType(Transaction::TYPE_REFUND);
        $tx->setPaypalOrderId($paypalOrderId);
        $tx->setTokensAmount(-$tokens);
        $tx->setBoostTokensAmount(-$boostTokens);
        $tx->setDescription($description);
        $this->em->persist($tx);

        $this->em->flush();
    }
}
