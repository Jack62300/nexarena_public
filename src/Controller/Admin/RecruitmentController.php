<?php

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\RecruitmentListing;
use App\Repository\RecruitmentListingRepository;
use App\Service\ActivityLogService;
use App\Service\RecruitmentService;
use App\Service\WebhookService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/recruitment', name: 'admin_recruitment_')]
#[IsGranted('ROLE_EDITEUR')]
class RecruitmentController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private RecruitmentService $recruitmentService,
        private WebhookService $webhookService,
        private ActivityLogService $activityLog,
    ) {
    }

    #[Route('', name: 'list')]
    public function list(Request $request, RecruitmentListingRepository $repo): Response
    {
        $status = $request->query->get('status');
        $validStatuses = [
            RecruitmentListing::STATUS_DRAFT,
            RecruitmentListing::STATUS_PENDING,
            RecruitmentListing::STATUS_APPROVED,
            RecruitmentListing::STATUS_REVISION_REQUESTED,
            RecruitmentListing::STATUS_REJECTED,
        ];

        if ($status && !in_array($status, $validStatuses, true)) {
            $status = null;
        }

        return $this->render('admin/recruitment/list.html.twig', [
            'listings' => $repo->findForAdmin($status),
            'currentStatus' => $status,
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(RecruitmentListing $listing): Response
    {
        return $this->render('admin/recruitment/show.html.twig', [
            'listing' => $listing,
        ]);
    }

    #[Route('/{id}/approve', name: 'approve', methods: ['POST'])]
    #[IsGranted('recruitment.moderate')]
    public function approve(RecruitmentListing $listing, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('recruitment_action_' . $listing->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_recruitment_show', ['id' => $listing->getId()]);
        }

        $listing->setStatus(RecruitmentListing::STATUS_APPROVED);
        $listing->setApprovedBy($this->getUser());
        $listing->setApprovedAt(new \DateTimeImmutable());
        $listing->setRevisionReason(null);
        $listing->setRejectionReason(null);
        $this->em->flush();

        $this->activityLog->log('recruitment.approve', ActivityLog::CAT_RECRUITMENT, 'RecruitmentListing', $listing->getId(), $listing->getTitle(), [
            'server' => $listing->getServer()->getName(),
        ]);

        $this->webhookService->dispatch('recruitment.approved', [
            'title' => 'Annonce approuvee',
            'fields' => [
                ['name' => 'Annonce', 'value' => $listing->getTitle(), 'inline' => true],
                ['name' => 'Serveur', 'value' => $listing->getServer()->getName(), 'inline' => true],
                ['name' => 'Approuvee par', 'value' => $this->getUser()->getUsername(), 'inline' => true],
            ],
        ]);

        $this->addFlash('success', 'L\'annonce a ete approuvee.');
        return $this->redirectToRoute('admin_recruitment_show', ['id' => $listing->getId()]);
    }

    #[Route('/{id}/revision', name: 'revision', methods: ['POST'])]
    #[IsGranted('recruitment.moderate')]
    public function revision(RecruitmentListing $listing, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('recruitment_action_' . $listing->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_recruitment_show', ['id' => $listing->getId()]);
        }

        $reason = trim((string) $request->request->get('reason'));
        if ($reason === '') {
            $this->addFlash('error', 'Veuillez indiquer une raison.');
            return $this->redirectToRoute('admin_recruitment_show', ['id' => $listing->getId()]);
        }

        $listing->setStatus(RecruitmentListing::STATUS_REVISION_REQUESTED);
        $listing->setRevisionReason($reason);
        $this->em->flush();

        $this->activityLog->log('recruitment.revision', ActivityLog::CAT_RECRUITMENT, 'RecruitmentListing', $listing->getId(), $listing->getTitle(), [
            'reason' => $reason,
        ]);

        $this->webhookService->dispatch('recruitment.revision_requested', [
            'title' => 'Revision demandee',
            'fields' => [
                ['name' => 'Annonce',       'value' => $listing->getTitle(),            'inline' => true],
                ['name' => 'Serveur',       'value' => $listing->getServer()->getName(), 'inline' => true],
                ['name' => 'Demandee par',  'value' => $this->getUser()->getUsername(),  'inline' => true],
                ['name' => 'Raison',        'value' => mb_substr($reason, 0, 200),       'inline' => false],
            ],
        ]);

        $this->addFlash('success', 'Demande de revision envoyee.');
        return $this->redirectToRoute('admin_recruitment_show', ['id' => $listing->getId()]);
    }

    #[Route('/{id}/reject', name: 'reject', methods: ['POST'])]
    #[IsGranted('recruitment.moderate')]
    public function reject(RecruitmentListing $listing, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('recruitment_action_' . $listing->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_recruitment_show', ['id' => $listing->getId()]);
        }

        $reason = trim((string) $request->request->get('reason'));
        if ($reason === '') {
            $this->addFlash('error', 'Veuillez indiquer une raison.');
            return $this->redirectToRoute('admin_recruitment_show', ['id' => $listing->getId()]);
        }

        $listing->setStatus(RecruitmentListing::STATUS_REJECTED);
        $listing->setRejectionReason($reason);
        $this->em->flush();

        $this->activityLog->log('recruitment.reject', ActivityLog::CAT_RECRUITMENT, 'RecruitmentListing', $listing->getId(), $listing->getTitle(), [
            'reason' => $reason,
        ]);

        $this->webhookService->dispatch('recruitment.rejected', [
            'title' => 'Annonce rejetee',
            'fields' => [
                ['name' => 'Annonce',     'value' => $listing->getTitle(),            'inline' => true],
                ['name' => 'Serveur',     'value' => $listing->getServer()->getName(), 'inline' => true],
                ['name' => 'Rejete par',  'value' => $this->getUser()->getUsername(),  'inline' => true],
                ['name' => 'Raison',      'value' => mb_substr($reason, 0, 200),       'inline' => false],
            ],
        ]);

        $this->addFlash('success', 'L\'annonce a ete rejetee.');
        return $this->redirectToRoute('admin_recruitment_show', ['id' => $listing->getId()]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('recruitment.moderate')]
    public function delete(RecruitmentListing $listing, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('recruitment_delete_' . $listing->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_recruitment_list');
        }

        // Clean up images
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

        $this->activityLog->log('recruitment.delete', ActivityLog::CAT_RECRUITMENT, 'RecruitmentListing', $listingId, $title);

        $this->addFlash('success', 'L\'annonce a ete supprimee.');
        return $this->redirectToRoute('admin_recruitment_list');
    }
}
