<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/api-tester', name: 'admin_api_tester_')]
#[IsGranted('api_tester.use')]
class ApiTesterController extends AbstractController
{
    #[Route('', name: 'index')]
    public function index(): Response
    {
        $endpoints = $this->getEndpoints();

        return $this->render('admin/api_tester/index.html.twig', [
            'endpoints' => $endpoints,
        ]);
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
