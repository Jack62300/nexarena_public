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
use App\Service\ServerAdminService;
use App\Service\SettingsService;
use App\Service\StatsService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/discord', name: 'api_discord_')]
class DiscordBotApiController extends AbstractController
{
    private const DISCORD_ID_SETTINGS_WHITELIST = [
        'discord_automod_warn_to_mute', 'discord_automod_warn_to_kick', 'discord_automod_warn_to_ban',
        'discord_automod_kick_enabled', 'discord_automod_ban_enabled', 'discord_automod_mute_duration',
        'discord_cmd_role_add_min_role', 'discord_cmd_role_remove_min_role',
        'discord_welcome_enabled', 'discord_welcome_channel_id', 'discord_welcome_message', 'discord_welcome_banner_url',
        'discord_antispam_enabled', 'discord_antispam_max_messages', 'discord_antispam_interval', 'discord_antispam_max_links',
        'discord_live_promo_enabled', 'discord_live_promo_channel_id', 'discord_live_promo_cost_per_day', 'discord_live_promo_max_days',
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private SettingsService $settings,
        private UserRepository $userRepo,
        private CacheItemPoolInterface $cache,
        private ServerAdminService $sas,
    ) {
    }

    private function checkApiKey(Request $request): ?JsonResponse
    {
        $key = $request->headers->get('X-Api-Key');
        $expected = $this->settings->get('discord_bot_api_key', '');

        if (!$key || !$expected || !hash_equals($expected, $key)) {
            // Rate limit auth failures: 10 per 5 min per IP
            $ip = $request->getClientIp();
            $cacheKey = 'discord_api_auth_fail_' . hash('sha256', $ip);
            $cacheItem = $this->cache->getItem($cacheKey);
            $failures = $cacheItem->isHit() ? (int) $cacheItem->get() : 0;
            $cacheItem->set($failures + 1);
            $cacheItem->expiresAfter(300);
            $this->cache->save($cacheItem);

            if ($failures >= 10) {
                return $this->json(['error' => 'Too many failed attempts'], 429);
            }

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

        $discordUserId = $data['discordUserId'] ?? '';
        if (!preg_match('/^\d{1,20}$/', $discordUserId)) {
            return $this->json(['error' => 'Invalid discordUserId'], 400);
        }

        $ticket = new DiscordTicket();
        $ticket->setDiscordUserId($discordUserId);
        $ticket->setDiscordUsername(mb_substr($data['discordUsername'] ?? '', 0, 100));
        $ticket->setCategory(mb_substr($data['category'] ?? 'autre', 0, 50));
        $ticket->setSubject(mb_substr($data['subject'] ?? 'Sans sujet', 0, 255));
        $ticket->setDescription(!empty($data['description']) ? mb_substr($data['description'], 0, 5000) : null);

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
        $message->setAuthorDiscordId(mb_substr($data['authorDiscordId'] ?? '', 0, 20));
        $message->setAuthorUsername(mb_substr($data['authorUsername'] ?? '', 0, 100));
        $message->setAuthorIsStaff((bool) ($data['authorIsStaff'] ?? false));
        $message->setContent(mb_substr($data['content'] ?? '', 0, 5000));

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

        if (!preg_match('/^\d{1,20}$/', $discordId)) {
            return $this->json(['error' => 'Invalid Discord ID format'], 400);
        }

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
        $metadata = !empty($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : null;
        if ($metadata && strlen(json_encode($metadata)) > 10000) {
            $metadata = null;
        }
        $log->setMetadata($metadata);

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

        $discordUserId = $data['discordUserId'] ?? '';
        if (!preg_match('/^\d{1,20}$/', $discordUserId)) {
            return $this->json(['error' => 'Invalid discordUserId'], 400);
        }

        $sanction = new DiscordSanction();
        $sanction->setDiscordUserId($discordUserId);
        $sanction->setDiscordUsername(mb_substr($data['discordUsername'] ?? '', 0, 100));
        $sanction->setType($data['type'] ?? DiscordSanction::TYPE_WARN);
        $sanction->setReason(!empty($data['reason']) ? mb_substr($data['reason'], 0, 500) : null);
        $sanction->setIssuedBy(mb_substr($data['issuedBy'] ?? '', 0, 100));
        $sanction->setIssuedByDiscordId(mb_substr($data['issuedByDiscordId'] ?? '', 0, 20));

        if (!empty($data['expiresAt'])) {
            try {
                $sanction->setExpiresAt(new \DateTimeImmutable($data['expiresAt']));
            } catch (\Exception) {
                return $this->json(['error' => 'Invalid expiresAt date format'], 400);
            }
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

        if (!preg_match('/^\d{1,20}$/', $discordUserId)) {
            return $this->json(['error' => 'Invalid Discord ID format'], 400);
        }

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

        // Whitelist: only discord-related settings
        $result = [];
        foreach ($keyList as $key) {
            if (!in_array($key, self::DISCORD_ID_SETTINGS_WHITELIST, true)) {
                continue;
            }
            $result[$key] = $this->settings->get($key, '');
        }

        if (empty($result)) {
            return $this->json(['error' => 'No valid keys requested'], 400);
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
        $rr->setMessageId(mb_substr($data['messageId'] ?? '', 0, 20));
        $rr->setChannelId(mb_substr($data['channelId'] ?? '', 0, 20));
        $rr->setEmoji(mb_substr($data['emoji'] ?? '', 0, 100));
        $rr->setRoleId(mb_substr($data['roleId'] ?? '', 0, 20));
        $rr->setLabel(!empty($data['label']) ? mb_substr($data['label'], 0, 100) : null);

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
        $invite->setInviterDiscordId(mb_substr($data['inviterDiscordId'] ?? '', 0, 20));
        $invite->setInviterUsername(mb_substr($data['inviterUsername'] ?? '', 0, 100));
        $invite->setInvitedDiscordId(mb_substr($data['invitedDiscordId'] ?? '', 0, 20));
        $invite->setInvitedUsername(mb_substr($data['invitedUsername'] ?? '', 0, 100));
        $invite->setInviteCode(!empty($data['inviteCode']) ? mb_substr($data['inviteCode'], 0, 50) : null);

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

    // ===== SERVER ADMIN (Ubuntu) =====

    #[Route('/server-admin/firewall', name: 'server_admin_firewall', methods: ['GET'])]
    public function serverAdminFirewall(Request $request): JsonResponse
    {
        $authError = $this->checkApiKey($request);
        if ($authError) return $authError;

        return $this->json($this->sas->getFirewallBannedIps());
    }

    #[Route('/server-admin/fail2ban', name: 'server_admin_fail2ban', methods: ['GET'])]
    public function serverAdminFail2ban(Request $request): JsonResponse
    {
        $authError = $this->checkApiKey($request);
        if ($authError) return $authError;

        return $this->json($this->sas->getFail2banStatus());
    }

    #[Route('/server-admin/system-info', name: 'server_admin_system_info', methods: ['GET'])]
    public function serverAdminSystemInfo(Request $request): JsonResponse
    {
        $authError = $this->checkApiKey($request);
        if ($authError) return $authError;

        return $this->json($this->sas->getSystemInfo());
    }

    #[Route('/server-admin/logs', name: 'server_admin_logs', methods: ['GET'])]
    public function serverAdminLogs(Request $request): JsonResponse
    {
        $authError = $this->checkApiKey($request);
        if ($authError) return $authError;

        $key   = (string) $request->query->get('key', '');
        $lines = min(50, max(1, (int) $request->query->get('lines', 10)));

        return $this->json($this->sas->readLogFile($key, $lines));
    }
}
