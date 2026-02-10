<?php

namespace App\Security;

use App\Repository\UserRepository;
use App\Security\Exception\OAuthEmailRequiredException;
use App\Service\OAuthRegistrationService;
use App\Service\SettingsService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class SteamAuthenticator extends AbstractAuthenticator
{
    private const STEAM_OPENID_URL = 'https://steamcommunity.com/openid/login';
    private const STEAM_API_URL = 'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/';
    private const CA_BUNDLE = 'C:\\wamp64\\bin\\php\\php8.3.6\\extras\\ssl\\cacert.pem';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private OAuthRegistrationService $oauthService,
        private SettingsService $settings,
        private UserRepository $userRepository,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'app_oauth_callback'
            && $request->attributes->get('provider') === 'steam'
            && $request->query->has('openid_claimed_id');
    }

    public function authenticate(Request $request): Passport
    {
        $claimedId = $request->query->get('openid_claimed_id', '');

        // Verifier la reponse OpenID avec Steam
        if (!$this->verifyOpenId($request)) {
            throw new AuthenticationException('La verification Steam OpenID a echoue.');
        }

        // Extraire le SteamID64
        if (!preg_match('/^https:\/\/steamcommunity\.com\/openid\/id\/(\d+)$/', $claimedId, $matches)) {
            throw new AuthenticationException('SteamID invalide.');
        }
        $steamId = $matches[1];

        // Recuperer les infos du profil Steam
        $profile = $this->getSteamProfile($steamId);
        if (!$profile) {
            throw new AuthenticationException('Impossible de recuperer le profil Steam.');
        }

        // Utilisateur existant avec ce Steam ID → login direct
        $existingUser = $this->userRepository->findByOAuthId('steam', $steamId);
        if ($existingUser) {
            return new SelfValidatingPassport(
                new UserBadge($existingUser->getUserIdentifier()),
            );
        }

        // Nouvel utilisateur → stocker en session et demander l'email
        $session = $request->getSession();
        $session->set('_oauth_pending', [
            'provider' => 'steam',
            'oauth_id' => $steamId,
            'username' => $profile['personaname'] ?? 'Steam_' . $steamId,
            'avatar' => $profile['avatarfull'] ?? null,
        ]);

        throw new OAuthEmailRequiredException();
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        if ($exception instanceof OAuthEmailRequiredException) {
            return new RedirectResponse($this->urlGenerator->generate('app_oauth_complete_registration'));
        }

        $request->getSession()->getFlashBag()->add('error', 'Erreur lors de la connexion Steam. Veuillez reessayer.');

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    public function getRedirectUrl(): string
    {
        $returnTo = $this->urlGenerator->generate('app_oauth_callback', ['provider' => 'steam'], UrlGeneratorInterface::ABSOLUTE_URL);

        $params = [
            'openid.ns' => 'http://specs.openid.net/auth/2.0',
            'openid.mode' => 'checkid_setup',
            'openid.return_to' => $returnTo,
            'openid.realm' => $this->urlGenerator->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'openid.identity' => 'http://specs.openid.net/auth/2.0/identifier_select',
            'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select',
        ];

        return self::STEAM_OPENID_URL . '?' . http_build_query($params);
    }

    private function verifyOpenId(Request $request): bool
    {
        $params = [
            'openid.assoc_handle' => $request->query->get('openid_assoc_handle'),
            'openid.signed' => $request->query->get('openid_signed'),
            'openid.sig' => $request->query->get('openid_sig'),
            'openid.ns' => $request->query->get('openid_ns'),
            'openid.mode' => 'check_authentication',
        ];

        $signed = explode(',', $request->query->get('openid_signed', ''));
        foreach ($signed as $item) {
            $key = 'openid_' . str_replace('.', '_', $item);
            $params['openid.' . $item] = $request->query->get($key);
        }

        $ch = curl_init(self::STEAM_OPENID_URL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        if (file_exists(self::CA_BUNDLE)) {
            curl_setopt($ch, CURLOPT_CAINFO, self::CA_BUNDLE);
        }
        $response = curl_exec($ch);
        curl_close($ch);

        return str_contains((string) $response, 'is_valid:true');
    }

    private function getSteamProfile(string $steamId): ?array
    {
        $url = self::STEAM_API_URL . '?' . http_build_query([
            'key' => $this->settings->get('steam_api_key'),
            'steamids' => $steamId,
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        if (file_exists(self::CA_BUNDLE)) {
            curl_setopt($ch, CURLOPT_CAINFO, self::CA_BUNDLE);
        }
        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            return null;
        }

        $data = json_decode($response, true);

        return $data['response']['players'][0] ?? null;
    }
}
