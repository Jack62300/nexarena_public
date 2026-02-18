<?php

namespace App\Controller\Admin;

use App\Entity\Server;
use App\Form\Admin\AdminServerFormType;
use App\Repository\CategoryRepository;
use App\Repository\ServerRepository;
use App\Service\ServerService;
use App\Service\SlugService;
use App\Service\WebhookService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/servers', name: 'admin_servers_')]
#[IsGranted('ROLE_EDITEUR')]
class ServerController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SlugService $slugService,
        private ServerService $serverService,
        private WebhookService $webhookService,
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
            $server->setSlug($this->slugService->slugify($server->getName()));
            $this->em->flush();

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
        if ($this->isCsrfTokenValid('approve_' . $server->getId(), $request->request->get('_token'))) {
            $server->setIsApproved(!$server->isApproved());
            $this->em->flush();
            $status = $server->isApproved() ? 'approuvé' : 'désapprouvé';

            if ($server->isApproved()) {
                $this->webhookService->dispatch('server.approved', [
                    'title' => 'Serveur approuvé',
                    'fields' => [
                        ['name' => 'Serveur', 'value' => $server->getName(), 'inline' => true],
                        ['name' => 'Approuvé par', 'value' => $this->getUser()->getUsername(), 'inline' => true],
                    ],
                ]);
            }

            $this->addFlash('success', "Serveur {$status}.");
        }

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

            $this->em->remove($server);
            $this->em->flush();

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
