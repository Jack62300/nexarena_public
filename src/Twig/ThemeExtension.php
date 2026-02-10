<?php

namespace App\Twig;

use App\Service\ThemeService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class ThemeExtension extends AbstractExtension
{
    public function __construct(
        private ThemeService $themeService,
        private string $projectDir,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('hex_to_rgb', [$this, 'hexToRgb']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('server_theme', [$this, 'getTheme']),
            new TwigFunction('theme_image', [$this, 'getThemeImage']),
        ];
    }

    public function hexToRgb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return '69,248,130';
        }

        return intval(substr($hex, 0, 2), 16) . ',' . intval(substr($hex, 2, 2), 16) . ',' . intval(substr($hex, 4, 2), 16);
    }

    public function getTheme(string $key): array
    {
        return $this->themeService->getTheme($key);
    }

    /**
     * Returns asset path for a theme image if it exists on disk.
     * Checks uploads/themes/{key}/{type}.{ext} for any image extension.
     */
    public function getThemeImage(string $themeKey, string $type): ?string
    {
        $dir = $this->projectDir . '/public/uploads/themes/' . $themeKey;
        foreach (['jpg', 'jpeg', 'png', 'webp', 'gif'] as $ext) {
            $path = $dir . '/' . $type . '.' . $ext;
            if (file_exists($path)) {
                return 'uploads/themes/' . $themeKey . '/' . $type . '.' . $ext;
            }
        }

        return null;
    }
}
