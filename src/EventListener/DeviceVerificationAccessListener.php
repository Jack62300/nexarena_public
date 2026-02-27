<?php

namespace App\EventListener;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Blocks all site access for authenticated users who have a pending device verification.
 * Redirects every request to the "validation en attente" page until the device is trusted.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 5)]
class DeviceVerificationAccessListener
{
    /** Routes the blocked user is still allowed to visit. */
    private const ALLOWED_ROUTES = [
        'app_login',
        'app_logout',
        'app_device_verify_pending',
        'app_verify_device',
    ];

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private UrlGeneratorInterface $urlGenerator,
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        // Only act on the main request
        if (!$event->isMainRequest()) {
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

        // Nothing pending: let the request through
        if ($user->getPendingDeviceIp() === null) {
            return;
        }

        $route = $event->getRequest()->attributes->get('_route', '');

        // Allow Symfony internal routes (profiler, etc.) and our whitelist
        if (str_starts_with($route, '_') || in_array($route, self::ALLOWED_ROUTES, true)) {
            return;
        }

        // If the verification token has expired: clear the pending state and allow access.
        // The user will need to log in again to get a fresh email.
        $expiry = $user->getDeviceVerificationTokenExpiry();
        if ($expiry !== null && $expiry < new \DateTimeImmutable()) {
            $user->setPendingDeviceIp(null);
            $user->setDeviceVerificationToken(null);
            $user->setDeviceVerificationTokenExpiry(null);
            $this->em->flush();
            return;
        }

        // Active pending verification: block access
        $url = $this->urlGenerator->generate('app_device_verify_pending');
        $event->setResponse(new RedirectResponse($url));
    }
}
