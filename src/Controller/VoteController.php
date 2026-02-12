<?php

namespace App\Controller;

use App\Repository\ServerRepository;
use App\Service\AntiBotService;
use App\Service\SettingsService;
use App\Service\VoteRewardService;
use App\Service\VoteService;
use App\Util\CurlHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class VoteController extends AbstractController
{

    public function __construct(
        private VoteService $voteService,
        private ServerRepository $serverRepo,
        private SettingsService $settings,
        private AntiBotService $antiBotService,
        private VoteRewardService $voteRewardService,
    ) {
    }

    #[Route('/vote/callback/discord', name: 'vote_callback_discord', priority: 10)]
    public function callbackDiscord(Request $request): Response
    {
        $slug = $request->getSession()->get('vote_slug');
        $request->getSession()->remove('vote_slug');
        $request->getSession()->remove('vote_provider');

        if (!$slug) {
            $this->addFlash('error', 'Session de vote expiree.');
            return $this->redirectToRoute('app_home');
        }

        $server = $this->findServer($slug);
        if (!$server) {
            $this->addFlash('error', 'Serveur introuvable.');
            return $this->redirectToRoute('app_home');
        }

        // Validate state
        $sessionState = $request->getSession()->get('vote_oauth_state');
        $request->getSession()->remove('vote_oauth_state');
        $queryState = $request->query->get('state');
        if (!$queryState || !$sessionState || !hash_equals($sessionState, $queryState)) {
            $this->addFlash('error', 'Etat OAuth invalide. Veuillez reessayer.');
            return $this->redirectToRoute('server_show', ['slug' => $slug]);
        }

        $code = $request->query->get('code');
        if (!$code) {
            $this->addFlash('error', 'Code d\'autorisation manquant.');
            return $this->redirectToRoute('server_show', ['slug' => $slug]);
        }

        // Exchange code for token via curl
        $redirectUri = $this->generateUrl('vote_callback_discord', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $tokenData = $this->discordTokenExchange($code, $redirectUri);
        if (!$tokenData || empty($tokenData['access_token'])) {
            $this->addFlash('error', 'Erreur lors de l\'echange de token Discord.');
            return $this->redirectToRoute('server_show', ['slug' => $slug]);
        }

        // Fetch Discord user info
        $discordUser = $this->discordFetchUser($tokenData['access_token']);
        if (!$discordUser || empty($discordUser['id'])) {
            $this->addFlash('error', 'Impossible de recuperer votre profil Discord.');
            return $this->redirectToRoute('server_show', ['slug' => $slug]);
        }

        $discordId = $discordUser['id'];
        $username = $discordUser['username'] ?? 'Discord_' . $discordId;

        $ip = $request->getClientIp();
        $fingerprint = $request->getSession()->get('vote_fingerprint');
        $request->getSession()->remove('vote_fingerprint');
        $user = $this->getUser();

        // Captcha check
        if ($request->getSession()->get('vote_captcha_required')) {
            $captchaAnswer = $request->getSession()->get('vote_captcha_answer');
            $request->getSession()->remove('vote_captcha_required');
            $request->getSession()->remove('vote_captcha_answer');
            // Captcha was already verified in the initiate step
        }

        $check = $this->voteService->canVote($server, $ip, $discordId, null, $fingerprint, $user);
        if (!$check['allowed']) {
            if (!empty($check['captcha_required'])) {
                // Store state and redirect to captcha page
                $request->getSession()->set('vote_captcha_pending', [
                    'slug' => $slug,
                    'provider' => 'discord',
                    'discordId' => $discordId,
                    'username' => $username,
                ]);
                $captcha = $this->antiBotService->generateCaptcha();
                $request->getSession()->set('vote_captcha_answer', $captcha['answer']);
                $request->getSession()->set('vote_captcha_question', $captcha['question']);
                return $this->redirectToRoute('vote_captcha', ['slug' => $slug]);
            }

            $message = $check['reason'];
            if ($check['cooldown'] > 0) {
                $minutes = ceil($check['cooldown'] / 60);
                $message .= ' (encore ' . $minutes . ' min)';
            }
            $this->addFlash('error', $message);
            return $this->redirectToRoute('server_show', ['slug' => $slug]);
        }

        $vote = $this->voteService->castVote($server, $ip, $username, $discordId, null, 'discord', $fingerprint, $user);

        // Vote rewards
        if ($user) {
            $this->voteRewardService->calculateReward($user, $server, $vote);
        }

        $this->addFlash('success', 'Vote enregistre avec succes via Discord !');
        return $this->redirectToRoute('server_show', ['slug' => $slug]);
    }

    #[Route('/vote/callback/steam', name: 'vote_callback_steam', priority: 10)]
    public function callbackSteam(Request $request): Response
    {
        $slug = $request->getSession()->get('vote_slug');
        $request->getSession()->remove('vote_slug');
        $request->getSession()->remove('vote_provider');

        if (!$slug) {
            $this->addFlash('error', 'Session de vote expiree.');
            return $this->redirectToRoute('app_home');
        }

        $server = $this->findServer($slug);
        if (!$server) {
            $this->addFlash('error', 'Serveur introuvable.');
            return $this->redirectToRoute('app_home');
        }

        $claimedId = $request->query->get('openid_claimed_id', '');
        if (!$this->verifySteamOpenId($request)) {
            $this->addFlash('error', 'La verification Steam a echoue. Veuillez reessayer.');
            return $this->redirectToRoute('server_show', ['slug' => $slug]);
        }

        if (!preg_match('/^https:\/\/steamcommunity\.com\/openid\/id\/(\d+)$/', $claimedId, $matches)) {
            $this->addFlash('error', 'SteamID invalide.');
            return $this->redirectToRoute('server_show', ['slug' => $slug]);
        }

        $steamId = $matches[1];
        $username = $this->getSteamUsername($steamId);

        $ip = $request->getClientIp();
        $fingerprint = $request->getSession()->get('vote_fingerprint');
        $request->getSession()->remove('vote_fingerprint');
        $user = $this->getUser();

        $check = $this->voteService->canVote($server, $ip, null, $steamId, $fingerprint, $user);
        if (!$check['allowed']) {
            if (!empty($check['captcha_required'])) {
                $request->getSession()->set('vote_captcha_pending', [
                    'slug' => $slug,
                    'provider' => 'steam',
                    'steamId' => $steamId,
                    'username' => $username,
                ]);
                $captcha = $this->antiBotService->generateCaptcha();
                $request->getSession()->set('vote_captcha_answer', $captcha['answer']);
                $request->getSession()->set('vote_captcha_question', $captcha['question']);
                return $this->redirectToRoute('vote_captcha', ['slug' => $slug]);
            }

            $message = $check['reason'];
            if ($check['cooldown'] > 0) {
                $minutes = ceil($check['cooldown'] / 60);
                $message .= ' (encore ' . $minutes . ' min)';
            }
            $this->addFlash('error', $message);
            return $this->redirectToRoute('server_show', ['slug' => $slug]);
        }

        $vote = $this->voteService->castVote($server, $ip, $username, null, $steamId, 'steam', $fingerprint, $user);

        // Vote rewards
        if ($user) {
            $this->voteRewardService->calculateReward($user, $server, $vote);
        }

        $this->addFlash('success', 'Vote enregistre avec succes via Steam !');
        return $this->redirectToRoute('server_show', ['slug' => $slug]);
    }

    #[Route('/vote/{slug}/discord', name: 'vote_initiate_discord', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'])]
    public function initiateDiscord(string $slug, Request $request): Response
    {
        $server = $this->findServer($slug);
        if (!$server) {
            throw $this->createNotFoundException('Serveur introuvable.');
        }

        // Store fingerprint in session
        $fingerprint = $request->query->get('fp');
        if ($fingerprint && $this->antiBotService->validateFingerprint($fingerprint)) {
            $request->getSession()->set('vote_fingerprint', $fingerprint);
        }

        $state = bin2hex(random_bytes(16));
        $request->getSession()->set('vote_slug', $slug);
        $request->getSession()->set('vote_provider', 'discord');
        $request->getSession()->set('vote_oauth_state', $state);

        $redirectUri = $this->generateUrl('vote_callback_discord', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $params = [
            'client_id' => $this->settings->get('discord_client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'identify',
            'state' => $state,
        ];

        return $this->redirect('https://discord.com/oauth2/authorize?' . http_build_query($params));
    }

    #[Route('/vote/{slug}/steam', name: 'vote_initiate_steam', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'])]
    public function initiateSteam(string $slug, Request $request): Response
    {
        $server = $this->findServer($slug);
        if (!$server) {
            throw $this->createNotFoundException('Serveur introuvable.');
        }

        // Store fingerprint in session
        $fingerprint = $request->query->get('fp');
        if ($fingerprint && $this->antiBotService->validateFingerprint($fingerprint)) {
            $request->getSession()->set('vote_fingerprint', $fingerprint);
        }

        $request->getSession()->set('vote_slug', $slug);
        $request->getSession()->set('vote_provider', 'steam');

        $returnTo = $this->generateUrl('vote_callback_steam', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $realm = $this->generateUrl('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $params = [
            'openid.ns' => 'http://specs.openid.net/auth/2.0',
            'openid.mode' => 'checkid_setup',
            'openid.return_to' => $returnTo,
            'openid.realm' => $realm,
            'openid.identity' => 'http://specs.openid.net/auth/2.0/identifier_select',
            'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select',
        ];

        return $this->redirect('https://steamcommunity.com/openid/login?' . http_build_query($params));
    }

    #[Route('/vote/{slug}/captcha', name: 'vote_captcha')]
    public function captcha(string $slug, Request $request): Response
    {
        $server = $this->findServer($slug);
        if (!$server) {
            throw $this->createNotFoundException('Serveur introuvable.');
        }

        $pending = $request->getSession()->get('vote_captcha_pending');
        if (!$pending || $pending['slug'] !== $slug) {
            $this->addFlash('error', 'Session captcha expiree.');
            return $this->redirectToRoute('server_show', ['slug' => $slug]);
        }

        $question = $request->getSession()->get('vote_captcha_question', '? + ? = ?');

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('vote_captcha', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('server_show', ['slug' => $slug]);
            }

            $answer = $request->request->getInt('captcha_answer');
            $expected = $request->getSession()->get('vote_captcha_answer');

            if (!$this->antiBotService->verifyCaptcha($answer, (int) $expected)) {
                // Regenerate captcha
                $captcha = $this->antiBotService->generateCaptcha();
                $request->getSession()->set('vote_captcha_answer', $captcha['answer']);
                $request->getSession()->set('vote_captcha_question', $captcha['question']);
                $this->addFlash('error', 'Reponse incorrecte. Reessayez.');
                return $this->redirectToRoute('vote_captcha', ['slug' => $slug]);
            }

            // Captcha passed, cast the vote
            $ip = $request->getClientIp();
            $fingerprint = $request->getSession()->get('vote_fingerprint');
            $user = $this->getUser();
            $provider = $pending['provider'];
            $discordId = $pending['discordId'] ?? null;
            $steamId = $pending['steamId'] ?? null;
            $username = $pending['username'] ?? null;

            // Clean up session
            $request->getSession()->remove('vote_captcha_pending');
            $request->getSession()->remove('vote_captcha_answer');
            $request->getSession()->remove('vote_captcha_question');
            $request->getSession()->remove('vote_fingerprint');

            $vote = $this->voteService->castVote($server, $ip, $username, $discordId, $steamId, $provider, $fingerprint, $user);

            if ($user) {
                $this->voteRewardService->calculateReward($user, $server, $vote);
            }

            $this->addFlash('success', 'Vote enregistre avec succes !');
            return $this->redirectToRoute('server_show', ['slug' => $slug]);
        }

        return $this->render('server/captcha.html.twig', [
            'server' => $server,
            'question' => $question,
        ]);
    }

    private function findServer(string $slug): ?\App\Entity\Server
    {
        return $this->serverRepo->findOneBy(['slug' => $slug, 'isActive' => true, 'isApproved' => true]);
    }

    private function curlRequest(string $url, ?array $postFields = null, array $headers = []): ?string
    {
        $ch = CurlHelper::createSecure($url);

        if ($postFields !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        curl_close($ch);

        return $response ?: null;
    }

    private function discordTokenExchange(string $code, string $redirectUri): ?array
    {
        $response = $this->curlRequest('https://discord.com/api/v10/oauth2/token', [
            'client_id' => $this->settings->get('discord_client_id'),
            'client_secret' => $this->settings->get('discord_client_secret'),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ], ['Content-Type: application/x-www-form-urlencoded']);

        if (!$response) {
            return null;
        }

        return json_decode($response, true);
    }

    private function discordFetchUser(string $accessToken): ?array
    {
        $response = $this->curlRequest('https://discord.com/api/v10/users/@me', null, [
            'Authorization: Bearer ' . $accessToken,
        ]);

        if (!$response) {
            return null;
        }

        return json_decode($response, true);
    }

    private function verifySteamOpenId(Request $request): bool
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

        $response = $this->curlRequest('https://steamcommunity.com/openid/login', $params);

        return str_contains((string) $response, 'is_valid:true');
    }

    private function getSteamUsername(string $steamId): string
    {
        $steamApiKey = $this->settings->get('steam_api_key');
        if (!$steamApiKey) {
            return 'Steam_' . $steamId;
        }

        $url = 'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?' . http_build_query([
            'key' => $steamApiKey,
            'steamids' => $steamId,
        ]);

        $response = $this->curlRequest($url);
        if (!$response) {
            return 'Steam_' . $steamId;
        }

        $data = json_decode($response, true);

        return $data['response']['players'][0]['personaname'] ?? 'Steam_' . $steamId;
    }
}
