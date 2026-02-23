<?php

namespace App\Twig;

use App\Repository\IpBanRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class IpBanExtension extends AbstractExtension
{
    public function __construct(
        private IpBanRepository $ipBanRepository,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('active_ip_bans_count', [$this, 'getActiveIpBansCount']),
        ];
    }

    public function getActiveIpBansCount(): int
    {
        return $this->ipBanRepository->countActive();
    }
}
