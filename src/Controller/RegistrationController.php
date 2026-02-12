<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\WebhookService;
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
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        if ($request->isMethod('POST')) {
            // Rate limit: 5 registrations / 15 min per IP
            $ip = $request->getClientIp();
            $cacheKey = 'register_limit_' . hash('sha256', $ip);
            $cacheItem = $cache->getItem($cacheKey);
            $attempts = $cacheItem->isHit() ? (int) $cacheItem->get() : 0;
            if ($attempts >= 5) {
                $this->addFlash('error', 'Trop de tentatives d\'inscription. Reessayez dans 15 minutes.');
                return $this->redirectToRoute('app_register');
            }
            $cacheItem->set($attempts + 1);
            $cacheItem->expiresAfter(900);
            $cache->save($cacheItem);

            $username = trim($request->request->get('username', ''));
            $email = trim($request->request->get('email', ''));
            $password = $request->request->get('password', '');
            $confirmPassword = $request->request->get('confirm_password', '');
            $csrfToken = $request->request->get('_csrf_token', '');

            // CSRF
            if (!$this->isCsrfTokenValid('registration', $csrfToken)) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('app_register');
            }

            // Validation
            $errors = [];
            if (empty($username) || strlen($username) < 3) {
                $errors[] = 'Le nom d\'utilisateur doit contenir au moins 3 caracteres.';
            }
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Adresse email invalide.';
            }
            if (strlen($password) < 10) {
                $errors[] = 'Le mot de passe doit contenir au moins 10 caracteres.';
            }
            if ($password !== $confirmPassword) {
                $errors[] = 'Les mots de passe ne correspondent pas.';
            }

            // Email deja utilise ?
            $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($existingUser) {
                $errors[] = 'Un compte existe deja avec cette adresse email.';
            }

            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }

                return $this->render('security/register.html.twig', [
                    'last_username' => $username,
                    'last_email' => $email,
                ]);
            }

            $user = new User();
            $user->setUsername($username);
            $user->setEmail($email);
            $user->setPassword($passwordHasher->hashPassword($user, $password));

            $em->persist($user);
            $em->flush();

            $webhookService->dispatch('user.registered', [
                'title' => 'Inscription formulaire',
                'fields' => [
                    ['name' => 'Utilisateur', 'value' => $user->getUsername(), 'inline' => true],
                    ['name' => 'Email', 'value' => $user->getEmail(), 'inline' => true],
                ],
            ]);

            $request->getSession()->migrate(true);
            $security->login($user, 'form_login', 'main');

            return $this->redirectToRoute('app_home');
        }

        return $this->render('security/register.html.twig', [
            'last_username' => '',
            'last_email' => '',
        ]);
    }
}
