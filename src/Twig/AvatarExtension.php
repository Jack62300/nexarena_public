<?php

namespace App\Twig;

use App\Entity\User;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AvatarExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('user_avatar', [$this, 'getUserAvatar']),
        ];
    }

    /**
     * Returns the correct avatar URL for a user.
     * - OAuth avatars start with 'http' → return as-is
     * - Local uploads → return asset path 'uploads/avatars/{filename}'
     * - No avatar → return null
     */
    public function getUserAvatar(?User $user): ?string
    {
        if (!$user) {
            return null;
        }

        $avatar = $user->getAvatar();
        if (!$avatar) {
            return null;
        }

        if (str_starts_with($avatar, 'http')) {
            return $avatar;
        }

        return 'uploads/avatars/' . $avatar;
    }
}
