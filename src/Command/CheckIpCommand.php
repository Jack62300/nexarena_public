<?php

namespace App\Command;

use App\Service\IpSecurityService;
use App\Service\SettingsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check-ip',
    description: 'Tester la détection VPN/proxy/pays (IPQS) pour une adresse IP',
)]
class CheckIpCommand extends Command
{
    public function __construct(
        private IpSecurityService $ipSecurity,
        private SettingsService $settings,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('ip', InputArgument::REQUIRED, 'Adresse IP à tester')
            ->addOption('clear-cache', 'c', InputOption::VALUE_NONE, 'Vider le cache pour cette IP avant le test')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $ip = $input->getArgument('ip');

        $io->title("Diagnostic IPQualityScore pour : $ip");

        // Vérif clé API
        $apiKey = $this->settings->get('ipqs_api_key', '');
        if (empty(trim($apiKey))) {
            $io->error([
                'ipqs_api_key non configurée !',
                'Le check VPN/pays est désactivé (fail-open).',
                'Aller dans Admin → Paramètres → Clés API → IPQualityScore API Key.',
            ]);
            return Command::FAILURE;
        }
        $io->text('<info>Clé API IPQS : ' . substr($apiKey, 0, 8) . '...</info>');

        // Vider le cache si demandé
        if ($input->getOption('clear-cache')) {
            $this->ipSecurity->clearCache($ip);
            $io->note("Cache vidé pour $ip");
        }

        // Appel API
        $io->text('Appel IPQualityScore en cours...');
        $data = $this->ipSecurity->getFullReport($ip);

        if (!empty($data['error'])) {
            $io->error('L\'API a retourné une erreur → fail-open → accès autorisé.');
            return Command::FAILURE;
        }

        // Affichage des champs pertinents
        $io->table(
            ['Champ', 'Valeur'],
            [
                ['success',          $data['success'] ? 'true' : 'false'],
                ['country_code',     $data['country_code'] ?? 'N/A'],
                ['city',             ($data['city'] ?? '') . ', ' . ($data['region'] ?? '')],
                ['ISP',              $data['ISP'] ?? 'N/A'],
                ['vpn',              ($data['vpn'] ?? false) ? '🔴 OUI' : '✅ non'],
                ['proxy',            ($data['proxy'] ?? false) ? '🔴 OUI' : '✅ non'],
                ['tor',              ($data['tor'] ?? false) ? '🔴 OUI' : '✅ non'],
                ['active_vpn',       ($data['active_vpn'] ?? false) ? '🔴 OUI' : '✅ non'],
                ['active_tor',       ($data['active_tor'] ?? false) ? '🔴 OUI' : '✅ non'],
                ['fraud_score',      ($data['fraud_score'] ?? 0) . '/100'],
                ['recent_abuse',     ($data['recent_abuse'] ?? false) ? 'OUI' : 'non'],
                ['bot_status',       ($data['bot_status'] ?? false) ? 'OUI' : 'non'],
                ['connection_type',  $data['connection_type'] ?? 'N/A'],
                ['mobile',           ($data['mobile'] ?? false) ? 'OUI' : 'non'],
            ]
        );

        // Résumé VPN
        $isVpn = $this->ipSecurity->isVpnOrProxy($ip);
        if ($isVpn) {
            $io->error("BLOQUÉ — VPN/proxy/Tor détecté.");
        } else {
            $io->success("VPN/proxy : non détecté (passerait).");
        }

        // Résumé pays
        $country     = $this->ipSecurity->getCountryCode($ip);
        $allowedStr  = $this->settings->get('allowed_countries', '');
        $blockActive = $this->settings->getBool('country_block_enabled', false);

        if (!$blockActive) {
            $io->note("Filtrage par pays : désactivé (country_block_enabled = 0).");
        } elseif (empty(trim($allowedStr))) {
            $io->note("Filtrage par pays : activé mais allowed_countries est vide → tous les pays passent.");
        } else {
            $countryAllowed = $this->ipSecurity->isCountryAllowed($ip);
            if ($countryAllowed) {
                $io->success("Pays $country : autorisé (liste : $allowedStr).");
            } else {
                $io->error("Pays $country : BLOQUÉ (liste : $allowedStr).");
            }
        }

        return Command::SUCCESS;
    }
}
