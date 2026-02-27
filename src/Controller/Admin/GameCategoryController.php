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
#[IsGranted('categories.list')]
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
    #[IsGranted('categories.create')]
    public function new(Request $request): Response
    {
        $category = new GameCategory();
        $form = $this->createForm(GameCategoryFormType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $category->setSlug($this->slugService->slugify($category->getName()));

            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/categories';

            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $filename = uniqid() . '.' . $imageFile->guessExtension();
                $imageFile->move($uploadDir, $filename);
                $category->setImage($filename);
            }

            $iconFile = $form->get('iconFile')->getData();
            if ($iconFile) {
                $filename = uniqid() . '.' . $iconFile->guessExtension();
                $iconFile->move($uploadDir, $filename);
                $category->setIcon($filename);
            }

            $raw = $request->request->get('server_form_fields_json', '');
            $fields = json_decode($raw, true);
            $category->setServerFormFields(is_array($fields) && count($fields) > 0 ? $fields : null);

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
    #[IsGranted('categories.edit')]
    public function edit(GameCategory $category, Request $request): Response
    {
        $form = $this->createForm(GameCategoryFormType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $category->setSlug($this->slugService->slugify($category->getName()));

            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/categories';

            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                if ($category->getImage()) {
                    $old = $uploadDir . '/' . basename($category->getImage());
                    if (file_exists($old)) unlink($old);
                }
                $filename = uniqid() . '.' . $imageFile->guessExtension();
                $imageFile->move($uploadDir, $filename);
                $category->setImage($filename);
            }

            $iconFile = $form->get('iconFile')->getData();
            if ($iconFile) {
                if ($category->getIcon()) {
                    $old = $uploadDir . '/' . basename($category->getIcon());
                    if (file_exists($old)) unlink($old);
                }
                $filename = uniqid() . '.' . $iconFile->guessExtension();
                $iconFile->move($uploadDir, $filename);
                $category->setIcon($filename);
            }

            $raw = $request->request->get('server_form_fields_json', '');
            $fields = json_decode($raw, true);
            $category->setServerFormFields(is_array($fields) && count($fields) > 0 ? $fields : null);

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
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/categories';
            foreach (['getImage', 'getIcon'] as $getter) {
                if ($category->$getter()) {
                    $path = $uploadDir . '/' . basename($category->$getter());
                    if (file_exists($path)) unlink($path);
                }
            }
            $this->em->remove($category);
            $this->em->flush();
            $this->addFlash('success', 'Catégorie supprimée.');
        }

        return $this->redirectToRoute('admin_categories_list');
    }
}
