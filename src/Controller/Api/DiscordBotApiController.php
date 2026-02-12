<?php

namespace App\Controller\Api;

use App\Entity\DiscordInvite;
use App\Entity\DiscordModerationLog;
use App\Entity\DiscordReactionRole;
use App\Entity\DiscordSanction;
use App\Entity\DiscordTicket;
use App\Entity\DiscordTicketMessage;
use App\Repository\BannedWordRepository;
use App\Repository\DiscordAnnouncementRepository;
use App\Repository\DiscordCommandRepository;
use App\Repository\DiscordInviteRepository;
use App\Repository\DiscordReactionRoleRepository;
use App\Repository\DiscordSanctionRepository;
use App\Repository\DiscordTicketRepository;
use App\Repository\LivePromotionRepository;
use App\Repository\UserRepository;
use App\Service\SettingsService;
use App\Service\StatsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/discord', name: 'api_discord_')]
class DiscordBotApiController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SettingsService $settings,
        private UserRepository $userRepo,
    ) {
    }

    private function checkApiKey(Request $request): ?JsonResponse
    {
        $key = $request->headers->get('X-Api-Key');
        $expected = $this->settings->get('discord_bot_api_key', '');

        if (!$key || !$expected || !hash_equals($expected, $key)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        return null;
    }

    // ===== BANNED WORDS =====

    #[Route('/banned-words', name: 'banned_words', methods: ['GET'])]
    public function bannedWords(Request $request, BannedWordRepository $bannedWordRepo): JsonResponse
    {
        $authError = $this->checkApiKey($request);
        if ($authError) return $authError;

        $words = $bannedWordRepo->findAllWords();
        return $this->json(['words' => $words]);
    }

    // ===== TICKETS =====

    #[Route('/tickets', name: 'ticket_create', methods: ['POST'])]
    public function createTicket(Request $request): JsonResponse
    {
        $authError = $this->checkApiKey($request);
        if ($authError) return $authError;

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], 400);
        }

        $ticket = new DiscordTicket();
        $ticket->setDiscordUserId($data['discordUserId'] ?? '');
        $ticket->setDiscordUsername($data['discordUsername'] ?? '');
        $ticket->setCategory($data['category'] ?? 'autre');
        $ticket->setSubject($data['subject'] ?? 'Sans sujet');
        $ticket->setDescription($data['description'] ?? null);

        // Try to link site user by Discord ID
        $siteUser = $this->userRepo->findOneBy(['discordId' => $data['discordUserId'] ?? '']);
        if ($siteUser) {
            $ticket->setSiteUser($siteUser);
        }

        $this->em->persist($ticket);
        $this->em->flush();

        return $this->json([
            'id' => $ticket->getId(),
            'status' => $ticket->getStatus(),
        ]);
    }

    #[Route('/tickets/{id}/messages', name: 'ticket_add_message', methods: ['POST'])]
    public function addTicketMessage(Request $request, DiscordTicketRepository $ticketRepo, int $id): JsonResponse
    {
        $authError = $this->checkApiKey($request);
        if ($authError) return $authError;

        $ticket = $ticketRepo->find($id);
        if (!$ticket) {
            return $this->json(['error' => 'Ticket not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], 400);
        }

        $message = new DiscordTicketMessage();
        $message->setTicket($ticket);
        $message->setAuthorDiscordId($data['authorDiscordId'] ?? '');
        $message->setAuthorUsername($data['authorUsername'] ?? '');
        $message->setAuthorIsStaff($data['authorIsStaff'] ?? false);
        $message->setContent($data['content'] ?? '');

        $this->em->persist($message);
        $this->em->flush();

        return $this->json(['id' => $message->getId()]);
    }

    #[Route('/tickets/{id}/close', name: 'ticket_close', methods: ['PATCH'])]
    public function closeTicket(Request $request, DiscordTicketRepository $ticketRepo, int $id): JsonResponse
    {
        $authError = $this->checkApiKey($request);
        if ($authError) return $authError;

        $ticket = $ticketRepo->find($id);
        if (!$ticket) {
            return $this->json(['error' => 'Ticket not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        $ticket->setStatus(DiscordTicket::STATUS_CLOSED);
        $ticket->setClosedAt(new \DateTimeImmutable());
        $ticket->setClosedBy($data['closedBy'] ?? 'Inconnu');

        $this->em->flush();

        return $this->json(['ok' => true]);
    }

    #[Route('/tickets/open', name: 'tickets_open', methods: ['GET'])]
    public function openTickets(Request $request, DiscordTicketRepository $ticketRepo): JsonResponse
    {
        $authError = $this->checkApiKey($request);
        if ($authError) return $authError;

        $tickets = $ticketRepo->findAllForAdmin('open');
        $result = [];
        foreach ($tickets as $ticket) {
            $result[] = [
                'id' => $ticket->getId(),
                'discordUserId' => $ticket->getDiscordUserId(),
                'discordChannelId' => $ticket->getDiscordChannelId(),
                'subject' => $ticket->getSubject(),
                'category' => $ticket->getCategory(),
            ];
        }

        return $this->json(['tickets' => $result]);
    }

    // ===== USER VERIFICATION =====

    #[Route('/user/{discordId}', name: 'user_by_discord', methods: ['GET'])]
    public function userByDiscord(Request $request, string $discordId): JsonResponse
    {
        $authError = $this->checkApiKey($request);
        if ($authError) return $authError;

        $user = $this->userRepo->findOneBy(['discordId' => $discordId]);
        if (!$user) {
            return $this->json(['found' => false]);
        }

        return $this->json([
            'found' => true,
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ]);
    }

    // ===== MODERATION LOG =====

    #[Route('/moderation-log', name: 'moderation_log', methods: ['POST'])]
    public function moderationLog(Request $request): JsonResponse
    {
        $authError = $this->checkApiKey($request);
        if ($authError) return $authError;

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], 400);
        }

        $action = $data['action'] ?? '';
        $discordUserId = $data['discordUserId'] ?? '';

        if (!$action || !$discordUserId) {
            return $this->json(['error' => 'action and discordUserId are required'], 400);
        }

        if (!in_array($action, DiscordModerationLog::VALID_ACTIONS, true)) {
            return $this->json(['error' => 'Invalid action'], 400);
        }

        $log = new DiscordModerationLog();
        $log->setAction($action);
        $log->setDiscordUserId(mb_substr($discordUserId, 0, 20));
        $log->setDiscordUsername(mb_substr($data['discordUsername'] ?? 'Inconnu', 0, 100));
        $log->setChannelId(!empty($data['channelId']) ? mb_substr($data['channelId'], 0, 20) : null);
        $log->setChannelName(!empty($data['channelName']) ? mb_substr($data['channelName'], 0, 100) : null);
        $log->setMessageContent(!empty($data['messageContent']) ? mb_substr($data['messageContent'], 0, 5000) : null);
        $log->setReason(!empty($data['reason']) ? mb_substr($data['reason'], 0, 255) : null);
        $log->setTriggeredWord(!empty($data['triggeredWord']) ? mb_substr($data['triggeredWord'], 0, 100) : null);
        $log->setMetadata(!empty($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : null);

        $this->em->persist($log);
        $this->em->flush();

        return $this->json(['ok' => true, 'id' => $log->getId()]);
    }

    // ===== SANCTIONS =====

    #[Route('/sanctions', name: 'sanction_create', methods: ['POST'])]
    public function createSanction(Request $request): JsonResponse
    {
        $authError = $this->checkApiKey($request);
        if ($authError) return $authError;

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], 400);
        }

        $sanction = new DiscordSanction();
        $sanction->setDiscordUserId($data['discordUserId'] ?? '');
        $sanction->setDiscordUsername($data['discordUsername'] ?? '');
        $sanction->setType($data['type'] ?? DiscordSanction::TYPE_WARN);
        $sanction->setReason($data['reason'] ?? null);
        $sanction->setIssuedBy($data['issuedBy'] ?? '');
        $sanction->setIssuedByDiscordId($data['issuedByDiscordId'] ?? '');

        if (!empty($data['expiresAt'])) {
            $sanction->setExpiresAt(new \DateTimeImmutable($data['expiresAt']));
        }

        // Link site user if possible
        $siteUser = $this->userRepo->findOneBy(['discordId' => $data['discordUserId'] ?? '']);
        if ($siteUser) {
            $sanction->setSiteUser($siteUser);
        }

        $this->em->persist($sanction);
        $this->em->flush();

        return $this->json([
            'id' => $sanction->getId(),
            'type' => $sanction->getType(),
        ]);
    }

    #[Route('/sanctions/{discordUserId}', name: 'sanction_list', methods: ['GET'])]
    public function userSanctions(Request $request, string $discordUserId, DiscordSanctionRepository $sanctionRepo): JsonResponse
    {
        $authError = $this->checkApiKey($request);
        if ($authError) return $authError;

        $sanctions = $sanctionRepo->findByDiscordUserId($discordUserId);
        $result = [];
        foreach ($sanctions as $s) {
            $result[] = [
                'id' => $s->getId(),
                'type' => $s->getType(),
                'reason' => $s->getReason(),
                'issuedBy' => $s->getIssuedBy(),
                'isActive' => $s->isActive(),
                'isRevoked' => $s->isRevoked(),
                'expiresAt' => $s->getExpiresAt()?->format('c'),
                'createdAt' => $s->getCreatedAt()->format('c'),
            ];
        }

        return $this->json([
            'sanctions' => $result,
            'activeWarns' => $sanctionRepo->countActiveWarnsByDiscordUserId($discordUserId),
        ]);
    }

    #[Route('/sanctions/{id}/revoke', name: 'sanction_revoke', methods: ['PATCH'])]
    public function revokeSanction(Request $request, DiscordSanctionRepository $sanctionRepo, int $id): JsonResponse
    {
        $authError = $this->checkApiKey($request);
        if ($authError) return $authError;

        $sanction = $sanctionRepo->find($id);
        if (!$sanction) {
            return $this->json(['error' => 'Sanction not found'], 404);
        }

        $sanction->setIsRevoked(true);
        $sanction->setRevokedAt(new \DateTimeImmutable());

        $this->em->flush();

        return $this->json(['ok' => true]);
    }

    // ===== SETTINGS =====

    #[Route('/settings', name: 'settings', methods: ['GET'])]
    public function getSettings(Request $request): JsonResponse
    {
        $authError = $this->checkApiKey($request);
        if ($authError) return $authError;

        $keys = $request->query->get('keys', '');
        $keyList = array_filter(array_map('trim', explode(',', $keys)));

        if (empty($keyList)) {
            return $this->json(['error' => 'keys parameter required'], 400);
        }

        $result = [];
        foreach ($keyList as $key) {
            $result[$key] = $this->settings->get($key, '');
        }

        return $this->json(['settings' => $result]);
    }

    // ===== REACTION ROLES =====

    #[Route('/reaction-roles', name: 'reaction_roles_list', methods: ['GET'])]
    public function listReactionRoles(Request $request, DiscordReactionRoleRepository $repo): JsonResponse
    {
        $authError = $this->checkApiKey($request);
        if ($authError) return $authError;

        $roles = $repo->findAll();
        $result = [];
        foreach ($roles as $rr) {
            $result[] = [
                'id' => $rr->getId(),
                'messageId' => $rr->getMessageId(),
                'channelId' => $rr->getChannelId(),
                'emoji' => $rr->getEmoji(),
                'roleId' => $rr->getRoleId(),
                'label' => $rr->getLabel(),
            ];
        }

        return $this->json(['reactionRoles' => $result]);
    }

    #[Route('/reaction-roles', name: 'reaction_roles_create', methods: ['POST'])]
    public function createReactionRole(Request $request): JsonResponse
    {
        $authError = $this->checkApiKey($request);
        if ($authError) return $authError;

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], 400);
        }

        $rr = new DiscordReactionRole();
        $rr->setMessageId($data['messageId'] ?? '');
        $rr->setChannelId($data['channelId'] ?? '');
        $rr->setEmoji($data['emoji'] ?? '');
        $rr->setRoleId($data['roleId'] ?? '');
        $rr->setLabel($data['label'] ?? null);

        $this->em->persist($rr);
        $this->em->flush();

        return $this->json(['id' => $rr->getId()]);
    }

    #[Route('/reaction-roles/{id}', name: 'reaction_roles_delete', methods: ['DELETE'])]
    public function deleteReactionRole(Request $request, DiscordReactionRoleRepository $repo, int $id): JsonResponse
    {
        $authError = $this->checkApiKey($request);
        if ($authError) return $authError;

        $rr = $repo->find($id);
        if (!$rr) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $this->em->remove($rr);
        $this->em->flush();

        return $this->json(['ok' => true]);
    }

    // ===== CUSTOM COMMANDS =====

    #[Route('/commands', name: 'commands_list', methods: ['GET'])]
    public function listCommands(Request $request, DiscordCommandRepository $repo): JsonResponse
    {
        $authError = $this->checkApiKey($request);
        if ($authError) return $authError;

        $commands = $repo->findAllActive();
        $result = [];
        foreach ($commands as $cmd) {
            $result[] = [
                'id' => $cmd->getId(),
                'name' => $cmd->getName(),
                'description' => $cmd->getDescription(),
                'response' => $cmd->getResponse(),
                'embedTitle' => $cmd->getEmbedTitle(),
                'embedDescription' => $cmd->getEmbedDescription(),
                'embedColor' => $cmd->getEmbedColor(),
                'embedImage' => $cmd->getEmbedImage(),
                'requiredRole' => $cmd->getRequiredRole(),
            ];
        }

        return $this->json(['commands' => $result]);
    }

    // ===== INVITES =====

    #[Route('/invites', name: 'invites_create', methods: ['POST'])]
    public function createInvite(Request $request): JsonResponse
    {
        $authError = $this->checkApiKey($request);
        if ($authError) return $authError;

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], 400);
        }

        $invite = new DiscordInvite();
        $invite->setInviterDiscordId($data['inviterDiscordId'] ?? '');
        $invite->setInviterUsername($data['inviterUsername'] ?? '');
        $invite->setInvitedDiscordId($data['invitedDiscordId'] ?? '');
        $invite->setInvitedUsername($data['invitedUsername'] ?? '');
        $invite->setInviteCode($data['inviteCode'] ?? null);

        $this->em->persist($invite);
        $this->em->flush();

        return $this->json(['id' => $invite->getId()]);
    }

    #[Route('/invites/leaderboard', name: 'invites_leaderboard', methods: ['GET'])]
    public function invitesLeaderboard(Request $request, DiscordInviteRepository $repo): JsonResponse
    {
        $authError = $this->checkApiKey($request);
        if ($authError) return $authError;

        return $this->json(['leaderboard' => $repo->getLeaderboard()]);
    }

    // ===== PUBLIC STATS =====

    #[Route('/public-stats', name: 'public_stats', methods: ['GET'])]
    public function publicStats(Request $request, StatsService $statsService): JsonResponse
    {
        $authError = $this->checkApiKey($request);
        if ($authError) return $authError;

        $stats = $statsService->getAllStats();

        return $this->json([
            'totalServers' => $stats['approved_servers']['value'] ?? 0,
            'totalUsers' => $stats['total_users']['value'] ?? 0,
            'votesThisMonth' => $stats['votes_this_month']['value'] ?? 0,
            'totalVotes' => $this->em->getRepository(\App\Entity\Vote::class)->count([]),
            'newUsersToday' => $stats['new_users_today']['value'] ?? 0,
        ]);
    }

    // ===== LIVE PROMOTIONS =====

    #[Route('/live-promotions/active', name: 'live_promotions_active', methods: ['GET'])]
    public function activeLivePromotions(Request $request, LivePromotionRepository $repo): JsonResponse
    {
        $authError = $this->checkApiKey($request);
        if ($authError) return $authError;

        $promos = $repo->findCurrentlyActive();
        $result = [];
        foreach ($promos as $p) {
            $result[] = [
                'id' => $p->getId(),
                'platform' => $p->getPlatform(),
                'channelUrl' => $p->getChannelUrl(),
                'channelName' => $p->getChannelName(),
                'serverName' => $p->getServer()?->getName(),
                'lastNotifiedAt' => $p->getLastNotifiedAt()?->format('c'),
            ];
        }

        return $this->json(['promotions' => $result]);
    }

    #[Route('/live-promotions/{id}/notified', name: 'live_promotions_notified', methods: ['PATCH'])]
    public function markLivePromotionNotified(Request $request, LivePromotionRepository $repo, int $id): JsonResponse
    {
        $authError = $this->checkApiKey($request);
        if ($authError) return $authError;

        $promo = $repo->find($id);
        if (!$promo) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $promo->setLastNotifiedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $this->json(['ok' => true]);
    }
}
