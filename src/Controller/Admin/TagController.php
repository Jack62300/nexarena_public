<?php

namespace App\Controller\Admin;

use App\Entity\Tag;
use App\Repository\TagRepository;
use App\Service\SlugService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/tags', name: 'admin_tags_')]
#[IsGranted('ROLE_EDITEUR')]
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
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('tag_form', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('admin_tags_new');
            }

            $tag = new Tag();
            $this->handleForm($tag, $request);

            $this->em->persist($tag);
            $this->em->flush();

            $this->addFlash('success', 'Tag cree avec succes.');
            return $this->redirectToRoute('admin_tags_list');
        }

        return $this->render('admin/tags/form.html.twig', [
            'tag' => null,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit')]
    public function edit(Tag $tag, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('tag_form', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('admin_tags_edit', ['id' => $tag->getId()]);
            }

            $this->handleForm($tag, $request);
            $this->em->flush();

            $this->addFlash('success', 'Tag modifie avec succes.');
            return $this->redirectToRoute('admin_tags_list');
        }

        return $this->render('admin/tags/form.html.twig', [
            'tag' => $tag,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('tags.manage')]
    public function delete(Tag $tag, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete_' . $tag->getId(), $request->request->get('_token'))) {
            $this->em->remove($tag);
            $this->em->flush();
            $this->addFlash('success', 'Tag supprime.');
        }

        return $this->redirectToRoute('admin_tags_list');
    }

    private function handleForm(Tag $tag, Request $request): void
    {
        $name = trim((string) $request->request->get('name', ''));
        $tag->setName($name);
        $tag->setSlug($this->slugService->slugify($name));

        $color = $request->request->get('color') ?: null;
        if ($color && preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $tag->setColor($color);
        } else {
            $tag->setColor(null);
        }

        $tag->setPosition((int) $request->request->get('position', 0));
        $tag->setIsActive($request->request->getBoolean('is_active'));
    }
}
