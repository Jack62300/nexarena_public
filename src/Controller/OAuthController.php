<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\OAuthRegistrationService;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class OAuthController extends AbstractController
{
    private const SCOPES = [
        'google' => ['email', 'profile'],
        'discord' => ['identify', 'email'],
        'twitch' => ['user:read:email'],
    ];

    #[Route('/oauth/connect/{provider}', name: 'app_oauth_connect')]
    public function connect(string $provider, ClientRegistry $clientRegistry): Response
    {
        if (!in_array($provider, ['google', 'discord', 'twitch'])) {
            throw $this->createNotFoundException('Provider inconnu.');
        }

        return $clientRegistry
            ->getClient($provider)
            ->redirect(self::SCOPES[$provider] ?? [], []);
    }

    #[Route('/oauth/callback/{provider}', name: 'app_oauth_callback')]
    public function callback(
        string $provider,
        Request $request,
        ClientRegistry $clientRegistry,
        OAuthRegistrationService $oauthService,
        UserRepository $userRepository,
        Security $security,
    ): Response {
        if (!in_array($provider, ['google', 'discord', 'twitch'])) {
            throw $this->createNotFoundException('Provider inconnu.');
        }

        try {
            $client = $clientRegistry->getClient($provider);
            $accessToken = $client->getAccessToken();
            $resourceOwner = $client->fetchUserFromToken($accessToken);

            [$oauthId, $email, $username, $avatar] = $this->extractUserData($provider, $resourceOwner);

            // Utilisateur existant avec cet OAuth ID → login direct
            $existingUser = $userRepository->findByOAuthId($provider, $oauthId);
            if ($existingUser) {
                $request->getSession()->migrate(true);
                $security->login($existingUser, 'form_login', 'main');
                return $this->redirectToRoute('app_home');
            }

            // Pas d'email fourni → demander l'email
            if (empty($email)) {
                $request->getSession()->set('_oauth_pending', [
                    'provider' => $provider,
                    'oauth_id' => $oauthId,
                    'username' => $username,
                    'avatar' => $avatar,
                ]);
                return $this->redirectToRoute('app_oauth_complete_registration');
            }

            // Email disponible → flux normal
            $user = $oauthService->findOrCreateFromOAuth($provider, $oauthId, $email, $username, $avatar);

            $request->getSession()->migrate(true);
            $security->login($user, 'form_login', 'main');

            return $this->redirectToRoute('app_home');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la connexion avec ' . ucfirst($provider) . '. Veuillez reessayer.');

            return $this->redirectToRoute('app_login');
        }
    }

    #[Route('/inscription/completer', name: 'app_oauth_complete_registration', methods: ['GET', 'POST'])]
    public function completeRegistration(
        Request $request,
        OAuthRegistrationService $oauthService,
        Security $security,
    ): Response {
        $session = $request->getSession();
        $pending = $session->get('_oauth_pending');

        if (!$pending) {
            return $this->redirectToRoute('app_login');
        }

        if ($request->isMethod('POST')) {
            $token = $request->request->get('_csrf_token', '');
            if (!$this->isCsrfTokenValid('oauth_complete', $token)) {
                $this->addFlash('error', 'Jeton CSRF invalide, veuillez reessayer.');
                return $this->redirectToRoute('app_oauth_complete_registration');
            }

            $email = trim($request->request->get('email', ''));

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Veuillez saisir une adresse email valide.');
                return $this->render('security/complete_registration.html.twig', [
                    'pending' => $pending,
                    'last_email' => $email,
                ]);
            }

            $user = $oauthService->findOrCreateFromOAuth(
                $pending['provider'],
                $pending['oauth_id'],
                $email,
                $pending['username'],
                $pending['avatar'],
            );

            $session->remove('_oauth_pending');
            $session->migrate(true);
            $security->login($user, 'form_login', 'main');

            return $this->redirectToRoute('app_home');
        }

        return $this->render('security/complete_registration.html.twig', [
            'pending' => $pending,
            'last_email' => '',
        ]);
    }

    private function extractUserData(string $provider, $resourceOwner): array
    {
        return match ($provider) {
            'google' => [
                $resourceOwner->getId(),
                $resourceOwner->getEmail(),
                $resourceOwner->getName() ?? $resourceOwner->getEmail(),
                $resourceOwner->getAvatar(),
            ],
            'discord' => [
                $resourceOwner->getId(),
                $resourceOwner->getEmail(),
                $resourceOwner->getUsername(),
                $resourceOwner->getAvatarHash()
                    ? 'https://cdn.discordapp.com/avatars/' . $resourceOwner->getId() . '/' . $resourceOwner->getAvatarHash() . '.png'
                    : null,
            ],
            'twitch' => [
                $resourceOwner->getId(),
                $resourceOwner->getEmail(),
                $resourceOwner->getDisplayName() ?? $resourceOwner->getLogin(),
                $resourceOwner->getProfileImageUrl(),
            ],
            default => throw new \InvalidArgumentException("Provider inconnu: $provider"),
        };
    }
}
