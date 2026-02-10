<?php

namespace App\Controller\Api;

use App\Repository\ServerRepository;
use App\Service\GameServerQueryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/server-status')]
class ServerStatusController extends AbstractController
{
    public function __construct(
        private GameServerQueryService $queryService,
    ) {
    }

    #[Route('/{id}', name: 'api_server_status', methods: ['GET'])]
    public function status(int $id, ServerRepository $serverRepo): JsonResponse
    {
        $server = $serverRepo->find($id);
        if (!$server || !$server->isActive() || !$server->isApproved()) {
            return $this->json(['error' => 'Server not found'], 404);
        }

        if (!$server->getIp() || !$server->getPort()) {
            return $this->json([
                'online' => false,
                'players' => 0,
                'maxPlayers' => 0,
                'playerList' => [],
                'serverName' => null,
                'map' => null,
                'queryType' => null,
            ]);
        }

        // Auto-detect protocol from category/subcategory names
        $queryType = $this->queryService->detectQueryType($server);
        if (!$queryType) {
            return $this->json([
                'online' => false,
                'players' => 0,
                'maxPlayers' => 0,
                'playerList' => [],
                'serverName' => null,
                'map' => null,
                'queryType' => null,
            ]);
        }

        $result = $this->queryService->query($queryType, $server->getIp(), $server->getPort());
        $result['queryType'] = $queryType;

        return $this->json($result);
    }
}
