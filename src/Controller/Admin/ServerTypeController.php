<?php

namespace App\Controller\Admin;

use App\Entity\ServerType;
use App\Form\Admin\ServerTypeFormType;
use App\Repository\ServerTypeRepository;
use App\Service\SlugService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/server-types', name: 'admin_server_types_')]
#[IsGranted('server_types.list')]
class ServerTypeController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SlugService $slugService,
    ) {
    }

    #[Route('', name: 'list')]
    public function list(ServerTypeRepository $repo): Response
    {
        return $this->render('admin/server_types/list.html.twig', [
            'serverTypes' => $repo->findBy([], ['position' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'new')]
    #[IsGranted('server_types.manage')]
    public function new(Request $request): Response
    {
        $serverType = new ServerType();
        $form = $this->createForm(ServerTypeFormType::class, $serverType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $serverType->setSlug($this->slugService->slugify($serverType->getName()));

            $this->em->persist($serverType);
            $this->em->flush();

            $this->addFlash('success', 'Type de serveur créé avec succès.');
            return $this->redirectToRoute('admin_server_types_list');
        }

        return $this->render('admin/server_types/form.html.twig', [
            'serverType' => null,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit')]
    #[IsGranted('server_types.manage')]
    public function edit(ServerType $serverType, Request $request): Response
    {
        $form = $this->createForm(ServerTypeFormType::class, $serverType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $serverType->setSlug($this->slugService->slugify($serverType->getName()));
            $this->em->flush();

            $this->addFlash('success', 'Type de serveur modifié avec succès.');
            return $this->redirectToRoute('admin_server_types_list');
        }

        return $this->render('admin/server_types/form.html.twig', [
            'serverType' => $serverType,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('server_types.manage')]
    public function delete(ServerType $serverType, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete_' . $serverType->getId(), $request->request->get('_token'))) {
            $this->em->remove($serverType);
            $this->em->flush();
            $this->addFlash('success', 'Type de serveur supprimé.');
        }

        return $this->redirectToRoute('admin_server_types_list');
    }
}
