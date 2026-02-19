<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class CountryFlagExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('country_flag', $this->countryFlag(...), ['is_safe' => ['html']]),
        ];
    }

    /**
     * Convertit un code ISO 3166-1 alpha-2 (ex: "FR") en emoji drapeau (ex: 🇫🇷).
     */
    public function countryFlag(string $code): string
    {
        $code = strtoupper(trim($code));
        if (strlen($code) !== 2) {
            return '🏳';
        }

        $emoji = '';
        foreach (str_split($code) as $letter) {
            $emoji .= mb_chr(0x1F1E6 + ord($letter) - ord('A'));
        }

        return $emoji;
    }
}
