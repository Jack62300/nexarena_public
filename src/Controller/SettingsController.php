<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Psr\Cache\CacheItemPoolInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class SettingsController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private string $projectDir,
    ) {
    }

    #[Route('/profil/parametres', name: 'user_settings')]
    public function index(): Response
    {
        return $this->render('user/settings.html.twig');
    }

    #[Route('/profil/parametres/avatar', name: 'user_settings_avatar', methods: ['POST'])]
    public function updateAvatar(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('profile_avatar', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('user_settings');
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('avatar');
        if (!$file) {
            $this->addFlash('error', 'Aucun fichier selectionne.');
            return $this->redirectToRoute('user_settings');
        }

        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowed)) {
            $this->addFlash('error', 'Format d\'image non supporte. Utilisez JPG, PNG, GIF ou WebP.');
            return $this->redirectToRoute('user_settings');
        }

        if ($file->getSize() > 2 * 1024 * 1024) {
            $this->addFlash('error', 'L\'image ne doit pas depasser 2 Mo.');
            return $this->redirectToRoute('user_settings');
        }

        $imageInfo = @getimagesize($file->getPathname());
        if ($imageInfo === false) {
            $this->addFlash('error', 'Le fichier n\'est pas une image valide.');
            return $this->redirectToRoute('user_settings');
        }

        $user = $this->getUser();

        $oldAvatar = $user->getAvatar();
        if ($oldAvatar && !str_starts_with($oldAvatar, 'http')) {
            $oldPath = $this->projectDir . '/public/uploads/avatars/' . basename($oldAvatar);
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        $uploadDir = $this->projectDir . '/public/uploads/avatars';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $filename = uniqid() . '.' . $file->guessExtension();
        $file->move($uploadDir, $filename);

        $user->setAvatar($filename);
        $this->em->flush();

        $this->addFlash('success', 'Avatar mis a jour.');
        return $this->redirectToRoute('user_settings');
    }

    #[Route('/profil/parametres/email', name: 'user_settings_email', methods: ['POST'])]
    public function updateEmail(Request $request, UserRepository $userRepo): Response
    {
        if (!$this->isCsrfTokenValid('profile_email', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('user_settings');
        }

        $email = trim((string) $request->request->get('email'));

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Adresse email invalide.');
            return $this->redirectToRoute('user_settings');
        }

        $user = $this->getUser();

        if ($email !== $user->getEmail()) {
            $existing = $userRepo->findOneBy(['email' => $email]);
            if ($existing) {
                $this->addFlash('error', 'Cette adresse email est deja utilisee.');
                return $this->redirectToRoute('user_settings');
            }

            $user->setEmail($email);
            $this->em->flush();
            $this->addFlash('success', 'Adresse email mise a jour.');
        }

        return $this->redirectToRoute('user_settings');
    }

    #[Route('/profil/parametres/password', name: 'user_settings_password', methods: ['POST'])]
    public function updatePassword(Request $request, UserPasswordHasherInterface $hasher): Response
    {
        if (!$this->isCsrfTokenValid('profile_password', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('user_settings');
        }

        $user = $this->getUser();
        $currentPassword = $request->request->get('current_password', '');
        $newPassword = $request->request->get('new_password', '');
        $confirmPassword = $request->request->get('confirm_password', '');

        if ($user->getPassword()) {
            if (!$hasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('error', 'Le mot de passe actuel est incorrect.');
                return $this->redirectToRoute('user_settings');
            }
        }

        if (strlen($newPassword) < 10) {
            $this->addFlash('error', 'Le nouveau mot de passe doit contenir au moins 10 caracteres.');
            return $this->redirectToRoute('user_settings');
        }

        if ($newPassword !== $confirmPassword) {
            $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
            return $this->redirectToRoute('user_settings');
        }

        $user->setPassword($hasher->hashPassword($user, $newPassword));
        $this->em->flush();

        $this->addFlash('success', 'Mot de passe mis a jour.');
        return $this->redirectToRoute('user_settings');
    }

    #[Route('/profil/parametres/2fa/enable', name: 'user_settings_2fa_enable', methods: ['POST'])]
    public function enable2fa(Request $request, TotpAuthenticatorInterface $totpAuth): JsonResponse
    {
        if (!$this->isCsrfTokenValid('profile_2fa', $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Token CSRF invalide.'], 400);
        }

        $user = $this->getUser();

        $secret = $totpAuth->generateSecret();
        $user->setTotpSecret($secret);
        $this->em->flush();

        $qrContent = $totpAuth->getQRContent($user);

        $qrCode = new QrCode($qrContent);
        $writer = new PngWriter();
        $result = $writer->write($qrCode);

        return new JsonResponse([
            'qr' => 'data:image/png;base64,' . base64_encode($result->getString()),
            'secret' => $secret,
        ]);
    }

    #[Route('/profil/parametres/2fa/confirm', name: 'user_settings_2fa_confirm', methods: ['POST'])]
    public function confirm2fa(Request $request, TotpAuthenticatorInterface $totpAuth, CacheItemPoolInterface $cache): JsonResponse
    {
        if (!$this->isCsrfTokenValid('profile_2fa', $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Token CSRF invalide.'], 400);
        }

        $cacheKey = '2fa_confirm_' . $this->getUser()->getId();
        $cacheItem = $cache->getItem($cacheKey);
        $attempts = $cacheItem->isHit() ? (int) $cacheItem->get() : 0;
        if ($attempts >= 5) {
            return new JsonResponse(['error' => 'Trop de tentatives. Reessayez dans 5 minutes.'], 429);
        }

        $user = $this->getUser();
        $code = $request->request->get('code', '');

        if (!$user->getTotpSecret()) {
            return new JsonResponse(['error' => 'Veuillez d\'abord generer un secret 2FA.'], 400);
        }

        if (!$totpAuth->checkCode($user, $code)) {
            $cacheItem->set($attempts + 1);
            $cacheItem->expiresAfter(300);
            $cache->save($cacheItem);
            return new JsonResponse(['error' => 'Code invalide. Veuillez reessayer.'], 400);
        }

        $user->setIsTwoFactorEnabled(true);
        $this->em->flush();

        $cache->deleteItem($cacheKey);

        return new JsonResponse(['success' => true, 'message' => 'Authentification 2FA activee avec succes.']);
    }

    #[Route('/profil/parametres/2fa/disable', name: 'user_settings_2fa_disable', methods: ['POST'])]
    public function disable2fa(Request $request, UserPasswordHasherInterface $hasher): Response
    {
        if (!$this->isCsrfTokenValid('profile_2fa', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('user_settings');
        }

        $user = $this->getUser();

        $password = $request->request->get('password', '');
        if ($user->getPassword() && !$hasher->isPasswordValid($user, $password)) {
            $this->addFlash('error', 'Mot de passe incorrect. Impossible de desactiver la 2FA.');
            return $this->redirectToRoute('user_settings');
        }

        $user->setIsTwoFactorEnabled(false);
        $user->setTotpSecret(null);
        $this->em->flush();

        $this->addFlash('success', 'Authentification 2FA desactivee.');
        return $this->redirectToRoute('user_settings');
    }
}
