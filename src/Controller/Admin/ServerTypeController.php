<?php

namespace App\Controller\Admin;

use App\Entity\ServerType;
use App\Repository\CategoryRepository;
use App\Repository\ServerTypeRepository;
use App\Service\SlugService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/server-types', name: 'admin_server_types_')]
#[IsGranted('ROLE_EDITEUR')]
class ServerTypeController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SlugService $slugService,
    ) {
    }

    #[Route('', name: 'list')]
    public function list(ServerTypeRepository $repo, CategoryRepository $categoryRepo): Response
    {
        return $this->render('admin/server_types/list.html.twig', [
            'serverTypes' => $repo->findBy([], ['position' => 'ASC']),
            'categories' => $categoryRepo->findBy([], ['position' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request, CategoryRepository $categoryRepo): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('server_type_form', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('admin_server_types_new');
            }

            $serverType = new ServerType();
            $this->handleForm($serverType, $request);

            $this->em->persist($serverType);
            $this->em->flush();

            $this->addFlash('success', 'Type de serveur cree avec succes.');
            return $this->redirectToRoute('admin_server_types_list');
        }

        return $this->render('admin/server_types/form.html.twig', [
            'serverType' => null,
            'categories' => $categoryRepo->findBy([], ['position' => 'ASC']),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit')]
    public function edit(ServerType $serverType, Request $request, CategoryRepository $categoryRepo): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('server_type_form', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('admin_server_types_edit', ['id' => $serverType->getId()]);
            }

            $this->handleForm($serverType, $request);
            $this->em->flush();

            $this->addFlash('success', 'Type de serveur modifie avec succes.');
            return $this->redirectToRoute('admin_server_types_list');
        }

        return $this->render('admin/server_types/form.html.twig', [
            'serverType' => $serverType,
            'categories' => $categoryRepo->findBy([], ['position' => 'ASC']),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('server_types.manage')]
    public function delete(ServerType $serverType, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete_' . $serverType->getId(), $request->request->get('_token'))) {
            $this->em->remove($serverType);
            $this->em->flush();
            $this->addFlash('success', 'Type de serveur supprime.');
        }

        return $this->redirectToRoute('admin_server_types_list');
    }

    private function handleForm(ServerType $serverType, Request $request): void
    {
        $name = $request->request->get('name', '');
        $serverType->setName($name);
        $serverType->setSlug($this->slugService->slugify($name));
        $serverType->setIsActive($request->request->getBoolean('is_active'));
        $serverType->setPosition((int) $request->request->get('position', 0));

        $categoryId = $request->request->get('category_id');
        if ($categoryId) {
            $category = $this->em->getRepository(\App\Entity\Category::class)->find((int) $categoryId);
            $serverType->setCategory($category);
        }
    }
}
