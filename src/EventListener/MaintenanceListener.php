<?php

namespace App\EventListener;

use App\Service\SettingsService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 5)]
class MaintenanceListener
{
    /**
     * Routes toujours accessibles pendant la maintenance.
     * Le prefix /oauth/ couvre /oauth/connect/* et /oauth/callback/*
     */
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
        private Security $security,
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

        // Les utilisateurs avec ROLE_EDITEUR ou supérieur passent toujours
        if ($this->security->isGranted('ROLE_EDITEUR')) {
            return;
        }

        $event->setResponse(new RedirectResponse(
            $this->urlGenerator->generate('app_maintenance')
        ));
    }
}
