<?php

namespace App\EventListener;

use App\Entity\User;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 9)]
class UserBanListener
{
    private const WHITELISTED_PREFIXES = [
        '/_profiler',
        '/_wdt',
        '/_dev',
        '/connexion',
        '/deconnexion',
        '/maintenance',
        '/2fa',
        '/oauth/',
    ];

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

        $path = $event->getRequest()->getPathInfo();
        foreach (self::WHITELISTED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        $token = $this->tokenStorage->getToken();
        if ($token === null) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isCurrentlyBanned()) {
            return;
        }

        // Invalidate session and redirect to login
        $this->tokenStorage->setToken(null);
        $event->getRequest()->getSession()->invalidate();

        $msg = 'Votre compte a été banni.';
        if ($user->getBanReason()) {
            $msg .= ' Raison : ' . $user->getBanReason() . '.';
        }
        if ($user->getBanExpiresAt()) {
            $msg .= ' Expiration : ' . $user->getBanExpiresAt()->format('d/m/Y à H:i') . '.';
        }

        $event->getRequest()->getSession()->getFlashBag()->add('error', $msg);

        $event->setResponse(new RedirectResponse(
            $this->urlGenerator->generate('app_login')
        ));
    }
}
