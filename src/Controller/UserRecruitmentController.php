<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Entity\Notification;
use App\Entity\RecruitmentApplication;
use App\Entity\RecruitmentListing;
use App\Entity\RecruitmentMessage;
use App\Entity\Server;
use App\Repository\RecruitmentApplicationRepository;
use App\Repository\RecruitmentListingRepository;
use App\Repository\RecruitmentMessageRepository;
use App\Repository\ServerCollaboratorRepository;
use App\Repository\ServerRepository;
use App\Service\ActivityLogService;
use App\Service\NotificationService;
use App\Service\PremiumService;
use App\Service\RecruitmentService;
use App\Service\SlugService;
use App\Service\WebhookService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class UserRecruitmentController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SlugService $slugService,
        private RecruitmentService $recruitmentService,
        private PremiumService $premiumService,
        private ServerCollaboratorRepository $collabRepo,
        private NotificationService $notificationService,
        private WebhookService $webhookService,
        private ActivityLogService $activityLog,
    ) {
    }

    private function canManageServerRecruitment(Server $server): bool
    {
        $user = $this->getUser();
        if ($server->getOwner() === $user) {
            return true;
        }

        $collab = $this->collabRepo->findByServerAndUser($server, $user);
        return $collab && $collab->hasPermission('manage_recruitment');
    }

    private function canManageListing(RecruitmentListing $listing): bool
    {
        $user = $this->getUser();
        // Author always has access
        if ($listing->getAuthor() === $user) {
            return true;
        }
        // If linked to a server, check collaborator permission
        $server = $listing->getServer();
        if ($server) {
            $collab = $this->collabRepo->findByServerAndUser($server, $user);
            if ($collab && $collab->hasPermission('manage_recruitment')) {
                return true;
            }
        }

        return false;
    }

    private function requireRecruitmentAccess(RecruitmentListing $listing): void
    {
        if (!$this->canManageListing($listing)) {
            throw $this->createAccessDeniedException();
        }
    }

    #[Route('/mes-recrutements', name: 'user_recruitment_list')]
    public function list(RecruitmentListingRepository $repo): Response
    {
        $user = $this->getUser();
        $listings = $repo->findByAuthor($user);

        return $this->render('user/recruitment/list.html.twig', [
            'listings' => $listings,
        ]);
    }

    #[Route('/mes-recrutements/creer', name: 'user_recruitment_new')]
    public function new(Request $request): Response
    {
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('recruitment_form', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('user_recruitment_new');
            }

            $listing = new RecruitmentListing();
            $listing->setServer(null);
            $listing->setAuthor($user);
            $this->handleForm($listing, $request);

            $this->em->persist($listing);
            $this->em->flush();

            $this->addFlash('success', 'Annonce creee en brouillon. Configurez le formulaire puis soumettez-la pour validation.');
            return $this->redirectToRoute('user_recruitment_form_builder', ['id' => $listing->getId()]);
        }

        return $this->render('user/recruitment/form.html.twig', [
            'listing' => null,
            'server' => null,
        ]);
    }

    #[Route('/mes-recrutements/creer/{serverId}', name: 'user_recruitment_new_for_server', requirements: ['serverId' => '\d+'])]
    public function newForServer(int $serverId, Request $request, ServerRepository $serverRepo): Response
    {
        $server = $serverRepo->find($serverId);
        if (!$server || !$this->canManageServerRecruitment($server)) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->getUser();

        // Premium check: recruitment limit
        if ($this->premiumService->isPremiumEnabled()) {
            $recruitmentCheck = $this->premiumService->canCreateRecruitment($server, $user);
            if (!$recruitmentCheck['allowed']) {
                $this->addFlash('error', $recruitmentCheck['reason']);
                return $this->redirectToRoute('user_servers_manage', ['id' => $serverId]);
            }
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('recruitment_form', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('user_recruitment_new_for_server', ['serverId' => $serverId]);
            }

            // Charge tokens for extra recruitment if needed
            if ($this->premiumService->isPremiumEnabled()) {
                $check = $this->premiumService->canCreateRecruitment($server, $user);
                if ($check['cost'] > 0) {
                    $this->premiumService->chargeRecruitmentExtra($user, $server);
                }
            }

            $listing = new RecruitmentListing();
            $listing->setServer($server);
            $listing->setAuthor($user);
            $this->handleForm($listing, $request);

            $this->em->persist($listing);
            $this->em->flush();

            $this->addFlash('success', 'Annonce creee en brouillon. Configurez le formulaire puis soumettez-la pour validation.');
            return $this->redirectToRoute('user_recruitment_form_builder', ['id' => $listing->getId()]);
        }

        return $this->render('user/recruitment/form.html.twig', [
            'listing' => null,
            'server' => $server,
        ]);
    }

    #[Route('/mes-recrutements/{id}/modifier', name: 'user_recruitment_edit', requirements: ['id' => '\d+'])]
    public function edit(RecruitmentListing $listing, Request $request): Response
    {
        $this->requireRecruitmentAccess($listing);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('recruitment_form', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('user_recruitment_edit', ['id' => $listing->getId()]);
            }

            $this->handleForm($listing, $request);
            $this->em->flush();

            $this->addFlash('success', 'Annonce modifiee avec succes.');
            return $this->redirectToRoute('user_recruitment_list');
        }

        return $this->render('user/recruitment/form.html.twig', [
            'listing' => $listing,
            'server' => $listing->getServer(),
        ]);
    }

    #[Route('/mes-recrutements/{id}/soumettre', name: 'user_recruitment_submit', methods: ['POST'])]
    public function submit(RecruitmentListing $listing, Request $request): Response
    {
        $this->requireRecruitmentAccess($listing);

        if (!$this->isCsrfTokenValid('recruitment_submit_' . $listing->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('user_recruitment_list');
        }

        if (!in_array($listing->getStatus(), [RecruitmentListing::STATUS_DRAFT, RecruitmentListing::STATUS_REVISION_REQUESTED], true)) {
            $this->addFlash('error', 'Cette annonce ne peut pas etre soumise dans son etat actuel.');
            return $this->redirectToRoute('user_recruitment_list');
        }

        $listing->setStatus(RecruitmentListing::STATUS_PENDING);
        $listing->setRevisionReason(null);
        $this->em->flush();

        $this->webhookService->dispatch('recruitment.submitted', [
            'title' => 'Annonce soumise',
            'fields' => [
                ['name' => 'Annonce', 'value' => $listing->getTitle(), 'inline' => true],
                ['name' => 'Serveur', 'value' => $listing->getServer()?->getName() ?? 'Annonce libre', 'inline' => true],
                ['name' => 'Auteur', 'value' => $this->getUser()->getUsername(), 'inline' => true],
            ],
        ]);

        $this->addFlash('success', 'Annonce soumise pour validation. Un administrateur la examinera prochainement.');
        return $this->redirectToRoute('user_recruitment_list');
    }

    #[Route('/mes-recrutements/{id}/formulaire', name: 'user_recruitment_form_builder', requirements: ['id' => '\d+'])]
    public function formBuilder(RecruitmentListing $listing, Request $request): Response
    {
        $this->requireRecruitmentAccess($listing);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('recruitment_form_builder', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('user_recruitment_form_builder', ['id' => $listing->getId()]);
            }

            $fieldsJson = $request->request->get('form_fields', '[]');
            $fields = json_decode($fieldsJson, true);

            if (!is_array($fields)) {
                $this->addFlash('error', 'Donnees de formulaire invalides.');
                return $this->redirectToRoute('user_recruitment_form_builder', ['id' => $listing->getId()]);
            }

            $validated = $this->recruitmentService->validateFormFields($fields);
            $listing->setFormFields($validated);
            $this->em->flush();

            $this->addFlash('success', 'Formulaire mis a jour (' . count($validated) . ' champ' . (count($validated) > 1 ? 's' : '') . ').');
            return $this->redirectToRoute('user_recruitment_list');
        }

        return $this->render('user/recruitment/form_builder.html.twig', [
            'listing' => $listing,
        ]);
    }

    #[Route('/mes-recrutements/{id}/candidatures', name: 'user_recruitment_applications', requirements: ['id' => '\d+'])]
    public function applications(RecruitmentListing $listing, RecruitmentApplicationRepository $appRepo): Response
    {
        $this->requireRecruitmentAccess($listing);

        return $this->render('user/recruitment/applications.html.twig', [
            'listing' => $listing,
            'applications' => $appRepo->findByListing($listing),
        ]);
    }

    #[Route('/mes-recrutements/{id}/candidatures/{appId}', name: 'user_recruitment_application_detail', requirements: ['id' => '\d+', 'appId' => '\d+'])]
    public function applicationDetail(RecruitmentListing $listing, int $appId, RecruitmentApplicationRepository $appRepo): Response
    {
        $this->requireRecruitmentAccess($listing);

        $application = $appRepo->find($appId);
        if (!$application || $application->getListing() !== $listing) {
            throw $this->createNotFoundException('Candidature introuvable.');
        }

        // Auto mark as read
        if (!$application->isRead()) {
            $application->setIsRead(true);
            $this->em->flush();
        }

        return $this->render('user/recruitment/application_detail.html.twig', [
            'listing' => $listing,
            'application' => $application,
        ]);
    }

    #[Route('/mes-recrutements/{id}/candidatures/{appId}/read', name: 'user_recruitment_application_read', methods: ['POST'])]
    public function markRead(RecruitmentListing $listing, int $appId, Request $request, RecruitmentApplicationRepository $appRepo): Response
    {
        $this->requireRecruitmentAccess($listing);

        $application = $appRepo->find($appId);
        if (!$application || $application->getListing() !== $listing) {
            throw $this->createNotFoundException('Candidature introuvable.');
        }

        if ($this->isCsrfTokenValid('app_read_' . $appId, $request->request->get('_token'))) {
            $application->setIsRead(true);
            $this->em->flush();
        }

        return $this->redirectToRoute('user_recruitment_applications', ['id' => $listing->getId()]);
    }

    #[Route('/mes-recrutements/{id}/supprimer', name: 'user_recruitment_delete', methods: ['POST'])]
    public function delete(RecruitmentListing $listing, Request $request): Response
    {
        $this->requireRecruitmentAccess($listing);

        if (!$this->isCsrfTokenValid('recruitment_delete_' . $listing->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('user_recruitment_list');
        }

        if ($listing->getImage1()) {
            $this->recruitmentService->deleteImage($listing->getImage1());
        }
        if ($listing->getImage2()) {
            $this->recruitmentService->deleteImage($listing->getImage2());
        }

        $title = $listing->getTitle();
        $listingId = $listing->getId();
        $this->em->remove($listing);
        $this->em->flush();

        $this->activityLog->log('recruitment.delete_self', ActivityLog::CAT_RECRUITMENT, 'RecruitmentListing', $listingId, $title);

        $this->addFlash('success', 'Annonce supprimee.');
        return $this->redirectToRoute('user_recruitment_list');
    }

    // ── Accept/Reject/Chat ──

    private function resolveApplication(int $id, int $appId, RecruitmentApplicationRepository $appRepo): array
    {
        $listing = $this->em->getRepository(RecruitmentListing::class)->find($id);
        if (!$listing) {
            throw $this->createNotFoundException();
        }
        $this->requireRecruitmentAccess($listing);

        $application = $appRepo->find($appId);
        if (!$application || $application->getListing() !== $listing) {
            throw $this->createNotFoundException('Candidature introuvable.');
        }

        return [$listing, $application];
    }

    #[Route('/mes-recrutements/{id}/candidatures/{appId}/accept', name: 'user_recruitment_application_accept', methods: ['POST'], requirements: ['id' => '\d+', 'appId' => '\d+'])]
    public function acceptApplication(int $id, int $appId, Request $request, RecruitmentApplicationRepository $appRepo): Response
    {
        [$listing, $application] = $this->resolveApplication($id, $appId, $appRepo);

        if (!$this->isCsrfTokenValid('app_review_' . $appId, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('user_recruitment_application_detail', ['id' => $id, 'appId' => $appId]);
        }

        $comment = trim((string) $request->request->get('comment'));
        $application->setStatus(RecruitmentApplication::STATUS_ACCEPTED);
        $application->setStatusComment($comment !== '' ? mb_substr($comment, 0, 2000) : null);
        $application->setReviewedBy($this->getUser());
        $application->setReviewedAt(new \DateTimeImmutable());
        $this->em->flush();

        $this->webhookService->dispatch('recruitment.application_accepted', [
            'title' => 'Candidature acceptee',
            'fields' => [
                ['name' => 'Annonce',       'value' => $listing->getTitle(),              'inline' => true],
                ['name' => 'Candidat',      'value' => $application->getApplicantName(),  'inline' => true],
                ['name' => 'Accepte par',   'value' => $this->getUser()->getUsername(),   'inline' => true],
            ],
        ]);

        // Notify applicant
        if ($application->getApplicantUser()) {
            $this->notificationService->create(
                $application->getApplicantUser(),
                Notification::TYPE_APPLICATION_STATUS,
                'Candidature acceptee',
                'Votre candidature pour "' . $listing->getTitle() . '" a ete acceptee !',
                $this->generateUrl('applicant_show', ['id' => $application->getId()])
            );
        }

        $this->addFlash('success', 'Candidature acceptee.');
        return $this->redirectToRoute('user_recruitment_application_detail', ['id' => $id, 'appId' => $appId]);
    }

    #[Route('/mes-recrutements/{id}/candidatures/{appId}/reject', name: 'user_recruitment_application_reject', methods: ['POST'], requirements: ['id' => '\d+', 'appId' => '\d+'])]
    public function rejectApplication(int $id, int $appId, Request $request, RecruitmentApplicationRepository $appRepo): Response
    {
        [$listing, $application] = $this->resolveApplication($id, $appId, $appRepo);

        if (!$this->isCsrfTokenValid('app_review_' . $appId, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('user_recruitment_application_detail', ['id' => $id, 'appId' => $appId]);
        }

        $comment = trim((string) $request->request->get('comment'));
        $application->setStatus(RecruitmentApplication::STATUS_REJECTED);
        $application->setStatusComment($comment !== '' ? mb_substr($comment, 0, 2000) : null);
        $application->setReviewedBy($this->getUser());
        $application->setReviewedAt(new \DateTimeImmutable());
        $this->em->flush();

        $this->webhookService->dispatch('recruitment.application_rejected', [
            'title' => 'Candidature refusee',
            'fields' => [
                ['name' => 'Annonce',      'value' => $listing->getTitle(),              'inline' => true],
                ['name' => 'Candidat',     'value' => $application->getApplicantName(),  'inline' => true],
                ['name' => 'Refuse par',   'value' => $this->getUser()->getUsername(),   'inline' => true],
            ],
        ]);

        // Notify applicant
        if ($application->getApplicantUser()) {
            $this->notificationService->create(
                $application->getApplicantUser(),
                Notification::TYPE_APPLICATION_STATUS,
                'Candidature refusee',
                'Votre candidature pour "' . $listing->getTitle() . '" a ete refusee.',
                $this->generateUrl('applicant_show', ['id' => $application->getId()])
            );
        }

        $this->addFlash('success', 'Candidature refusee.');
        return $this->redirectToRoute('user_recruitment_application_detail', ['id' => $id, 'appId' => $appId]);
    }

    #[Route('/mes-recrutements/{id}/candidatures/{appId}/chat/enable', name: 'user_recruitment_application_chat_enable', methods: ['POST'], requirements: ['id' => '\d+', 'appId' => '\d+'])]
    public function enableChat(int $id, int $appId, Request $request, RecruitmentApplicationRepository $appRepo): Response
    {
        [$listing, $application] = $this->resolveApplication($id, $appId, $appRepo);

        if (!$this->isCsrfTokenValid('app_chat_enable_' . $appId, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('user_recruitment_application_detail', ['id' => $id, 'appId' => $appId]);
        }

        $application->setChatEnabled(true);
        $this->em->flush();

        // Notify applicant
        if ($application->getApplicantUser()) {
            $this->notificationService->create(
                $application->getApplicantUser(),
                Notification::TYPE_NEW_MESSAGE,
                'Chat active',
                'Le gestionnaire de "' . $listing->getTitle() . '" a active le chat sur votre candidature.',
                $this->generateUrl('applicant_show', ['id' => $application->getId()])
            );
        }

        $this->addFlash('success', 'Chat active pour cette candidature.');
        return $this->redirectToRoute('user_recruitment_application_detail', ['id' => $id, 'appId' => $appId]);
    }

    // ── Chat API (manager side) ──

    #[Route('/api/recruitment/chat/{appId}/messages', name: 'api_recruitment_chat_messages', methods: ['GET'], requirements: ['appId' => '\d+'])]
    public function chatMessages(int $appId, Request $request, RecruitmentApplicationRepository $appRepo, RecruitmentMessageRepository $msgRepo): JsonResponse
    {
        $application = $appRepo->find($appId);
        if (!$application) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        $listing = $application->getListing();
        if (!$this->canManageListing($listing)) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        if (!$application->isChatEnabled()) {
            return new JsonResponse(['error' => 'Chat disabled'], 403);
        }

        $afterId = (int) $request->query->get('after', 0);
        $messages = $afterId > 0
            ? $msgRepo->findNewMessages($application, $afterId)
            : $msgRepo->findByApplication($application);

        return new JsonResponse($this->formatMessages($messages));
    }

    #[Route('/api/recruitment/chat/{appId}/send', name: 'api_recruitment_chat_send', methods: ['POST'], requirements: ['appId' => '\d+'])]
    public function chatSend(int $appId, Request $request, RecruitmentApplicationRepository $appRepo): JsonResponse
    {
        $application = $appRepo->find($appId);
        if (!$application) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        $listing = $application->getListing();
        if (!$this->canManageListing($listing)) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        if (!$application->isChatEnabled()) {
            return new JsonResponse(['error' => 'Chat disabled'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $content = trim((string) ($data['content'] ?? ''));
        if ($content === '' || mb_strlen($content) > 2000) {
            return new JsonResponse(['error' => 'Message invalide (1-2000 caracteres)'], 422);
        }

        $msg = new RecruitmentMessage();
        $msg->setApplication($application);
        $msg->setSender($this->getUser());
        $msg->setContent($content);
        $this->em->persist($msg);
        $this->em->flush();

        // Notify applicant
        if ($application->getApplicantUser() && $application->getApplicantUser()->getId() !== $this->getUser()->getId()) {
            $this->notificationService->create(
                $application->getApplicantUser(),
                Notification::TYPE_NEW_MESSAGE,
                'Nouveau message',
                'Vous avez recu un message concernant votre candidature pour "' . $listing->getTitle() . '".',
                $this->generateUrl('applicant_show', ['id' => $application->getId()])
            );
        }

        return new JsonResponse($this->formatMessage($msg));
    }

    private function formatMessages(array $messages): array
    {
        return array_map(fn(RecruitmentMessage $m) => $this->formatMessage($m), $messages);
    }

    private function formatMessage(RecruitmentMessage $m): array
    {
        return [
            'id' => $m->getId(),
            'senderId' => $m->getSender()->getId(),
            'senderName' => $m->getSender()->getUsername(),
            'senderAvatar' => $m->getSender()->getAvatar(),
            'content' => $m->getContent(),
            'createdAt' => $m->getCreatedAt()->format('d/m/Y H:i'),
        ];
    }

    private function handleForm(RecruitmentListing $listing, Request $request): void
    {
        $title = trim((string) $request->request->get('title'));
        $listing->setTitle($title);
        // Only generate slug on creation — editing the title must NOT change the URL (SEO stability)
        if ($listing->getId() === null) {
            $listing->setSlug($this->slugService->uniqueSlugify($title, function (string $s) {
                return $this->em->getRepository(RecruitmentListing::class)->findOneBy(['slug' => $s]) !== null;
            }));
        }
        $listing->setDescription($request->request->get('description', ''));
        $listing->setRequiresLogin($request->request->getBoolean('requires_login'));

        /** @var UploadedFile|null $img1 */
        $img1 = $request->files->get('image1');
        if ($img1) {
            $filename = $this->recruitmentService->processImage($img1);
            if ($filename) {
                if ($listing->getImage1()) {
                    $this->recruitmentService->deleteImage($listing->getImage1());
                }
                $listing->setImage1($filename);
            } else {
                // Flash will be shown but form continues
            }
        }

        /** @var UploadedFile|null $img2 */
        $img2 = $request->files->get('image2');
        if ($img2) {
            $filename = $this->recruitmentService->processImage($img2);
            if ($filename) {
                if ($listing->getImage2()) {
                    $this->recruitmentService->deleteImage($listing->getImage2());
                }
                $listing->setImage2($filename);
            }
        }
    }
}
