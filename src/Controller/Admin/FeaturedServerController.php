<?php

namespace App\Controller\Admin;

use App\Entity\Server;
use App\Repository\ServerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/featured-servers', name: 'admin_featured_')]
#[IsGranted('ROLE_MANAGER')]
class FeaturedServerController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'list')]
    public function list(ServerRepository $repo): Response
    {
        return $this->render('admin/featured/list.html.twig', [
            'featured' => $repo->findFeaturedForAdmin(),
        ]);
    }

    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request, ServerRepository $repo): JsonResponse
    {
        $q = trim($request->query->get('q', ''));
        if (strlen($q) < 2) {
            return $this->json([]);
        }

        $servers = $repo->searchByName($q, 10);
        $results = [];
        foreach ($servers as $server) {
            $results[] = [
                'id' => $server->getId(),
                'name' => $server->getName(),
                'category' => $server->getCategory()?->getName(),
                'owner' => $server->getOwner()?->getUsername(),
                'isFeatured' => $server->isFeatured(),
            ];
        }

        return $this->json($results);
    }

    #[Route('/add', name: 'add', methods: ['POST'])]
    public function add(Request $request, ServerRepository $repo): Response
    {
        if (!$this->isCsrfTokenValid('featured_manage', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_featured_list');
        }

        $serverId = (int) $request->request->get('server_id');
        $server = $repo->find($serverId);

        if (!$server) {
            $this->addFlash('error', 'Serveur introuvable.');
            return $this->redirectToRoute('admin_featured_list');
        }

        if ($server->isFeatured()) {
            $this->addFlash('warning', 'Ce serveur est deja mis en avant.');
            return $this->redirectToRoute('admin_featured_list');
        }

        // Get next position
        $maxPos = $this->em->createQuery('SELECT MAX(s.featuredPosition) FROM App\Entity\Server s WHERE s.isFeatured = true')
            ->getSingleScalarResult();

        $server->setIsFeatured(true);
        $server->setFeaturedPosition(((int) $maxPos) + 1);
        $this->em->flush();

        $this->addFlash('success', 'Serveur "' . $server->getName() . '" mis en avant.');
        return $this->redirectToRoute('admin_featured_list');
    }

    #[Route('/{id}/remove', name: 'remove', methods: ['POST'])]
    public function remove(Server $server, Request $request): Response
    {
        if ($this->isCsrfTokenValid('featured_' . $server->getId(), $request->request->get('_token'))) {
            $server->setIsFeatured(false);
            $server->setFeaturedPosition(0);
            $this->em->flush();
            $this->addFlash('success', 'Serveur retire de la mise en avant.');
        }

        return $this->redirectToRoute('admin_featured_list');
    }

    #[Route('/{id}/position', name: 'position', methods: ['POST'])]
    public function position(Server $server, Request $request): Response
    {
        if ($this->isCsrfTokenValid('featured_' . $server->getId(), $request->request->get('_token'))) {
            $server->setFeaturedPosition((int) $request->request->get('position', 0));
            $this->em->flush();
            $this->addFlash('success', 'Position mise a jour.');
        }

        return $this->redirectToRoute('admin_featured_list');
    }
}
