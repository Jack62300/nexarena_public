<?php

namespace App\Twig;

use App\Repository\UserRepository;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class DevToolbarExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private string $kernelEnvironment,
    ) {}

    public function getGlobals(): array
    {
        if ($this->kernelEnvironment !== 'dev') {
            return [];
        }

        $users = $this->userRepository->createQueryBuilder('u')
            ->orderBy('u.id', 'ASC')
            ->setMaxResults(30)
            ->getQuery()
            ->getResult();

        return [
            '_dev_users' => $users,
        ];
    }
}
