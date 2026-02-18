<?php

namespace App\Controller\Admin;

use App\Entity\Transaction;
use App\Entity\User;
use App\Form\Admin\AdminUserFormType;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use App\Service\WebhookService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users', name: 'admin_users_')]
#[IsGranted('ROLE_MANAGER')]
class UserController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private RoleRepository $roleRepo,
        private WebhookService $webhookService,
    ) {
    }

    #[Route('', name: 'list')]
    public function list(UserRepository $repo): Response
    {
        return $this->render('admin/users/list.html.twig', [
            'users' => $repo->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit')]
    #[IsGranted('users.edit')]
    public function edit(User $user, Request $request): Response
    {
        $assignableRoles = $this->roleRepo->findBy(
            [],
            ['position' => 'ASC'],
        );

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('user_edit', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('admin_users_edit', ['id' => $user->getId()]);
            }

            $user->setUsername($request->request->get('username', $user->getUsername()));

            $selectedRoles = $request->request->all('roles') ?: [];
            // Validate against existing roles in database
            $validTechnicalNames = array_map(
                fn($r) => $r->getTechnicalName(),
                $assignableRoles,
            );
            $roles = array_intersect($selectedRoles, $validTechnicalNames);
            // Remove ROLE_USER as it's always added automatically
            $roles = array_filter($roles, fn($r) => $r !== 'ROLE_USER');

            // Prevent role escalation: cannot assign roles equal or higher than own
            $roleHierarchy = [
                'ROLE_EDITEUR' => 1,
                'ROLE_MANAGER' => 2,
                'ROLE_RESPONSABLE' => 3,
                'ROLE_DEVELOPPEUR' => 4,
                'ROLE_FONDATEUR' => 5,
            ];
            $currentUser = $this->getUser();
            $currentMaxLevel = 0;
            foreach ($currentUser->getRoles() as $r) {
                $currentMaxLevel = max($currentMaxLevel, $roleHierarchy[$r] ?? 0);
            }
            $roles = array_filter($roles, fn($r) => ($roleHierarchy[$r] ?? 0) < $currentMaxLevel);

            $user->setRoles(array_values($roles));

            $this->em->flush();

            $this->addFlash('success', 'Utilisateur modifie avec succes.');
            return $this->redirectToRoute('admin_users_list');
        }

        return $this->render('admin/users/edit.html.twig', [
            'user' => $user,
            'assignable_roles' => $assignableRoles,
        ]);
    }

    #[Route('/{id}/credit', name: 'credit', methods: ['POST'])]
    #[IsGranted('users.credit')]
    public function credit(User $user, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('user_credit_' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_users_edit', ['id' => $user->getId()]);
        }

        $nexbits = $request->request->getInt('nexbits', 0);
        $nexboost = $request->request->getInt('nexboost', 0);
        $reason = trim($request->request->get('credit_reason', ''));

        if ($nexbits === 0 && $nexboost === 0) {
            $this->addFlash('error', 'Veuillez entrer un montant.');
            return $this->redirectToRoute('admin_users_edit', ['id' => $user->getId()]);
        }

        // Apply credits (can be negative for debit)
        if ($nexbits !== 0) {
            $newBalance = $user->getTokenBalance() + $nexbits;
            $user->setTokenBalance(max(0, $newBalance));
        }

        if ($nexboost !== 0) {
            $newBalance = $user->getBoostTokenBalance() + $nexboost;
            $user->setBoostTokenBalance(max(0, $newBalance));
        }

        // Log transaction
        $tx = new Transaction();
        $tx->setUser($user);
        $tx->setType(Transaction::TYPE_ADMIN_CREDIT);
        $tx->setTokensAmount($nexbits);
        $tx->setBoostTokensAmount($nexboost);
        $desc = 'Credit admin par ' . $this->getUser()->getUsername();
        if ($reason) {
            $desc .= ' : ' . mb_substr($reason, 0, 200);
        }
        $tx->setDescription($desc);
        $this->em->persist($tx);

        $this->em->flush();

        $parts = [];
        if ($nexbits !== 0) {
            $parts[] = ($nexbits > 0 ? '+' : '') . $nexbits . ' NexBits';
        }
        if ($nexboost !== 0) {
            $parts[] = ($nexboost > 0 ? '+' : '') . $nexboost . ' NexBoost';
        }

        $this->webhookService->dispatch('admin.tokens_credited', [
            'title' => 'Tokens credites par admin',
            'fields' => [
                ['name' => 'Utilisateur', 'value' => $user->getUsername(), 'inline' => true],
                ['name' => 'Montant', 'value' => implode(', ', $parts), 'inline' => true],
                ['name' => 'Par', 'value' => $this->getUser()->getUsername(), 'inline' => true],
                ['name' => 'Raison', 'value' => $reason ?: '-', 'inline' => false],
            ],
        ]);

        $this->addFlash('success', implode(', ', $parts) . ' credite(s) a ' . $user->getUsername() . '.');
        return $this->redirectToRoute('admin_users_edit', ['id' => $user->getId()]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('users.delete')]
    public function delete(User $user, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete_' . $user->getId(), $request->request->get('_token'))) {
            if ($user === $this->getUser()) {
                $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
                return $this->redirectToRoute('admin_users_list');
            }

            $this->em->remove($user);
            $this->em->flush();
            $this->addFlash('success', 'Utilisateur supprime.');
        }

        return $this->redirectToRoute('admin_users_list');
    }
}
