<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Service\BlacklistService;
use App\Service\MailerService;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\WebhookService;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/inscription', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        Security $security,
        WebhookService $webhookService,
        CacheItemPoolInterface $cache,
        SettingsService $settings,
        MailerService $mailerService,
        LoggerInterface $logger,
        BlacklistService $blacklistService,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $form = $this->createForm(RegistrationFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // Rate limit: 5 registrations / 15 min per IP
            $ip = $request->getClientIp();
            $cacheKey = 'register_limit_' . hash('sha256', $ip);
            $cacheItem = $cache->getItem($cacheKey);
            $attempts = $cacheItem->isHit() ? (int) $cacheItem->get() : 0;
            if ($attempts >= 5) {
                $this->addFlash('error', "Trop de tentatives d'inscription. Réessayez dans 15 minutes.");
                return $this->redirectToRoute('app_register');
            }
            $cacheItem->set($attempts + 1);
            $cacheItem->expiresAfter(900);
            $cache->save($cacheItem);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            // ToS acceptance (checkbox outside Symfony form type)
            if (!$request->request->getBoolean('accept_terms', false)) {
                $this->addFlash('error', 'Vous devez accepter le règlement de Nexarena pour vous inscrire.');
                return $this->render('security/register.html.twig', ['form' => $form]);
            }

            $username = $form->get('username')->getData();
            $email = $form->get('email')->getData();
            $plainPassword = $form->get('plainPassword')->getData();

            // Blacklist checks
            if ($blacklistService->isUsernameBlacklisted($username)) {
                $this->addFlash('error', "Ce pseudo n'est pas autorisé sur Nexarena.");
                return $this->render('security/register.html.twig', ['form' => $form]);
            }
            if ($blacklistService->isEmailDomainBlacklisted($email)) {
                $this->addFlash('error', "Les inscriptions avec ce domaine email ne sont pas acceptées.");
                return $this->render('security/register.html.twig', ['form' => $form]);
            }

            // Email already in use? Don't reveal this to prevent user enumeration.
            $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($existingUser) {
                $this->addFlash('success', 'Si cette adresse email est valide et disponible, votre compte a été créé.');
                return $this->redirectToRoute('app_register');
            }

            $requireVerification = $settings->get('register_require_email_verification', false);

            $user = new User();
            $user->setUsername($username);
            $user->setEmail($email);
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));

            if ($requireVerification) {
                $user->setIsEmailVerified(false);
                $user->setEmailVerificationToken(bin2hex(random_bytes(32)));
            }

            $em->persist($user);
            $em->flush();

            $webhookService->dispatch('user.registered', [
                'title' => 'Inscription formulaire',
                'fields' => [
                    ['name' => 'Utilisateur', 'value' => $user->getUsername(), 'inline' => true],
                    ['name' => 'Email', 'value' => $user->getEmail(), 'inline' => true],
                ],
            ]);

            if ($requireVerification) {
                try {
                    $mailerService->sendEmailVerification($user);
                } catch (\Throwable $e) {
                    $logger->error('Mailer: sendEmailVerification failed.', [
                        'user' => $user->getEmail(),
                        'error' => $e->getMessage(),
                    ]);
                }
                $this->addFlash('success', 'Votre compte a été créé ! Vérifiez votre boîte mail pour activer votre compte.');
                return $this->redirectToRoute('app_register_email_sent');
            }

            $request->getSession()->migrate(true);
            $security->login($user, 'form_login', 'main');

            return $this->redirectToRoute('app_home');
        }

        return $this->render('security/register.html.twig', [
            'form' => $form,
        ]);
    }
}
