<?php

namespace App\Twig;

use App\Repository\CategoryRepository;
use App\Repository\ServerRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class NavigationExtension extends AbstractExtension
{
    private const MAX_SUBCATEGORIES = 8;

    public function __construct(
        private CategoryRepository $categoryRepository,
        private ServerRepository $serverRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('nav_categories', [$this, 'getNavCategories']),
        ];
    }

    /**
     * Returns parent categories with only the top N most active game categories (by server count).
     *
     * @return array<array{category: \App\Entity\Category, gameCategories: \App\Entity\GameCategory[]}>
     */
    public function getNavCategories(): array
    {
        $categories = $this->categoryRepository->findAllActiveWithGameCategories();
        $serverCounts = $this->serverRepository->countActiveByGameCategory();

        $result = [];
        foreach ($categories as $category) {
            $gameCategories = $category->getGameCategories()->toArray();

            // Sort by server count descending
            usort($gameCategories, function ($a, $b) use ($serverCounts) {
                $countA = $serverCounts[$a->getId()] ?? 0;
                $countB = $serverCounts[$b->getId()] ?? 0;
                return $countB <=> $countA;
            });

            // Keep only top N with at least 1 server
            $topGames = [];
            foreach ($gameCategories as $gc) {
                $count = $serverCounts[$gc->getId()] ?? 0;
                if ($count > 0 && count($topGames) < self::MAX_SUBCATEGORIES) {
                    $topGames[] = $gc;
                }
            }

            $result[] = [
                'category' => $category,
                'gameCategories' => $topGames,
                'hasMore' => count($gameCategories) > count($topGames),
            ];
        }

        return $result;
    }
}
