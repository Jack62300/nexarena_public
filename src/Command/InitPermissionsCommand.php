<?php

namespace App\Command;

use App\Entity\Permission;
use App\Entity\Role;
use App\Repository\PermissionRepository;
use App\Repository\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:init-permissions',
    description: 'Initialiser les roles et permissions par defaut',
)]
class InitPermissionsCommand extends Command
{
    private const DEFAULT_PERMISSIONS = [
        // Dashboard
        ['code' => 'dashboard.view', 'label' => 'Voir le dashboard', 'category' => 'dashboard'],
        // Articles
        ['code' => 'articles.list', 'label' => 'Voir la liste des articles', 'category' => 'articles'],
        ['code' => 'articles.create', 'label' => 'Creer des articles', 'category' => 'articles'],
        ['code' => 'articles.edit', 'label' => 'Modifier des articles', 'category' => 'articles'],
        ['code' => 'articles.delete', 'label' => 'Supprimer des articles', 'category' => 'articles'],
        // Categories
        ['code' => 'categories.list', 'label' => 'Voir les categories de jeux', 'category' => 'categories'],
        ['code' => 'categories.create', 'label' => 'Creer des categories', 'category' => 'categories'],
        ['code' => 'categories.edit', 'label' => 'Modifier des categories', 'category' => 'categories'],
        ['code' => 'categories.delete', 'label' => 'Supprimer des categories', 'category' => 'categories'],
        // Servers
        ['code' => 'servers.list', 'label' => 'Voir les serveurs', 'category' => 'servers'],
        ['code' => 'servers.create', 'label' => 'Creer des serveurs', 'category' => 'servers'],
        ['code' => 'servers.edit', 'label' => 'Modifier des serveurs', 'category' => 'servers'],
        ['code' => 'servers.delete', 'label' => 'Supprimer des serveurs', 'category' => 'servers'],
        // Votes
        ['code' => 'votes.list', 'label' => 'Voir les votes', 'category' => 'votes'],
        ['code' => 'votes.manage', 'label' => 'Gerer les votes', 'category' => 'votes'],
        // Users
        ['code' => 'users.list', 'label' => 'Voir les utilisateurs', 'category' => 'users'],
        ['code' => 'users.edit', 'label' => 'Modifier les utilisateurs', 'category' => 'users'],
        ['code' => 'users.delete', 'label' => 'Supprimer les utilisateurs', 'category' => 'users'],
        // Roles
        ['code' => 'roles.view', 'label' => 'Voir les roles et permissions', 'category' => 'roles'],
        ['code' => 'roles.create', 'label' => 'Creer de nouveaux roles', 'category' => 'roles'],
        ['code' => 'roles.edit', 'label' => 'Modifier les roles et permissions', 'category' => 'roles'],
        ['code' => 'roles.delete', 'label' => 'Supprimer les roles', 'category' => 'roles'],
        // Settings
        ['code' => 'settings.view', 'label' => 'Voir les parametres', 'category' => 'settings'],
        ['code' => 'settings.edit', 'label' => 'Modifier les parametres', 'category' => 'settings'],
        // Webhooks
        ['code' => 'webhooks.list', 'label' => 'Voir les webhooks', 'category' => 'webhooks'],
        ['code' => 'webhooks.manage', 'label' => 'Gerer les webhooks', 'category' => 'webhooks'],
        // Logs
        ['code' => 'logs.view', 'label' => 'Voir les logs', 'category' => 'logs'],
    ];

    private const DEFAULT_ROLES = [
        [
            'name' => 'Utilisateur',
            'technicalName' => 'ROLE_USER',
            'color' => '#5a5c69',
            'position' => 0,
            'description' => 'Acces de base',
            'permissions' => [],
        ],
        [
            'name' => 'Editeur',
            'technicalName' => 'ROLE_EDITEUR',
            'color' => '#1cc88a',
            'position' => 10,
            'description' => 'Edition d\'articles et categories',
            'permissions' => [
                'dashboard.view',
                'articles.list', 'articles.create', 'articles.edit',
                'categories.list', 'categories.create', 'categories.edit',
                'servers.list', 'servers.create', 'servers.edit',
                'votes.list',
            ],
        ],
        [
            'name' => 'Manager',
            'technicalName' => 'ROLE_MANAGER',
            'color' => '#36b9cc',
            'position' => 20,
            'description' => 'Gestion du contenu et des utilisateurs',
            'permissions' => [
                'dashboard.view',
                'articles.list', 'articles.create', 'articles.edit', 'articles.delete',
                'categories.list', 'categories.create', 'categories.edit', 'categories.delete',
                'servers.list', 'servers.create', 'servers.edit', 'servers.delete',
                'votes.list', 'votes.manage',
                'users.list',
            ],
        ],
        [
            'name' => 'Responsable',
            'technicalName' => 'ROLE_RESPONSABLE',
            'color' => '#f6c23e',
            'position' => 30,
            'description' => 'Configuration et permissions',
            'permissions' => [
                'dashboard.view',
                'articles.list', 'articles.create', 'articles.edit', 'articles.delete',
                'categories.list', 'categories.create', 'categories.edit', 'categories.delete',
                'servers.list', 'servers.create', 'servers.edit', 'servers.delete',
                'votes.list', 'votes.manage',
                'users.list', 'users.edit',
                'roles.view', 'roles.edit',
                'settings.view', 'settings.edit',
            ],
        ],
        [
            'name' => 'Developpeur',
            'technicalName' => 'ROLE_DEVELOPPEUR',
            'color' => '#6fffa0',
            'position' => 40,
            'description' => 'Technique, logs, webhooks',
            'permissions' => [
                'dashboard.view',
                'articles.list', 'articles.create', 'articles.edit', 'articles.delete',
                'categories.list', 'categories.create', 'categories.edit', 'categories.delete',
                'servers.list', 'servers.create', 'servers.edit', 'servers.delete',
                'votes.list', 'votes.manage',
                'users.list', 'users.edit',
                'roles.view', 'roles.edit',
                'settings.view', 'settings.edit',
                'webhooks.list', 'webhooks.manage',
                'logs.view',
            ],
        ],
        [
            'name' => 'Fondateur',
            'technicalName' => 'ROLE_FONDATEUR',
            'color' => '#e74a3b',
            'position' => 50,
            'description' => 'Tous les droits',
            'permissions' => [], // All permissions handled via voter shortcut
        ],
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private PermissionRepository $permissionRepo,
        private RoleRepository $roleRepo,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Reset les permissions des roles systeme aux valeurs par defaut');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');

        // Create permissions
        $permissionMap = [];
        $createdPerms = 0;
        foreach (self::DEFAULT_PERMISSIONS as $data) {
            $perm = $this->permissionRepo->findOneBy(['code' => $data['code']]);
            if (!$perm) {
                $perm = new Permission();
                $perm->setCode($data['code']);
                $perm->setLabel($data['label']);
                $perm->setCategory($data['category']);
                $this->em->persist($perm);
                $createdPerms++;
            }
            $permissionMap[$data['code']] = $perm;
        }

        $io->info("Permissions : $createdPerms creees, " . (count(self::DEFAULT_PERMISSIONS) - $createdPerms) . " existantes.");

        // Create roles
        $createdRoles = 0;
        $updatedRoles = 0;
        foreach (self::DEFAULT_ROLES as $data) {
            $role = $this->roleRepo->findOneBy(['technicalName' => $data['technicalName']]);
            $isNew = false;

            if (!$role) {
                $role = new Role();
                $role->setTechnicalName($data['technicalName']);
                $role->setIsSystem(true);
                $this->em->persist($role);
                $isNew = true;
                $createdRoles++;
            }

            if ($isNew || $force) {
                $role->setName($data['name']);
                $role->setColor($data['color']);
                $role->setPosition($data['position']);
                $role->setDescription($data['description']);

                // Assign permissions
                $role->clearPermissions();

                // Fondateur gets ALL permissions
                if ($data['technicalName'] === 'ROLE_FONDATEUR') {
                    foreach ($permissionMap as $perm) {
                        $role->addPermission($perm);
                    }
                } else {
                    foreach ($data['permissions'] as $code) {
                        if (isset($permissionMap[$code])) {
                            $role->addPermission($permissionMap[$code]);
                        }
                    }
                }

                if (!$isNew) {
                    $updatedRoles++;
                }
            }
        }

        $this->em->flush();

        $io->info("Roles : $createdRoles crees, $updatedRoles mis a jour.");
        $io->success('Permissions et roles initialises avec succes.');

        return Command::SUCCESS;
    }
}
