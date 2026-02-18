<?php

namespace App\Controller\Admin;

use App\Entity\BannedWord;
use App\Entity\DiscordAnnouncement;
use App\Entity\DiscordCommand;
use App\Form\Admin\DiscordCommandFormType;
use App\Repository\BannedWordRepository;
use App\Repository\DiscordAnnouncementRepository;
use App\Repository\DiscordCommandRepository;
use App\Repository\DiscordInviteRepository;
use App\Repository\DiscordModerationLogRepository;
use App\Repository\DiscordReactionRoleRepository;
use App\Repository\DiscordSanctionRepository;
use App\Repository\DiscordTicketRepository;
use App\Repository\DiscordTicketMessageRepository;
use App\Service\DiscordBotService;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/discord', name: 'admin_discord_')]
#[IsGranted('ROLE_MANAGER')]
class DiscordController extends AbstractController
{
    private const CATEGORY_LABELS = [
        'support-site' => 'Support Site',
        'support-serveur' => 'Support Serveur',
        'support-premium' => 'Support Premium',
        'bug-report' => 'Bug Report',
        'autre' => 'Autre',
    ];

    private const SANCTION_LABELS = [
        'warn' => 'Avertissement',
        'mute' => 'Mute',
        'kick' => 'Kick',
        'ban' => 'Ban',
    ];

    private const RESERVED_COMMAND_NAMES = ['setup', 'warn', 'mute', 'kick', 'ban', 'warnings', 'role'];

    public function __construct(
        private EntityManagerInterface $em,
        private DiscordBotService $botService,
        private SettingsService $settings,
    ) {
    }

    // ===== DASHBOARD =====

    #[Route('', name: 'index')]
    public function index(
        DiscordTicketRepository $ticketRepo,
        DiscordTicketMessageRepository $messageRepo,
        BannedWordRepository $bannedWordRepo,
        DiscordSanctionRepository $sanctionRepo,
    ): Response {
        $botAvailable = $this->botService->isAvailable();
        $guildInfo = $botAvailable ? $this->botService->getGuildInfo() : null;

        return $this->render('admin/discord/index.html.twig', [
            'bot_available' => $botAvailable,
            'guild' => $guildInfo,
            'open_tickets' => $ticketRepo->countOpen(),
            'closed_tickets' => $ticketRepo->countByStatus('closed'),
            'total_tickets' => $ticketRepo->count([]),
            'total_messages' => $messageRepo->count([]),
            'banned_words_count' => $bannedWordRepo->count([]),
            'active_sanctions' => $sanctionRepo->countActive(),
        ]);
    }

    // ===== MODERATION (BANNED WORDS) =====

    #[Route('/moderation', name: 'moderation')]
    public function moderation(BannedWordRepository $bannedWordRepo): Response
    {
        return $this->render('admin/discord/moderation.html.twig', [
            'words' => $bannedWordRepo->findAllOrdered(),
        ]);
    }

    #[Route('/moderation/add', name: 'moderation_add', methods: ['POST'])]
    public function moderationAdd(Request $request, BannedWordRepository $bannedWordRepo): Response
    {
        if (!$this->isCsrfTokenValid('banned_word_add', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_discord_moderation');
        }

        $word = trim($request->request->get('word', ''));
        if ($word === '') {
            $this->addFlash('error', 'Le mot ne peut pas etre vide.');
            return $this->redirectToRoute('admin_discord_moderation');
        }

        if ($bannedWordRepo->findByWord($word)) {
            $this->addFlash('warning', 'Ce mot est deja dans la liste.');
            return $this->redirectToRoute('admin_discord_moderation');
        }

        $banned = new BannedWord();
        $banned->setWord($word);
        $banned->setAddedBy($this->getUser());

        $this->em->persist($banned);
        $this->em->flush();

        $this->botService->refreshBannedWords();

        $this->addFlash('success', "Mot interdit \"$word\" ajoute.");
        return $this->redirectToRoute('admin_discord_moderation');
    }

    #[Route('/moderation/{id}/delete', name: 'moderation_delete', methods: ['POST'])]
    public function moderationDelete(Request $request, BannedWord $bannedWord): Response
    {
        if (!$this->isCsrfTokenValid('banned_word_delete_' . $bannedWord->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_discord_moderation');
        }

        $word = $bannedWord->getWord();
        $this->em->remove($bannedWord);
        $this->em->flush();

        $this->botService->refreshBannedWords();

        $this->addFlash('success', "Mot interdit \"$word\" supprime.");
        return $this->redirectToRoute('admin_discord_moderation');
    }

    // ===== MODERATION LOG =====

    #[Route('/moderation-log', name: 'moderation_log')]
    public function moderationLog(Request $request, DiscordModerationLogRepository $modlogRepo): Response
    {
        $action = $request->query->get('action');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $logs = $modlogRepo->findAllForAdmin($action, $limit, $offset);
        $total = $modlogRepo->countAll($action);
        $totalPages = max(1, (int) ceil($total / $limit));

        return $this->render('admin/discord/moderation_log.html.twig', [
            'logs' => $logs,
            'current_action' => $action,
            'page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
            'stats_today' => $modlogRepo->countByActionToday(),
        ]);
    }

    // ===== TICKETS =====

    #[Route('/tickets', name: 'tickets')]
    public function tickets(Request $request, DiscordTicketRepository $ticketRepo): Response
    {
        $status = $request->query->get('status');
        $tickets = $ticketRepo->findAllForAdmin($status);

        return $this->render('admin/discord/tickets.html.twig', [
            'tickets' => $tickets,
            'current_status' => $status,
            'category_labels' => self::CATEGORY_LABELS,
            'open_count' => $ticketRepo->countByStatus('open'),
            'closed_count' => $ticketRepo->countByStatus('closed'),
        ]);
    }

    #[Route('/tickets/{id}', name: 'ticket_show')]
    public function ticketShow(DiscordTicketRepository $ticketRepo, int $id): Response
    {
        $ticket = $ticketRepo->find($id);
        if (!$ticket) {
            throw $this->createNotFoundException('Ticket introuvable.');
        }

        return $this->render('admin/discord/ticket_show.html.twig', [
            'ticket' => $ticket,
            'category_labels' => self::CATEGORY_LABELS,
        ]);
    }

    // ===== SANCTIONS =====

    #[Route('/sanctions', name: 'sanctions')]
    public function sanctions(Request $request, DiscordSanctionRepository $sanctionRepo): Response
    {
        $type = $request->query->get('type');
        $status = $request->query->get('status');

        return $this->render('admin/discord/sanctions.html.twig', [
            'sanctions' => $sanctionRepo->findAllForAdmin($type, $status),
            'current_type' => $type,
            'current_status' => $status,
            'sanction_labels' => self::SANCTION_LABELS,
        ]);
    }

    #[Route('/sanctions/{id}', name: 'sanction_show', requirements: ['id' => '\d+'])]
    public function sanctionShow(DiscordSanctionRepository $sanctionRepo, int $id): Response
    {
        $sanction = $sanctionRepo->find($id);
        if (!$sanction) {
            throw $this->createNotFoundException('Sanction introuvable.');
        }

        return $this->render('admin/discord/sanction_show.html.twig', [
            'sanction' => $sanction,
            'sanction_labels' => self::SANCTION_LABELS,
        ]);
    }

    #[Route('/sanctions/{id}/revoke', name: 'sanction_revoke', methods: ['POST'])]
    public function sanctionRevoke(Request $request, DiscordSanctionRepository $sanctionRepo, int $id): Response
    {
        if (!$this->isCsrfTokenValid('sanction_revoke_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_discord_sanctions');
        }

        $sanction = $sanctionRepo->find($id);
        if (!$sanction) {
            throw $this->createNotFoundException('Sanction introuvable.');
        }

        $sanction->setIsRevoked(true);
        $sanction->setRevokedBy($this->getUser());
        $sanction->setRevokedAt(new \DateTimeImmutable());
        $this->em->flush();

        $this->addFlash('success', 'Sanction revoquee.');
        return $this->redirectToRoute('admin_discord_sanctions');
    }

    // ===== REACTION ROLES =====

    #[Route('/reaction-roles', name: 'reaction_roles')]
    public function reactionRoles(DiscordReactionRoleRepository $repo): Response
    {
        return $this->render('admin/discord/reaction_roles.html.twig', [
            'reaction_roles' => $repo->findAll(),
        ]);
    }

    #[Route('/reaction-roles/{id}/delete', name: 'reaction_role_delete', methods: ['POST'])]
    public function reactionRoleDelete(Request $request, DiscordReactionRoleRepository $repo, int $id): Response
    {
        if (!$this->isCsrfTokenValid('rr_delete_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_discord_reaction_roles');
        }

        $rr = $repo->find($id);
        if ($rr) {
            $this->em->remove($rr);
            $this->em->flush();
            $this->addFlash('success', 'Reaction role supprime.');
        }

        return $this->redirectToRoute('admin_discord_reaction_roles');
    }

    // ===== CUSTOM COMMANDS =====

    #[Route('/commands', name: 'commands')]
    public function commands(DiscordCommandRepository $repo): Response
    {
        return $this->render('admin/discord/commands.html.twig', [
            'commands' => $repo->findAllForAdmin(),
        ]);
    }

    #[Route('/commands/new', name: 'command_new')]
    public function commandNew(Request $request): Response
    {
        $command = new DiscordCommand();
        $form = $this->createForm(DiscordCommandFormType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (in_array($command->getName(), self::RESERVED_COMMAND_NAMES, true)) {
                $this->addFlash('error', "Le nom \"{$command->getName()}\" est réservé.");
                return $this->render('admin/discord/command_form.html.twig', [
                    'command' => null,
                    'form' => $form,
                ]);
            }

            $this->em->persist($command);
            $this->em->flush();

            $this->addFlash('success', "Commande /{$command->getName()} enregistrée.");
            return $this->redirectToRoute('admin_discord_commands');
        }

        return $this->render('admin/discord/command_form.html.twig', [
            'command' => null,
            'form' => $form,
        ]);
    }

    #[Route('/commands/{id}/edit', name: 'command_edit')]
    public function commandEdit(Request $request, DiscordCommandRepository $repo, int $id): Response
    {
        $command = $repo->find($id);
        if (!$command) {
            throw $this->createNotFoundException('Commande introuvable.');
        }

        $form = $this->createForm(DiscordCommandFormType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (in_array($command->getName(), self::RESERVED_COMMAND_NAMES, true)) {
                $this->addFlash('error', "Le nom \"{$command->getName()}\" est réservé.");
                return $this->render('admin/discord/command_form.html.twig', [
                    'command' => $command,
                    'form' => $form,
                ]);
            }

            $command->setUpdatedAt(new \DateTimeImmutable());
            $this->em->flush();

            $this->addFlash('success', "Commande /{$command->getName()} enregistrée.");
            return $this->redirectToRoute('admin_discord_commands');
        }

        return $this->render('admin/discord/command_form.html.twig', [
            'command' => $command,
            'form' => $form,
        ]);
    }

    #[Route('/commands/{id}/delete', name: 'command_delete', methods: ['POST'])]
    public function commandDelete(Request $request, DiscordCommandRepository $repo, int $id): Response
    {
        if (!$this->isCsrfTokenValid('cmd_delete_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_discord_commands');
        }

        $cmd = $repo->find($id);
        if ($cmd) {
            $this->em->remove($cmd);
            $this->em->flush();
            $this->addFlash('success', "Commande /{$cmd->getName()} supprimée.");
        }

        return $this->redirectToRoute('admin_discord_commands');
    }

    // ===== ANNOUNCEMENTS =====

    #[Route('/announcements', name: 'announcements')]
    public function announcements(DiscordAnnouncementRepository $repo): Response
    {
        return $this->render('admin/discord/announcements.html.twig', [
            'announcements' => $repo->findAllForAdmin(),
        ]);
    }

    #[Route('/announcements/new', name: 'announcement_new')]
    public function announcementNew(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleAnnouncementForm($request);
        }

        return $this->render('admin/discord/announcement_form.html.twig', [
            'announcement' => null,
        ]);
    }

    #[Route('/announcements/{id}/delete', name: 'announcement_delete', methods: ['POST'])]
    public function announcementDelete(Request $request, DiscordAnnouncementRepository $repo, int $id): Response
    {
        if (!$this->isCsrfTokenValid('ann_delete_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_discord_announcements');
        }

        $ann = $repo->find($id);
        if ($ann) {
            $this->em->remove($ann);
            $this->em->flush();
            $this->addFlash('success', 'Annonce supprimee.');
        }

        return $this->redirectToRoute('admin_discord_announcements');
    }

    #[Route('/announcements/{id}/send', name: 'announcement_send', methods: ['POST'])]
    public function announcementSend(Request $request, DiscordAnnouncementRepository $repo, int $id): Response
    {
        if (!$this->isCsrfTokenValid('ann_send_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_discord_announcements');
        }

        $ann = $repo->find($id);
        if (!$ann || $ann->isSent()) {
            $this->addFlash('error', 'Annonce introuvable ou deja envoyee.');
            return $this->redirectToRoute('admin_discord_announcements');
        }

        $result = $this->botService->sendAnnouncement($ann->getChannelId(), $this->buildAnnouncementPayload($ann));

        if ($result && isset($result['messageId'])) {
            $ann->setSentAt(new \DateTimeImmutable());
            $ann->setDiscordMessageId($result['messageId']);
            $this->em->flush();
            $this->addFlash('success', 'Annonce envoyee sur Discord !');
        } else {
            $this->addFlash('error', 'Erreur lors de l\'envoi. Le bot est-il en ligne ?');
        }

        return $this->redirectToRoute('admin_discord_announcements');
    }

    private const ALLOWED_IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    private function handleAnnouncementForm(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('discord_announcement', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_discord_announcements');
        }

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/announcements';

        $ann = new DiscordAnnouncement();
        $ann->setTitle(mb_substr(trim($request->request->get('title', '')), 0, 256));
        $ann->setContent(trim($request->request->get('content', '')));
        $ann->setChannelId(trim($request->request->get('channel_id', '')));
        $ann->setType($request->request->get('type', DiscordAnnouncement::TYPE_ANNOUNCEMENT));
        $ann->setEmbedColor($request->request->get('embed_color') ?: '#45f882');
        $ann->setCreatedBy($this->getUser());

        // Process image upload (main embed image)
        $imageFile = $request->files->get('image_file');
        if ($imageFile && $imageFile->isValid() && in_array($imageFile->getMimeType(), self::ALLOWED_IMAGE_MIMES, true)) {
            $filename = 'img_' . uniqid() . '.' . $imageFile->guessExtension();
            $imageFile->move($uploadDir, $filename);
            $ann->setImageUrl('/uploads/announcements/' . $filename);
        } else {
            $ann->setImageUrl($request->request->get('image_url') ?: null);
        }

        // Parse embedData for patchnotes
        $embedDataJson = $request->request->get('embed_data');
        if ($embedDataJson && $ann->getType() === DiscordAnnouncement::TYPE_PATCHNOTE) {
            $embedData = json_decode($embedDataJson, true);
            if (is_array($embedData)) {
                // Sanitize sections
                if (isset($embedData['sections']) && is_array($embedData['sections'])) {
                    $embedData['sections'] = array_values(array_filter($embedData['sections'], function ($s) {
                        return is_array($s)
                            && !empty($s['label'])
                            && !empty($s['items'])
                            && is_array($s['items'])
                            && count(array_filter($s['items'], fn($i) => trim($i) !== '')) > 0;
                    }));
                    foreach ($embedData['sections'] as &$section) {
                        $section['items'] = array_values(array_filter($section['items'], fn($i) => trim($i) !== ''));
                        $section['label'] = mb_substr(trim($section['label'] ?? ''), 0, 100);
                        $section['emoji'] = mb_substr(trim($section['emoji'] ?? ''), 0, 10);
                        $section['type'] = mb_substr(trim($section['type'] ?? 'custom'), 0, 30);
                    }
                    unset($section);
                }
                // Sanitize scalar fields
                foreach (['version', 'authorName', 'authorIconUrl', 'thumbnailUrl', 'footerText', 'footerIconUrl'] as $key) {
                    if (isset($embedData[$key])) {
                        $embedData[$key] = mb_substr(trim($embedData[$key]), 0, 500);
                    }
                }

                // Process thumbnail upload
                $thumbFile = $request->files->get('thumbnail_file');
                if ($thumbFile && $thumbFile->isValid() && in_array($thumbFile->getMimeType(), self::ALLOWED_IMAGE_MIMES, true)) {
                    $thumbName = 'thumb_' . uniqid() . '.' . $thumbFile->guessExtension();
                    $thumbFile->move($uploadDir, $thumbName);
                    $embedData['thumbnailFile'] = $thumbName;
                }

                $ann->setEmbedData($embedData);
            }
        }

        $scheduledAt = $request->request->get('scheduled_at');
        if ($scheduledAt) {
            $ann->setScheduledAt(new \DateTimeImmutable($scheduledAt));
        }

        if (!$ann->getTitle() || !$ann->getContent() || !$ann->getChannelId()) {
            $this->addFlash('error', 'Titre, contenu et canal sont requis.');
            return $this->redirectToRoute('admin_discord_announcement_new');
        }

        $this->em->persist($ann);
        $this->em->flush();

        // If no scheduled date, send immediately
        if (!$scheduledAt) {
            $result = $this->botService->sendAnnouncement($ann->getChannelId(), $this->buildAnnouncementPayload($ann));

            if ($result && isset($result['messageId'])) {
                $ann->setSentAt(new \DateTimeImmutable());
                $ann->setDiscordMessageId($result['messageId']);
                $this->em->flush();
                $this->addFlash('success', 'Annonce envoyee sur Discord !');
            } else {
                $this->addFlash('warning', 'Annonce enregistree mais l\'envoi a echoue. Vous pouvez reessayer.');
            }
        } else {
            $this->addFlash('success', 'Annonce programmee.');
        }

        return $this->redirectToRoute('admin_discord_announcements');
    }

    private function buildAnnouncementPayload(DiscordAnnouncement $ann): array
    {
        $publicDir = $this->getParameter('kernel.project_dir') . '/public';

        $payload = [
            'title' => $ann->getTitle(),
            'content' => $ann->getContent(),
            'color' => $ann->getEmbedColor(),
            'type' => $ann->getType(),
        ];

        // Main image: local file → base64, or keep URL
        $imageUrl = $ann->getImageUrl();
        if ($imageUrl && str_starts_with($imageUrl, '/uploads/')) {
            $filePath = $publicDir . $imageUrl;
            if (file_exists($filePath)) {
                $payload['imageBase64'] = base64_encode(file_get_contents($filePath));
                $payload['imageName'] = basename($filePath);
            }
        } elseif ($imageUrl) {
            $payload['imageUrl'] = $imageUrl;
        }

        if ($ann->getEmbedData()) {
            $embedData = $ann->getEmbedData();

            // Thumbnail: local file → base64
            if (!empty($embedData['thumbnailFile'])) {
                $thumbPath = $publicDir . '/uploads/announcements/' . basename($embedData['thumbnailFile']);
                if (file_exists($thumbPath)) {
                    $embedData['thumbnailBase64'] = base64_encode(file_get_contents($thumbPath));
                    $embedData['thumbnailName'] = basename($thumbPath);
                }
            }

            $payload['embedData'] = $embedData;
        }

        return $payload;
    }

    // ===== INVITES =====

    #[Route('/invites', name: 'invites')]
    public function invites(DiscordInviteRepository $repo): Response
    {
        return $this->render('admin/discord/invites.html.twig', [
            'leaderboard' => $repo->getLeaderboard(),
            'recent' => $repo->findRecent(),
        ]);
    }

    // ===== AJAX: Member check =====

    #[Route('/member-check/{discordId}', name: 'member_check', methods: ['GET'])]
    public function memberCheck(string $discordId): JsonResponse
    {
        $isMember = $this->botService->isGuildMember($discordId);
        return $this->json(['isMember' => $isMember]);
    }
}
