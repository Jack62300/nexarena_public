<?php

namespace App\Controller\Admin;

use App\Entity\Role;
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
#[IsGranted('ROLE_RESPONSABLE')]
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
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('role_form', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('admin_roles_new');
            }

            $name = trim($request->request->get('name', ''));
            if (empty($name)) {
                $this->addFlash('error', 'Le nom du role est requis.');
                return $this->redirectToRoute('admin_roles_new');
            }

            $technicalName = $this->generateTechnicalName($name);

            // Check uniqueness
            if ($this->roleRepo->findOneBy(['technicalName' => $technicalName])) {
                $this->addFlash('error', "Un role avec le nom technique '$technicalName' existe deja.");
                return $this->redirectToRoute('admin_roles_new');
            }

            $role = new Role();
            $role->setName($name);
            $role->setTechnicalName($technicalName);
            $role->setColor($request->request->get('color', '#5a5c69'));
            $role->setPosition((int) $request->request->get('position', 5));
            $role->setDescription($request->request->get('description', ''));
            $role->setIsSystem(false);

            $this->assignPermissions($role, $request->request->all('permissions'));

            $this->em->persist($role);
            $this->em->flush();

            $this->addFlash('success', "Role '$name' cree avec succes.");
            return $this->redirectToRoute('admin_roles_index');
        }

        return $this->render('admin/roles/form.html.twig', [
            'role' => null,
            'permissions_by_category' => $this->permissionRepo->findAllGroupedByCategory(),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit')]
    #[IsGranted('roles.edit')]
    public function edit(Role $role, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('role_form', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('admin_roles_edit', ['id' => $role->getId()]);
            }

            // System roles: only allow editing color, description, and permissions
            if (!$role->isSystem()) {
                $name = trim($request->request->get('name', ''));
                if (!empty($name)) {
                    $role->setName($name);
                }
                $role->setPosition((int) $request->request->get('position', $role->getPosition()));
            }

            $role->setColor($request->request->get('color', $role->getColor()));
            $role->setDescription($request->request->get('description', ''));

            $this->assignPermissions($role, $request->request->all('permissions'));

            $this->em->flush();

            $this->addFlash('success', "Role '{$role->getName()}' modifie avec succes.");
            return $this->redirectToRoute('admin_roles_index');
        }

        return $this->render('admin/roles/form.html.twig', [
            'role' => $role,
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
            $this->addFlash('error', 'Les roles systeme ne peuvent pas etre supprimes.');
            return $this->redirectToRoute('admin_roles_index');
        }

        // Check if users have this role
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

        $message = "Role '{$role->getName()}' supprime.";
        if ($affectedUsers > 0) {
            $message .= " $affectedUsers utilisateur(s) mis a jour.";
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
