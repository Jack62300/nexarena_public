<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\SettingsService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 5)]
class MaintenanceListener
{
    private const ADMIN_ROLES = [
        'ROLE_EDITEUR',
        'ROLE_MANAGER',
        'ROLE_RESPONSABLE',
        'ROLE_DEVELOPPEUR',
        'ROLE_FONDATEUR',
    ];

    private const WHITELISTED_PREFIXES = [
        '/_profiler',
        '/_wdt',
        '/_dev',
        '/assets',
        '/build',
        '/maintenance',
        '/oauth/',
        '/deconnexion',
    ];

    public function __construct(
        private SettingsService $settings,
        private TokenStorageInterface $tokenStorage,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!$this->settings->getBool('maintenance_mode', false)) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();

        foreach (self::WHITELISTED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        // Vérifier si l'utilisateur connecté a un rôle admin
        $token = $this->tokenStorage->getToken();
        if ($token !== null) {
            $user = $token->getUser();
            if ($user instanceof User && !empty(array_intersect($user->getRoles(), self::ADMIN_ROLES))) {
                return;
            }
        }

        $event->setResponse(new RedirectResponse(
            $this->urlGenerator->generate('app_maintenance')
        ));
    }
}
