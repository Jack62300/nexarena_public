<?php

namespace App\Controller\Admin;

use App\Repository\ArticleRepository;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use App\Service\StatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin', name: 'admin_')]
#[IsGranted('ROLE_EDITEUR')]
class DashboardController extends AbstractController
{
    #[Route('', name: 'dashboard')]
    public function index(StatsService $statsService, UserRepository $userRepository, ArticleRepository $articleRepository, RoleRepository $roleRepository): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'stats' => $statsService->getAllStats(),
            'recent_users' => $userRepository->findBy([], ['createdAt' => 'DESC'], 10),
            'recent_articles' => $articleRepository->findBy([], ['createdAt' => 'DESC'], 5),
            'roles' => $roleRepository->findBy([], ['position' => 'DESC']),
            'chart_servers_by_category' => $statsService->getServerCountByCategory(),
            'chart_users_by_month' => $statsService->getUserRegistrationsByMonth(),
            'chart_votes_by_month' => $statsService->getVotesByMonth(),
        ]);
    }
}
