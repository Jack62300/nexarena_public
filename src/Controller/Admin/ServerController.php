<?php

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\Server;
use App\Form\Admin\AdminServerFormType;
use App\Repository\CategoryRepository;
use App\Repository\ServerRepository;
use App\Service\ActivityLogService;
use App\Service\MailerService;
use App\Service\ServerService;
use App\Service\WebhookService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/servers', name: 'admin_servers_')]
#[IsGranted('servers.list')]
class ServerController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ServerService $serverService,
        private WebhookService $webhookService,
        private ActivityLogService $activityLog,
        private MailerService $mailerService,
        private LoggerInterface $logger,
    ) {
    }

    #[Route('', name: 'list')]
    public function list(Request $request, ServerRepository $repo, CategoryRepository $categoryRepo): Response
    {
        $categoryId = $request->query->get('category');
        $isApproved = $request->query->get('approved');
        $isActive = $request->query->get('active');

        $category = $categoryId ? $categoryRepo->find((int) $categoryId) : null;
        $approved = $isApproved !== null && $isApproved !== '' ? (bool) $isApproved : null;
        $active = $isActive !== null && $isActive !== '' ? (bool) $isActive : null;

        return $this->render('admin/servers/list.html.twig', [
            'servers' => $repo->findAllForAdmin($category, $approved, $active),
            'categories' => $categoryRepo->findBy([], ['position' => 'ASC']),
            'filters' => [
                'category' => $categoryId,
                'approved' => $isApproved,
                'active' => $isActive,
            ],
        ]);
    }

    #[Route('/{id}/edit', name: 'edit')]
    #[IsGranted('servers.edit')]
    public function edit(Server $server, Request $request, CategoryRepository $categoryRepo): Response
    {
        $form = $this->createForm(AdminServerFormType::class, $server);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Slug is intentionally NOT regenerated on edit (SEO: stable URLs)
            $this->em->flush();

            $this->activityLog->log('server.edit', ActivityLog::CAT_SERVER, 'Server', $server->getId(), $server->getName());

            $this->addFlash('success', 'Serveur modifié avec succès.');
            return $this->redirectToRoute('admin_servers_list');
        }

        return $this->render('admin/servers/edit.html.twig', [
            'server' => $server,
            'form' => $form,
            'categories' => $categoryRepo->findBy([], ['position' => 'ASC']),
        ]);
    }

    #[Route('/{id}/approve', name: 'approve', methods: ['POST'])]
    #[IsGranted('servers.edit')]
    public function approve(Server $server, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('approve_' . $server->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_servers_list');
        }

        $server->setIsApproved(true);
        $this->em->flush();

        $this->webhookService->dispatch('server.approved', [
            'title' => 'Serveur approuvé',
            'fields' => [
                ['name' => 'Serveur', 'value' => $server->getName(), 'inline' => true],
                ['name' => 'Approuvé par', 'value' => $this->getUser()->getUsername(), 'inline' => true],
            ],
        ]);

        $this->activityLog->log('server.approve', ActivityLog::CAT_SERVER, 'Server', $server->getId(), $server->getName(), [
            'approved' => true,
        ]);

        $owner = $server->getOwner();
        if ($owner && $owner->getEmail()) {
            try {
                $this->mailerService->sendServerApprovedEmail($owner, $server);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to send server approved email', [
                    'server' => $server->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->addFlash('success', 'Serveur approuvé.');
        return $this->redirectToRoute('admin_servers_list');
    }

    #[Route('/{id}/reject', name: 'reject', methods: ['POST'])]
    #[IsGranted('servers.edit')]
    public function reject(Server $server, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('reject_' . $server->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_servers_list');
        }

        $reason = trim($request->request->get('rejection_reason', ''));
        if ($reason === '') {
            $this->addFlash('error', 'Vous devez indiquer une raison pour le refus du serveur.');
            return $this->redirectToRoute('admin_servers_list');
        }

        $server->setIsApproved(false);
        $this->em->flush();

        $this->webhookService->dispatch('server.rejected', [
            'title' => 'Serveur refusé',
            'fields' => [
                ['name' => 'Serveur', 'value' => $server->getName(), 'inline' => true],
                ['name' => 'Refusé par', 'value' => $this->getUser()->getUsername(), 'inline' => true],
                ['name' => 'Raison', 'value' => mb_substr($reason, 0, 200), 'inline' => false],
            ],
        ]);

        $this->activityLog->log('server.reject', ActivityLog::CAT_SERVER, 'Server', $server->getId(), $server->getName(), [
            'approved' => false,
            'reason' => $reason,
        ]);

        $owner = $server->getOwner();
        if ($owner && $owner->getEmail()) {
            try {
                $this->mailerService->sendServerRejectedEmail($owner, $server, $reason);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to send server rejected email', [
                    'server' => $server->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->addFlash('success', 'Serveur refusé. Le propriétaire a été notifié par email.');
        return $this->redirectToRoute('admin_servers_list');
    }

    #[Route('/{id}/toggle-active', name: 'toggle_active', methods: ['POST'])]
    #[IsGranted('servers.edit')]
    public function toggleActive(Server $server, Request $request): Response
    {
        if ($this->isCsrfTokenValid('toggle_' . $server->getId(), $request->request->get('_token'))) {
            $server->setIsActive(!$server->isActive());
            $this->em->flush();
            $status = $server->isActive() ? 'activé' : 'désactivé';
            $this->activityLog->log('server.toggle_active', ActivityLog::CAT_SERVER, 'Server', $server->getId(), $server->getName(), [
                'active' => $server->isActive(),
            ]);
            $this->addFlash('success', "Serveur {$status}.");
        }

        return $this->redirectToRoute('admin_servers_list');
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('servers.delete')]
    public function delete(Server $server, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete_' . $server->getId(), $request->request->get('_token'))) {
            if ($server->getBanner()) {
                $this->serverService->deleteFile('servers/banners', $server->getBanner());
            }
            if ($server->getPresentationImage()) {
                $this->serverService->deleteFile('servers/presentations', $server->getPresentationImage());
            }

            $serverName = $server->getName();
            $ownerName = $server->getOwner() ? $server->getOwner()->getUsername() : 'Inconnu';

            $serverId = $server->getId();
            $this->em->remove($server);
            $this->em->flush();

            $this->activityLog->log('server.delete', ActivityLog::CAT_SERVER, 'Server', $serverId, $serverName, [
                'owner' => $ownerName,
            ]);

            $this->webhookService->dispatch('server.deleted', [
                'title' => 'Serveur supprimé',
                'fields' => [
                    ['name' => 'Serveur', 'value' => $serverName, 'inline' => true],
                    ['name' => 'Propriétaire', 'value' => $ownerName, 'inline' => true],
                    ['name' => 'Supprimé par', 'value' => $this->getUser()->getUsername(), 'inline' => true],
                ],
            ]);

            $this->addFlash('success', 'Serveur supprimé.');
        }

        return $this->redirectToRoute('admin_servers_list');
    }
}
