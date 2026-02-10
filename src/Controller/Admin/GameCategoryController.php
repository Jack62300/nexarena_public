<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Entity\GameCategory;
use App\Repository\CategoryRepository;
use App\Repository\GameCategoryRepository;
use App\Service\SlugService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/categories', name: 'admin_categories_')]
#[IsGranted('ROLE_EDITEUR')]
class GameCategoryController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SlugService $slugService,
    ) {
    }

    #[Route('', name: 'list')]
    public function list(GameCategoryRepository $repo, CategoryRepository $categoryRepo): Response
    {
        return $this->render('admin/categories/list.html.twig', [
            'categories' => $repo->findBy([], ['position' => 'ASC']),
            'parentCategories' => $categoryRepo->findBy([], ['position' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request, CategoryRepository $categoryRepo): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('category_form', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('admin_categories_new');
            }

            $category = new GameCategory();
            $this->handleForm($category, $request);

            $this->em->persist($category);
            $this->em->flush();

            $this->addFlash('success', 'Sous-categorie creee avec succes.');
            return $this->redirectToRoute('admin_categories_list');
        }

        return $this->render('admin/categories/form.html.twig', [
            'category' => null,
            'parentCategories' => $categoryRepo->findBy([], ['position' => 'ASC']),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit')]
    public function edit(GameCategory $category, Request $request, CategoryRepository $categoryRepo): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('category_form', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('admin_categories_edit', ['id' => $category->getId()]);
            }

            $this->handleForm($category, $request);
            $this->em->flush();

            $this->addFlash('success', 'Sous-categorie modifiee avec succes.');
            return $this->redirectToRoute('admin_categories_list');
        }

        return $this->render('admin/categories/form.html.twig', [
            'category' => $category,
            'parentCategories' => $categoryRepo->findBy([], ['position' => 'ASC']),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function delete(GameCategory $category, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete_' . $category->getId(), $request->request->get('_token'))) {
            if ($category->getImage()) {
                $path = $this->getParameter('kernel.project_dir') . '/public/uploads/categories/' . basename($category->getImage());
                if (file_exists($path)) {
                    unlink($path);
                }
            }
            $this->em->remove($category);
            $this->em->flush();
            $this->addFlash('success', 'Categorie supprimee.');
        }

        return $this->redirectToRoute('admin_categories_list');
    }

    private function handleForm(GameCategory $category, Request $request): void
    {
        $category->setName($request->request->get('name', ''));
        $category->setSlug($this->slugService->slugify($request->request->get('name', '')));
        $category->setDescription($request->request->get('description', ''));
        $category->setIsActive($request->request->getBoolean('is_active'));
        $category->setPosition((int) $request->request->get('position', 0));

        $color = trim($request->request->get('color', ''));
        if ($color && preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $category->setColor($color);
        } elseif (!$color) {
            $category->setColor(null);
        }

        $categoryId = $request->request->get('category_id');
        if ($categoryId) {
            $parentCategory = $this->em->getRepository(Category::class)->find((int) $categoryId);
            $category->setCategory($parentCategory);
        } else {
            $category->setCategory(null);
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('image');
        if ($file) {
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
            if (!in_array($file->getMimeType(), $allowedMimes, true) || $file->getSize() > 5 * 1024 * 1024) {
                return;
            }

            $filename = uniqid() . '.' . $file->guessExtension();
            $file->move(
                $this->getParameter('kernel.project_dir') . '/public/uploads/categories',
                $filename,
            );

            if ($category->getImage()) {
                $dir = $this->getParameter('kernel.project_dir') . '/public/uploads/categories';
                $oldPath = $dir . '/' . basename($category->getImage());
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }

            $category->setImage($filename);
        }
    }
}
