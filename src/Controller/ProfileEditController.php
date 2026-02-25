<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ProfileEditController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepo,
    ) {
    }

    #[Route('/profil/modifier', name: 'user_profile_edit')]
    public function edit(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleForm($request);
        }

        return $this->render('user/profile_edit.html.twig');
    }

    private function handleForm(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('profile_edit', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('user_profile_edit');
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Username
        $newUsername = trim((string) $request->request->get('username', ''));
        if ($newUsername !== '' && $newUsername !== $user->getUsername()) {
            if (!preg_match('/^[a-zA-Z0-9_\-]{3,30}$/', $newUsername)) {
                $this->addFlash('error', 'Pseudo invalide : 3 à 30 caractères, lettres, chiffres, _ et - uniquement.');
                return $this->redirectToRoute('user_profile_edit');
            }
            $existing = $this->userRepo->findOneByUsernameInsensitive($newUsername);
            if ($existing !== null && $existing->getId() !== $user->getId()) {
                $this->addFlash('error', 'Ce pseudo est déjà utilisé.');
                return $this->redirectToRoute('user_profile_edit');
            }
            $user->setUsername($newUsername);
        }

        // Bio (HTML from Quill)
        $bio = $request->request->get('bio');
        $user->setBio($bio ? trim($bio) : null);

        // Social usernames
        $user->setDiscordUsername($this->sanitizeUsername($request->request->get('discord_username')));
        $user->setSteamUsername($this->sanitizeUsername($request->request->get('steam_username')));
        $user->setTwitchUsername($this->sanitizeUsername($request->request->get('twitch_username')));

        // Game usernames (dynamic key-value pairs)
        $gameNames = $request->request->all('game_usernames');
        $filtered = [];
        if (is_array($gameNames)) {
            foreach ($gameNames as $game => $name) {
                $game = substr(strip_tags(trim((string) $game)), 0, 50);
                $name = substr(strip_tags(trim((string) $name)), 0, 100);
                if ($game !== '' && $name !== '') {
                    $filtered[$game] = $name;
                }
            }
        }
        $user->setGameUsernames(!empty($filtered) ? $filtered : null);

        // Visibility toggles
        $visibility = [
            'email' => (bool) $request->request->get('vis_email'),
            'discord' => (bool) $request->request->get('vis_discord'),
            'steam' => (bool) $request->request->get('vis_steam'),
            'twitch' => (bool) $request->request->get('vis_twitch'),
            'games' => (bool) $request->request->get('vis_games'),
            'servers' => (bool) $request->request->get('vis_servers'),
        ];
        $user->setProfileVisibility($visibility);

        $this->em->flush();
        $this->addFlash('success', 'Profil mis a jour.');

        return $this->redirectToRoute('profile_show', ['username' => $user->getUsername()]);
    }

    private function sanitizeUsername(?string $value): ?string
    {
        if (!$value) {
            return null;
        }
        $value = substr(strip_tags(trim($value)), 0, 100);

        return $value !== '' ? $value : null;
    }
}
