<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\MailerService;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
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
        Request $request,
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

    // ─── Mot de passe oublié ──────────────────────────────────────────────────

    #[Route('/mot-de-passe-oublie', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(
        Request $request,
        UserRepository $userRepo,
        EntityManagerInterface $em,
        MailerService $mailerService,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('forgot_password', $request->request->get('_csrf_token'))) {
                $this->addFlash('error', 'Jeton CSRF invalide.');
                return $this->redirectToRoute('app_forgot_password');
            }

            $email = trim((string) $request->request->get('email'));
            $user = $userRepo->findOneBy(['email' => $email]);

            if ($user) {
                $user->setPasswordResetToken(bin2hex(random_bytes(32)));
                $user->setPasswordResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
                $em->flush();

                try {
                    $mailerService->sendPasswordResetEmail($user);
                } catch (\Throwable) {}
            }

            $this->addFlash('success', 'Si un compte existe avec cet email, un lien de réinitialisation a été envoyé.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/forgot_password.html.twig');
    }

    #[Route('/reinitialiser-mot-de-passe/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(
        string $token,
        Request $request,
        UserRepository $userRepo,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $user = $userRepo->findOneBy(['passwordResetToken' => $token]);

        if (!$user) {
            $this->addFlash('error', 'Ce lien de réinitialisation est invalide.');
            return $this->redirectToRoute('app_login');
        }

        if ($user->getPasswordResetTokenExpiresAt() < new \DateTimeImmutable()) {
            $user->setPasswordResetToken(null);
            $user->setPasswordResetTokenExpiresAt(null);
            $em->flush();

            $this->addFlash('error', 'Ce lien de réinitialisation a expiré. Veuillez en demander un nouveau.');
            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('reset_password', $request->request->get('_csrf_token'))) {
                $this->addFlash('error', 'Jeton CSRF invalide.');
                return $this->redirectToRoute('app_reset_password', ['token' => $token]);
            }

            $password = (string) $request->request->get('password');
            $passwordConfirm = (string) $request->request->get('password_confirm');

            if (mb_strlen($password) < 10) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 10 caractères.');
                return $this->render('security/reset_password.html.twig', ['token' => $token]);
            }

            if ($password !== $passwordConfirm) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
                return $this->render('security/reset_password.html.twig', ['token' => $token]);
            }

            $user->setPassword($passwordHasher->hashPassword($user, $password));
            $user->setPasswordResetToken(null);
            $user->setPasswordResetTokenExpiresAt(null);
            $em->flush();

            $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', ['token' => $token]);
    }

}
