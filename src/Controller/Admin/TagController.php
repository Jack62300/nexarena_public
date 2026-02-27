<?php

namespace App\Controller\Admin;

use App\Entity\Tag;
use App\Form\Admin\TagFormType;
use App\Repository\TagRepository;
use App\Service\SlugService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/tags', name: 'admin_tags_')]
#[IsGranted('tags.list')]
class TagController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SlugService $slugService,
    ) {
    }

    #[Route('', name: 'list')]
    public function list(TagRepository $repo): Response
    {
        return $this->render('admin/tags/list.html.twig', [
            'tags' => $repo->findAllForAdmin(),
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        $tag = new Tag();
        $form = $this->createForm(TagFormType::class, $tag);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $tag->setSlug($this->slugService->slugify($tag->getName()));

            $this->em->persist($tag);
            $this->em->flush();

            $this->addFlash('success', 'Tag créé avec succès.');
            return $this->redirectToRoute('admin_tags_list');
        }

        return $this->render('admin/tags/form.html.twig', [
            'tag' => null,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit')]
    public function edit(Tag $tag, Request $request): Response
    {
        $form = $this->createForm(TagFormType::class, $tag);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $tag->setSlug($this->slugService->slugify($tag->getName()));
            $this->em->flush();

            $this->addFlash('success', 'Tag modifié avec succès.');
            return $this->redirectToRoute('admin_tags_list');
        }

        return $this->render('admin/tags/form.html.twig', [
            'tag' => $tag,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('tags.manage')]
    public function delete(Tag $tag, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete_' . $tag->getId(), $request->request->get('_token'))) {
            $this->em->remove($tag);
            $this->em->flush();
            $this->addFlash('success', 'Tag supprimé.');
        }

        return $this->redirectToRoute('admin_tags_list');
    }
}
