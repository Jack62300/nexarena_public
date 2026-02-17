<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\SecurityBundle\Security;

class DevLoginController extends AbstractController
{
    public function __construct(
        private string $kernelEnvironment,
    ) {}

    #[Route('/_dev/login/{id}', name: 'dev_login', methods: ['POST'])]
    public function login(
        int $id,
        Request $request,
        UserRepository $userRepository,
        Security $security,
        EntityManagerInterface $em,
    ): Response {
        if ($this->kernelEnvironment !== 'dev') {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('dev_login', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $user = $userRepository->find($id);
        if (!$user) {
            $this->addFlash('danger', 'Utilisateur introuvable.');
            return $this->redirectToRoute('app_home');
        }

        // Bypass 2FA: save & clear TOTP state, detach from Doctrine to prevent flush
        $savedSecret = $user->getTotpSecret();
        $saved2fa = $user->isTwoFactorEnabled();
        $user->setIsTwoFactorEnabled(false);
        $user->setTotpSecret(null);
        $em->detach($user);

        $request->getSession()->migrate(true);
        $security->login($user, 'form_login', 'main');

        return $this->redirectToRoute('app_home');
    }
}
