<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\DiscordModerationLogRepository;
use App\Repository\DiscordSanctionRepository;
use App\Repository\DiscordTicketRepository;
use App\Service\DiscordBotService;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class DiscordExtension extends AbstractExtension
{
    public function __construct(
        private DiscordTicketRepository $ticketRepo,
        private DiscordSanctionRepository $sanctionRepo,
        private DiscordModerationLogRepository $modlogRepo,
        private DiscordBotService $botService,
        private CacheInterface $cache,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('discord_open_tickets_count', [$this, 'getOpenTicketsCount']),
            new TwigFunction('discord_active_sanctions_count', [$this, 'getActiveSanctionsCount']),
            new TwigFunction('discord_member', [$this, 'isDiscordMember']),
            new TwigFunction('discord_modlog_today_count', [$this, 'getModlogTodayCount']),
        ];
    }

    public function getOpenTicketsCount(): int
    {
        return $this->ticketRepo->countOpen();
    }

    public function getActiveSanctionsCount(): int
    {
        return $this->sanctionRepo->countActive();
    }

    public function getModlogTodayCount(): int
    {
        return $this->modlogRepo->countToday();
    }

    public function isDiscordMember(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $discordId = $user->getDiscordId();
        if (!$discordId) {
            return false;
        }

        return $this->cache->get('discord_member_' . $discordId, function (ItemInterface $item) use ($discordId) {
            $item->expiresAfter(3600); // 1h cache
            try {
                return $this->botService->isGuildMember($discordId);
            } catch (\Throwable) {
                return false;
            }
        });
    }
}
