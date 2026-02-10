<?php

namespace App\Controller\Admin;

use App\Entity\Article;
use App\Repository\ArticleRepository;
use App\Service\SlugService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

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
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('article_form', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('admin_articles_new');
            }

            $article = new Article();
            $this->handleForm($article, $request);

            $this->em->persist($article);
            $this->em->flush();

            $this->addFlash('success', 'Article cree avec succes.');
            return $this->redirectToRoute('admin_articles_list');
        }

        return $this->render('admin/articles/form.html.twig', [
            'article' => null,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit')]
    public function edit(Article $article, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('article_form', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('admin_articles_edit', ['id' => $article->getId()]);
            }

            $this->handleForm($article, $request);
            $this->em->flush();

            $this->addFlash('success', 'Article modifie avec succes.');
            return $this->redirectToRoute('admin_articles_list');
        }

        return $this->render('admin/articles/form.html.twig', [
            'article' => $article,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER')]
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
            $this->addFlash('success', 'Article supprime.');
        }

        return $this->redirectToRoute('admin_articles_list');
    }

    private function handleForm(Article $article, Request $request): void
    {
        $article->setTitle($request->request->get('title', ''));
        $article->setSlug($this->slugService->slugify($request->request->get('title', '')));
        $article->setContent($request->request->get('content', ''));
        $article->setIsPublished($request->request->getBoolean('is_published'));

        /** @var UploadedFile|null $file */
        $file = $request->files->get('image');
        if ($file) {
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file->getMimeType(), $allowedMimes, true) || $file->getSize() > 5 * 1024 * 1024) {
                return;
            }

            $filename = uniqid() . '.' . $file->guessExtension();
            $file->move(
                $this->getParameter('kernel.project_dir') . '/public/uploads/articles',
                $filename,
            );

            // Supprimer l'ancienne image
            if ($article->getImage()) {
                $dir = $this->getParameter('kernel.project_dir') . '/public/uploads/articles';
                $oldPath = $dir . '/' . basename($article->getImage());
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }

            $article->setImage($filename);
        }
    }
}
