<?php

namespace App\Command;

use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:promote-user',
    description: 'Promouvoir un utilisateur avec un role specifique',
)]
class PromoteUserCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private RoleRepository $roleRepository,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email de l\'utilisateur')
            ->addArgument('role', InputArgument::REQUIRED, 'Nom technique du role (ex: ROLE_EDITEUR)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getArgument('email');
        $roleName = strtoupper($input->getArgument('role'));

        // Validate against database roles
        $role = $this->roleRepository->findOneBy(['technicalName' => $roleName]);
        if (!$role) {
            $allRoles = $this->roleRepository->findAll();
            $validNames = array_map(fn($r) => $r->getTechnicalName(), $allRoles);
            $io->error("Role invalide '$roleName'. Roles disponibles : " . implode(', ', $validNames));
            return Command::FAILURE;
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user) {
            $io->error("Aucun utilisateur trouve avec l'email : $email");
            return Command::FAILURE;
        }

        $roles = $user->getRoles();
        if (!in_array($roleName, $roles, true)) {
            $roles[] = $roleName;
            $user->setRoles(array_values(array_unique($roles)));
            $this->em->flush();
        }

        $io->success("L'utilisateur {$user->getUsername()} ($email) a maintenant le role {$role->getName()} ($roleName).");

        return Command::SUCCESS;
    }
}
