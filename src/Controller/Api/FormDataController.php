<?php

namespace App\Controller\Api;

use App\Entity\Category;
use App\Repository\GameCategoryRepository;
use App\Repository\ServerTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/form')]
class FormDataController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    #[Route('/game-categories/{categoryId}', name: 'api_form_game_categories', methods: ['GET'])]
    public function gameCategories(int $categoryId, GameCategoryRepository $repo): JsonResponse
    {
        $category = $this->em->getRepository(Category::class)->find($categoryId);
        if (!$category) {
            return $this->json([]);
        }

        $items = $repo->findByCategory($category);
        $data = [];
        foreach ($items as $gc) {
            $data[] = ['id' => $gc->getId(), 'name' => $gc->getName()];
        }

        return $this->json($data);
    }

    #[Route('/server-types/{categoryId}', name: 'api_form_server_types', methods: ['GET'])]
    public function serverTypes(int $categoryId, ServerTypeRepository $repo): JsonResponse
    {
        $category = $this->em->getRepository(Category::class)->find($categoryId);
        if (!$category) {
            return $this->json([]);
        }

        $items = $repo->findByCategory($category);
        $data = [];
        foreach ($items as $st) {
            $data[] = ['id' => $st->getId(), 'name' => $st->getName()];
        }

        return $this->json($data);
    }
}
