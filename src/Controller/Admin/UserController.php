<?php

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\Transaction;
use App\Entity\User;
use App\Form\Admin\AdminUserFormType;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use App\Service\ActivityLogService;
use App\Service\UserDeletionService;
use App\Service\WebhookService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users', name: 'admin_users_')]
#[IsGranted('users.list')]
class UserController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private RoleRepository $roleRepo,
        private WebhookService $webhookService,
        private ActivityLogService $activityLog,
        private UserDeletionService $userDeletion,
    ) {
    }

    #[Route('', name: 'list')]
    public function list(UserRepository $repo): Response
    {
        return $this->render('admin/users/list.html.twig', [
            'users' => $repo->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/bans', name: 'bans')]
    public function bans(UserRepository $repo): Response
    {
        return $this->render('admin/users/bans.html.twig', [
            'banned_users' => $repo->findBanned(),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit')]
    #[IsGranted('users.edit')]
    public function edit(User $user, Request $request): Response
    {
        $allRoles        = $this->roleRepo->findBy([], ['position' => 'ASC']);
        $assignableRoles = $this->filterAssignableRoleObjects($allRoles);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('user_edit', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('admin_users_edit', ['id' => $user->getId()]);
            }

            $user->setUsername($request->request->get('username', $user->getUsername()));

            $selectedRoles = $request->request->all('roles') ?: [];

            // Validate against the assignable role names only (server-side guard)
            $assignableNames = array_map(fn($r) => $r->getTechnicalName(), $assignableRoles);
            $roles = array_intersect($selectedRoles, $assignableNames);
            $roles = array_filter($roles, fn($r) => $r !== 'ROLE_USER');

            $user->setRoles(array_values($roles));

            $this->em->flush();

            $this->activityLog->log('user.edit', ActivityLog::CAT_USER, 'User', $user->getId(), $user->getUsername(), [
                'roles' => $user->getRoles(),
            ]);

            $this->addFlash('success', 'Utilisateur modifie avec succes.');
            return $this->redirectToRoute('admin_users_list');
        }

        return $this->render('admin/users/edit.html.twig', [
            'user'             => $user,
            'assignable_roles' => $assignableRoles,
        ]);
    }

    /**
     * Retourne les rôles que l'utilisateur courant est autorisé à assigner.
     *
     * Hiérarchie : EDITEUR(1) < MANAGER(2) < RESPONSABLE(3) < DEVELOPPEUR(4) < FONDATEUR(5)
     *
     * Règles :
     *   - DEVELOPPEUR  → peut tout assigner (FONDATEUR compris)
     *   - RESPONSABLE  → peut assigner tout sauf FONDATEUR et DEVELOPPEUR
     *   - Autres       → peut assigner uniquement les niveaux strictement inférieurs au sien
     */
    private function filterAssignableRoleObjects(array $allRoles): array
    {
        $hierarchy = [
            'ROLE_EDITEUR'     => 1,
            'ROLE_MANAGER'     => 2,
            'ROLE_RESPONSABLE' => 3,
            'ROLE_DEVELOPPEUR' => 4,
            'ROLE_FONDATEUR'   => 5,
        ];

        $currentMax = 0;
        foreach ($this->getUser()->getRoles() as $r) {
            $currentMax = max($currentMax, $hierarchy[$r] ?? 0);
        }

        return array_values(array_filter($allRoles, function ($role) use ($hierarchy, $currentMax): bool {
            $name  = $role->getTechnicalName();
            $level = $hierarchy[$name] ?? 0;

            if ($name === 'ROLE_USER') {
                return false;
            }

            // DEVELOPPEUR : peut tout assigner
            if ($currentMax === 4) {
                return true;
            }

            // RESPONSABLE : peut assigner tout sauf FONDATEUR et DEVELOPPEUR
            if ($currentMax === 3) {
                return !in_array($name, ['ROLE_FONDATEUR', 'ROLE_DEVELOPPEUR'], true);
            }

            // Règle générale : niveau strictement inférieur au sien
            return $level < $currentMax;
        }));
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

        $this->activityLog->log('user.credit', ActivityLog::CAT_USER, 'User', $user->getId(), $user->getUsername(), [
            'nexbits'  => $nexbits,
            'nexboost' => $nexboost,
            'reason'   => $reason,
        ]);

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

    #[Route('/{id}/ban', name: 'ban', methods: ['POST'])]
    #[IsGranted('users.ban')]
    public function ban(User $user, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('user_ban_' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_users_list');
        }

        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas vous bannir vous-même.');
            return $this->redirectToRoute('admin_users_list');
        }

        $roleHierarchy = ['ROLE_EDITEUR' => 1, 'ROLE_MANAGER' => 2, 'ROLE_RESPONSABLE' => 3, 'ROLE_DEVELOPPEUR' => 4, 'ROLE_FONDATEUR' => 5];
        $currentUser = $this->getUser();
        $currentMaxLevel = 0;
        foreach ($currentUser->getRoles() as $r) {
            $currentMaxLevel = max($currentMaxLevel, $roleHierarchy[$r] ?? 0);
        }
        $targetMaxLevel = 0;
        foreach ($user->getRoles() as $r) {
            $targetMaxLevel = max($targetMaxLevel, $roleHierarchy[$r] ?? 0);
        }
        if ($targetMaxLevel >= $currentMaxLevel) {
            $this->addFlash('error', 'Vous ne pouvez pas bannir un utilisateur avec un rôle égal ou supérieur au vôtre.');
            return $this->redirectToRoute('admin_users_list');
        }

        $type = $request->request->get('type', 'permanent');
        $reason = trim($request->request->get('reason', '')) ?: null;

        $expiresAt = null;
        if ($type === 'temporary') {
            $duration = max(1, min(365, $request->request->getInt('duration', 1)));
            $unit = $request->request->get('duration_unit', 'days');
            $interval = $unit === 'hours'
                ? new \DateInterval('PT' . $duration . 'H')
                : new \DateInterval('P' . $duration . 'D');
            $expiresAt = (new \DateTimeImmutable())->add($interval);
        }

        $user->ban($reason, $expiresAt, $currentUser);
        $this->em->flush();

        $this->activityLog->log('user.ban', ActivityLog::CAT_USER, 'User', $user->getId(), $user->getUsername(), [
            'reason'    => $reason,
            'type'      => $type,
            'expiresAt' => $expiresAt?->format('Y-m-d H:i'),
        ]);

        $this->addFlash('success', sprintf(
            'Utilisateur %s banni %s.',
            $user->getUsername(),
            $expiresAt ? 'jusqu\'au ' . $expiresAt->format('d/m/Y à H:i') : 'définitivement'
        ));

        return $this->redirectToRoute('admin_users_list');
    }

    #[Route('/{id}/unban', name: 'unban', methods: ['POST'])]
    #[IsGranted('users.ban')]
    public function unban(User $user, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('user_unban_' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_users_list');
        }

        $user->unban();
        $this->em->flush();

        $this->activityLog->log('user.unban', ActivityLog::CAT_USER, 'User', $user->getId(), $user->getUsername());

        $this->addFlash('success', 'Le ban de ' . $user->getUsername() . ' a été levé.');
        return $this->redirectToRoute('admin_users_list');
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('users.delete')]
    public function delete(User $user, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('user_delete_' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_users_list');
        }

        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
            return $this->redirectToRoute('admin_users_list');
        }

        // Prevent deleting users with equal or higher role
        $roleHierarchy = [
            'ROLE_EDITEUR'     => 1,
            'ROLE_MANAGER'     => 2,
            'ROLE_RESPONSABLE' => 3,
            'ROLE_DEVELOPPEUR' => 4,
            'ROLE_FONDATEUR'   => 5,
        ];
        $currentUser = $this->getUser();
        $currentMax  = 0;
        foreach ($currentUser->getRoles() as $r) {
            $currentMax = max($currentMax, $roleHierarchy[$r] ?? 0);
        }
        $targetMax = 0;
        foreach ($user->getRoles() as $r) {
            $targetMax = max($targetMax, $roleHierarchy[$r] ?? 0);
        }
        if ($targetMax >= $currentMax) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer un utilisateur avec un rôle égal ou supérieur au vôtre.');
            return $this->redirectToRoute('admin_users_list');
        }

        $username = $user->getUsername();
        $userId   = $user->getId();

        try {
            $stats = $this->userDeletion->deleteUser($user);

            $this->activityLog->log('user.delete', ActivityLog::CAT_USER, 'User', $userId, $username, [
                'servers'     => $stats['servers']    ?? 0,
                'comments'    => $stats['comments']   ?? 0,
                'listings'    => $stats['listings']   ?? 0,
                'messages'    => $stats['messages']   ?? 0,
            ]);

            $this->addFlash('success', sprintf(
                'Utilisateur "%s" supprimé avec toutes ses données (%d serveur(s), %d commentaire(s), %d annonce(s)).',
                $username,
                $stats['servers']  ?? 0,
                $stats['comments'] ?? 0,
                $stats['listings'] ?? 0,
            ));
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur lors de la suppression : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_users_list');
    }
}
