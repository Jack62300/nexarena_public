<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/admin/api-tester', name: 'admin_api_tester_')]
#[IsGranted('ROLE_FONDATEUR')]
class ApiTesterController extends AbstractController
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {}

    #[Route('', name: 'index')]
    public function index(): Response
    {
        $endpoints = $this->getEndpoints();

        return $this->render('admin/api_tester/index.html.twig', [
            'endpoints' => $endpoints,
        ]);
    }

    #[Route('/execute', name: 'execute', methods: ['POST'])]
    public function execute(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('api_tester_execute', $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Token CSRF invalide.'], 403);
        }

        $method  = strtoupper($request->request->get('method', 'GET'));
        $url     = $request->request->get('url', '');
        $headers = json_decode($request->request->get('headers', '{}'), true) ?: [];
        $body    = $request->request->get('body', '');

        if (!in_array($method, ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'])) {
            return new JsonResponse(['error' => 'Méthode HTTP invalide.'], 400);
        }

        // Security: only allow /api/ paths
        $parsedPath = parse_url($url, PHP_URL_PATH);
        if (!$parsedPath || !str_starts_with($parsedPath, '/api/')) {
            return new JsonResponse(['error' => 'Seules les URLs commençant par /api/ sont autorisées.'], 400);
        }

        // Build the full local URL
        $scheme = $request->getScheme();
        $host   = $request->getHttpHost();
        $fullUrl = $scheme . '://' . $host . $url;

        $options = [
            'headers' => $headers,
            'timeout' => 15,
            'verify_peer' => false,
            'verify_host' => false,
        ];

        if (in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE']) && $body !== '') {
            $options['body'] = $body;
            if (!isset($headers['Content-Type'])) {
                $options['headers']['Content-Type'] = 'application/json';
            }
        }

        try {
            $start = microtime(true);
            $response = $this->httpClient->request($method, $fullUrl, $options);
            $statusCode = $response->getStatusCode();
            $responseHeaders = $response->getHeaders(false);
            $responseBody = $response->getContent(false);
            $duration = round((microtime(true) - $start) * 1000);

            // Flatten headers
            $flatHeaders = [];
            foreach ($responseHeaders as $key => $values) {
                $flatHeaders[$key] = implode(', ', $values);
            }

            return new JsonResponse([
                'status'   => $statusCode,
                'headers'  => $flatHeaders,
                'body'     => $responseBody,
                'duration' => $duration,
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'error'    => $e->getMessage(),
                'status'   => 0,
                'duration' => 0,
            ], 500);
        }
    }

    private function getEndpoints(): array
    {
        return [
            'Server API' => [
                ['method' => 'GET', 'url' => '/api/v1/servers/{token}/vote/{username}', 'description' => 'Vérifier le vote d\'un joueur par username'],
                ['method' => 'GET', 'url' => '/api/v1/servers/{token}/vote/ip/{ip}', 'description' => 'Vérifier le vote par IP'],
                ['method' => 'GET', 'url' => '/api/v1/servers/{token}/vote/discord/{discordId}', 'description' => 'Vérifier le vote par Discord ID'],
                ['method' => 'GET', 'url' => '/api/v1/servers/{token}/vote/steam/{steamId}', 'description' => 'Vérifier le vote par Steam ID'],
                ['method' => 'GET', 'url' => '/api/v1/servers/{token}/vote/user/{userId}', 'description' => 'Vérifier le vote par User ID'],
                ['method' => 'GET', 'url' => '/api/v1/servers/{token}/stats', 'description' => 'Statistiques du serveur'],
                ['method' => 'GET', 'url' => '/api/v1/servers/{token}/voters', 'description' => 'Liste des votants'],
            ],
            'Discord Bot API' => [
                ['method' => 'GET', 'url' => '/api/discord/banned-words', 'description' => 'Liste des mots bannis'],
                ['method' => 'POST', 'url' => '/api/discord/tickets', 'description' => 'Créer un ticket'],
                ['method' => 'GET', 'url' => '/api/discord/tickets/open', 'description' => 'Tickets ouverts'],
                ['method' => 'GET', 'url' => '/api/discord/user/{discordId}', 'description' => 'Info utilisateur Discord'],
                ['method' => 'POST', 'url' => '/api/discord/moderation-log', 'description' => 'Log de modération'],
                ['method' => 'POST', 'url' => '/api/discord/sanctions', 'description' => 'Créer une sanction'],
                ['method' => 'GET', 'url' => '/api/discord/sanctions/{discordUserId}', 'description' => 'Sanctions d\'un utilisateur'],
                ['method' => 'GET', 'url' => '/api/discord/settings?keys=key1,key2', 'description' => 'Paramètres Discord'],
                ['method' => 'GET', 'url' => '/api/discord/reaction-roles', 'description' => 'Reaction roles'],
                ['method' => 'GET', 'url' => '/api/discord/commands', 'description' => 'Commandes du bot'],
                ['method' => 'GET', 'url' => '/api/discord/invites/leaderboard', 'description' => 'Classement invitations'],
                ['method' => 'GET', 'url' => '/api/discord/public-stats', 'description' => 'Statistiques publiques'],
                ['method' => 'GET', 'url' => '/api/discord/live-promotions/active', 'description' => 'Promotions actives'],
            ],
            'Utilitaires' => [
                ['method' => 'GET', 'url' => '/api/blacklist/check?type=username&value=test', 'description' => 'Vérifier la blacklist'],
                ['method' => 'GET', 'url' => '/api/form/game-categories/{categoryId}', 'description' => 'Sous-catégories d\'un jeu'],
                ['method' => 'GET', 'url' => '/api/form/server-types/{categoryId}', 'description' => 'Types de serveur d\'une catégorie'],
                ['method' => 'GET', 'url' => '/api/server-status/{id}', 'description' => 'Statut live d\'un serveur'],
                ['method' => 'GET', 'url' => '/api/notifications', 'description' => 'Notifications (session requise)'],
                ['method' => 'GET', 'url' => '/api/twitch-info/{id}', 'description' => 'Infos Twitch d\'un serveur'],
            ],
        ];
    }
}
