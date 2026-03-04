<?php

namespace App\Controller;

use App\Repository\ServerRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SearchController extends AbstractController
{
    #[Route('/recherche', name: 'app_search')]
    public function index(Request $request, ServerRepository $serverRepo): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $page = max(1, (int) $request->query->get('page', 1));

        $servers = [];
        $total = 0;
        $pages = 1;

        if ($query !== '' && mb_strlen($query) >= 2) {
            $result = $serverRepo->searchPaginated($query, $page, 20);
            $servers = $result['servers'];
            $total = $result['total'];
            $pages = $result['pages'];
        }

        return $this->render('search/index.html.twig', [
            'query' => $query,
            'servers' => $servers,
            'total' => $total,
            'pages' => $pages,
            'page' => $page,
        ]);
    }
}
