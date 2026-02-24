<?php

namespace App\Controller\Admin;

use App\Service\ServerAdminService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/linux-users', name: 'admin_linux_users_')]
#[IsGranted('ROLE_DEVELOPPEUR')]
class LinuxUserController extends AbstractController
{
    public function __construct(private ServerAdminService $sas) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/linux_user/index.html.twig', [
            'users'  => $this->sas->listLinuxUsers(),
            'groups' => $this->sas->getAvailableGroups(),
        ]);
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('linux_user', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_linux_users_index');
        }

        $username   = trim((string) $request->request->get('username', ''));
        $groups     = array_values(array_filter($request->request->all('groups')));
        $isSudo     = (bool) $request->request->get('is_sudo', false);
        $authMethod = $request->request->get('auth_method', 'password') === 'rsa' ? 'rsa' : 'password';
        $credential = trim((string) $request->request->get('credential', ''));

        if ($credential === '') {
            $this->addFlash('error', 'Le mot de passe ou la clé RSA est obligatoire.');
            return $this->redirectToRoute('admin_linux_users_index');
        }

        $result = $this->sas->createLinuxUser($username, $groups, $isSudo, $authMethod, $credential);

        $this->addFlash($result['success'] ? 'success' : 'error', $result['output']);
        return $this->redirectToRoute('admin_linux_users_index');
    }

    #[Route('/{username}/update', name: 'update', methods: ['POST'])]
    public function update(string $username, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('linux_user', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_linux_users_index');
        }

        $groups     = array_values(array_filter($request->request->all('groups')));
        $isSudo     = (bool) $request->request->get('is_sudo', false);
        $authMethod = $request->request->get('auth_method', 'password') === 'rsa' ? 'rsa' : 'password';
        $credential = trim((string) $request->request->get('credential', ''));

        $result = $this->sas->updateLinuxUser($username, $groups, $isSudo, $authMethod, $credential);

        $this->addFlash($result['success'] ? 'success' : 'error', $result['output']);
        return $this->redirectToRoute('admin_linux_users_index');
    }

    #[Route('/{username}/delete', name: 'delete', methods: ['POST'])]
    public function delete(string $username, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('linux_user_del_' . $username, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_linux_users_index');
        }

        $result = $this->sas->deleteLinuxUser($username);
        $this->addFlash($result['success'] ? 'success' : 'error', $result['output']);
        return $this->redirectToRoute('admin_linux_users_index');
    }
}
