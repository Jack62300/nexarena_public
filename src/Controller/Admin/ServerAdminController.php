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
            'logFiles'       => $this->sas->getAllLogFiles(),
            'phpInfo'        => $this->sas->getPhpInfo(),
            'opcache'        => $this->sas->getOpcacheStatus(),
        ]);
    }

    // ── System ────────────────────────────────────────────────────────────
    #[Route('/system/info', name: 'system_info', methods: ['GET'])]
    public function systemInfo(): JsonResponse
    {
        return $this->json($this->sas->getSystemInfo());
    }

    // ── Services ──────────────────────────────────────────────────────────
    #[Route('/services/status', name: 'services_status', methods: ['GET'])]
    public function servicesStatus(): JsonResponse
    {
        return $this->json($this->sas->getAllServicesStatus());
    }

    #[Route('/service/restart', name: 'service_restart', methods: ['POST'])]
    public function serviceRestart(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('server_admin', $request->request->get('_token'))) {
            return $this->json(['success' => false, 'output' => 'Token CSRF invalide.'], 403);
        }

        return $this->json($this->sas->restartService((string) $request->request->get('service', '')));
    }

    #[Route('/service/reload', name: 'service_reload', methods: ['POST'])]
    public function serviceReload(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('server_admin', $request->request->get('_token'))) {
            return $this->json(['success' => false, 'output' => 'Token CSRF invalide.'], 403);
        }

        return $this->json($this->sas->reloadService((string) $request->request->get('service', '')));
    }

    #[Route('/service/log', name: 'service_log', methods: ['GET'])]
    public function serviceLog(Request $request): JsonResponse
    {
        return $this->json($this->sas->getServiceLog((string) $request->query->get('service', '')));
    }

    // ── Firewall ──────────────────────────────────────────────────────────
    #[Route('/firewall/ban', name: 'firewall_ban', methods: ['POST'])]
    public function firewallBan(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('server_admin', $request->request->get('_token'))) {
            return $this->json(['success' => false, 'output' => 'Token CSRF invalide.'], 403);
        }

        return $this->json($this->sas->banIp((string) $request->request->get('ip', '')));
    }

    #[Route('/firewall/unban', name: 'firewall_unban', methods: ['POST'])]
    public function firewallUnban(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('server_admin', $request->request->get('_token'))) {
            return $this->json(['success' => false, 'output' => 'Token CSRF invalide.'], 403);
        }

        return $this->json($this->sas->unbanIp((string) $request->request->get('ip', '')));
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

        return $this->json($this->sas->unbanFromFail2ban(
            (string) $request->request->get('ip', ''),
            (string) $request->request->get('jail', '')
        ));
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
        return $this->json($this->sas->readConfigFile((string) $request->query->get('key', '')));
    }

    #[Route('/config/write', name: 'config_write', methods: ['POST'])]
    public function configWrite(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('server_admin', $request->request->get('_token'))) {
            return $this->json(['success' => false, 'output' => 'Token CSRF invalide.'], 403);
        }

        return $this->json($this->sas->writeConfigFile(
            (string) $request->request->get('key', ''),
            (string) $request->request->get('content', '')
        ));
    }

    // ── Logs ─────────────────────────────────────────────────────────────
    #[Route('/logs/read', name: 'logs_read', methods: ['GET'])]
    public function logsRead(Request $request): JsonResponse
    {
        $lines = (int) $request->query->get('lines', 300);

        return $this->json($this->sas->readLogFile(
            (string) $request->query->get('key', ''),
            $lines
        ));
    }

    #[Route('/logs/search', name: 'logs_search', methods: ['GET'])]
    public function logsSearch(Request $request): JsonResponse
    {
        return $this->json($this->sas->searchLogFile(
            (string) $request->query->get('key', ''),
            (string) $request->query->get('q', ''),
            (int) $request->query->get('lines', 200)
        ));
    }

    // ── Symfony ──────────────────────────────────────────────────────────
    #[Route('/symfony/run', name: 'symfony_run', methods: ['POST'])]
    public function symfonyRun(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('server_admin', $request->request->get('_token'))) {
            return $this->json(['success' => false, 'output' => 'Token CSRF invalide.'], 403);
        }

        return $this->json($this->sas->runSymfonyCommand((string) $request->request->get('cmd', '')));
    }

    #[Route('/symfony/env', name: 'symfony_env', methods: ['GET'])]
    public function symfonyEnv(): JsonResponse
    {
        return $this->json($this->sas->getSymfonyEnvInfo());
    }

    // ── Processus ────────────────────────────────────────────────────────
    #[Route('/processes', name: 'processes', methods: ['GET'])]
    public function processes(): JsonResponse
    {
        return $this->json($this->sas->getProcesses());
    }

    #[Route('/process/kill', name: 'process_kill', methods: ['POST'])]
    public function processKill(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('server_admin', $request->request->get('_token'))) {
            return $this->json(['success' => false, 'output' => 'Token CSRF invalide.'], 403);
        }

        return $this->json($this->sas->killProcess((int) $request->request->get('pid', 0)));
    }

    // ── Réseau ───────────────────────────────────────────────────────────
    #[Route('/network/ports', name: 'network_ports', methods: ['GET'])]
    public function networkPorts(): JsonResponse
    {
        return $this->json($this->sas->getListeningPorts());
    }

    #[Route('/network/stats', name: 'network_stats', methods: ['GET'])]
    public function networkStats(): JsonResponse
    {
        return $this->json([
            'connections' => $this->sas->getConnectionStats(),
            'http'        => $this->sas->getActiveHttpConnections(),
        ]);
    }

    // ── PHP ──────────────────────────────────────────────────────────────
    #[Route('/php/info', name: 'php_info', methods: ['GET'])]
    public function phpInfo(): JsonResponse
    {
        return $this->json($this->sas->getPhpInfo());
    }

    #[Route('/php/opcache', name: 'php_opcache', methods: ['GET'])]
    public function phpOpcache(): JsonResponse
    {
        return $this->json($this->sas->getOpcacheStatus());
    }

    #[Route('/php/opcache-reset', name: 'php_opcache_reset', methods: ['POST'])]
    public function phpOpcacheReset(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('server_admin', $request->request->get('_token'))) {
            return $this->json(['success' => false, 'output' => 'Token CSRF invalide.'], 403);
        }

        return $this->json($this->sas->resetOpcache());
    }

    // ── Disk ─────────────────────────────────────────────────────────────
    #[Route('/disk', name: 'disk', methods: ['GET'])]
    public function disk(): JsonResponse
    {
        return $this->json($this->sas->getDiskDetails());
    }

    // ── Cron ─────────────────────────────────────────────────────────────
    #[Route('/cron', name: 'cron', methods: ['GET'])]
    public function cron(): JsonResponse
    {
        return $this->json($this->sas->getCrontabs());
    }
}
