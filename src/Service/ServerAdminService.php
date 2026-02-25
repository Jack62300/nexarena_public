<?php

namespace App\Service;

class ServerAdminService
{
    private const PROJECT_DIR = '/var/www/nexarena';

    private const ALLOWED_SERVICES = ['nginx', 'php8.4-fpm', 'fail2ban', 'mariadb'];

    private const ALLOWED_CONFIG_FILES = [
        'nginx_site'    => '/etc/nginx/sites-available/nexarena',
        'nginx_bot'     => '/etc/nginx/sites-available/nexarena-bot',
        'nginx_main'    => '/etc/nginx/nginx.conf',
        'fail2ban_jail' => '/etc/fail2ban/jail.local',
        'php_ini'       => '/etc/php/8.4/fpm/php.ini',
        'php_pool'      => '/etc/php/8.4/fpm/pool.d/www.conf',
    ];

    private const ALLOWED_LOG_FILES = [
        'symfony_prod' => self::PROJECT_DIR . '/var/log/prod.log',
        'symfony_dev'  => self::PROJECT_DIR . '/var/log/dev.log',
        'nginx_access' => '/var/log/nginx/access.log',
        'nginx_error'  => '/var/log/nginx/error.log',
        'php_fpm'      => '/var/log/php8.4-fpm.log',
        'mariadb'      => '/var/log/mysql/error.log',
        'fail2ban'     => '/var/log/fail2ban.log',
        'auth'         => '/var/log/auth.log',
        'syslog'       => '/var/log/syslog',
    ];

    private const ALLOWED_SYMFONY_COMMANDS = [
        'cache:clear'          => 'php8.4 bin/console cache:clear --env=prod --no-debug',
        'cache:warmup'         => 'php8.4 bin/console cache:warmup --env=prod --no-debug',
        'migrations:status'    => 'php8.4 bin/console doctrine:migrations:status --no-ansi',
        'migrations:migrate'   => 'php8.4 bin/console doctrine:migrations:migrate --no-interaction --no-ansi',
        'messenger:stop'       => 'php8.4 bin/console messenger:stop-workers',
        'router:match'         => 'php8.4 bin/console debug:router --no-ansi',
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
            $memData = ['total' => $total, 'used' => $used, 'percent' => $total > 0 ? (int) round((float) $used / $total * 100) : 0];
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

    // ── Logs ─────────────────────────────────────────────────
    public function getAllLogFiles(): array
    {
        return self::ALLOWED_LOG_FILES;
    }

    public function readLogFile(string $key, int $lines = 300): array
    {
        if (!array_key_exists($key, self::ALLOWED_LOG_FILES)) {
            return ['success' => false, 'output' => 'Fichier non autorisé.'];
        }

        $file  = self::ALLOWED_LOG_FILES[$key];
        $lines = max(10, min(2000, $lines));

        $r = $this->run('sudo /usr/bin/tail -n ' . $lines . ' ' . escapeshellarg($file));

        if (!$r['success']) {
            if (str_contains($r['output'], 'No such file') || str_contains($r['output'], 'cannot open')) {
                return ['success' => true, 'output' => "(Fichier inexistant : {$file})\nAucun log enregistré pour le moment."];
            }
        }

        return $r;
    }

    public function searchLogFile(string $key, string $pattern, int $lines = 200): array
    {
        if (!array_key_exists($key, self::ALLOWED_LOG_FILES)) {
            return ['success' => false, 'output' => 'Fichier non autorisé.'];
        }

        $file  = self::ALLOWED_LOG_FILES[$key];
        $lines = max(10, min(500, $lines));

        $r = $this->run('sudo /usr/bin/grep -i ' . escapeshellarg($pattern) . ' ' . escapeshellarg($file) . ' | /usr/bin/tail -n ' . $lines);

        if (!$r['success']) {
            if (str_contains($r['output'], 'No such file') || str_contains($r['output'], 'cannot open')) {
                return ['success' => true, 'output' => "(Fichier inexistant : {$file})"];
            }
            // grep renvoie exit 1 quand aucun résultat — c'est normal
            return ['success' => true, 'output' => $r['output'] ?: '(Aucun résultat)'];
        }

        return $r;
    }

    // ── Symfony ───────────────────────────────────────────────
    public function runSymfonyCommand(string $cmd): array
    {
        if (!array_key_exists($cmd, self::ALLOWED_SYMFONY_COMMANDS)) {
            return ['success' => false, 'output' => 'Commande non autorisée.'];
        }

        set_time_limit(120);
        $fullCmd = 'cd ' . escapeshellarg(self::PROJECT_DIR) . ' && sudo -u www-data ' . self::ALLOWED_SYMFONY_COMMANDS[$cmd];

        return $this->run($fullCmd);
    }

    public function getSymfonyEnvInfo(): array
    {
        $version = $this->run('cd ' . escapeshellarg(self::PROJECT_DIR) . ' && php8.4 bin/console --version --no-ansi 2>&1');
        $env     = $this->run('cd ' . escapeshellarg(self::PROJECT_DIR) . ' && php8.4 bin/console debug:container --env-vars --no-ansi 2>&1 | head -5');

        return [
            'version' => trim($version['output']),
            'success' => $version['success'],
        ];
    }

    // ── Processus ─────────────────────────────────────────────
    public function getProcesses(): array
    {
        $r = $this->run('ps aux --sort=-%cpu --no-headers | head -20');
        $processes = [];
        foreach ($r['lines'] as $line) {
            $parts = preg_split('/\s+/', trim($line), 11);
            if ($parts !== false && count($parts) >= 11) {
                $processes[] = [
                    'user'    => $parts[0],
                    'pid'     => $parts[1],
                    'cpu'     => $parts[2],
                    'mem'     => $parts[3],
                    'vsz'     => $parts[4],
                    'rss'     => $parts[5],
                    'stat'    => $parts[7],
                    'start'   => $parts[8],
                    'time'    => $parts[9],
                    'command' => mb_substr($parts[10], 0, 80),
                ];
            }
        }

        return ['processes' => $processes, 'success' => $r['success']];
    }

    public function killProcess(int $pid): array
    {
        if ($pid <= 1) {
            return ['success' => false, 'output' => 'PID invalide.'];
        }

        // Only allow killing www-data processes
        $check = $this->run('ps -p ' . $pid . ' -o user= --no-headers');
        $owner = trim($check['output']);
        if (!in_array($owner, ['www-data', 'nginx'], true)) {
            return ['success' => false, 'output' => "Impossible de tuer le processus de l'utilisateur '{$owner}'."];
        }

        return $this->run('sudo /bin/kill -15 ' . $pid);
    }

    // ── Réseau ────────────────────────────────────────────────
    public function getListeningPorts(): array
    {
        // Try both common paths for ss on Ubuntu
        $r = $this->run('sudo ss -tulpn 2>/dev/null');
        if (!$r['success'] || empty($r['output'])) {
            $r = $this->run('sudo /usr/sbin/ss -tulpn 2>/dev/null');
        }

        $ports = [];
        foreach ($r['lines'] as $line) {
            // Format: Netid State Recv-Q Send-Q Local:Port Peer:Port [Process]
            // tcp   LISTEN  0  128  0.0.0.0:22  0.0.0.0:*  users:(("sshd",pid=921,fd=3))
            if (preg_match('/^(tcp|udp)\s+\S+\s+\d+\s+\d+\s+(\S+)\s+\S+\s+users:\(\("?([^",\)]+)/', $line, $m)) {
                $ports[] = ['proto' => $m[1], 'local' => $m[2], 'process' => $m[3]];
            } elseif (preg_match('/^(tcp|udp)\s+\S+\s+\d+\s+\d+\s+(\S+)/', $line, $m)) {
                $ports[] = ['proto' => $m[1], 'local' => $m[2], 'process' => '—'];
            }
        }

        return ['ports' => $ports, 'raw' => $r['output'], 'success' => $r['success']];
    }

    public function getConnectionStats(): array
    {
        $r = $this->run('sudo ss -s 2>/dev/null');
        if (!$r['success'] || empty($r['output'])) {
            $r = $this->run('sudo /usr/sbin/ss -s 2>/dev/null');
        }

        return ['output' => $r['output'], 'success' => $r['success']];
    }

    public function getActiveHttpConnections(): array
    {
        $r = $this->run("sudo ss -tn state established '( dport = :80 or dport = :443 or sport = :80 or sport = :443 )' 2>/dev/null");
        $count = max(0, count($r['lines']) - 1);

        return ['count' => $count, 'output' => $r['output'], 'success' => $r['success']];
    }

    // ── PHP & OPcache ─────────────────────────────────────────
    public function getPhpInfo(): array
    {
        $version    = $this->run('php8.4 -r "echo PHP_VERSION;"');
        $extensions = $this->run('php8.4 -m 2>/dev/null | sort');
        $ini        = $this->run('php8.4 -r "echo \'memory_limit=\'.ini_get(\'memory_limit\').\'\nmax_execution_time=\'.ini_get(\'max_execution_time\').\'\nupload_max_filesize=\'.ini_get(\'upload_max_filesize\').\'\npost_max_size=\'.ini_get(\'post_max_size\').\'\nopcache.enable=\'.ini_get(\'opcache.enable\').\'\ndate.timezone=\'.ini_get(\'date.timezone\');"');

        $iniData = [];
        foreach (explode("\n", $ini['output']) as $line) {
            [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
            if ($k) $iniData[trim($k)] = trim($v);
        }

        return [
            'version'    => trim($version['output']),
            'extensions' => array_filter(array_map('trim', $extensions['lines'])),
            'ini'        => $iniData,
            'success'    => true,
        ];
    }

    public function getOpcacheStatus(): array
    {
        $r = $this->run('php8.4 -r "if(function_exists(\'opcache_get_status\')){$s=opcache_get_status(false);echo json_encode([\'enabled\'=>$s[\'opcache_enabled\'],\'hits\'=>$s[\'opcache_statistics\'][\'hits\'],\'misses\'=>$s[\'opcache_statistics\'][\'misses\'],\'cached_scripts\'=>$s[\'opcache_statistics\'][\'num_cached_scripts\'],\'used_memory\'=>$s[\'memory_usage\'][\'used_memory\'],\'free_memory\'=>$s[\'memory_usage\'][\'free_memory\'],\'wasted_memory\'=>$s[\'memory_usage\'][\'wasted_memory\']]);}else{echo json_encode([\'enabled\'=>false]);}"');

        $data = json_decode($r['output'], true) ?? ['enabled' => false];

        if (isset($data['used_memory'], $data['free_memory'])) {
            $total = $data['used_memory'] + $data['free_memory'];
            $data['percent'] = $total > 0 ? round($data['used_memory'] / $total * 100) : 0;
            $data['used_mb'] = round($data['used_memory'] / 1024 / 1024, 1);
            $data['free_mb'] = round($data['free_memory'] / 1024 / 1024, 1);
        }

        return $data;
    }

    public function resetOpcache(): array
    {
        $r = $this->run('php8.4 -r "opcache_reset(); echo \'ok\';"');

        return ['success' => trim($r['output']) === 'ok', 'output' => $r['output']];
    }

    // ── Disk détails ─────────────────────────────────────────
    public function getDiskDetails(): array
    {
        $df    = $this->run('df -h --output=source,fstype,size,used,avail,pcent,target 2>/dev/null | grep -v tmpfs | grep -v devtmpfs | grep -v udev');
        $duLog = $this->run('sudo /usr/bin/du -sh ' . escapeshellarg(self::PROJECT_DIR . '/var/log') . ' 2>/dev/null');
        $duVar = $this->run('sudo /usr/bin/du -sh ' . escapeshellarg(self::PROJECT_DIR . '/var') . ' 2>/dev/null');
        $duPub = $this->run('sudo /usr/bin/du -sh ' . escapeshellarg(self::PROJECT_DIR . '/public/uploads') . ' 2>/dev/null');

        return [
            'df'     => $df['output'],
            'dirs'   => [
                'var/log'        => trim(explode("\t", $duLog['output'])[0] ?? '-'),
                'var'            => trim(explode("\t", $duVar['output'])[0] ?? '-'),
                'public/uploads' => trim(explode("\t", $duPub['output'])[0] ?? '-'),
            ],
            'success' => true,
        ];
    }

    // ── Cron ─────────────────────────────────────────────────
    public function getCrontabs(): array
    {
        $root    = $this->run('sudo /usr/bin/crontab -l 2>/dev/null');
        $wwwData = $this->run('sudo /usr/bin/crontab -u www-data -l 2>/dev/null');
        $system  = $this->run('sudo /bin/cat /etc/crontab 2>/dev/null');

        return [
            'root'     => $root['output'] ?: '(vide)',
            'www-data' => $wwwData['output'] ?: '(vide)',
            'system'   => $system['output'] ?: '(vide)',
            'success'  => true,
        ];
    }

    // ── Gestion utilisateurs Linux ────────────────────────────

    private const USERNAME_REGEX = '/^[a-z_][a-z0-9_-]{0,30}$/';

    private const RSA_KEY_REGEX = '/^(ssh-rsa|ssh-ed25519|ecdsa-sha2-nistp256|ecdsa-sha2-nistp384|ecdsa-sha2-nistp521|sk-ssh-ed25519@openssh\.com|sk-ecdsa-sha2-nistp256@openssh\.com)\s+[A-Za-z0-9+\/=]+(\s+\S+)?$/';

    /** Groupes utiles exposables dans l'UI */
    private const USEFUL_GROUPS = [
        'sudo', 'adm', 'www-data', 'docker', 'nginx', 'ssl-cert',
        'dialout', 'cdrom', 'audio', 'video', 'plugdev', 'staff',
        'users', 'lxd', 'netdev',
    ];

    public function listLinuxUsers(): array
    {
        $passwd = $this->run('sudo /bin/cat /etc/passwd');
        $users  = [];

        foreach ($passwd['lines'] as $line) {
            $p = explode(':', $line);
            if (count($p) < 7) continue;

            [$username, , $uid, , , $home, $shell] = $p;
            $uid = (int) $uid;

            // Only non-system users (UID 1000–60000)
            if ($uid < 1000 || $uid > 60000) continue;
            if (in_array($shell, ['/usr/sbin/nologin', '/bin/false', '/sbin/nologin'], true)) continue;

            // Groups (no sudo needed)
            $gr     = $this->run('id -Gn ' . escapeshellarg($username));
            $groups = array_values(array_filter(explode(' ', trim($gr['output']))));
            $isSudo = in_array('sudo', $groups, true);

            // Check for RSA key
            $keyR   = $this->run('sudo /bin/cat ' . escapeshellarg($home . '/.ssh/authorized_keys'));
            $hasKey = $keyR['success'] && !empty(trim($keyR['output']));

            // Password locked?
            $passR    = $this->run('sudo /usr/bin/passwd -S ' . escapeshellarg($username));
            $locked   = str_contains($passR['output'], ' L ');

            $users[] = [
                'username' => $username,
                'uid'      => $uid,
                'home'     => $home,
                'shell'    => $shell,
                'groups'   => $groups,
                'isSudo'   => $isSudo,
                'hasKey'   => $hasKey,
                'locked'   => $locked,
                'authMethod' => $hasKey ? 'rsa' : 'password',
            ];
        }

        return $users;
    }

    public function getAvailableGroups(): array
    {
        $r      = $this->run('sudo /bin/cat /etc/group');
        $groups = [];

        foreach ($r['lines'] as $line) {
            $p = explode(':', $line);
            if (count($p) < 4) continue;
            [$name, , $gid] = $p;
            $gid = (int) $gid;

            if ($gid >= 1000 || in_array($name, self::USEFUL_GROUPS, true)) {
                $groups[] = ['name' => $name, 'gid' => $gid];
            }
        }

        usort($groups, fn($a, $b) => strcmp($a['name'], $b['name']));

        return $groups;
    }

    public function createLinuxUser(
        string $username,
        array  $groups,
        bool   $isSudo,
        string $authMethod,
        string $credential
    ): array {
        if (!preg_match(self::USERNAME_REGEX, $username)) {
            return ['success' => false, 'output' => 'Nom d\'utilisateur invalide (lettres minuscules, chiffres, _ et - uniquement).'];
        }

        // Validate RSA key if applicable
        if ($authMethod === 'rsa' && !preg_match(self::RSA_KEY_REGEX, trim($credential))) {
            return ['success' => false, 'output' => 'Clé RSA publique invalide (format attendu: ssh-rsa/ed25519 AAAA...).'];
        }

        if ($authMethod === 'password' && strlen($credential) < 8) {
            return ['success' => false, 'output' => 'Le mot de passe doit faire au moins 8 caractères.'];
        }

        // Check already exists
        $check = $this->run('id ' . escapeshellarg($username));
        if ($check['success']) {
            return ['success' => false, 'output' => "L'utilisateur '{$username}' existe déjà."];
        }

        // Create user with home dir
        $r = $this->run('sudo /usr/sbin/useradd -m -s /bin/bash ' . escapeshellarg($username));
        if (!$r['success']) {
            return ['success' => false, 'output' => 'Erreur création: ' . $r['output']];
        }

        // Set credential
        if ($authMethod === 'password') {
            $this->runWithInput('sudo /usr/sbin/chpasswd', $username . ':' . $credential);
        } else {
            $home = '/home/' . $username;
            $this->run('sudo /bin/mkdir -p ' . escapeshellarg($home . '/.ssh'));
            $this->run('sudo /bin/chmod 700 ' . escapeshellarg($home . '/.ssh'));
            $this->writeFileContent($home . '/.ssh/authorized_keys', trim($credential) . "\n");
            $this->run('sudo /bin/chmod 600 ' . escapeshellarg($home . '/.ssh/authorized_keys'));
            $this->run('sudo /bin/chown -R ' . escapeshellarg($username . ':' . $username) . ' ' . escapeshellarg($home . '/.ssh'));
            // Lock password login when RSA is used
            $this->run('sudo /usr/bin/passwd -l ' . escapeshellarg($username));
        }

        // Build groups list
        $allGroups = array_values(array_unique(array_filter(array_map('trim', $groups))));
        if ($isSudo && !in_array('sudo', $allGroups, true)) {
            $allGroups[] = 'sudo';
        }

        foreach ($allGroups as $g) {
            if (preg_match('/^[a-z_][a-z0-9_-]{0,31}$/', $g)) {
                $this->run('sudo /usr/sbin/usermod -aG ' . escapeshellarg($g) . ' ' . escapeshellarg($username));
            }
        }

        return ['success' => true, 'output' => "Utilisateur '{$username}' créé avec succès."];
    }

    public function updateLinuxUser(
        string $username,
        array  $groups,
        bool   $isSudo,
        string $authMethod,
        string $credential
    ): array {
        if (!preg_match(self::USERNAME_REGEX, $username)) {
            return ['success' => false, 'output' => 'Nom d\'utilisateur invalide.'];
        }

        // Security: don't touch system users
        $idR = $this->run('id -u ' . escapeshellarg($username));
        $uid = (int) trim($idR['output']);
        if ($uid < 1000) {
            return ['success' => false, 'output' => 'Modification d\'un utilisateur système refusée.'];
        }

        // Build supplementary groups
        $allGroups = array_values(array_unique(array_filter(array_map('trim', $groups))));
        if ($isSudo && !in_array('sudo', $allGroups, true)) {
            $allGroups[] = 'sudo';
        } elseif (!$isSudo) {
            $allGroups = array_values(array_filter($allGroups, fn($g) => $g !== 'sudo'));
        }

        // Validate each group name
        $safeGroups = array_filter($allGroups, fn($g) => preg_match('/^[a-z_][a-z0-9_-]{0,31}$/', $g));
        $groupStr   = implode(',', $safeGroups);

        $this->run('sudo /usr/sbin/usermod -G ' . escapeshellarg($groupStr) . ' ' . escapeshellarg($username));

        // Update credential (only if provided)
        $credential = trim($credential);
        if ($credential !== '') {
            if ($authMethod === 'rsa') {
                if (!preg_match(self::RSA_KEY_REGEX, $credential)) {
                    return ['success' => false, 'output' => 'Clé RSA publique invalide.'];
                }
                $homeR = $this->run('eval echo ~' . escapeshellarg($username));
                $home  = trim($homeR['output']) ?: '/home/' . $username;

                $this->run('sudo /bin/mkdir -p ' . escapeshellarg($home . '/.ssh'));
                $this->run('sudo /bin/chmod 700 ' . escapeshellarg($home . '/.ssh'));
                $this->writeFileContent($home . '/.ssh/authorized_keys', $credential . "\n");
                $this->run('sudo /bin/chmod 600 ' . escapeshellarg($home . '/.ssh/authorized_keys'));
                $this->run('sudo /bin/chown -R ' . escapeshellarg($username . ':' . $username) . ' ' . escapeshellarg($home . '/.ssh'));
                $this->run('sudo /usr/bin/passwd -l ' . escapeshellarg($username));
            } else {
                if (strlen($credential) < 8) {
                    return ['success' => false, 'output' => 'Le mot de passe doit faire au moins 8 caractères.'];
                }
                $this->runWithInput('sudo /usr/sbin/chpasswd', $username . ':' . $credential);
                $this->run('sudo /usr/bin/passwd -u ' . escapeshellarg($username));
            }
        }

        return ['success' => true, 'output' => "Utilisateur '{$username}' mis à jour."];
    }

    public function deleteLinuxUser(string $username): array
    {
        if (!preg_match(self::USERNAME_REGEX, $username)) {
            return ['success' => false, 'output' => 'Nom d\'utilisateur invalide.'];
        }

        $idR = $this->run('id -u ' . escapeshellarg($username));
        $uid = (int) trim($idR['output']);
        if ($uid < 1000 || $uid === 65534) {
            return ['success' => false, 'output' => 'Suppression d\'un utilisateur système refusée (UID < 1000).'];
        }

        $r = $this->run('sudo /usr/sbin/userdel -r ' . escapeshellarg($username));

        return [
            'success' => $r['success'],
            'output'  => $r['success'] ? "Utilisateur '{$username}' supprimé avec son répertoire home." : $r['output'],
        ];
    }

    // ── Helpers privés ───────────────────────────────────────

    private function runWithInput(string $cmd, string $input): array
    {
        $desc = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open($cmd, $desc, $pipes);

        if (!is_resource($proc)) {
            return ['success' => false, 'output' => 'Impossible d\'ouvrir le processus.'];
        }

        fwrite($pipes[0], $input . "\n");
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);

        return ['success' => $exitCode === 0, 'output' => $stdout ?: $stderr];
    }

    private function writeFileContent(string $path, string $content): bool
    {
        $desc = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open('sudo /usr/bin/tee ' . escapeshellarg($path) . ' > /dev/null', $desc, $pipes);

        if (!is_resource($proc)) {
            return false;
        }

        fwrite($pipes[0], $content);
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($proc) === 0;
    }
}
