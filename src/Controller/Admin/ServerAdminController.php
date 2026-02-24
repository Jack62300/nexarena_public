<?php

namespace App\Controller\Admin;

use App\Service\ServerAdminService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/server-admin', name: 'admin_server_admin_')]
#[IsGranted('ROLE_DEVELOPPEUR')]
class ServerAdminController extends AbstractController
{
    public function __construct(private ServerAdminService $sas) {}

    // ── Dashboard principal ────────────────────────────────────────────────
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/server_admin/index.html.twig', [
            'systemInfo'     => $this->sas->getSystemInfo(),
            'servicesStatus' => $this->sas->getAllServicesStatus(),
            'fail2ban'       => $this->sas->getFail2banStatus(),
            'firewall'       => $this->sas->getFirewallBannedIps(),
            'configFiles'    => $this->sas->getConfigFiles(),
            'upgradable'     => $this->sas->getUpgradablePackages(),
        ]);
    }

    // ── Services ──────────────────────────────────────────────────────────
    #[Route('/service/restart', name: 'service_restart', methods: ['POST'])]
    public function serviceRestart(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('server_admin', $request->request->get('_token'))) {
            return $this->json(['success' => false, 'output' => 'Token CSRF invalide.'], 403);
        }

        $result = $this->sas->restartService((string) $request->request->get('service', ''));

        return $this->json($result);
    }

    #[Route('/service/reload', name: 'service_reload', methods: ['POST'])]
    public function serviceReload(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('server_admin', $request->request->get('_token'))) {
            return $this->json(['success' => false, 'output' => 'Token CSRF invalide.'], 403);
        }

        $result = $this->sas->reloadService((string) $request->request->get('service', ''));

        return $this->json($result);
    }

    #[Route('/service/log', name: 'service_log', methods: ['GET'])]
    public function serviceLog(Request $request): JsonResponse
    {
        $result = $this->sas->getServiceLog((string) $request->query->get('service', ''));

        return $this->json($result);
    }

    #[Route('/system/info', name: 'system_info', methods: ['GET'])]
    public function systemInfo(): JsonResponse
    {
        return $this->json($this->sas->getSystemInfo());
    }

    // ── Firewall ──────────────────────────────────────────────────────────
    #[Route('/firewall/ban', name: 'firewall_ban', methods: ['POST'])]
    public function firewallBan(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('server_admin', $request->request->get('_token'))) {
            return $this->json(['success' => false, 'output' => 'Token CSRF invalide.'], 403);
        }

        $result = $this->sas->banIp((string) $request->request->get('ip', ''));

        return $this->json($result);
    }

    #[Route('/firewall/unban', name: 'firewall_unban', methods: ['POST'])]
    public function firewallUnban(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('server_admin', $request->request->get('_token'))) {
            return $this->json(['success' => false, 'output' => 'Token CSRF invalide.'], 403);
        }

        $result = $this->sas->unbanIp((string) $request->request->get('ip', ''));

        return $this->json($result);
    }

    #[Route('/firewall/list', name: 'firewall_list', methods: ['GET'])]
    public function firewallList(): JsonResponse
    {
        return $this->json($this->sas->getFirewallBannedIps());
    }

    // ── Fail2ban ─────────────────────────────────────────────────────────
    #[Route('/fail2ban/unban', name: 'fail2ban_unban', methods: ['POST'])]
    public function fail2banUnban(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('server_admin', $request->request->get('_token'))) {
            return $this->json(['success' => false, 'output' => 'Token CSRF invalide.'], 403);
        }

        $result = $this->sas->unbanFromFail2ban(
            (string) $request->request->get('ip', ''),
            (string) $request->request->get('jail', '')
        );

        return $this->json($result);
    }

    #[Route('/fail2ban/status', name: 'fail2ban_status', methods: ['GET'])]
    public function fail2banStatus(): JsonResponse
    {
        return $this->json($this->sas->getFail2banStatus());
    }

    // ── APT ──────────────────────────────────────────────────────────────
    #[Route('/apt/update', name: 'apt_update', methods: ['POST'])]
    public function aptUpdate(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('server_admin', $request->request->get('_token'))) {
            return $this->json(['success' => false, 'output' => 'Token CSRF invalide.'], 403);
        }

        return $this->json($this->sas->runAptUpdate());
    }

    #[Route('/apt/upgrade', name: 'apt_upgrade', methods: ['POST'])]
    public function aptUpgrade(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('server_admin', $request->request->get('_token'))) {
            return $this->json(['success' => false, 'output' => 'Token CSRF invalide.'], 403);
        }

        return $this->json($this->sas->runAptUpgrade());
    }

    #[Route('/apt/upgradable', name: 'apt_upgradable', methods: ['GET'])]
    public function aptUpgradable(): JsonResponse
    {
        return $this->json($this->sas->getUpgradablePackages());
    }

    // ── Config Files ─────────────────────────────────────────────────────
    #[Route('/config/read', name: 'config_read', methods: ['GET'])]
    public function configRead(Request $request): JsonResponse
    {
        $result = $this->sas->readConfigFile((string) $request->query->get('key', ''));

        return $this->json($result);
    }

    #[Route('/config/write', name: 'config_write', methods: ['POST'])]
    public function configWrite(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('server_admin', $request->request->get('_token'))) {
            return $this->json(['success' => false, 'output' => 'Token CSRF invalide.'], 403);
        }

        $result = $this->sas->writeConfigFile(
            (string) $request->request->get('key', ''),
            (string) $request->request->get('content', '')
        );

        return $this->json($result);
    }
}
