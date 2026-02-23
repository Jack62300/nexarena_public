<?php

namespace App\Twig;

use App\Repository\IpBanRepository;
use App\Repository\UserRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class IpBanExtension extends AbstractExtension
{
    public function __construct(
        private IpBanRepository $ipBanRepository,
        private UserRepository $userRepository,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('active_ip_bans_count', [$this, 'getActiveIpBansCount']),
            new TwigFunction('banned_users_count', [$this, 'getBannedUsersCount']),
        ];
    }

    public function getActiveIpBansCount(): int
    {
        return $this->ipBanRepository->countActive();
    }

    public function getBannedUsersCount(): int
    {
        return $this->userRepository->countBanned();
    }
}
