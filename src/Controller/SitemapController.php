<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\GameCategoryRepository;
use App\Repository\RecruitmentListingRepository;
use App\Repository\ServerRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SitemapController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'sitemap', defaults: ['_format' => 'xml'])]
    public function index(
        Request $request,
        ServerRepository $serverRepo,
        CategoryRepository $categoryRepo,
        GameCategoryRepository $gameCategoryRepo,
        RecruitmentListingRepository $recruitmentRepo,
    ): Response {
        $base = $request->getSchemeAndHttpHost();
        $now  = new \DateTimeImmutable();

        $urls = [];

        // ── Static pages ──────────────────────────────────────────
        $statics = [
            ['loc' => $base . '/',                  'priority' => '1.0', 'changefreq' => 'daily'],
            ['loc' => $base . '/recrutement',        'priority' => '0.7', 'changefreq' => 'daily'],
            ['loc' => $base . '/plugins',            'priority' => '0.6', 'changefreq' => 'weekly'],
            ['loc' => $base . '/premium',            'priority' => '0.5', 'changefreq' => 'monthly'],
        ];
        foreach ($statics as $s) {
            $urls[] = array_merge($s, ['lastmod' => $now->format('Y-m-d')]);
        }

        // ── Parent categories (/classement/categorie/{slug}) ───────
        foreach ($categoryRepo->findAll() as $cat) {
            $urls[] = [
                'loc'        => $base . $this->generateUrl('ranking_category', ['slug' => $cat->getSlug()]),
                'priority'   => '0.8',
                'changefreq' => 'daily',
                'lastmod'    => $now->format('Y-m-d'),
            ];
        }

        // ── Game categories (/classement/{slug}) ──────────────────
        foreach ($gameCategoryRepo->findAll() as $gc) {
            $urls[] = [
                'loc'        => $base . $this->generateUrl('ranking_game_category', ['slug' => $gc->getSlug()]),
                'priority'   => '0.7',
                'changefreq' => 'daily',
                'lastmod'    => $now->format('Y-m-d'),
            ];
        }

        // ── Server pages (/serveur/{slug}) ────────────────────────
        $servers = $serverRepo->findBy(['isActive' => true, 'isApproved' => true]);
        foreach ($servers as $server) {
            $urls[] = [
                'loc'        => $base . $this->generateUrl('server_show', ['slug' => $server->getSlug()]),
                'priority'   => '0.9',
                'changefreq' => 'weekly',
                'lastmod'    => $now->format('Y-m-d'),
            ];
        }

        // ── Recruitment listings (/recrutement/{slug}) ────────────
        $listings = $recruitmentRepo->findPubliclyVisible();
        foreach ($listings as $listing) {
            $urls[] = [
                'loc'        => $base . $this->generateUrl('recruitment_show', ['slug' => $listing->getSlug()]),
                'priority'   => '0.6',
                'changefreq' => 'weekly',
                'lastmod'    => ($listing->getApprovedAt() ?? $now)->format('Y-m-d'),
            ];
        }

        $response = new Response(
            $this->renderView('sitemap.xml.twig', ['urls' => $urls]),
            200,
            ['Content-Type' => 'application/xml; charset=utf-8']
        );

        // Cache for 1 hour
        $response->setSharedMaxAge(3600);

        return $response;
    }
}
