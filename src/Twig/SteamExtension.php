<?php

namespace App\Twig;

use App\Security\SteamAuthenticator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SteamExtension extends AbstractExtension
{
    public function __construct(
        private SteamAuthenticator $steamAuthenticator,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('steam_login_url', [$this, 'getSteamLoginUrl']),
        ];
    }

    public function getSteamLoginUrl(): string
    {
        return $this->steamAuthenticator->getRedirectUrl();
    }
}
