<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\MailerService;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

#[AsEventListener(event: LoginSuccessEvent::class)]
class LoginIpVerificationListener
{
    public function __construct(
        private EntityManagerInterface $em,
        private SettingsService $settings,
        private MailerService $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private RequestStack $requestStack,
    ) {}

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getAuthenticatedToken()->getUser();
        if (!$user instanceof User) {
            return;
        }

        // Only check for form_login (not OAuth which already has trust established)
        $firewallName = $event->getFirewallName();
        if ($firewallName !== 'main') {
            return;
        }

        // Check if IP verification is enabled
        if (!$this->settings->get('login_ip_verification_enabled', false)) {
            return;
        }

        $request = $event->getRequest();
        $currentIp = $request->getClientIp() ?? '0.0.0.0';

        // First login ever: trust this IP immediately
        if ($user->getTrustedIps() === null) {
            $user->addTrustedIp($currentIp);
            $this->em->flush();
            return;
        }

        // Already trusted IP: let through
        if ($user->isTrustedIp($currentIp)) {
            return;
        }

        // New IP: generate device verification token, save pending IP, log out, send email
        $token = bin2hex(random_bytes(32));
        $user->setDeviceVerificationToken($token);
        $user->setDeviceVerificationTokenExpiry(new \DateTimeImmutable('+1 hour'));
        $user->setPendingDeviceIp($currentIp);
        $this->em->flush();

        // Invalidate the session to prevent access
        $session = $request->getSession();
        $session->invalidate();

        try {
            $this->mailer->sendDeviceVerification($user, $currentIp);
        } catch (\Throwable) {
            // Fail silently — user still gets blocked
        }

        $redirectUrl = $this->urlGenerator->generate('app_device_verify_pending');
        $event->setResponse(new RedirectResponse($redirectUrl));
    }
}
