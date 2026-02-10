<?php

namespace App\Security\Exception;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

class OAuthEmailRequiredException extends AuthenticationException
{
    public function getMessageKey(): string
    {
        return 'Un email est requis pour finaliser votre inscription.';
    }
}
