<?php

namespace App\Controller\Admin;

use App\Entity\Server;
use App\Repository\CategoryRepository;
use App\Repository\ServerRepository;
use App\Service\ServerService;
use App\Service\SlugService;
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
    #[IsGranted('ROLE_MANAGER')]
    public function edit(Server $server, Request $request, CategoryRepository $categoryRepo): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_server_form', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('admin_servers_edit', ['id' => $server->getId()]);
            }

            $this->handleForm($server, $request);
            $this->em->flush();

            $this->addFlash('success', 'Serveur modifie avec succes.');
            return $this->redirectToRoute('admin_servers_list');
        }

        return $this->render('admin/servers/edit.html.twig', [
            'server' => $server,
            'categories' => $categoryRepo->findBy([], ['position' => 'ASC']),
        ]);
    }

    #[Route('/{id}/approve', name: 'approve', methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function approve(Server $server, Request $request): Response
    {
        if ($this->isCsrfTokenValid('approve_' . $server->getId(), $request->request->get('_token'))) {
            $server->setIsApproved(!$server->isApproved());
            $this->em->flush();
            $status = $server->isApproved() ? 'approuve' : 'desapprouve';
            $this->addFlash('success', "Serveur {$status}.");
        }

        return $this->redirectToRoute('admin_servers_list');
    }

    #[Route('/{id}/toggle-active', name: 'toggle_active', methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function toggleActive(Server $server, Request $request): Response
    {
        if ($this->isCsrfTokenValid('toggle_' . $server->getId(), $request->request->get('_token'))) {
            $server->setIsActive(!$server->isActive());
            $this->em->flush();
            $status = $server->isActive() ? 'active' : 'desactive';
            $this->addFlash('success', "Serveur {$status}.");
        }

        return $this->redirectToRoute('admin_servers_list');
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function delete(Server $server, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete_' . $server->getId(), $request->request->get('_token'))) {
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

        return $this->redirectToRoute('admin_servers_list');
    }

    private function handleForm(Server $server, Request $request): void
    {
        $server->setName($request->request->get('name', ''));
        $server->setSlug($this->slugService->slugify($request->request->get('name', '')));
        $server->setShortDescription($request->request->get('short_description', ''));
        $server->setIsActive($request->request->getBoolean('is_active'));
        $server->setIsApproved($request->request->getBoolean('is_approved'));
        $server->setIsPrivate($request->request->getBoolean('is_private'));
        $server->setSlots((int) $request->request->get('slots', 0));
        $server->setIp($request->request->get('ip') ?: null);
        $server->setPort($request->request->get('port') ? (int) $request->request->get('port') : null);
        $server->setWebsite($request->request->get('website') ?: null);
        $server->setDiscordUrl($request->request->get('discord_url') ?: null);
        $server->setTwitchChannel($request->request->get('twitch_channel') ?: null);
    }
}
