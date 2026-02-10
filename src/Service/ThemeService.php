<?php

namespace App\Service;

class ThemeService
{
    /**
     * Each theme defines a full page atmosphere:
     * - label/icon: Display name + FontAwesome icon
     * - primary/secondary/rgb: Accent colors
     * - bg_body/bg_card_rgb/border_rgb: Dark background tones
     * - hero_from/hero_to/glow: Hero section gradient + glow
     * - bg_image: Full-page background image (in public/assets/themes/{key}/)
     * - decor_left/decor_right: Decorative elements (characters, objects) on sides
     * - overlay_opacity: Darkness of overlay on bg_image (0.0 - 1.0)
     *
     * Images are stored in public/assets/themes/{key}/
     * Expected files: bg.jpg (or .png/.webp), decor-left.png, decor-right.png
     */
    public const THEMES = [
        'default' => [
            'label' => 'Nexarena', 'icon' => 'fas fa-star',
            'primary' => '#45f882', 'secondary' => '#33d96e', 'rgb' => '69,248,130',
            'bg_body' => '#0a1018', 'bg_card_rgb' => '22,34,49', 'border_rgb' => '30,48,72',
            'hero_from' => '#0d1b2a', 'hero_to' => '#0a1e14', 'glow' => '#45f882',
            'bg_image' => null, 'decor_left' => null, 'decor_right' => null,
            'overlay_opacity' => '0.7',
        ],
        'minecraft' => [
            'label' => 'Minecraft', 'icon' => 'fas fa-cube',
            'primary' => '#55c553', 'secondary' => '#3d8c3a', 'rgb' => '85,197,83',
            'bg_body' => '#0f0d08', 'bg_card_rgb' => '32,28,18', 'border_rgb' => '50,44,30',
            'hero_from' => '#1a1408', 'hero_to' => '#0a1e0d', 'glow' => '#55c553',
            'bg_image' => 'bg.jpg', 'decor_left' => 'decor-left.png', 'decor_right' => 'decor-right.png',
            'overlay_opacity' => '0.75',
        ],
        'gta' => [
            'label' => 'GTA / FiveM', 'icon' => 'fas fa-car',
            'primary' => '#f5a623', 'secondary' => '#d4891a', 'rgb' => '245,166,35',
            'bg_body' => '#110e0a', 'bg_card_rgb' => '34,28,18', 'border_rgb' => '52,42,28',
            'hero_from' => '#1a1208', 'hero_to' => '#201a0a', 'glow' => '#f5a623',
            'bg_image' => 'bg.jpg', 'decor_left' => 'decor-left.png', 'decor_right' => 'decor-right.png',
            'overlay_opacity' => '0.7',
        ],
        'fivem' => [
            'label' => 'FiveM / RedM', 'icon' => 'fas fa-road',
            'primary' => '#f66435', 'secondary' => '#d14a20', 'rgb' => '246,100,53',
            'bg_body' => '#120c08', 'bg_card_rgb' => '36,24,16', 'border_rgb' => '55,36,24',
            'hero_from' => '#1a0e08', 'hero_to' => '#221208', 'glow' => '#f66435',
            'bg_image' => 'bg.jpg', 'decor_left' => 'decor-left.png', 'decor_right' => 'decor-right.png',
            'overlay_opacity' => '0.7',
        ],
        'arma' => [
            'label' => 'Arma', 'icon' => 'fas fa-crosshairs',
            'primary' => '#8b7d3c', 'secondary' => '#6b5f28', 'rgb' => '139,125,60',
            'bg_body' => '#0c0e08', 'bg_card_rgb' => '26,30,18', 'border_rgb' => '42,46,28',
            'hero_from' => '#141a08', 'hero_to' => '#0e1608', 'glow' => '#8b7d3c',
            'bg_image' => 'bg.jpg', 'decor_left' => 'decor-left.png', 'decor_right' => null,
            'overlay_opacity' => '0.7',
        ],
        'rust' => [
            'label' => 'Rust', 'icon' => 'fas fa-hammer',
            'primary' => '#cd412b', 'secondary' => '#a83220', 'rgb' => '205,65,43',
            'bg_body' => '#100a08', 'bg_card_rgb' => '34,20,16', 'border_rgb' => '52,32,26',
            'hero_from' => '#1a0c08', 'hero_to' => '#200e08', 'glow' => '#cd412b',
            'bg_image' => 'bg.jpg', 'decor_left' => 'decor-left.png', 'decor_right' => 'decor-right.png',
            'overlay_opacity' => '0.7',
        ],
        'cs2' => [
            'label' => 'Counter-Strike 2', 'icon' => 'fas fa-bullseye',
            'primary' => '#de9b35', 'secondary' => '#b87e28', 'rgb' => '222,155,53',
            'bg_body' => '#0e0c08', 'bg_card_rgb' => '30,26,16', 'border_rgb' => '48,40,24',
            'hero_from' => '#181208', 'hero_to' => '#1e1608', 'glow' => '#de9b35',
            'bg_image' => 'bg.jpg', 'decor_left' => 'decor-left.png', 'decor_right' => 'decor-right.png',
            'overlay_opacity' => '0.7',
        ],
        'valorant' => [
            'label' => 'Valorant', 'icon' => 'fas fa-shield-alt',
            'primary' => '#ff4655', 'secondary' => '#d13440', 'rgb' => '255,70,85',
            'bg_body' => '#0e0808', 'bg_card_rgb' => '32,16,18', 'border_rgb' => '52,26,30',
            'hero_from' => '#1a0a0c', 'hero_to' => '#200c10', 'glow' => '#ff4655',
            'bg_image' => 'bg.jpg', 'decor_left' => 'decor-left.png', 'decor_right' => 'decor-right.png',
            'overlay_opacity' => '0.65',
        ],
        'lol' => [
            'label' => 'League of Legends', 'icon' => 'fas fa-crown',
            'primary' => '#c8aa6e', 'secondary' => '#a08550', 'rgb' => '200,170,110',
            'bg_body' => '#0e0c08', 'bg_card_rgb' => '30,26,18', 'border_rgb' => '48,40,28',
            'hero_from' => '#161208', 'hero_to' => '#1c160a', 'glow' => '#c8aa6e',
            'bg_image' => 'bg.jpg', 'decor_left' => 'decor-left.png', 'decor_right' => 'decor-right.png',
            'overlay_opacity' => '0.7',
        ],
        'fortnite' => [
            'label' => 'Fortnite', 'icon' => 'fas fa-bolt',
            'primary' => '#00a2ff', 'secondary' => '#007dcc', 'rgb' => '0,162,255',
            'bg_body' => '#080e14', 'bg_card_rgb' => '16,28,40', 'border_rgb' => '24,44,62',
            'hero_from' => '#0a1420', 'hero_to' => '#081a2a', 'glow' => '#00a2ff',
            'bg_image' => 'bg.jpg', 'decor_left' => 'decor-left.png', 'decor_right' => 'decor-right.png',
            'overlay_opacity' => '0.7',
        ],
        'ark' => [
            'label' => 'ARK: Survival', 'icon' => 'fas fa-dragon',
            'primary' => '#00d4aa', 'secondary' => '#00a888', 'rgb' => '0,212,170',
            'bg_body' => '#080e0e', 'bg_card_rgb' => '16,30,32', 'border_rgb' => '24,48,50',
            'hero_from' => '#0a1618', 'hero_to' => '#081a1e', 'glow' => '#00d4aa',
            'bg_image' => 'bg.jpg', 'decor_left' => 'decor-left.png', 'decor_right' => null,
            'overlay_opacity' => '0.7',
        ],
        'gmod' => [
            'label' => 'Garry\'s Mod', 'icon' => 'fas fa-cog',
            'primary' => '#2596be', 'secondary' => '#1c7499', 'rgb' => '37,150,190',
            'bg_body' => '#080c12', 'bg_card_rgb' => '16,26,36', 'border_rgb' => '24,40,56',
            'hero_from' => '#0a1218', 'hero_to' => '#0e1822', 'glow' => '#2596be',
            'bg_image' => 'bg.jpg', 'decor_left' => null, 'decor_right' => null,
            'overlay_opacity' => '0.7',
        ],
        'dayz' => [
            'label' => 'DayZ', 'icon' => 'fas fa-biohazard',
            'primary' => '#8c1a1a', 'secondary' => '#6b1010', 'rgb' => '140,26,26',
            'bg_body' => '#0e0808', 'bg_card_rgb' => '28,14,14', 'border_rgb' => '44,22,22',
            'hero_from' => '#160a0a', 'hero_to' => '#1c0c0c', 'glow' => '#8c1a1a',
            'bg_image' => 'bg.jpg', 'decor_left' => 'decor-left.png', 'decor_right' => null,
            'overlay_opacity' => '0.7',
        ],
        'unturned' => [
            'label' => 'Unturned', 'icon' => 'fas fa-leaf',
            'primary' => '#7ab648', 'secondary' => '#5f9235', 'rgb' => '122,182,72',
            'bg_body' => '#0a0e08', 'bg_card_rgb' => '20,30,16', 'border_rgb' => '34,48,26',
            'hero_from' => '#0c160a', 'hero_to' => '#101a0c', 'glow' => '#7ab648',
            'bg_image' => 'bg.jpg', 'decor_left' => null, 'decor_right' => null,
            'overlay_opacity' => '0.7',
        ],
        'space_engineers' => [
            'label' => 'Space Engineers', 'icon' => 'fas fa-rocket',
            'primary' => '#3b6db5', 'secondary' => '#2c5490', 'rgb' => '59,109,181',
            'bg_body' => '#080c14', 'bg_card_rgb' => '16,24,38', 'border_rgb' => '24,38,58',
            'hero_from' => '#0a1220', 'hero_to' => '#0e162a', 'glow' => '#3b6db5',
            'bg_image' => 'bg.jpg', 'decor_left' => null, 'decor_right' => 'decor-right.png',
            'overlay_opacity' => '0.7',
        ],
        'terraria' => [
            'label' => 'Terraria', 'icon' => 'fas fa-tree',
            'primary' => '#5ba58b', 'secondary' => '#478570', 'rgb' => '91,165,139',
            'bg_body' => '#080e0c', 'bg_card_rgb' => '18,28,26', 'border_rgb' => '28,46,42',
            'hero_from' => '#0a1614', 'hero_to' => '#0e1a16', 'glow' => '#5ba58b',
            'bg_image' => 'bg.jpg', 'decor_left' => null, 'decor_right' => null,
            'overlay_opacity' => '0.7',
        ],
        'roblox' => [
            'label' => 'Roblox', 'icon' => 'fas fa-puzzle-piece',
            'primary' => '#e2231a', 'secondary' => '#b81c15', 'rgb' => '226,35,26',
            'bg_body' => '#100808', 'bg_card_rgb' => '32,16,14', 'border_rgb' => '50,24,22',
            'hero_from' => '#1a0a08', 'hero_to' => '#1e0c0a', 'glow' => '#e2231a',
            'bg_image' => 'bg.jpg', 'decor_left' => 'decor-left.png', 'decor_right' => null,
            'overlay_opacity' => '0.7',
        ],
        'discord' => [
            'label' => 'Discord', 'icon' => 'fab fa-discord',
            'primary' => '#5865f2', 'secondary' => '#4752c4', 'rgb' => '88,101,242',
            'bg_body' => '#0a0a14', 'bg_card_rgb' => '20,22,42', 'border_rgb' => '32,36,64',
            'hero_from' => '#10102a', 'hero_to' => '#141438', 'glow' => '#5865f2',
            'bg_image' => 'bg.jpg', 'decor_left' => 'decor-left.png', 'decor_right' => null,
            'overlay_opacity' => '0.7',
        ],
        'teamspeak' => [
            'label' => 'TeamSpeak', 'icon' => 'fas fa-headset',
            'primary' => '#2580c3', 'secondary' => '#1c6699', 'rgb' => '37,128,195',
            'bg_body' => '#080c14', 'bg_card_rgb' => '16,26,38', 'border_rgb' => '24,40,58',
            'hero_from' => '#0a1220', 'hero_to' => '#0e182a', 'glow' => '#2580c3',
            'bg_image' => 'bg.jpg', 'decor_left' => null, 'decor_right' => null,
            'overlay_opacity' => '0.7',
        ],
        'hosting' => [
            'label' => 'Hosting / Tech', 'icon' => 'fas fa-server',
            'primary' => '#0ea5e9', 'secondary' => '#0284c7', 'rgb' => '14,165,233',
            'bg_body' => '#080e14', 'bg_card_rgb' => '14,28,38', 'border_rgb' => '22,44,58',
            'hero_from' => '#0a1420', 'hero_to' => '#081a28', 'glow' => '#0ea5e9',
            'bg_image' => 'bg.jpg', 'decor_left' => null, 'decor_right' => null,
            'overlay_opacity' => '0.7',
        ],
        'cyberpunk' => [
            'label' => 'Cyberpunk', 'icon' => 'fas fa-microchip',
            'primary' => '#fcee0a', 'secondary' => '#d4c900', 'rgb' => '252,238,10',
            'bg_body' => '#0e0e08', 'bg_card_rgb' => '28,28,14', 'border_rgb' => '44,44,22',
            'hero_from' => '#14140a', 'hero_to' => '#1a1a0c', 'glow' => '#fcee0a',
            'bg_image' => 'bg.jpg', 'decor_left' => 'decor-left.png', 'decor_right' => 'decor-right.png',
            'overlay_opacity' => '0.65',
        ],
        'purple' => [
            'label' => 'Purple / Royal', 'icon' => 'fas fa-gem',
            'primary' => '#a855f7', 'secondary' => '#8b30e8', 'rgb' => '168,85,247',
            'bg_body' => '#0c0814', 'bg_card_rgb' => '24,16,38', 'border_rgb' => '38,26,58',
            'hero_from' => '#14082a', 'hero_to' => '#1a0e36', 'glow' => '#a855f7',
            'bg_image' => null, 'decor_left' => null, 'decor_right' => null,
            'overlay_opacity' => '0.7',
        ],
        'crimson' => [
            'label' => 'Crimson', 'icon' => 'fas fa-fire',
            'primary' => '#dc2626', 'secondary' => '#b91c1c', 'rgb' => '220,38,38',
            'bg_body' => '#100808', 'bg_card_rgb' => '32,14,14', 'border_rgb' => '52,22,22',
            'hero_from' => '#1a0808', 'hero_to' => '#200a0a', 'glow' => '#dc2626',
            'bg_image' => null, 'decor_left' => null, 'decor_right' => null,
            'overlay_opacity' => '0.7',
        ],
        'ocean' => [
            'label' => 'Ocean Blue', 'icon' => 'fas fa-water',
            'primary' => '#06b6d4', 'secondary' => '#0891b2', 'rgb' => '6,182,212',
            'bg_body' => '#080e10', 'bg_card_rgb' => '14,28,32', 'border_rgb' => '22,44,50',
            'hero_from' => '#0a1618', 'hero_to' => '#081c20', 'glow' => '#06b6d4',
            'bg_image' => null, 'decor_left' => null, 'decor_right' => null,
            'overlay_opacity' => '0.7',
        ],
        'rose' => [
            'label' => 'Rose / Pink', 'icon' => 'fas fa-heart',
            'primary' => '#f472b6', 'secondary' => '#db2777', 'rgb' => '244,114,182',
            'bg_body' => '#100810', 'bg_card_rgb' => '30,16,28', 'border_rgb' => '48,26,44',
            'hero_from' => '#1a0a16', 'hero_to' => '#200e1c', 'glow' => '#f472b6',
            'bg_image' => null, 'decor_left' => null, 'decor_right' => null,
            'overlay_opacity' => '0.7',
        ],
    ];

    public function getTheme(string $key): array
    {
        return self::THEMES[$key] ?? self::THEMES['default'];
    }

    public function getAllThemes(): array
    {
        return self::THEMES;
    }

    public function isValidTheme(string $key): bool
    {
        return isset(self::THEMES[$key]);
    }

    /**
     * Returns the asset path for a theme image, or null if file doesn't exist.
     */
    public function getThemeImagePath(string $themeKey, string $filename, string $projectDir): ?string
    {
        $path = $projectDir . '/public/assets/themes/' . $themeKey . '/' . $filename;
        if (file_exists($path)) {
            return 'assets/themes/' . $themeKey . '/' . $filename;
        }

        return null;
    }
}
