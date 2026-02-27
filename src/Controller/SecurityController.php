<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\MailerService;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/connexion', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils, SettingsService $settings): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        if ($settings->getBool('maintenance_mode', false)) {
            return $this->redirectToRoute('app_maintenance');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/deconnexion', name: 'app_logout')]
    public function logout(): void
    {
        // Intercepte par le firewall
    }

    // ─── Email verification ───────────────────────────────────────────────────

    #[Route('/inscription/email-envoye', name: 'app_register_email_sent')]
    public function emailSent(): Response
    {
        return $this->render('security/email_sent.html.twig');
    }

    #[Route('/inscription/verifier-email/{token}', name: 'app_verify_email')]
    public function verifyEmail(
        string $token,
        UserRepository $userRepo,
        EntityManagerInterface $em,
    ): Response {
        $user = $userRepo->findOneBy(['emailVerificationToken' => $token]);

        if (!$user) {
            $this->addFlash('error', 'Lien de vérification invalide ou expiré.');
            return $this->redirectToRoute('app_login');
        }

        $user->setIsEmailVerified(true);
        $user->setEmailVerificationToken(null);
        $em->flush();

        $this->addFlash('success', 'Votre adresse email a été vérifiée ! Vous pouvez maintenant vous connecter.');
        return $this->redirectToRoute('app_login');
    }

    #[Route('/inscription/renvoyer-email', name: 'app_resend_verification_email', methods: ['POST'])]
    public function resendVerificationEmail(
        \Symfony\Component\HttpFoundation\Request $request,
        UserRepository $userRepo,
        EntityManagerInterface $em,
        MailerService $mailerService,
    ): Response {
        $email = $request->request->get('email');
        if (!$email) {
            return $this->redirectToRoute('app_register_email_sent');
        }

        $user = $userRepo->findOneBy(['email' => $email]);
        if ($user && !$user->isEmailVerified()) {
            $user->setEmailVerificationToken(bin2hex(random_bytes(32)));
            $em->flush();
            try {
                $mailerService->sendEmailVerification($user);
            } catch (\Throwable) {}
        }

        $this->addFlash('success', 'Si un compte existe avec cet email, un nouveau lien a été envoyé.');
        return $this->redirectToRoute('app_register_email_sent');
    }

}
