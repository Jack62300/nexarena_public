<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Comment;
use App\Entity\GameCategory;
use App\Entity\Server;
use App\Entity\ServerCollaborator;
use App\Entity\ServerType;
use App\Entity\FeaturedBooking;
use App\Entity\Transaction;
use App\Entity\Tag;
use App\Repository\CategoryRepository;
use App\Repository\CommentRepository;
use App\Repository\FeaturedBookingRepository;
use App\Repository\RecruitmentListingRepository;
use App\Repository\ServerCollaboratorRepository;
use App\Repository\ServerRepository;
use App\Repository\TagRepository;
use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use App\Service\PremiumService;
use App\Service\ServerService;
use App\Service\SlugService;
use App\Service\ThemeService;
use App\Service\WebhookService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class UserServerController extends AbstractController
{
    public const PERMISSIONS = [
        'edit_info' => 'Modifier les informations',
        'edit_images' => 'Modifier les images',
        'edit_social' => 'Modifier les reseaux sociaux',
        'manage_webhooks' => 'Configurer les webhooks',
        'manage_theme' => 'Changer le theme',
        'manage_api' => 'Gerer la cle API',
        'manage_status' => 'Gerer le status serveur',
        'moderate_comments' => 'Moderer les commentaires',
        'manage_recruitment' => 'Gerer le recrutement',
        'delete_server' => 'Supprimer le serveur',
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private SlugService $slugService,
        private ServerService $serverService,
        private ThemeService $themeService,
        private PremiumService $premiumService,
        private ServerCollaboratorRepository $collabRepo,
        private UserRepository $userRepo,
        private WebhookService $webhookService,
        private TagRepository $tagRepo,
    ) {
    }

    // ──────────────────────────────────────────────
    // Helper methods
    // ──────────────────────────────────────────────

    private function isOwner(Server $server): bool
    {
        return $server->getOwner() === $this->getUser();
    }

    private function getCollaboration(Server $server): ?ServerCollaborator
    {
        $user = $this->getUser();
        if (!$user) {
            return null;
        }

        return $this->collabRepo->findByServerAndUser($server, $user);
    }

    private function requireAccess(Server $server, ?string $permission = null): void
    {
        if ($this->isOwner($server)) {
            return;
        }

        $collab = $this->getCollaboration($server);
        if (!$collab) {
            throw $this->createAccessDeniedException();
        }

        if ($permission !== null && !$collab->hasPermission($permission)) {
            throw $this->createAccessDeniedException();
        }
    }

    private function requireAccessAny(Server $server, array $permissions): void
    {
        if ($this->isOwner($server)) {
            return;
        }

        $collab = $this->getCollaboration($server);
        if (!$collab) {
            throw $this->createAccessDeniedException();
        }

        foreach ($permissions as $perm) {
            if ($collab->hasPermission($perm)) {
                return;
            }
        }

        throw $this->createAccessDeniedException();
    }

    private function hasPermission(Server $server, string $permission): bool
    {
        if ($this->isOwner($server)) {
            return true;
        }

        $collab = $this->getCollaboration($server);
        if (!$collab) {
            return false;
        }

        return $collab->hasPermission($permission);
    }

    // ──────────────────────────────────────────────
    // Routes
    // ──────────────────────────────────────────────

    #[Route('/mes-serveurs', name: 'user_servers_list')]
    public function list(ServerRepository $repo): Response
    {
        $user = $this->getUser();
        $servers = $repo->findByOwner($user);
        $collaborations = $this->collabRepo->findByUser($user);

        return $this->render('user/servers/list.html.twig', [
            'servers' => $servers,
            'collaborations' => $collaborations,
        ]);
    }

    #[Route('/serveur/ajouter', name: 'user_servers_new')]
    public function new(Request $request, CategoryRepository $categoryRepo): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('server_form', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('user_servers_new');
            }

            $server = new Server();
            $server->setOwner($this->getUser());
            $this->handleForm($server, $request);

            $this->em->persist($server);
            $this->em->flush();

            $this->webhookService->dispatch('server.created', [
                'title' => 'Nouveau serveur cree',
                'fields' => [
                    ['name' => 'Serveur', 'value' => $server->getName(), 'inline' => true],
                    ['name' => 'Proprietaire', 'value' => $this->getUser()->getUsername(), 'inline' => true],
                    ['name' => 'Categorie', 'value' => $server->getCategory() ? $server->getCategory()->getName() : '-', 'inline' => true],
                ],
            ]);

            $this->addFlash('success', 'Serveur cree avec succes ! Il sera visible apres approbation par un administrateur.');
            return $this->redirectToRoute('user_servers_manage', ['id' => $server->getId()]);
        }

        return $this->render('user/servers/form.html.twig', [
            'server' => null,
            'categories' => $categoryRepo->findBy(['isActive' => true], ['position' => 'ASC']),
            'tags' => $this->tagRepo->findAllActive(),
        ]);
    }

    #[Route('/serveur/{id}/modifier', name: 'user_servers_edit')]
    public function edit(Server $server, Request $request, CategoryRepository $categoryRepo): Response
    {
        $this->requireAccessAny($server, ['edit_info', 'edit_images', 'edit_social']);

        $canEditInfo = $this->hasPermission($server, 'edit_info');
        $canEditImages = $this->hasPermission($server, 'edit_images');
        $canEditSocial = $this->hasPermission($server, 'edit_social');

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('server_form', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('user_servers_edit', ['id' => $server->getId()]);
            }

            $this->handleForm($server, $request, $canEditInfo, $canEditImages, $canEditSocial);
            $this->em->flush();

            $this->addFlash('success', 'Serveur modifie avec succes.');
            return $this->redirectToRoute('user_servers_manage', ['id' => $server->getId()]);
        }

        return $this->render('user/servers/form.html.twig', [
            'server' => $server,
            'categories' => $categoryRepo->findBy(['isActive' => true], ['position' => 'ASC']),
            'can_edit_info' => $canEditInfo,
            'can_edit_images' => $canEditImages,
            'can_edit_social' => $canEditSocial,
            'tags' => $this->tagRepo->findAllActive(),
        ]);
    }

    #[Route('/serveur/{id}/gestion', name: 'user_servers_manage')]
    public function manage(Server $server, Request $request, CommentRepository $commentRepo, RecruitmentListingRepository $recruitmentRepo, FeaturedBookingRepository $bookingRepo, TransactionRepository $transactionRepo): Response
    {
        $allPerms = array_keys(self::PERMISSIONS);
        $this->requireAccessAny($server, $allPerms);

        $isOwner = $this->isOwner($server);

        if ($request->isMethod('POST')) {
            $action = $request->request->get('_action');

            if (!$this->isCsrfTokenValid('server_manage', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('user_servers_manage', ['id' => $server->getId()]);
            }

            if ($action === 'webhook') {
                $this->requireAccess($server, 'manage_webhooks');
                $server->setWebhookEnabled($request->request->getBoolean('webhook_enabled'));
                $server->setWebhookUrl($request->request->get('webhook_url') ?: null);
                $this->em->flush();
                $this->addFlash('success', 'Webhooks mis a jour.');
            }

            if ($action === 'status_check') {
                $this->requireAccess($server, 'manage_status');
                $server->setStatusCheckEnabled(!$server->isStatusCheckEnabled());
                $this->em->flush();
                $this->addFlash('success', $server->isStatusCheckEnabled()
                    ? 'Verification du status activee.'
                    : 'Verification du status desactivee.');
            }

            if ($action === 'banner') {
                $this->requireAccess($server, 'edit_images');
                /** @var UploadedFile|null $file */
                $file = $request->files->get('banner');
                if ($file) {
                    $filename = $this->serverService->processBanner($file);
                    if ($filename) {
                        if ($server->getBanner()) {
                            $this->serverService->deleteFile('servers/banners', $server->getBanner());
                        }
                        $server->setBanner($filename);
                        $this->em->flush();
                        $this->addFlash('success', 'Banniere mise a jour.');
                    } else {
                        $this->addFlash('error', 'Format d\'image non supporte ou fichier trop volumineux (max 5 Mo).');
                    }
                }
            }

            if ($action === 'page_template') {
                $this->requireAccess($server, 'manage_theme');
                $template = $request->request->get('page_template', 'default');
                if ($this->themeService->isValidTheme($template)) {
                    $server->setPageTemplate($template);
                    $this->em->flush();
                    $this->addFlash('success', 'Theme de la page mis a jour.');
                } else {
                    $this->addFlash('error', 'Theme invalide.');
                }
            }

            return $this->redirectToRoute('user_servers_manage', ['id' => $server->getId()]);
        }

        $comments = $commentRepo->findVisibleByServer($server);
        $collaborators = $isOwner ? $this->collabRepo->findByServer($server) : [];
        $canManageRecruitment = $this->hasPermission($server, 'manage_recruitment');
        $recruitmentListings = $canManageRecruitment ? $recruitmentRepo->findByServer($server) : [];

        $premiumEnabled = $this->premiumService->isPremiumEnabled();
        $serverBookings = $isOwner && $premiumEnabled ? $bookingRepo->findByServer($server) : [];
        $userTransactions = $isOwner && $premiumEnabled ? $transactionRepo->findByUser($this->getUser()) : [];

        $twitchSub = $this->premiumService->getTwitchSubscription($server);
        $twitchLiveGated = $this->premiumService->isFeatureGated('twitch_live');

        return $this->render('user/servers/manage.html.twig', [
            'server' => $server,
            'comments' => $comments,
            'themes' => $this->themeService->getAllThemes(),
            'is_owner' => $isOwner,
            'can_manage_api' => $this->hasPermission($server, 'manage_api'),
            'can_manage_webhooks' => $this->hasPermission($server, 'manage_webhooks'),
            'can_manage_status' => $this->hasPermission($server, 'manage_status'),
            'can_edit_images' => $this->hasPermission($server, 'edit_images'),
            'can_manage_theme' => $this->hasPermission($server, 'manage_theme'),
            'can_moderate_comments' => $this->hasPermission($server, 'moderate_comments'),
            'can_manage_recruitment' => $canManageRecruitment,
            'can_delete_server' => $this->hasPermission($server, 'delete_server'),
            'can_edit_info' => $this->hasPermission($server, 'edit_info'),
            'can_edit_social' => $this->hasPermission($server, 'edit_social'),
            'collaborators' => $collaborators,
            'permissions' => self::PERMISSIONS,
            'recruitment_listings' => $recruitmentListings,
            'server_bookings' => $serverBookings,
            'user_transactions' => $userTransactions,
            'premium_enabled' => $premiumEnabled,
            'has_premium_theme' => !$premiumEnabled || $this->premiumService->hasServerFeature($server, 'theme'),
            'has_premium_widget' => !$premiumEnabled || $this->premiumService->hasServerFeature($server, 'widget'),
            'theme_cost' => $this->premiumService->getFeatureCost('theme'),
            'widget_cost' => $this->premiumService->getFeatureCost('widget'),
            'twitch_sub' => $twitchSub,
            'twitch_live_gated' => $twitchLiveGated,
            'twitch_live_cost_tokens' => $this->premiumService->getTwitchLiveMonthlyTokenCost(),
            'twitch_live_cost_eur' => $this->premiumService->getTwitchLiveMonthlyEurPrice(),
        ]);
    }

    #[Route('/serveur/{id}/regenerate-token', name: 'user_servers_regenerate_token', methods: ['POST'])]
    public function regenerateToken(Server $server, Request $request): Response
    {
        $this->requireAccess($server, 'manage_api');

        if ($this->isCsrfTokenValid('regenerate_token', $request->request->get('_token'))) {
            $server->setApiToken(ServerService::generateToken());
            $this->em->flush();
            $this->addFlash('success', 'Cle API regeneree.');
        }

        return $this->redirectToRoute('user_servers_manage', ['id' => $server->getId()]);
    }

    #[Route('/serveur/{id}/supprimer', name: 'user_servers_delete', methods: ['POST'])]
    public function delete(Server $server, Request $request): Response
    {
        $this->requireAccess($server, 'delete_server');

        if ($this->isCsrfTokenValid('delete_server_' . $server->getId(), $request->request->get('_token'))) {
            if ($server->getBanner()) {
                $this->serverService->deleteFile('servers/banners', $server->getBanner());
            }
            if ($server->getPresentationImage()) {
                $this->serverService->deleteFile('servers/presentations', $server->getPresentationImage());
            }

            $this->em->remove($server);
            $this->em->flush();
            $this->addFlash('success', 'Serveur supprime.');
        }

        return $this->redirectToRoute('user_servers_list');
    }

    #[Route('/serveur/{id}/gestion/api-ips', name: 'user_servers_update_api_ips', methods: ['POST'])]
    public function updateApiIps(Server $server, Request $request): Response
    {
        $this->requireAccess($server, 'manage_api');

        if (!$this->isCsrfTokenValid('api_ips', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('user_servers_manage', ['id' => $server->getId()]);
        }

        $ip1 = trim((string) $request->request->get('allowed_ip_1'));
        $ip2 = trim((string) $request->request->get('allowed_ip_2'));

        $ips = [];
        foreach ([$ip1, $ip2] as $ip) {
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                $ips[] = $ip;
            } elseif ($ip !== '') {
                $this->addFlash('error', 'Adresse IP invalide : ' . $ip);
                return $this->redirectToRoute('user_servers_manage', ['id' => $server->getId()]);
            }
        }

        $server->setAllowedApiIps(!empty($ips) ? array_values(array_unique($ips)) : null);
        $this->em->flush();

        $this->addFlash('success', empty($ips)
            ? 'Restriction IP desactivee. L\'API est accessible depuis toutes les IPs.'
            : 'IPs autorisees mises a jour : ' . implode(', ', $ips));
        return $this->redirectToRoute('user_servers_manage', ['id' => $server->getId()]);
    }

    #[Route('/serveur/{id}/gestion/comment/{commentId}/flag', name: 'user_servers_flag_comment', methods: ['POST'])]
    public function flagComment(Server $server, int $commentId, Request $request, CommentRepository $commentRepo): Response
    {
        $this->requireAccess($server, 'moderate_comments');

        $comment = $commentRepo->find($commentId);
        if (!$comment || $comment->getServer() !== $server) {
            throw $this->createNotFoundException('Commentaire introuvable.');
        }

        if (!$this->isCsrfTokenValid('flag_comment_' . $comment->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('user_servers_manage', ['id' => $server->getId()]);
        }

        $reason = trim((string) $request->request->get('reason'));

        $comment->setIsFlagged(true);
        $comment->setFlaggedBy($this->getUser());
        $comment->setFlaggedAt(new \DateTimeImmutable());
        $comment->setFlagReason($reason ?: null);

        $this->em->flush();

        $this->addFlash('success', 'Le commentaire a ete signale aux moderateurs.');
        return $this->redirectToRoute('user_servers_manage', ['id' => $server->getId()]);
    }

    #[Route('/serveur/{id}/unlock-feature', name: 'user_servers_unlock_feature', methods: ['POST'])]
    public function unlockFeature(Server $server, Request $request): Response
    {
        $this->requireAccess($server);

        if (!$this->isCsrfTokenValid('unlock_feature', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('user_servers_manage', ['id' => $server->getId()]);
        }

        $feature = $request->request->get('feature', '');
        $validFeatures = ['theme', 'widget'];
        if (!in_array($feature, $validFeatures, true)) {
            $this->addFlash('error', 'Fonctionnalite invalide.');
            return $this->redirectToRoute('user_servers_manage', ['id' => $server->getId()]);
        }

        $user = $this->getUser();
        $result = $this->premiumService->unlockFeature($server, $user, $feature);

        if ($result) {
            $label = $feature === 'theme' ? 'Theme personnalise' : 'Widget personnalise';
            $this->addFlash('success', $label . ' debloque avec succes !');
        } else {
            $this->addFlash('error', 'NexBits insuffisants. Achetez des NexBits sur la page Premium.');
        }

        return $this->redirectToRoute('user_servers_manage', ['id' => $server->getId()]);
    }

    #[Route('/serveur/{id}/twitch-subscribe', name: 'user_servers_twitch_subscribe', methods: ['POST'])]
    public function twitchSubscribe(Server $server, Request $request): Response
    {
        $this->requireAccess($server);

        if (!$this->isCsrfTokenValid('twitch_subscribe', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('user_servers_manage', ['id' => $server->getId()]);
        }

        if (!$server->getTwitchChannel()) {
            $this->addFlash('error', 'Vous devez d\'abord renseigner votre chaine Twitch dans les informations du serveur.');
            return $this->redirectToRoute('user_servers_manage', ['id' => $server->getId()]);
        }

        $method = $request->request->get('payment_method', 'nexbits');
        $autoRenew = $request->request->getBoolean('auto_renew');
        $user = $this->getUser();

        if ($method === 'nexbits') {
            $result = $this->premiumService->subscribeTwitchLiveWithTokens($server, $user);
            if ($result) {
                $sub = $this->premiumService->getTwitchSubscription($server);
                if ($sub) {
                    $sub->setAutoRenew($autoRenew);
                    $this->em->flush();
                }
                $this->addFlash('success', 'Abonnement Twitch Live active pour 30 jours !');
            } else {
                $this->addFlash('error', 'NexBits insuffisants. Il vous faut ' . $this->premiumService->getTwitchLiveMonthlyTokenCost() . ' NexBits.');
            }
        }
        // PayPal flow is handled via AJAX in a separate route

        return $this->redirectToRoute('user_servers_manage', ['id' => $server->getId()]);
    }

    #[Route('/serveur/{id}/twitch-subscribe/paypal', name: 'user_servers_twitch_subscribe_paypal', methods: ['POST'])]
    public function twitchSubscribePaypal(Server $server, Request $request): Response
    {
        $this->requireAccess($server);

        $data = json_decode($request->getContent(), true);
        $paypalOrderId = $data['orderID'] ?? '';

        if (!$paypalOrderId || !$server->getTwitchChannel()) {
            return $this->json(['error' => 'Donnees invalides'], 400);
        }

        // Verify the PayPal order was captured
        if ($this->premiumService->isOrderAlreadyCaptured($paypalOrderId)) {
            return $this->json(['error' => 'Commande deja traitee'], 400);
        }

        $user = $this->getUser();
        $this->premiumService->subscribeTwitchLiveWithPaypal($server, $user, $paypalOrderId);

        return $this->json(['success' => true]);
    }

    #[Route('/serveur/{id}/twitch-cancel', name: 'user_servers_twitch_cancel', methods: ['POST'])]
    public function twitchCancel(Server $server, Request $request): Response
    {
        $this->requireAccess($server);

        if (!$this->isCsrfTokenValid('twitch_cancel', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('user_servers_manage', ['id' => $server->getId()]);
        }

        $this->premiumService->cancelTwitchLive($server);
        $this->addFlash('success', 'Abonnement Twitch Live annule. L\'acces reste actif jusqu\'a la date d\'expiration.');

        return $this->redirectToRoute('user_servers_manage', ['id' => $server->getId()]);
    }

    // ──────────────────────────────────────────────
    // Collaborator routes (owner only)
    // ──────────────────────────────────────────────

    #[Route('/serveur/{id}/gestion/collaborateurs/ajouter', name: 'user_servers_collab_add', methods: ['POST'])]
    public function addCollaborator(Server $server, Request $request): Response
    {
        if (!$this->isOwner($server)) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('collab_add', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('user_servers_manage', ['id' => $server->getId()]);
        }

        $username = trim((string) $request->request->get('username'));
        if ($username === '') {
            $this->addFlash('error', 'Veuillez saisir un nom d\'utilisateur.');
            return $this->redirectToRoute('user_servers_manage', ['id' => $server->getId()]);
        }

        $targetUser = $this->userRepo->findOneByUsernameInsensitive($username);
        if (!$targetUser) {
            $this->addFlash('error', 'Utilisateur "' . $username . '" introuvable.');
            return $this->redirectToRoute('user_servers_manage', ['id' => $server->getId()]);
        }

        if ($targetUser === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas vous ajouter vous-meme.');
            return $this->redirectToRoute('user_servers_manage', ['id' => $server->getId()]);
        }

        $existing = $this->collabRepo->findByServerAndUser($server, $targetUser);
        if ($existing) {
            $this->addFlash('error', 'Cet utilisateur est deja collaborateur de ce serveur.');
            return $this->redirectToRoute('user_servers_manage', ['id' => $server->getId()]);
        }

        $currentCollabs = $this->collabRepo->findByServer($server);
        if (count($currentCollabs) >= 10) {
            $this->addFlash('error', 'Vous ne pouvez pas avoir plus de 10 collaborateurs.');
            return $this->redirectToRoute('user_servers_manage', ['id' => $server->getId()]);
        }

        $permissions = $request->request->all('permissions');
        $validPerms = array_intersect($permissions, array_keys(self::PERMISSIONS));

        $collab = new ServerCollaborator();
        $collab->setServer($server);
        $collab->setUser($targetUser);
        $collab->setPermissions(array_values($validPerms));

        $this->em->persist($collab);
        $this->em->flush();

        $this->addFlash('success', $targetUser->getUsername() . ' a ete ajoute comme collaborateur.');
        return $this->redirectToRoute('user_servers_manage', ['id' => $server->getId()]);
    }

    #[Route('/serveur/{id}/gestion/collaborateurs/{collabId}/supprimer', name: 'user_servers_collab_remove', methods: ['POST'])]
    public function removeCollaborator(Server $server, int $collabId, Request $request): Response
    {
        if (!$this->isOwner($server)) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('collab_remove_' . $collabId, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('user_servers_manage', ['id' => $server->getId()]);
        }

        $collab = $this->collabRepo->find($collabId);
        if (!$collab || $collab->getServer() !== $server) {
            throw $this->createNotFoundException('Collaborateur introuvable.');
        }

        $this->em->remove($collab);
        $this->em->flush();

        $this->addFlash('success', 'Collaborateur retire.');
        return $this->redirectToRoute('user_servers_manage', ['id' => $server->getId()]);
    }

    #[Route('/serveur/{id}/gestion/collaborateurs/{collabId}/permissions', name: 'user_servers_collab_update', methods: ['POST'])]
    public function updateCollaboratorPermissions(Server $server, int $collabId, Request $request): Response
    {
        if (!$this->isOwner($server)) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('collab_perms_' . $collabId, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('user_servers_manage', ['id' => $server->getId()]);
        }

        $collab = $this->collabRepo->find($collabId);
        if (!$collab || $collab->getServer() !== $server) {
            throw $this->createNotFoundException('Collaborateur introuvable.');
        }

        $permissions = $request->request->all('permissions');
        $validPerms = array_intersect($permissions, array_keys(self::PERMISSIONS));
        $collab->setPermissions(array_values($validPerms));

        $this->em->flush();

        $this->addFlash('success', 'Permissions mises a jour.');
        return $this->redirectToRoute('user_servers_manage', ['id' => $server->getId()]);
    }

    #[Route('/serveur/{id}/boost/{bookingId}/cancel', name: 'user_servers_cancel_boost', methods: ['POST'])]
    public function cancelBoost(Server $server, int $bookingId, Request $request, FeaturedBookingRepository $bookingRepo): Response
    {
        if (!$this->isOwner($server)) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('cancel_boost_' . $bookingId, $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('user_servers_manage', ['id' => $server->getId()]);
        }

        $booking = $bookingRepo->find($bookingId);
        if (!$booking || $booking->getServer() !== $server) {
            $this->addFlash('danger', 'Reservation introuvable.');
            return $this->redirectToRoute('user_servers_manage', ['id' => $server->getId()]);
        }

        $now = new \DateTimeImmutable();
        if ($booking->getStartsAt() <= $now) {
            $this->addFlash('danger', 'Impossible d\'annuler une reservation deja commencee ou passee.');
            return $this->redirectToRoute('user_servers_manage', ['id' => $server->getId()]);
        }

        $refundAmount = $booking->getBoostTokensUsed();
        $user = $this->getUser();
        $user->addBoostTokens($refundAmount);

        $tx = new Transaction();
        $tx->setUser($user);
        $tx->setType(Transaction::TYPE_REFUND);
        $tx->setBoostTokensAmount($refundAmount);
        $tx->setTokensAmount(0);
        $tx->setDescription('Annulation boost ' . $server->getName() . ' (' . $booking->getStartsAt()->format('d/m/Y H:i') . ')');
        $this->em->persist($tx);

        $this->em->remove($booking);
        $this->em->flush();

        $this->addFlash('success', 'Reservation annulee. ' . $refundAmount . ' NexBoost recredites.');
        return $this->redirectToRoute('user_servers_manage', ['id' => $server->getId()]);
    }

    // ──────────────────────────────────────────────
    // Form handler
    // ──────────────────────────────────────────────

    private function handleForm(Server $server, Request $request, bool $canEditInfo = true, bool $canEditImages = true, bool $canEditSocial = true): void
    {
        if ($canEditInfo) {
            $name = $request->request->get('name', '');
            $server->setName($name);
            $server->setSlug($this->slugService->slugify($name));
            $server->setShortDescription($request->request->get('short_description', ''));
            $server->setFullDescription($request->request->get('full_description') ?: null);
            $server->setIsPrivate($request->request->getBoolean('is_private'));
            $server->setSlots((int) $request->request->get('slots', 0));

            // Connection
            $server->setIp($request->request->get('ip') ?: null);
            $server->setPort($request->request->get('port') ? (int) $request->request->get('port') : null);
            $server->setConnectUrl($request->request->get('connect_url') ?: null);

            // Category
            $categoryId = $request->request->get('category_id');
            if ($categoryId) {
                $category = $this->em->getRepository(Category::class)->find((int) $categoryId);
                $server->setCategory($category);
            }

            // Game category
            $gameCategoryId = $request->request->get('game_category_id');
            if ($gameCategoryId) {
                $gc = $this->em->getRepository(GameCategory::class)->find((int) $gameCategoryId);
                $server->setGameCategory($gc);
            } else {
                $server->setGameCategory(null);
            }

            // Server type
            $serverTypeId = $request->request->get('server_type_id');
            if ($serverTypeId) {
                $st = $this->em->getRepository(ServerType::class)->find((int) $serverTypeId);
                $server->setServerType($st);
            } else {
                $server->setServerType(null);
            }

            // Tags
            $server->clearTags();
            $tagIds = $request->request->all('tags');
            foreach ($tagIds as $tagId) {
                $tag = $this->em->getRepository(Tag::class)->find((int) $tagId);
                if ($tag && $tag->isActive()) {
                    $server->addTag($tag);
                }
            }
        }

        if ($canEditSocial) {
            $urlFields = ['website', 'discord_url', 'twitter_url', 'youtube_url', 'instagram_url'];
            $urlSetters = [
                'website' => 'setWebsite',
                'discord_url' => 'setDiscordUrl',
                'twitter_url' => 'setTwitterUrl',
                'youtube_url' => 'setYoutubeUrl',
                'instagram_url' => 'setInstagramUrl',
            ];
            foreach ($urlSetters as $field => $setter) {
                $value = $request->request->get($field) ?: null;
                if ($value && !preg_match('#^https?://#i', $value)) {
                    $value = null;
                }
                $server->$setter($value);
            }
            $server->setTwitchChannel($request->request->get('twitch_channel') ?: null);
        }

        if ($canEditImages) {
            /** @var UploadedFile|null $file */
            $file = $request->files->get('presentation_image');
            if ($file) {
                $filename = $this->serverService->processPresentation($file);
                if ($filename) {
                    if ($server->getPresentationImage()) {
                        $this->serverService->deleteFile('servers/presentations', $server->getPresentationImage());
                    }
                    $server->setPresentationImage($filename);
                }
            }
        }
    }
}
