<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Service\SlugService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/parent-categories', name: 'admin_parent_categories_')]
#[IsGranted('ROLE_EDITEUR')]
class CategoryController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SlugService $slugService,
    ) {
    }

    #[Route('', name: 'list')]
    public function list(CategoryRepository $repo): Response
    {
        return $this->render('admin/parent_categories/list.html.twig', [
            'categories' => $repo->findBy([], ['position' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('parent_category_form', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('admin_parent_categories_new');
            }

            $category = new Category();
            $this->handleForm($category, $request);

            $this->em->persist($category);
            $this->em->flush();

            $this->addFlash('success', 'Categorie creee avec succes.');
            return $this->redirectToRoute('admin_parent_categories_list');
        }

        return $this->render('admin/parent_categories/form.html.twig', [
            'category' => null,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit')]
    public function edit(Category $category, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('parent_category_form', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('admin_parent_categories_edit', ['id' => $category->getId()]);
            }

            $this->handleForm($category, $request);
            $this->em->flush();

            $this->addFlash('success', 'Categorie modifiee avec succes.');
            return $this->redirectToRoute('admin_parent_categories_list');
        }

        return $this->render('admin/parent_categories/form.html.twig', [
            'category' => $category,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function delete(Category $category, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete_' . $category->getId(), $request->request->get('_token'))) {
            // Detach children (set category to null)
            foreach ($category->getGameCategories() as $gameCategory) {
                $gameCategory->setCategory(null);
            }

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

        return $this->redirectToRoute('admin_parent_categories_list');
    }

    private function handleForm(Category $category, Request $request): void
    {
        $category->setName($request->request->get('name', ''));
        $category->setSlug($this->slugService->slugify($request->request->get('name', '')));
        $category->setIcon($request->request->get('icon', '') ?: null);
        $category->setDescription($request->request->get('description', '') ?: null);
        $category->setIsActive($request->request->getBoolean('is_active'));
        $category->setPosition((int) $request->request->get('position', 0));
        $queryType = $request->request->get('query_type', '') ?: null;
        $category->setQueryType($queryType);

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
