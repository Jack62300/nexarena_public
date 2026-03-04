<?php

namespace App\Controller;

use App\Entity\FeaturedBooking;
use App\Repository\ActivityLogRepository;
use App\Repository\CategoryRepository;
use App\Repository\FeaturedBookingRepository;
use App\Repository\GameCategoryRepository;
use App\Repository\PartnerRepository;
use App\Repository\ServerRepository;
use App\Service\SettingsService;
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
        FeaturedBookingRepository $bookingRepo,
        SettingsService $settingsService,
        ActivityLogRepository $activityLogRepo,
    ): Response {
        // Admin-featured servers (isFeatured flag set by admins)
        $adminFeaturedServers = $serverRepository->findFeatured();

        // Premium positions (5 slots, each can be booked or empty)
        $now = new \DateTime('now');
        $premiumPositions = $bookingRepo->findActivePositions(FeaturedBooking::SCOPE_HOMEPAGE, $now);

        // Banner slides
        $bannerSlides = json_decode($settingsService->get('banner_slides', '[]'), true) ?: [];

        return $this->render('home/index.html.twig', [
            'popularCategories' => $gameCategoryRepository->findMostActive(10),
            'gameCategories' => $gameCategoryRepository->findAllActive(),
            'premiumPositions' => $premiumPositions,
            'adminFeaturedServers' => $adminFeaturedServers,
            'serverCounts' => $serverRepository->countActiveByGameCategory(),
            'partners' => $partnerRepository->findActiveByType('partner'),
            'services' => $partnerRepository->findActiveByType('service'),
            'bannerSlides' => $bannerSlides,
            'activityFeed' => $activityLogRepo->findPublicFeed(10),
        ]);
    }
}
