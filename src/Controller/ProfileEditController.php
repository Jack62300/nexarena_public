<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Repository\UserRepository;
use App\Service\ActivityLogService;
use App\Service\PremiumService;
use App\Service\WheelService;
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
        private PremiumService $premiumService,
        private ActivityLogService $activityLog,
        private WheelService $wheelService,
    ) {
    }

    #[Route('/profil/modifier', name: 'user_profile_edit')]
    public function edit(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleForm($request);
        }

        /** @var \App\Entity\User $user */
        $user    = $this->getUser();
        $twitchSub = $this->premiumService->getUserTwitchSubscription($user);

        return $this->render('user/profile_edit.html.twig', [
            'twitch_sub'          => $twitchSub,
            'twitch_gated'        => $this->premiumService->isUserTwitchLiveGated(),
            'twitch_cost_tokens'  => $this->premiumService->getUserTwitchLiveCostTokens(),
            'wheel_enabled'       => $this->wheelService->isEnabled(),
            'wheel_spin_cost'     => $this->wheelService->getSpinCost(),
            'wheel_prizes'        => $this->wheelService->getPrizes(),
        ]);
    }

    #[Route('/profil/twitch-subscribe', name: 'user_profile_twitch_subscribe', methods: ['POST'])]
    public function twitchSubscribe(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('profile_twitch_subscribe', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('user_profile_edit');
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$user->getTwitchUsername()) {
            $this->addFlash('error', 'Renseignez d\'abord votre pseudo Twitch dans les réseaux sociaux.');
            return $this->redirectToRoute('user_profile_edit');
        }

        $autoRenew = $request->request->getBoolean('auto_renew');
        $result    = $this->premiumService->subscribeUserTwitchLiveWithTokens($user);

        if ($result) {
            $sub = $this->premiumService->getUserTwitchSubscription($user);
            if ($sub) {
                $sub->setAutoRenew($autoRenew);
                $this->em->flush();
            }
            $this->addFlash('success', 'Twitch Live activé sur votre profil pour 30 jours !');
        } else {
            $cost = $this->premiumService->getUserTwitchLiveCostTokens();
            $this->addFlash('error', 'NexBits insuffisants. Il vous faut ' . $cost . ' NexBits.');
        }

        return $this->redirectToRoute('user_profile_edit');
    }

    #[Route('/profil/twitch-cancel', name: 'user_profile_twitch_cancel', methods: ['POST'])]
    public function twitchCancel(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('profile_twitch_cancel', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('user_profile_edit');
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $this->premiumService->cancelUserTwitchLive($user);
        $this->addFlash('success', 'Abonnement Twitch Live annulé. Il reste actif jusqu\'à son expiration.');

        return $this->redirectToRoute('user_profile_edit');
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
            'email'   => (bool) $request->request->get('vis_email'),
            'discord' => (bool) $request->request->get('vis_discord'),
            'steam'   => (bool) $request->request->get('vis_steam'),
            'twitch'  => (bool) $request->request->get('vis_twitch'),
            'games'   => (bool) $request->request->get('vis_games'),
            'servers' => (bool) $request->request->get('vis_servers'),
        ];
        $user->setProfileVisibility($visibility);

        $this->em->flush();
        $this->activityLog->log('profile.edit', ActivityLog::CAT_PROFILE, 'User', $user->getId(), $user->getUsername());
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
