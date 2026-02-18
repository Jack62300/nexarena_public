<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class DateExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('time_ago', [$this, 'timeAgo'], ['is_safe' => ['html']]),
        ];
    }

    public function timeAgo(?\DateTimeInterface $date, string $format = 'd/m/Y H:i'): string
    {
        if ($date === null) {
            return '—';
        }

        $now = new \DateTimeImmutable();
        $diff = $now->getTimestamp() - $date->getTimestamp();
        $absolute = abs($diff);
        $isFuture = $diff < 0;

        if ($absolute < 45) {
            $relative = "à l'instant";
        } elseif ($absolute < 2700) {
            // < 45 minutes
            $mins = max(1, (int) round($absolute / 60));
            $relative = $isFuture
                ? "dans {$mins} min"
                : "il y a {$mins} min";
        } elseif ($absolute < 5400) {
            // < 1h30 → 1 heure
            $relative = $isFuture ? "dans 1 h" : "il y a 1 h";
        } elseif ($absolute < 79200) {
            // < 22 h
            $hours = (int) round($absolute / 3600);
            $relative = $isFuture
                ? "dans {$hours} h"
                : "il y a {$hours} h";
        } elseif ($absolute < 129600) {
            // < 36 h → hier / demain
            $relative = $isFuture ? "demain" : "hier";
        } elseif ($absolute < 86400 * 6.5) {
            // < ~6.5 jours
            $days = (int) round($absolute / 86400);
            $relative = $isFuture
                ? "dans {$days} j"
                : "il y a {$days} j";
        } elseif ($absolute < 86400 * 25) {
            // < 25 jours
            $weeks = (int) round($absolute / (86400 * 7));
            $w = $weeks > 1 ? 'sem.' : 'sem.';
            $relative = $isFuture
                ? "dans {$weeks} {$w}"
                : "il y a {$weeks} {$w}";
        } elseif ($absolute < 86400 * 345) {
            // < ~11.5 mois
            $months = (int) round($absolute / (86400 * 30.5));
            $months = max(1, $months);
            $relative = $isFuture
                ? "dans {$months} mois"
                : "il y a {$months} mois";
        } else {
            $years = (int) round($absolute / (86400 * 365.25));
            $years = max(1, $years);
            $y = $years > 1 ? 'ans' : 'an';
            $relative = $isFuture
                ? "dans {$years} {$y}"
                : "il y a {$years} {$y}";
        }

        $fullDate = $date->format($format);
        $iso = $date->format(\DateTimeInterface::ATOM);

        return sprintf(
            '<time datetime="%s" title="%s" style="cursor:help;text-decoration:underline;text-decoration-style:dotted;text-underline-offset:2px;">%s</time>',
            htmlspecialchars($iso, ENT_QUOTES),
            htmlspecialchars($fullDate, ENT_QUOTES),
            htmlspecialchars($relative, ENT_QUOTES)
        );
    }
}
