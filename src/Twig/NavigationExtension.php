<?php

namespace App\Twig;

use App\Repository\CategoryRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class NavigationExtension extends AbstractExtension
{
    public function __construct(
        private CategoryRepository $categoryRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('nav_categories', [$this, 'getNavCategories']),
        ];
    }

    /**
     * @return \App\Entity\Category[]
     */
    public function getNavCategories(): array
    {
        return $this->categoryRepository->findAllActiveWithGameCategories();
    }
}
