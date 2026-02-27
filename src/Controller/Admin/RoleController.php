<?php

namespace App\Controller\Admin;

use App\Entity\Role;
use App\Form\Admin\RoleFormType;
use App\Repository\PermissionRepository;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/admin/roles', name: 'admin_roles_')]
#[IsGranted('roles.view')]
class RoleController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private RoleRepository $roleRepo,
        private PermissionRepository $permissionRepo,
    ) {
    }

    #[Route('', name: 'index')]
    public function index(): Response
    {
        return $this->render('admin/roles/index.html.twig', [
            'roles' => $this->roleRepo->findBy([], ['position' => 'DESC']),
            'permissions_by_category' => $this->permissionRepo->findAllGroupedByCategory(),
        ]);
    }

    #[Route('/new', name: 'new')]
    #[IsGranted('roles.create')]
    public function new(Request $request): Response
    {
        $role = new Role();
        $form = $this->createForm(RoleFormType::class, $role);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $technicalName = $this->generateTechnicalName($role->getName());

            if ($this->roleRepo->findOneBy(['technicalName' => $technicalName])) {
                $this->addFlash('error', "Un rôle avec le nom technique '$technicalName' existe déjà.");
                return $this->redirectToRoute('admin_roles_new');
            }

            $role->setTechnicalName($technicalName);
            $role->setIsSystem(false);

            $this->assignPermissions($role, $request->request->all('permissions'));

            $this->em->persist($role);
            $this->em->flush();

            $this->addFlash('success', "Rôle '{$role->getName()}' créé avec succès.");
            return $this->redirectToRoute('admin_roles_index');
        }

        return $this->render('admin/roles/form.html.twig', [
            'role' => null,
            'form' => $form,
            'permissions_by_category' => $this->permissionRepo->findAllGroupedByCategory(),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit')]
    #[IsGranted('roles.edit')]
    public function edit(Role $role, Request $request): Response
    {
        $form = $this->createForm(RoleFormType::class, $role, [
            'is_system' => $role->isSystem(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->assignPermissions($role, $request->request->all('permissions'));
            $this->em->flush();

            $this->addFlash('success', "Rôle '{$role->getName()}' modifié avec succès.");
            return $this->redirectToRoute('admin_roles_index');
        }

        return $this->render('admin/roles/form.html.twig', [
            'role' => $role,
            'form' => $form,
            'permissions_by_category' => $this->permissionRepo->findAllGroupedByCategory(),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('roles.delete')]
    public function delete(Role $role, Request $request, UserRepository $userRepo): Response
    {
        if (!$this->isCsrfTokenValid('delete_' . $role->getId(), $request->request->get('_token'))) {
            return $this->redirectToRoute('admin_roles_index');
        }

        if ($role->isSystem()) {
            $this->addFlash('error', 'Les rôles système ne peuvent pas être supprimés.');
            return $this->redirectToRoute('admin_roles_index');
        }

        $users = $userRepo->findAll();
        $affectedUsers = 0;
        foreach ($users as $user) {
            if (in_array($role->getTechnicalName(), $user->getRoles(), true)) {
                $roles = array_filter($user->getRoles(), fn($r) => $r !== $role->getTechnicalName());
                $user->setRoles(array_values($roles));
                $affectedUsers++;
            }
        }

        $this->em->remove($role);
        $this->em->flush();

        $message = "Rôle '{$role->getName()}' supprimé.";
        if ($affectedUsers > 0) {
            $message .= " $affectedUsers utilisateur(s) mis à jour.";
        }
        $this->addFlash('success', $message);

        return $this->redirectToRoute('admin_roles_index');
    }

    private function generateTechnicalName(string $name): string
    {
        $slugger = new AsciiSlugger('fr');
        $slug = $slugger->slug($name, '_')->upper()->toString();

        return 'ROLE_' . $slug;
    }

    /**
     * @param string[] $permissionCodes
     */
    private function assignPermissions(Role $role, array $permissionCodes): void
    {
        $role->clearPermissions();

        if (empty($permissionCodes)) {
            return;
        }

        $allPermissions = $this->permissionRepo->findAll();
        $permissionMap = [];
        foreach ($allPermissions as $perm) {
            $permissionMap[$perm->getCode()] = $perm;
        }

        foreach ($permissionCodes as $code) {
            if (isset($permissionMap[$code])) {
                $role->addPermission($permissionMap[$code]);
            }
        }
    }
}
