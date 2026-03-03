<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class OAuthRegistrationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private WebhookService $webhookService,
    ) {
    }

    public function findOrCreateFromOAuth(string $provider, string $oauthId, string $email, string $username, ?string $avatar = null): User
    {
        // 1. Chercher par OAuth ID
        $user = $this->userRepository->findByOAuthId($provider, $oauthId);
        if ($user) {
            return $user;
        }

        // 2. Chercher par email (lier le compte OAuth a un compte existant)
        $user = $this->userRepository->findOneBy(['email' => $email]);
        if ($user) {
            $this->setOAuthId($user, $provider, $oauthId);
            if (!$user->getAvatar() && $avatar) {
                $user->setAvatar($avatar);
            }
            $this->em->flush();

            return $user;
        }

        // 3. Creer un nouveau compte
        $user = new User();
        $user->setEmail($email);
        $user->setUsername($username);
        $user->setAvatar($avatar);
        $user->setIsVerified(true);
        $this->setOAuthId($user, $provider, $oauthId);

        $this->em->persist($user);
        $this->em->flush();

        $this->webhookService->dispatch('user.oauth_created', [
            'title' => 'Inscription OAuth',
            'fields' => [
                ['name' => 'Utilisateur', 'value' => $username, 'inline' => true],
                ['name' => 'Provider', 'value' => ucfirst($provider), 'inline' => true],
            ],
        ]);

        return $user;
    }

    public function findOrCreateFromSteam(string $steamId, string $username, ?string $avatar = null): User
    {
        $user = $this->userRepository->findByOAuthId('steam', $steamId);
        if ($user) {
            return $user;
        }

        // Steam ne fournit pas d'email, on genere un placeholder
        $email = 'steam_' . $steamId . '@nexarena.local';

        $user = $this->userRepository->findOneBy(['email' => $email]);
        if ($user) {
            return $user;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setUsername($username);
        $user->setAvatar($avatar);
        $user->setSteamId($steamId);
        $user->setIsVerified(true);

        $this->em->persist($user);
        $this->em->flush();

        $this->webhookService->dispatch('user.oauth_created', [
            'title' => 'Inscription OAuth',
            'fields' => [
                ['name' => 'Utilisateur', 'value' => $username, 'inline' => true],
                ['name' => 'Provider', 'value' => 'Steam', 'inline' => true],
            ],
        ]);

        return $user;
    }

    public function flush(): void
    {
        $this->em->flush();
    }

    private function setOAuthId(User $user, string $provider, string $oauthId): void
    {
        match ($provider) {
            'google' => $user->setGoogleId($oauthId),
            'discord' => $user->setDiscordId($oauthId),
            'twitch' => $user->setTwitchId($oauthId),
            'steam' => $user->setSteamId($oauthId),
        };
    }
}
