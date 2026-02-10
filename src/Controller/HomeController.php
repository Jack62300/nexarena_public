<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\GameCategoryRepository;
use App\Repository\PartnerRepository;
use App\Repository\ServerRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        CategoryRepository $categoryRepository,
        GameCategoryRepository $gameCategoryRepository,
        ServerRepository $serverRepository,
        PartnerRepository $partnerRepository,
    ): Response {
        return $this->render('home/index.html.twig', [
            'categories' => $categoryRepository->findAllActiveWithGameCategories(),
            'gameCategories' => $gameCategoryRepository->findAllActive(),
            'topServers' => $serverRepository->findTopByMonthlyVotesWithDetails(5),
            'featuredServers' => $serverRepository->findFeatured(),
            'serverCounts' => $serverRepository->countActiveByGameCategory(),
            'partners' => $partnerRepository->findActiveByType('partner'),
            'services' => $partnerRepository->findActiveByType('service'),
        ]);
    }
}
