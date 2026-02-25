<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class CountryFlagExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('country_flag', [$this, 'countryFlag']),
        ];
    }

    /**
     * Converts a 2-letter ISO country code to an emoji flag.
     * Example: 'FR' → '🇫🇷'
     */
    public function countryFlag(string $code): string
    {
        $code = strtoupper(trim($code));
        if (strlen($code) !== 2 || !ctype_alpha($code)) {
            return '';
        }

        $emoji = '';
        foreach (str_split($code) as $letter) {
            $emoji .= (string) mb_chr(0x1F1E6 + ord($letter) - ord('A'));
        }

        return $emoji;
    }
}
