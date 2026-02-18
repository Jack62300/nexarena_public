<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isEmailVerified()) {
            throw new CustomUserMessageAccountStatusException(
                'Votre adresse email n\'est pas vérifiée. Vérifiez votre boîte mail pour activer votre compte.'
            );
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // Post-auth checks handled by LoginIpVerificationListener
    }
}
