<?php

namespace App\Controller;

use App\Entity\FeaturedBooking;
use App\Repository\CategoryRepository;
use App\Repository\FeaturedBookingRepository;
use App\Repository\GameCategoryRepository;
use App\Repository\ServerRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RankingController extends AbstractController
{
    private const PER_PAGE = 10;

    #[Route('/classement/{slug}', name: 'ranking_game_category', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'])]
    public function gameCategory(string $slug, Request $request, GameCategoryRepository $gcRepo, ServerRepository $serverRepo, FeaturedBookingRepository $bookingRepo): Response
    {
        $gameCategory = $gcRepo->findOneBy(['slug' => $slug, 'isActive' => true]);
        if (!$gameCategory) {
            throw $this->createNotFoundException('Categorie introuvable.');
        }

        $page = max(1, $request->query->getInt('page', 1));
        $result = $serverRepo->findByGameCategoryPaginated($gameCategory, $page, self::PER_PAGE);

        $now = new \DateTime('now');
        $premiumPositions = $bookingRepo->findActivePositions(FeaturedBooking::SCOPE_GAME, $now, $gameCategory);

        return $this->render('ranking/index.html.twig', [
            'title' => $gameCategory->getName(),
            'description' => $gameCategory->getDescription(),
            'image' => $gameCategory->getImage(),
            'servers' => $result['servers'],
            'total_servers' => $result['total'],
            'current_page' => $page,
            'total_pages' => $result['pages'],
            'per_page' => self::PER_PAGE,
            'gameCategory' => $gameCategory,
            'parentCategory' => $gameCategory->getCategory(),
            'premiumPositions' => $premiumPositions,
        ]);
    }

    #[Route('/classement/categorie/{slug}', name: 'ranking_category', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'], priority: 1)]
    public function category(string $slug, Request $request, CategoryRepository $catRepo, ServerRepository $serverRepo): Response
    {
        $category = $catRepo->findOneBy(['slug' => $slug, 'isActive' => true]);
        if (!$category) {
            throw $this->createNotFoundException('Categorie introuvable.');
        }

        $page = max(1, $request->query->getInt('page', 1));
        $result = $serverRepo->findByCategoryPaginated($category, $page, self::PER_PAGE);

        return $this->render('ranking/index.html.twig', [
            'title' => $category->getName(),
            'description' => $category->getDescription(),
            'image' => $category->getImage(),
            'servers' => $result['servers'],
            'total_servers' => $result['total'],
            'current_page' => $page,
            'total_pages' => $result['pages'],
            'per_page' => self::PER_PAGE,
            'parentCategory' => $category,
            'gameCategory' => null,
            'premiumPositions' => [],
        ]);
    }
}
