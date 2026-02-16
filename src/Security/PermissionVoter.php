<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\RoleRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class PermissionVoter extends Voter
{
    /** @var array<string, bool>|null */
    private ?array $cachedPermissions = null;
    private ?int $cachedUserId = null;

    public function __construct(
        private RoleRepository $roleRepository,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return str_contains($attribute, '.');
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        // Fondateur always has all permissions
        if (in_array('ROLE_FONDATEUR', $user->getRoles(), true)) {
            return true;
        }

        // Use per-request cache
        if ($this->cachedUserId !== $user->getId()) {
            $this->cachedPermissions = null;
            $this->cachedUserId = $user->getId();
        }

        if ($this->cachedPermissions === null) {
            $this->cachedPermissions = [];
            $roles = $this->roleRepository->findByTechnicalNames($user->getRoles());

            foreach ($roles as $role) {
                foreach ($role->getPermissions() as $permission) {
                    $this->cachedPermissions[$permission->getCode()] = true;
                }
            }
        }

        return $this->cachedPermissions[$attribute] ?? false;
    }
}
