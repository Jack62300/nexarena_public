<?php

namespace App\EventListener;

use App\Entity\User;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Requires 2FA to be enabled on the user's account to access /admin/*.
 * If 2FA is not enabled, redirects to the profile page with a flash message.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 6)]
class Admin2faListener
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Only apply to /admin routes
        if (!str_starts_with($path, '/admin')) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if ($token === null) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return;
        }

        // Check if user has ROLE_EDITEUR or higher (admin access)
        $roles = $user->getRoles();
        $adminRoles = ['ROLE_EDITEUR', 'ROLE_MANAGER', 'ROLE_RESPONSABLE', 'ROLE_DEVELOPPEUR', 'ROLE_FONDATEUR'];
        $isAdmin = !empty(array_intersect($roles, $adminRoles));

        if (!$isAdmin) {
            return;
        }

        // If 2FA is not enabled, block admin access
        if (!$user->isTwoFactorEnabled()) {
            $request->getSession()->getFlashBag()->add(
                'error',
                'Vous devez activer l\'authentification a deux facteurs (2FA) pour acceder au panel d\'administration.'
            );

            $event->setResponse(new RedirectResponse(
                $this->urlGenerator->generate('user_profile')
            ));
        }
    }
}
