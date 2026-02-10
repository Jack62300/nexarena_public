<?php

namespace App\Twig;

use App\Service\SettingsService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SettingsExtension extends AbstractExtension
{
    public function __construct(
        private SettingsService $settings,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('setting', [$this, 'getSetting']),
            new TwigFunction('setting_bool', [$this, 'getSettingBool']),
        ];
    }

    public function getSetting(string $key, ?string $default = null): ?string
    {
        return $this->settings->get($key, $default);
    }

    public function getSettingBool(string $key, bool $default = false): bool
    {
        return $this->settings->getBool($key, $default);
    }
}
