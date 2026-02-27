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
        ['code' => 'articles.list',   'label' => 'Voir la liste des articles', 'category' => 'articles'],
        ['code' => 'articles.create', 'label' => 'Creer des articles',          'category' => 'articles'],
        ['code' => 'articles.edit',   'label' => 'Modifier des articles',        'category' => 'articles'],
        ['code' => 'articles.delete', 'label' => 'Supprimer des articles',       'category' => 'articles'],

        // Categories
        ['code' => 'categories.list',   'label' => 'Voir les categories',       'category' => 'categories'],
        ['code' => 'categories.create', 'label' => 'Creer des categories',       'category' => 'categories'],
        ['code' => 'categories.edit',   'label' => 'Modifier des categories',    'category' => 'categories'],
        ['code' => 'categories.delete', 'label' => 'Supprimer des categories',   'category' => 'categories'],

        // Servers
        ['code' => 'servers.list',   'label' => 'Voir les serveurs',         'category' => 'servers'],
        ['code' => 'servers.create', 'label' => 'Creer des serveurs',         'category' => 'servers'],
        ['code' => 'servers.edit',   'label' => 'Modifier / approuver les serveurs', 'category' => 'servers'],
        ['code' => 'servers.delete', 'label' => 'Supprimer des serveurs',     'category' => 'servers'],

        // Server Types
        ['code' => 'server_types.list',   'label' => 'Voir les types de serveur',    'category' => 'server_types'],
        ['code' => 'server_types.manage', 'label' => 'Gerer les types de serveur',   'category' => 'server_types'],

        // Votes
        ['code' => 'votes.list',   'label' => 'Voir les votes',    'category' => 'votes'],
        ['code' => 'votes.manage', 'label' => 'Gerer les votes',   'category' => 'votes'],

        // Comments
        ['code' => 'comments.list',     'label' => 'Voir les commentaires',          'category' => 'comments'],
        ['code' => 'comments.moderate', 'label' => 'Moderer / supprimer les commentaires', 'category' => 'comments'],

        // Tags
        ['code' => 'tags.list',   'label' => 'Voir les tags',    'category' => 'tags'],
        ['code' => 'tags.manage', 'label' => 'Gerer les tags',   'category' => 'tags'],

        // Achievements
        ['code' => 'achievements.list',   'label' => 'Voir les succès',               'category' => 'achievements'],
        ['code' => 'achievements.manage', 'label' => 'Gérer / attribuer les succès',  'category' => 'achievements'],

        // Plugins
        ['code' => 'plugins.list',   'label' => 'Voir les plugins',    'category' => 'plugins'],
        ['code' => 'plugins.manage', 'label' => 'Gerer les plugins',   'category' => 'plugins'],

        // Partners
        ['code' => 'partners.list',   'label' => 'Voir les partenaires',    'category' => 'partners'],
        ['code' => 'partners.manage', 'label' => 'Gerer les partenaires',   'category' => 'partners'],

        // Recruitment (admin)
        ['code' => 'recruitment.list',     'label' => 'Voir les annonces de recrutement',          'category' => 'recruitment'],
        ['code' => 'recruitment.moderate', 'label' => 'Approuver / rejeter les annonces',          'category' => 'recruitment'],

        // Premium / Transactions
        ['code' => 'transactions.list',    'label' => 'Voir les transactions',              'category' => 'premium'],
        ['code' => 'premium_plans.list',   'label' => 'Voir les plans premium',             'category' => 'premium'],
        ['code' => 'premium_plans.manage', 'label' => 'Creer / modifier des plans premium', 'category' => 'premium'],

        // Featured (selection premium admin)
        ['code' => 'featured.list',   'label' => 'Voir les selections premium', 'category' => 'featured'],
        ['code' => 'featured.manage', 'label' => 'Gerer les selections premium', 'category' => 'featured'],

        // Discord bot
        ['code' => 'discord.manage', 'label' => 'Gerer le bot Discord', 'category' => 'discord'],

        // Themes
        ['code' => 'themes.manage', 'label' => 'Gerer les images de themes', 'category' => 'themes'],

        // Users
        ['code' => 'users.list',   'label' => 'Voir les utilisateurs',                   'category' => 'users'],
        ['code' => 'users.edit',   'label' => 'Modifier les utilisateurs',                'category' => 'users'],
        ['code' => 'users.credit', 'label' => 'Crediter des tokens aux utilisateurs',    'category' => 'users'],
        ['code' => 'users.delete', 'label' => 'Supprimer des utilisateurs',               'category' => 'users'],

        // Roles
        ['code' => 'roles.view',   'label' => 'Voir les roles et permissions',   'category' => 'roles'],
        ['code' => 'roles.create', 'label' => 'Creer de nouveaux roles',          'category' => 'roles'],
        ['code' => 'roles.edit',   'label' => 'Modifier les roles et permissions', 'category' => 'roles'],
        ['code' => 'roles.delete', 'label' => 'Supprimer les roles',               'category' => 'roles'],

        // Settings
        ['code' => 'settings.view', 'label' => 'Voir les parametres',      'category' => 'settings'],
        ['code' => 'settings.edit', 'label' => 'Modifier les parametres',  'category' => 'settings'],

        // Webhooks
        ['code' => 'webhooks.list',   'label' => 'Voir les webhooks',    'category' => 'webhooks'],
        ['code' => 'webhooks.manage', 'label' => 'Gerer les webhooks',   'category' => 'webhooks'],

        // Logs
        ['code' => 'logs.view',   'label' => 'Voir les logs systeme',            'category' => 'logs'],
        ['code' => 'logs.access', 'label' => 'Voir les logs d\'acces IP',        'category' => 'logs'],
        ['code' => 'logs.purge',  'label' => 'Purger les logs d\'acces IP',      'category' => 'logs'],

        // Security
        ['code' => 'security.manage',  'label' => 'Gerer les regles de securite et acces', 'category' => 'security'],
        ['code' => 'blacklist.manage', 'label' => 'Gerer la liste noire',                   'category' => 'security'],
        ['code' => 'ip_bans.manage',   'label' => 'Gerer les bans IP',                      'category' => 'security'],

        // Users (extra)
        ['code' => 'users.ban', 'label' => 'Bannir / debannir des utilisateurs', 'category' => 'users'],

        // Transactions (extra)
        ['code' => 'transactions.delete', 'label' => 'Supprimer des transactions', 'category' => 'premium'],
    ];

    private const DEFAULT_ROLES = [
        [
            'name' => 'Utilisateur',
            'technicalName' => 'ROLE_USER',
            'color' => '#5a5c69',
            'position' => 0,
            'description' => 'Acces de base — espace utilisateur uniquement',
            'permissions' => [],
        ],
        [
            'name' => 'Editeur',
            'technicalName' => 'ROLE_EDITEUR',
            'color' => '#1cc88a',
            'position' => 10,
            'description' => 'Consultation et edition du contenu',
            'permissions' => [
                'dashboard.view',
                'articles.list', 'articles.create', 'articles.edit',
                'categories.list', 'categories.create', 'categories.edit',
                'servers.list', 'servers.create',
                'server_types.list',
                'votes.list',
                'comments.list',
                'tags.list',
                'achievements.list',
                'plugins.list',
                'partners.list',
                'recruitment.list',
            ],
        ],
        [
            'name' => 'Manager',
            'technicalName' => 'ROLE_MANAGER',
            'color' => '#36b9cc',
            'position' => 20,
            'description' => 'Gestion du contenu, moderation et utilisateurs',
            'permissions' => [
                'dashboard.view',
                'articles.list', 'articles.create', 'articles.edit', 'articles.delete',
                'categories.list', 'categories.create', 'categories.edit', 'categories.delete',
                'servers.list', 'servers.create', 'servers.edit', 'servers.delete',
                'server_types.list', 'server_types.manage',
                'votes.list', 'votes.manage',
                'comments.list', 'comments.moderate',
                'tags.list', 'tags.manage',
                'achievements.list', 'achievements.manage',
                'plugins.list', 'plugins.manage',
                'partners.list', 'partners.manage',
                'recruitment.list', 'recruitment.moderate',
                'transactions.list',
                'premium_plans.list', 'premium_plans.manage',
                'featured.list', 'featured.manage',
                'discord.manage',
                'users.list', 'users.ban',
                'blacklist.manage', 'ip_bans.manage',
            ],
        ],
        [
            'name' => 'Responsable',
            'technicalName' => 'ROLE_RESPONSABLE',
            'color' => '#f6c23e',
            'position' => 30,
            'description' => 'Configuration, permissions et gestion des utilisateurs',
            'permissions' => [
                'dashboard.view',
                'articles.list', 'articles.create', 'articles.edit', 'articles.delete',
                'categories.list', 'categories.create', 'categories.edit', 'categories.delete',
                'servers.list', 'servers.create', 'servers.edit', 'servers.delete',
                'server_types.list', 'server_types.manage',
                'votes.list', 'votes.manage',
                'comments.list', 'comments.moderate',
                'tags.list', 'tags.manage',
                'achievements.list', 'achievements.manage',
                'plugins.list', 'plugins.manage',
                'partners.list', 'partners.manage',
                'recruitment.list', 'recruitment.moderate',
                'transactions.list',
                'premium_plans.list', 'premium_plans.manage',
                'featured.list', 'featured.manage',
                'discord.manage',
                'themes.manage',
                'users.list', 'users.edit', 'users.credit', 'users.ban', 'users.delete',
                'roles.view', 'roles.create', 'roles.edit',
                'settings.view', 'settings.edit',
                'transactions.delete',
                'security.manage', 'blacklist.manage', 'ip_bans.manage',
                'logs.view', 'logs.access', 'logs.purge',
                'webhooks.list', 'webhooks.manage',
            ],
        ],
        [
            'name' => 'Developpeur',
            'technicalName' => 'ROLE_DEVELOPPEUR',
            'color' => '#6fffa0',
            'position' => 40,
            'description' => 'Technique — logs, webhooks et tout le reste',
            'permissions' => [
                'dashboard.view',
                'articles.list', 'articles.create', 'articles.edit', 'articles.delete',
                'categories.list', 'categories.create', 'categories.edit', 'categories.delete',
                'servers.list', 'servers.create', 'servers.edit', 'servers.delete',
                'server_types.list', 'server_types.manage',
                'votes.list', 'votes.manage',
                'comments.list', 'comments.moderate',
                'tags.list', 'tags.manage',
                'achievements.list', 'achievements.manage',
                'plugins.list', 'plugins.manage',
                'partners.list', 'partners.manage',
                'recruitment.list', 'recruitment.moderate',
                'transactions.list',
                'premium_plans.list', 'premium_plans.manage',
                'featured.list', 'featured.manage',
                'discord.manage',
                'themes.manage',
                'users.list', 'users.edit', 'users.credit', 'users.ban', 'users.delete',
                'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
                'settings.view', 'settings.edit',
                'transactions.delete',
                'security.manage', 'blacklist.manage', 'ip_bans.manage',
                'webhooks.list', 'webhooks.manage',
                'logs.view', 'logs.access', 'logs.purge',
            ],
        ],
        [
            'name' => 'Fondateur',
            'technicalName' => 'ROLE_FONDATEUR',
            'color' => '#e74a3b',
            'position' => 50,
            'description' => 'Tous les droits sans restriction',
            'permissions' => [], // Tous les droits geres par le voter (shortcut ROLE_FONDATEUR)
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
