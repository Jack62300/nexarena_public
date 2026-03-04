<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\OAuthRegistrationService;
use App\Service\ReferralService;
use App\Service\SettingsService;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class OAuthController extends AbstractController
{
    /** Rôles considérés comme "admin" (ROLE_EDITEUR et supérieurs via hiérarchie). */
    private const ADMIN_ROLES = [
        'ROLE_EDITEUR',
        'ROLE_MANAGER',
        'ROLE_RESPONSABLE',
        'ROLE_DEVELOPPEUR',
        'ROLE_FONDATEUR',
    ];

    public function __construct(private SettingsService $settings)
    {
    }

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

            $maintenance = $this->settings->getBool('maintenance_mode', false);

            // Utilisateur existant avec cet OAuth ID → login direct
            $existingUser = $userRepository->findByOAuthId($provider, $oauthId);
            if ($existingUser) {
                // Pendant la maintenance, seuls les admins peuvent se connecter
                if ($maintenance && !$this->userHasAdminRole($existingUser)) {
                    $this->addFlash('error', 'Le site est actuellement en maintenance. Seuls les administrateurs peuvent se connecter.');
                    return $this->redirectToRoute('app_maintenance');
                }

                $request->getSession()->migrate(true);
                $security->login($existingUser, 'form_login', 'main');
                return $this->redirectToRoute('app_home');
            }

            // Maintenance active → nouvelles inscriptions bloquées
            if ($maintenance) {
                $this->addFlash('error', 'Les inscriptions sont desactivees pendant la maintenance.');
                return $this->redirectToRoute('app_maintenance');
            }

            // Nouveau utilisateur → ToS acceptance requise avant création de compte
            $request->getSession()->set('_oauth_pending', [
                'provider' => $provider,
                'oauth_id' => $oauthId,
                'username' => $username,
                'avatar'   => $avatar,
                'email'    => $email, // peut être vide (Steam)
                'referral_code' => $request->query->get('ref', ''),
            ]);
            return $this->redirectToRoute('app_oauth_complete_registration');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la connexion avec ' . ucfirst($provider) . '. Veuillez reessayer.');

            return $this->redirectToRoute('app_login');
        }
    }

    #[Route('/inscription/completer', name: 'app_oauth_complete_registration', methods: ['GET', 'POST'])]
    public function completeRegistration(
        Request $request,
        OAuthRegistrationService $oauthService,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        Security $security,
        ReferralService $referralService,
    ): Response {
        // Inscriptions bloquées pendant la maintenance
        if ($this->settings->getBool('maintenance_mode', false)) {
            $this->addFlash('error', 'Les inscriptions sont desactivees pendant la maintenance.');
            return $this->redirectToRoute('app_maintenance');
        }

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

            // ToS acceptance
            if (!$request->request->getBoolean('accept_terms', false)) {
                $this->addFlash('error', 'Vous devez accepter le règlement de Nexarena pour vous inscrire.');
                return $this->render('security/complete_registration.html.twig', [
                    'pending'    => $pending,
                    'last_email' => '',
                ]);
            }

            // Email : utiliser celui du provider si disponible, sinon récupérer depuis le formulaire
            $email = !empty($pending['email']) ? $pending['email'] : trim($request->request->get('email', ''));

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Veuillez saisir une adresse email valide.');
                return $this->render('security/complete_registration.html.twig', [
                    'pending'    => $pending,
                    'last_email' => $email,
                ]);
            }

            // Mot de passe obligatoire pour les nouveaux comptes
            $password = (string) $request->request->get('password');
            $passwordConfirm = (string) $request->request->get('password_confirm');

            // Vérifier si c'est un nouveau compte (pas de liaison à un compte existant)
            $existingByOAuth = $userRepository->findByOAuthId($pending['provider'], $pending['oauth_id']);
            $existingByEmail = $existingByOAuth ? null : $userRepository->findOneBy(['email' => $email]);
            $isNewAccount = !$existingByOAuth && !$existingByEmail;

            if ($isNewAccount) {
                if (mb_strlen($password) < 10) {
                    $this->addFlash('error', 'Le mot de passe doit contenir au moins 10 caractères.');
                    return $this->render('security/complete_registration.html.twig', [
                        'pending'    => $pending,
                        'last_email' => $email,
                    ]);
                }

                if ($password !== $passwordConfirm) {
                    $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
                    return $this->render('security/complete_registration.html.twig', [
                        'pending'    => $pending,
                        'last_email' => $email,
                    ]);
                }
            }

            $user = $oauthService->findOrCreateFromOAuth(
                $pending['provider'],
                $pending['oauth_id'],
                $email,
                $pending['username'],
                $pending['avatar'],
            );

            // Définir le mot de passe si nouveau compte
            if ($isNewAccount && mb_strlen($password) >= 10) {
                $user->setPassword($passwordHasher->hashPassword($user, $password));
                $oauthService->flush();
            }

            // Process referral for new accounts
            if ($isNewAccount) {
                if (!$user->getReferralCode()) {
                    $user->setReferralCode($referralService->generateCode());
                    $oauthService->flush();
                }
                $refCode = $pending['referral_code'] ?? '';
                if ($refCode) {
                    try {
                        $referralService->processReferral($user, $refCode);
                    } catch (\Throwable) {}
                }
            }

            $session->remove('_oauth_pending');
            $session->migrate(true);
            $security->login($user, 'form_login', 'main');

            return $this->redirectToRoute('app_home');
        }

        return $this->render('security/complete_registration.html.twig', [
            'pending'    => $pending,
            'last_email' => '',
        ]);
    }

    private function userHasAdminRole(object $user): bool
    {
        $userRoles = $user->getRoles();
        return !empty(array_intersect(self::ADMIN_ROLES, $userRoles));
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
