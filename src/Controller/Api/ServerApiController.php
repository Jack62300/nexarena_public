<?php

namespace App\Controller\Api;

use App\Entity\Server;
use App\Repository\ServerRepository;
use App\Repository\VoteRepository;
use App\Service\SettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/servers/{token}')]
class ServerApiController extends AbstractController
{
    public function __construct(
        private ServerRepository $serverRepo,
        private VoteRepository $voteRepo,
        private SettingsService $settings,
    ) {
    }

    /**
     * Resolve server by token and check IP whitelist.
     */
    private function resolveServer(string $token, Request $request): Server|JsonResponse
    {
        $server = $this->serverRepo->findByToken($token);
        if (!$server) {
            return $this->json(['error' => 'Invalid token.'], 401);
        }

        $serverIp = $server->getIp();
        if ($serverIp) {
            $clientIp = $request->getClientIp();
            if ($clientIp !== $serverIp) {
                return $this->json(['error' => 'IP not authorized.'], 403);
            }
        }

        return $server;
    }

    #[Route('/vote/{username}', name: 'api_server_check_vote_username', methods: ['GET'])]
    public function checkVoteByUsername(string $token, string $username, Request $request): JsonResponse
    {
        if (!preg_match('/^[a-zA-Z0-9_\-\.]{1,64}$/', $username)) {
            return $this->json(['error' => 'Invalid username format.'], 400);
        }

        $result = $this->resolveServer($token, $request);
        if ($result instanceof JsonResponse) {
            return $result;
        }
        $server = $result;

        $interval = $this->settings->getInt('vote_interval', 120);
        $vote = $this->voteRepo->findRecentVoteByUsername($server, $username, $interval);

        return $this->json([
            'voted' => $vote !== null,
            'username' => $username,
            'voted_at' => $vote?->getVotedAt()?->format('c'),
        ]);
    }

    #[Route('/vote/ip/{ip}', name: 'api_server_check_vote_ip', methods: ['GET'])]
    public function checkVoteByIp(string $token, string $ip, Request $request): JsonResponse
    {
        // Validate IP format
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return $this->json(['error' => 'Invalid IP address format.'], 400);
        }

        $result = $this->resolveServer($token, $request);
        if ($result instanceof JsonResponse) {
            return $result;
        }
        $server = $result;

        $interval = $this->settings->getInt('vote_interval', 120);
        $vote = $this->voteRepo->findRecentVoteByIp($server, $ip, $interval);

        return $this->json([
            'voted' => $vote !== null,
            'ip' => $ip,
            'voted_at' => $vote?->getVotedAt()?->format('c'),
        ]);
    }

    #[Route('/vote/discord/{discordId}', name: 'api_server_check_vote_discord', methods: ['GET'])]
    public function checkVoteByDiscord(string $token, string $discordId, Request $request): JsonResponse
    {
        // Validate Discord ID format (numeric string)
        if (!preg_match('/^\d{1,20}$/', $discordId)) {
            return $this->json(['error' => 'Invalid Discord ID format.'], 400);
        }

        $result = $this->resolveServer($token, $request);
        if ($result instanceof JsonResponse) {
            return $result;
        }
        $server = $result;

        $interval = $this->settings->getInt('vote_interval', 120);
        $vote = $this->voteRepo->findRecentVoteByDiscordId($server, $discordId, $interval);

        return $this->json([
            'voted' => $vote !== null,
            'discord_id' => $discordId,
            'voted_at' => $vote?->getVotedAt()?->format('c'),
        ]);
    }

    #[Route('/vote/user/{userId}', name: 'api_server_check_vote_user', methods: ['GET'])]
    public function checkVoteByUserId(string $token, int $userId, Request $request): JsonResponse
    {
        $result = $this->resolveServer($token, $request);
        if ($result instanceof JsonResponse) {
            return $result;
        }
        $server = $result;

        $interval = $this->settings->getInt('vote_interval', 120);
        $vote = $this->voteRepo->findRecentVoteByUserId($server, $userId, $interval);

        return $this->json([
            'voted' => $vote !== null,
            'user_id' => $userId,
            'voted_at' => $vote?->getVotedAt()?->format('c'),
        ]);
    }

    #[Route('/stats', name: 'api_server_stats', methods: ['GET'])]
    public function stats(string $token, Request $request): JsonResponse
    {
        $result = $this->resolveServer($token, $request);
        if ($result instanceof JsonResponse) {
            return $result;
        }
        $server = $result;

        return $this->json([
            'server_id' => $server->getId(),
            'name' => $server->getName(),
            'slug' => $server->getSlug(),
            'monthly_votes' => $server->getMonthlyVotes(),
            'total_votes' => $server->getTotalVotes(),
            'category' => $server->getCategory()?->getName(),
            'game_category' => $server->getGameCategory()?->getName(),
        ]);
    }

    #[Route('/voters', name: 'api_server_voters', methods: ['GET'])]
    public function voters(string $token, Request $request): JsonResponse
    {
        $result = $this->resolveServer($token, $request);
        if ($result instanceof JsonResponse) {
            return $result;
        }
        $server = $result;

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 20)));

        $voters = $this->voteRepo->getTopVotersByServer($server, $limit, $page);
        $totalVoters = $this->voteRepo->countUniqueVotersByServer($server);

        return $this->json([
            'server_id' => $server->getId(),
            'page' => $page,
            'limit' => $limit,
            'total_voters' => $totalVoters,
            'voters' => array_map(function (array $row) {
                return [
                    'username' => $row['username'],
                    'votes' => (int) $row['votes'],
                    'last_vote' => $row['last_vote'] instanceof \DateTimeInterface
                        ? $row['last_vote']->format('c')
                        : $row['last_vote'],
                ];
            }, $voters),
        ]);
    }
}
