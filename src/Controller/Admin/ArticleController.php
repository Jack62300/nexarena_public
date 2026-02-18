<?php

namespace App\Controller\Admin;

use App\Entity\Article;
use App\Form\Admin\ArticleFormType;
use App\Repository\ArticleRepository;
use App\Service\SlugService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/articles', name: 'admin_articles_')]
#[IsGranted('ROLE_EDITEUR')]
class ArticleController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SlugService $slugService,
    ) {
    }

    #[Route('', name: 'list')]
    public function list(ArticleRepository $repo): Response
    {
        return $this->render('admin/articles/list.html.twig', [
            'articles' => $repo->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        $article = new Article();
        $form = $this->createForm(ArticleFormType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $article->setSlug($this->slugService->slugify($article->getTitle()));

            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $filename = uniqid() . '.' . $imageFile->guessExtension();
                $imageFile->move(
                    $this->getParameter('kernel.project_dir') . '/public/uploads/articles',
                    $filename,
                );
                $article->setImage($filename);
            }

            $this->em->persist($article);
            $this->em->flush();

            $this->addFlash('success', 'Article créé avec succès.');
            return $this->redirectToRoute('admin_articles_list');
        }

        return $this->render('admin/articles/form.html.twig', [
            'article' => null,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit')]
    public function edit(Article $article, Request $request): Response
    {
        $form = $this->createForm(ArticleFormType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $article->setSlug($this->slugService->slugify($article->getTitle()));

            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $filename = uniqid() . '.' . $imageFile->guessExtension();
                $imageFile->move(
                    $this->getParameter('kernel.project_dir') . '/public/uploads/articles',
                    $filename,
                );

                if ($article->getImage()) {
                    $oldPath = $this->getParameter('kernel.project_dir') . '/public/uploads/articles/' . basename($article->getImage());
                    if (file_exists($oldPath)) {
                        unlink($oldPath);
                    }
                }

                $article->setImage($filename);
            }

            $this->em->flush();

            $this->addFlash('success', 'Article modifié avec succès.');
            return $this->redirectToRoute('admin_articles_list');
        }

        return $this->render('admin/articles/form.html.twig', [
            'article' => $article,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('articles.delete')]
    public function delete(Article $article, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete_' . $article->getId(), $request->request->get('_token'))) {
            if ($article->getImage()) {
                $path = $this->getParameter('kernel.project_dir') . '/public/uploads/articles/' . basename($article->getImage());
                if (file_exists($path)) {
                    unlink($path);
                }
            }
            $this->em->remove($article);
            $this->em->flush();
            $this->addFlash('success', 'Article supprimé.');
        }

        return $this->redirectToRoute('admin_articles_list');
    }
}
