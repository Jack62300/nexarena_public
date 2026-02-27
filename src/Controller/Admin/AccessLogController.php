<?php

namespace App\Controller\Admin;

use App\Repository\AccessLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/acces-logs', name: 'admin_access_logs_')]
#[IsGranted('logs.access')]
class AccessLogController extends AbstractController
{
    private const PER_PAGE = 60;

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, AccessLogRepository $repo): Response
    {
        $filter  = $request->query->get('filter', 'all');
        $search  = trim((string) $request->query->get('q', ''));
        $country = strtoupper(trim((string) $request->query->get('country', '')));
        $reason  = $request->query->get('reason', '');
        $page    = max(1, (int) $request->query->get('page', 1));

        // Validation
        if (!in_array($filter, ['all', 'blocked', 'allowed'], true)) {
            $filter = 'all';
        }

        [$logs, $total] = $repo->findFiltered($filter, $search, $page, self::PER_PAGE, $country, $reason);
        $stats = $repo->getStats24h();

        return $this->render('admin/access_log/index.html.twig', [
            'logs'       => $logs,
            'total'      => $total,
            'page'       => $page,
            'per_page'   => self::PER_PAGE,
            'pages'      => (int) ceil($total / self::PER_PAGE),
            'filter'     => $filter,
            'search'     => $search,
            'country'    => $country,
            'reason'     => $reason,
            'stats'      => $stats,
        ]);
    }

    #[Route('/purger', name: 'purge', methods: ['POST'])]
    #[IsGranted('logs.purge')]
    public function purge(Request $request, AccessLogRepository $repo): Response
    {
        if (!$this->isCsrfTokenValid('access_log_purge', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_access_logs_index');
        }

        $days    = max(1, (int) $request->request->get('days', 30));
        $deleted = $repo->deleteOlderThan($days);

        $this->addFlash('success', "{$deleted} entrée(s) supprimée(s) (plus vieilles que {$days} jours).");
        return $this->redirectToRoute('admin_access_logs_index');
    }
}
