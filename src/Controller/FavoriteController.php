<?php

namespace App\Controller;

use App\Repository\ServerFavoriteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class FavoriteController extends AbstractController
{
    #[Route('/mes-favoris', name: 'app_favorites')]
    public function index(Request $request, ServerFavoriteRepository $favoriteRepo): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $page = max(1, (int) $request->query->get('page', 1));

        $result = $favoriteRepo->findByUserPaginated($user, $page, 20);

        return $this->render('favorite/index.html.twig', [
            'favorites' => $result['favorites'],
            'total' => $result['total'],
            'pages' => $result['pages'],
            'page' => $page,
        ]);
    }
}
