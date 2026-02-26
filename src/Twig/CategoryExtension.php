<?php

namespace App\Twig;

use App\Entity\Category;
use App\Entity\GameCategory;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Fournit gc_image(gameCategory, parentCategory = null) :
 * retourne le chemin relatif de l'image de la sous-catégorie,
 * en remontant vers la catégorie parente si nécessaire,
 * et en tombant sur l'image générique si aucune image n'est définie.
 */
class CategoryExtension extends AbstractExtension
{
    private const DEFAULT_IMAGE = 'images/default-category.svg';

    public function getFunctions(): array
    {
        return [
            new TwigFunction('gc_image', [$this, 'gcImage']),
        ];
    }

    /**
     * Retourne le chemin relatif de l'image à utiliser avec asset().
     *
     * Priorité :
     *   1. gc.image  → uploads/categories/{image}
     *   2. parent.image → uploads/categories/{image}
     *   3. Image générique  → images/default-category.svg
     */
    public function gcImage(?GameCategory $gc, ?Category $parent = null): string
    {
        if ($gc !== null && $gc->getImage()) {
            return 'uploads/categories/' . $gc->getImage();
        }

        if ($parent !== null && $parent->getImage()) {
            return 'uploads/categories/' . $parent->getImage();
        }

        return self::DEFAULT_IMAGE;
    }
}
