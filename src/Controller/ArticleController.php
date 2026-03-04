<?php

namespace App\Controller;

use App\Repository\ArticleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ArticleController extends AbstractController
{
    #[Route('/actualites', name: 'articles_list')]
    public function list(Request $request, ArticleRepository $articleRepository): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $data = $articleRepository->findPublishedPaginated($page, 16);

        return $this->render('articles/list.html.twig', [
            'articles' => $data['articles'],
            'currentPage' => $page,
            'totalPages' => $data['pages'],
            'totalArticles' => $data['total'],
        ]);
    }

    #[Route('/actualites/{slug}', name: 'articles_show', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'])]
    public function show(string $slug, ArticleRepository $articleRepository): Response
    {
        $article = $articleRepository->findOneBy(['slug' => $slug, 'isPublished' => true]);
        if (!$article) {
            throw $this->createNotFoundException('Article introuvable.');
        }

        $relatedArticles = $articleRepository->findRelated($article, 3);

        return $this->render('articles/show.html.twig', [
            'article' => $article,
            'relatedArticles' => $relatedArticles,
        ]);
    }
}
