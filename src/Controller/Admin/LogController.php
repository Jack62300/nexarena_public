<?php

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Repository\ActivityLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/logs', name: 'admin_logs_')]
#[IsGranted('ROLE_DEVELOPPEUR')]
class LogController extends AbstractController
{
    private const PER_PAGE = 60;

    #[Route('', name: 'list')]
    public function list(Request $request, ActivityLogRepository $repo): Response
    {
        $category = $request->query->get('category', '');
        $search   = $request->query->get('search', '');
        $page     = max(1, (int) $request->query->get('page', 1));

        [$logs, $total] = $repo->findFiltered(
            $category ?: null,
            $search   ?: null,
            $page,
            self::PER_PAGE
        );

        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));

        return $this->render('admin/logs/list.html.twig', [
            'logs'       => $logs,
            'total'      => $total,
            'page'       => $page,
            'totalPages' => $totalPages,
            'category'   => $category,
            'search'     => $search,
            'categories' => ActivityLog::CATEGORIES,
            'perPage'    => self::PER_PAGE,
        ]);
    }
}
