<?php

namespace App\Service;

class ServerAdminService
{
    private const ALLOWED_SERVICES = ['nginx', 'php8.4-fpm', 'fail2ban', 'mariadb'];

    private const ALLOWED_CONFIG_FILES = [
        'nginx_site'    => '/etc/nginx/sites-available/nexarena',
        'nginx_bot'     => '/etc/nginx/sites-available/nexarena-bot',
        'nginx_main'    => '/etc/nginx/nginx.conf',
        'fail2ban_jail' => '/etc/fail2ban/jail.local',
        'php_ini'       => '/etc/php/8.4/fpm/php.ini',
        'php_pool'      => '/etc/php/8.4/fpm/pool.d/www.conf',
    ];

    private function run(string $cmd): array
    {
        $output   = [];
        $exitCode = 0;
        exec($cmd . ' 2>&1', $output, $exitCode);

        return [
            'output'  => implode("\n", $output),
            'lines'   => $output,
            'success' => $exitCode === 0,
        ];
    }

    // ── System ─────────────────────────────────────────────
    public function getSystemInfo(): array
    {
        $uptime  = $this->run('uptime -p');
        $loadAvg = $this->run('cat /proc/loadavg');
        $mem     = $this->run('free -m');
        $disk    = $this->run('df -h /');

        $memData = ['used' => 0, 'total' => 0, 'percent' => 0];
        if (isset($mem['lines'][1])) {
            $p = preg_split('/\s+/', trim($mem['lines'][1]));
            $total = (int)($p[1] ?? 0);
            $used  = (int)($p[2] ?? 0);
            $memData = ['total' => $total, 'used' => $used, 'percent' => $total > 0 ? round($used / $total * 100) : 0];
        }

        $diskData = ['total' => '-', 'used' => '-', 'percent' => 0];
        if (isset($disk['lines'][1])) {
            $p = preg_split('/\s+/', trim($disk['lines'][1]));
            $diskData = ['total' => $p[1] ?? '-', 'used' => $p[2] ?? '-', 'percent' => isset($p[4]) ? (int)rtrim($p[4], '%') : 0];
        }

        $load = ['1m' => '-', '5m' => '-', '15m' => '-'];
        if ($loadAvg['output']) {
            $p    = explode(' ', $loadAvg['output']);
            $load = ['1m' => $p[0] ?? '-', '5m' => $p[1] ?? '-', '15m' => $p[2] ?? '-'];
        }

        return ['uptime' => trim($uptime['output']), 'load' => $load, 'memory' => $memData, 'disk' => $diskData];
    }

    // ── Services ────────────────────────────────────────────
    public function getAllServicesStatus(): array
    {
        $result = [];
        foreach (self::ALLOWED_SERVICES as $svc) {
            $r            = $this->run('sudo /usr/bin/systemctl is-active ' . escapeshellarg($svc));
            $result[$svc] = trim($r['output']) === 'active';
        }

        return $result;
    }

    public function restartService(string $service): array
    {
        if (!in_array($service, self::ALLOWED_SERVICES, true)) {
            return ['success' => false, 'output' => 'Service non autorisé.'];
        }

        return $this->run('sudo /usr/bin/systemctl restart ' . escapeshellarg($service));
    }

    public function reloadService(string $service): array
    {
        if (!in_array($service, ['nginx', 'php8.4-fpm'], true)) {
            return ['success' => false, 'output' => 'Reload non supporté pour ce service.'];
        }

        return $this->run('sudo /usr/bin/systemctl reload ' . escapeshellarg($service));
    }

    public function getServiceLog(string $service): array
    {
        if (!in_array($service, self::ALLOWED_SERVICES, true)) {
            return ['success' => false, 'output' => 'Service non autorisé.'];
        }

        return $this->run('sudo /usr/bin/journalctl -u ' . escapeshellarg($service) . ' -n 100 --no-pager');
    }

    // ── Firewall (iptables f2b-global) ──────────────────────
    public function getFirewallBannedIps(): array
    {
        $r   = $this->run('sudo /usr/sbin/iptables -L f2b-global --line-numbers -n');
        $ips = [];
        foreach ($r['lines'] as $line) {
            if (preg_match('/^(\d+)\s+REJECT\s+all\s+--\s+(\S+)\s/', $line, $m)) {
                $ips[] = ['num' => (int)$m[1], 'ip' => $m[2]];
            }
        }

        return ['ips' => $ips, 'success' => $r['success']];
    }

    public function banIp(string $ip): array
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return ['success' => false, 'output' => 'Adresse IP invalide.'];
        }

        $existing = $this->getFirewallBannedIps();
        foreach ($existing['ips'] as $rule) {
            if ($rule['ip'] === $ip) {
                return ['success' => false, 'output' => "L'IP {$ip} est déjà bannie."];
            }
        }

        $r = $this->run('sudo /usr/sbin/iptables -I f2b-global 1 -s ' . escapeshellarg($ip) . ' -j REJECT --reject-with icmp-port-unreachable');
        if ($r['success']) {
            $this->run('sudo /usr/sbin/netfilter-persistent save');
        }

        return $r;
    }

    public function unbanIp(string $ip): array
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return ['success' => false, 'output' => 'Adresse IP invalide.'];
        }

        $r = $this->run('sudo /usr/sbin/iptables -D f2b-global -s ' . escapeshellarg($ip) . ' -j REJECT --reject-with icmp-port-unreachable');
        if ($r['success']) {
            $this->run('sudo /usr/sbin/netfilter-persistent save');
        }

        return $r;
    }

    // ── Fail2ban ─────────────────────────────────────────────
    public function getFail2banStatus(): array
    {
        $r = $this->run('sudo /usr/bin/fail2ban-client status');
        if (!$r['success']) {
            return ['jails' => [], 'success' => false];
        }

        $jails = [];
        if (preg_match('/Jail list:\s*(.+)/i', $r['output'], $m)) {
            foreach (array_map('trim', explode(',', $m[1])) as $jail) {
                if ($jail) {
                    $jr          = $this->run('sudo /usr/bin/fail2ban-client status ' . escapeshellarg($jail));
                    $jails[$jail] = $this->parseFail2banJail($jr['output']);
                }
            }
        }

        return ['jails' => $jails, 'success' => true];
    }

    private function parseFail2banJail(string $output): array
    {
        $d = ['failed_current' => 0, 'failed_total' => 0, 'banned_current' => 0, 'banned_total' => 0, 'banned_ips' => []];
        if (preg_match('/Currently failed:\s*(\d+)/i', $output, $m))  { $d['failed_current'] = (int)$m[1]; }
        if (preg_match('/Total failed:\s*(\d+)/i', $output, $m))      { $d['failed_total']   = (int)$m[1]; }
        if (preg_match('/Currently banned:\s*(\d+)/i', $output, $m))  { $d['banned_current'] = (int)$m[1]; }
        if (preg_match('/Total banned:\s*(\d+)/i', $output, $m))      { $d['banned_total']   = (int)$m[1]; }
        if (preg_match('/Banned IP list:\s*(.+)/i', $output, $m)) {
            $d['banned_ips'] = array_values(array_filter(array_map('trim', explode(' ', $m[1]))));
        }

        return $d;
    }

    public function unbanFromFail2ban(string $ip, string $jail): array
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP))    { return ['success' => false, 'output' => 'IP invalide.']; }
        if (!preg_match('/^[a-z0-9_-]+$/i', $jail))  { return ['success' => false, 'output' => 'Jail invalide.']; }

        return $this->run('sudo /usr/bin/fail2ban-client set ' . escapeshellarg($jail) . ' unbanip ' . escapeshellarg($ip));
    }

    // ── APT ──────────────────────────────────────────────────
    public function runAptUpdate(): array
    {
        return $this->run('sudo /usr/bin/apt-get update -q');
    }

    public function getUpgradablePackages(): array
    {
        $r        = $this->run('sudo /usr/bin/apt list --upgradable 2>/dev/null');
        $packages = [];
        foreach ($r['lines'] as $line) {
            if (preg_match('/^([^\/]+)\/\S+\s+(\S+)\s+\S+\s+\[upgradable from:\s*(\S+)\]/', $line, $m)) {
                $packages[] = ['name' => $m[1], 'new' => $m[2], 'old' => $m[3]];
            }
        }

        return ['packages' => $packages, 'count' => count($packages)];
    }

    public function runAptUpgrade(): array
    {
        set_time_limit(300);

        return $this->run('sudo /usr/bin/apt-get upgrade -y -q');
    }

    // ── Config Files ──────────────────────────────────────────
    public function getConfigFiles(): array
    {
        return self::ALLOWED_CONFIG_FILES;
    }

    public function readConfigFile(string $key): array
    {
        if (!array_key_exists($key, self::ALLOWED_CONFIG_FILES)) {
            return ['success' => false, 'output' => 'Fichier non autorisé.'];
        }

        return $this->run('sudo /bin/cat ' . escapeshellarg(self::ALLOWED_CONFIG_FILES[$key]));
    }

    public function writeConfigFile(string $key, string $content): array
    {
        if (!array_key_exists($key, self::ALLOWED_CONFIG_FILES)) {
            return ['success' => false, 'output' => 'Fichier non autorisé.'];
        }

        $file  = self::ALLOWED_CONFIG_FILES[$key];
        $desc  = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc  = proc_open('sudo /usr/bin/tee ' . escapeshellarg($file) . ' > /dev/null', $desc, $pipes);

        if (!is_resource($proc)) {
            return ['success' => false, 'output' => 'Impossible d\'ouvrir le processus.'];
        }

        fwrite($pipes[0], $content);
        fclose($pipes[0]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);

        return ['success' => $exitCode === 0, 'output' => $exitCode !== 0 ? ($stderr ?: 'Erreur inconnue.') : ''];
    }
}
