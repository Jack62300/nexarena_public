<?php

namespace App\Twig;

use App\Entity\Server;
use App\Repository\ServerPremiumFeatureRepository;
use App\Repository\TwitchSubscriptionRepository;
use App\Service\PremiumService;
use App\Service\SettingsService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PremiumExtension extends AbstractExtension
{
    private ?array $twitchLiveIds = null;

    public function __construct(
        private ServerPremiumFeatureRepository $featureRepo,
        private TwitchSubscriptionRepository $twitchSubRepo,
        private SettingsService $settings,
        private PremiumService $premiumService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('server_has_premium', [$this, 'serverHasPremium']),
            new TwigFunction('premium_enabled', [$this, 'isPremiumEnabled']),
            new TwigFunction('premium_feature_gated', [$this, 'isFeatureGated']),
            new TwigFunction('server_has_twitch_live', [$this, 'serverHasTwitchLive']),
        ];
    }

    public function serverHasPremium(Server $server, string $feature): bool
    {
        if (!$this->isPremiumEnabled()) {
            return true;
        }

        return $this->premiumService->hasServerFeature($server, $feature);
    }

    public function isPremiumEnabled(): bool
    {
        return $this->settings->getBool('premium_enabled', true);
    }

    public function isFeatureGated(string $feature): bool
    {
        return $this->premiumService->isFeatureGated($feature);
    }

    public function serverHasTwitchLive(Server $server): bool
    {
        if (!$server->getTwitchChannel()) {
            return false;
        }

        if (!$this->premiumService->isFeatureGated('twitch_live')) {
            return true;
        }

        if ($this->twitchLiveIds === null) {
            $this->twitchLiveIds = $this->twitchSubRepo->findActiveServerIds();
        }

        return in_array($server->getId(), $this->twitchLiveIds, true);
    }
}
