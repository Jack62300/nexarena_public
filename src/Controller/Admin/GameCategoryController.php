<?php

namespace App\Controller\Admin;

use App\Entity\GameCategory;
use App\Form\Admin\GameCategoryFormType;
use App\Repository\CategoryRepository;
use App\Repository\GameCategoryRepository;
use App\Service\SlugService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    public function new(Request $request): Response
    {
        $category = new GameCategory();
        $form = $this->createForm(GameCategoryFormType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $category->setSlug($this->slugService->slugify($category->getName()));

            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $filename = uniqid() . '.' . $imageFile->guessExtension();
                $imageFile->move(
                    $this->getParameter('kernel.project_dir') . '/public/uploads/categories',
                    $filename,
                );
                $category->setImage($filename);
            }

            $this->em->persist($category);
            $this->em->flush();

            $this->addFlash('success', 'Sous-catégorie créée avec succès.');
            return $this->redirectToRoute('admin_categories_list');
        }

        return $this->render('admin/categories/form.html.twig', [
            'category' => null,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit')]
    public function edit(GameCategory $category, Request $request): Response
    {
        $form = $this->createForm(GameCategoryFormType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $category->setSlug($this->slugService->slugify($category->getName()));

            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $filename = uniqid() . '.' . $imageFile->guessExtension();
                $imageFile->move(
                    $this->getParameter('kernel.project_dir') . '/public/uploads/categories',
                    $filename,
                );

                if ($category->getImage()) {
                    $oldPath = $this->getParameter('kernel.project_dir') . '/public/uploads/categories/' . basename($category->getImage());
                    if (file_exists($oldPath)) {
                        unlink($oldPath);
                    }
                }

                $category->setImage($filename);
            }

            $this->em->flush();

            $this->addFlash('success', 'Sous-catégorie modifiée avec succès.');
            return $this->redirectToRoute('admin_categories_list');
        }

        return $this->render('admin/categories/form.html.twig', [
            'category' => $category,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('categories.delete')]
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
            $this->addFlash('success', 'Catégorie supprimée.');
        }

        return $this->redirectToRoute('admin_categories_list');
    }
}
