<?php

namespace App\Twig;

use App\Entity\Role;
use App\Repository\RoleRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class RoleExtension extends AbstractExtension
{
    /** @var array<string, Role>|null */
    private ?array $roleCache = null;

    public function __construct(
        private RoleRepository $roleRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('role_badge', [$this, 'roleBadge'], ['is_safe' => ['html']]),
            new TwigFunction('get_role', [$this, 'getRole']),
            new TwigFunction('get_highest_role', [$this, 'getHighestRole']),
        ];
    }

    public function getRole(string $technicalName): ?Role
    {
        $this->loadRoles();

        return $this->roleCache[$technicalName] ?? null;
    }

    /**
     * @param string[] $userRoles
     */
    public function getHighestRole(array $userRoles): ?Role
    {
        $this->loadRoles();

        $highest = null;
        foreach ($userRoles as $roleName) {
            $role = $this->roleCache[$roleName] ?? null;
            if ($role && ($highest === null || $role->getPosition() > $highest->getPosition())) {
                $highest = $role;
            }
        }

        return $highest;
    }

    /**
     * @param string[] $userRoles
     */
    public function roleBadge(array $userRoles): string
    {
        $role = $this->getHighestRole($userRoles);
        if (!$role) {
            return '<span class="role-badge" style="background:#5a5c6922;color:#8b949e;border:1px solid #5a5c6944;">Utilisateur</span>';
        }

        $color = htmlspecialchars($role->getColor(), ENT_QUOTES);
        $name = htmlspecialchars($role->getName(), ENT_QUOTES);

        return sprintf(
            '<span class="role-badge" style="background:%s22;color:%s;border:1px solid %s44;">%s</span>',
            $color,
            $color,
            $color,
            $name,
        );
    }

    private function loadRoles(): void
    {
        if ($this->roleCache !== null) {
            return;
        }

        $this->roleCache = [];
        $roles = $this->roleRepository->findAll();
        foreach ($roles as $role) {
            $this->roleCache[$role->getTechnicalName()] = $role;
        }
    }
}
