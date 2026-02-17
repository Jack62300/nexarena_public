<?php

namespace App\Command;

use App\Entity\MonthlyBattle;
use App\Repository\MonthlyBattleRepository;
use App\Repository\ServerRepository;
use App\Repository\VoteRepository;
use App\Service\NotificationService;
use App\Entity\Notification;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-vote-system',
    description: 'Teste et simule le systeme de votes mensuel (battle, boost, reset)',
)]
class TestVoteSystemCommand extends Command
{
    // Thresholds (mirror MonthlyRandomBoostCommand)
    private const MIN_VOTES = 15;
    private const MAX_CANDIDATES = 10;
    private const ACTIVITY_HOURS = 48;
    private const BOOST_TOKENS_REWARD = 1;

    public function __construct(
        private ServerRepository $serverRepo,
        private VoteRepository $voteRepo,
        private MonthlyBattleRepository $battleRepo,
        private EntityManagerInterface $em,
        private NotificationService $notificationService,
        private SettingsService $settings,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('stats', null, InputOption::VALUE_NONE, 'Afficher les statistiques de votes actuelles')
            ->addOption('battle', null, InputOption::VALUE_NONE, 'Simuler l\'archivage Monthly Battle (mois courant)')
            ->addOption('boost', null, InputOption::VALUE_NONE, 'Simuler le tirage mensuel NexBoost')
            ->addOption('reset-preview', null, InputOption::VALUE_NONE, 'Previsualiser le reset des votes mensuels')
            ->addOption('full-cycle', null, InputOption::VALUE_NONE, 'Lancer toutes les verifications en sequence (dry-run)')
            ->addOption('execute-battle', null, InputOption::VALUE_NONE, 'EXECUTER l\'archivage Monthly Battle pour le mois courant')
            ->addOption('execute-boost', null, InputOption::VALUE_NONE, 'EXECUTER le tirage NexBoost (attribue les tokens)')
            ->addOption('execute-reset', null, InputOption::VALUE_NONE, 'EXECUTER le reset des votes mensuels');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Test du systeme de votes Nexarena');

        $hasOption = false;

        if ($input->getOption('full-cycle')) {
            $hasOption = true;
            $this->showStats($io);
            $io->newLine();
            $this->simulateBattle($io, false);
            $io->newLine();
            $this->simulateBoost($io, false);
            $io->newLine();
            $this->previewReset($io);
            return Command::SUCCESS;
        }

        if ($input->getOption('stats')) {
            $hasOption = true;
            $this->showStats($io);
        }

        if ($input->getOption('battle')) {
            $hasOption = true;
            $this->simulateBattle($io, false);
        }

        if ($input->getOption('execute-battle')) {
            $hasOption = true;
            $this->simulateBattle($io, true);
        }

        if ($input->getOption('boost')) {
            $hasOption = true;
            $this->simulateBoost($io, false);
        }

        if ($input->getOption('execute-boost')) {
            $hasOption = true;
            $this->simulateBoost($io, true);
        }

        if ($input->getOption('reset-preview')) {
            $hasOption = true;
            $this->previewReset($io);
        }

        if ($input->getOption('execute-reset')) {
            $hasOption = true;
            $this->executeReset($io);
        }

        if (!$hasOption) {
            $io->writeln([
                'Utilisez une ou plusieurs options :',
                '  <info>--stats</info>           Statistiques generales de votes',
                '  <info>--battle</info>          Simuler l\'archivage Monthly Battle',
                '  <info>--boost</info>           Simuler le tirage NexBoost mensuel',
                '  <info>--reset-preview</info>   Previsualiser le reset des votes',
                '  <info>--full-cycle</info>      Toutes les verifications (dry-run)',
                '',
                '  <fg=yellow>--execute-battle</> Executer l\'archivage (ECRIT EN BASE)',
                '  <fg=yellow>--execute-boost</>  Executer le tirage boost (ECRIT EN BASE)',
                '  <fg=yellow>--execute-reset</>  Executer le reset des votes (ECRIT EN BASE)',
            ]);
        }

        return Command::SUCCESS;
    }

    // ─── Stats ────────────────────────────────────────────────────────────────

    private function showStats(SymfonyStyle $io): void
    {
        $io->section('Statistiques de votes actuelles');

        $now = new \DateTimeImmutable();
        $io->writeln(sprintf('Date / heure : <info>%s</info>', $now->format('d/m/Y H:i:s')));

        $allServers = $this->serverRepo->findAllApprovedActive();
        $io->writeln(sprintf('Serveurs actifs et approuves : <info>%d</info>', count($allServers)));

        if (empty($allServers)) {
            $io->warning('Aucun serveur actif.');
            return;
        }

        // Sort by monthly votes desc
        usort($allServers, fn($a, $b) => $b->getMonthlyVotes() <=> $a->getMonthlyVotes());

        $io->newLine();
        $io->writeln('<comment>Top 15 serveurs (votes mensuels) :</comment>');

        $rows = [];
        $rank = 1;
        foreach (array_slice($allServers, 0, 15) as $s) {
            $rows[] = [
                $rank++,
                sprintf('#%d', $s->getId()),
                $s->getName(),
                $s->getMonthlyVotes(),
                $s->getTotalVotes(),
                $s->getCategory()->getName(),
                $s->getOwner()->getUsername(),
            ];
        }
        $io->table(
            ['#', 'ID', 'Serveur', 'Votes mois', 'Votes total', 'Categorie', 'Proprietaire'],
            $rows
        );

        // Summary
        $totalMonthly = array_sum(array_map(fn($s) => $s->getMonthlyVotes(), $allServers));
        $totalAll = array_sum(array_map(fn($s) => $s->getTotalVotes(), $allServers));
        $io->writeln(sprintf('Total votes mensuels (tous serveurs) : <info>%d</info>', $totalMonthly));
        $io->writeln(sprintf('Total votes cumulatifs                : <info>%d</info>', $totalAll));

        // Settings
        $interval = $this->settings->getInt('vote_interval', 120);
        $vpnCheck = $this->settings->getBool('vote_vpn_check_enabled', true);
        $antifraud = $this->settings->getBool('vote_antifraud_enabled', true);
        $io->newLine();
        $io->writeln('<comment>Parametres actifs :</comment>');
        $io->writeln(sprintf('  Intervalle de vote : <info>%d min</info>', $interval));
        $io->writeln(sprintf('  Verification VPN   : %s', $vpnCheck ? '<fg=green>ACTIVE</>' : '<fg=red>DESACTIVEE</>'));
        $io->writeln(sprintf('  Anti-fraude        : %s', $antifraud ? '<fg=green>ACTIF</>' : '<fg=red>DESACTIVE</>'));

        // Monthly Battle history
        $io->newLine();
        $io->writeln('<comment>Historique des Monthly Battles :</comment>');
        $battles = $this->battleRepo->findAll();
        if (empty($battles)) {
            $io->writeln('  Aucun Monthly Battle archive.');
        } else {
            $battleRows = [];
            foreach (array_slice(array_reverse($battles), 0, 5) as $b) {
                $winner = $b->getWinner();
                $battleRows[] = [
                    sprintf('%02d/%d', $b->getMonth(), $b->getYear()),
                    $winner ? $winner->getName() : '(serveur supprime)',
                    count($b->getServersData()),
                ];
            }
            $io->table(['Mois', 'Gagnant', 'Serveurs archives'], $battleRows);
        }
    }

    // ─── Battle ───────────────────────────────────────────────────────────────

    private function simulateBattle(SymfonyStyle $io, bool $execute): void
    {
        $label = $execute ? 'Archivage Monthly Battle (EXECUTION)' : 'Simulation Monthly Battle (dry-run)';
        $io->section($label);

        $now = new \DateTimeImmutable();

        // Use current month (for test), not previous month like the real cron
        $month = (int) $now->format('n');
        $year  = (int) $now->format('Y');

        $io->writeln(sprintf('Mois cible : <info>%02d/%d</info> (mois courant pour le test)', $month, $year));

        // Check if already archived
        $existing = $this->battleRepo->findByMonthYear($month, $year);
        if ($existing) {
            $io->warning(sprintf('Un Monthly Battle pour %02d/%d existe deja en base.', $month, $year));
            if ($execute) {
                $io->writeln('Aucune action effectuee (deja archive).');
            }
            return;
        }

        // Top 10 by monthly votes
        $topServers = $this->serverRepo->findTopByMonthlyVotes(10);

        if (empty($topServers)) {
            $io->warning('Aucun serveur avec des votes. Pas de Monthly Battle possible.');
            return;
        }

        $io->writeln(sprintf('Serveurs dans le top : <info>%d</info>', count($topServers)));
        $io->newLine();

        $rows = [];
        $rank = 1;
        $serversData = [];
        foreach ($topServers as $s) {
            $rows[] = [
                $rank,
                sprintf('#%d', $s->getId()),
                $s->getName(),
                $s->getMonthlyVotes(),
                $s->getOwner()->getUsername(),
                $rank === 1 ? '<fg=yellow>GAGNANT</>' : '',
            ];
            $serversData[] = [
                'serverId'     => $s->getId(),
                'serverName'   => $s->getName(),
                'monthlyVotes' => $s->getMonthlyVotes(),
                'rank'         => $rank,
            ];
            $rank++;
        }

        $io->table(['Rang', 'ID', 'Serveur', 'Votes mensuels', 'Proprietaire', 'Statut'], $rows);

        $winner = $topServers[0];
        $io->writeln(sprintf(
            'Gagnant du mois : <fg=green>#%d - %s</> (%d votes)',
            $winner->getId(),
            $winner->getName(),
            $winner->getMonthlyVotes()
        ));

        if (!$execute) {
            $io->note('[DRY-RUN] Aucune ecriture en base. Utilisez --execute-battle pour persister.');
            return;
        }

        // Execute: create MonthlyBattle
        $battle = new MonthlyBattle();
        $battle->setMonth($month);
        $battle->setYear($year);
        $battle->setServersData($serversData);
        $battle->setWinner($winner);

        $this->em->persist($battle);
        $this->em->flush();

        $io->success(sprintf(
            'Monthly Battle %02d/%d archive ! Gagnant : %s (%d votes)',
            $month,
            $year,
            $winner->getName(),
            $winner->getMonthlyVotes()
        ));
    }

    // ─── Boost ────────────────────────────────────────────────────────────────

    private function simulateBoost(SymfonyStyle $io, bool $execute): void
    {
        $label = $execute ? 'Tirage NexBoost mensuel (EXECUTION)' : 'Simulation tirage NexBoost (dry-run)';
        $io->section($label);

        $today = new \DateTimeImmutable();
        $io->writeln(sprintf('Date du tirage : <info>%s</info>', $today->format('d/m/Y')));

        // Step 1: All active+approved servers
        $allServers = $this->serverRepo->findAllApprovedActive();
        $io->writeln(sprintf('Serveurs actifs et approuves : <info>%d</info>', count($allServers)));

        if (empty($allServers)) {
            $io->warning('Aucun serveur actif.');
            return;
        }

        // Step 2: Apply eligibility filters
        $cutoff = new \DateTimeImmutable(sprintf('-%d hours', self::ACTIVITY_HOURS));
        $excluded = ['votes' => 0, 'age' => 0, 'owner' => 0];
        $eligible = [];

        foreach ($allServers as $server) {
            $owner = $server->getOwner();

            if ($server->getMonthlyVotes() < self::MIN_VOTES) {
                $excluded['votes']++;
                continue;
            }

            if ($server->getCreatedAt() === null || $server->getCreatedAt() > $cutoff) {
                $excluded['age']++;
                continue;
            }

            if ($owner->getLastLoginAt() === null || $owner->getLastLoginAt() < $cutoff) {
                $excluded['owner']++;
                continue;
            }

            $eligible[] = $server;
        }

        $io->newLine();
        $io->writeln('<comment>Filtres appliques :</comment>');
        $io->writeln(sprintf(
            '  Exclus (< %d votes mensuels)         : <fg=red>%d</>',
            self::MIN_VOTES,
            $excluded['votes']
        ));
        $io->writeln(sprintf(
            '  Exclus (serveur < %dh d\'existence)    : <fg=red>%d</>',
            self::ACTIVITY_HOURS,
            $excluded['age']
        ));
        $io->writeln(sprintf(
            '  Exclus (proprietaire inactif > %dh)   : <fg=red>%d</>',
            self::ACTIVITY_HOURS,
            $excluded['owner']
        ));
        $io->writeln(sprintf('  Eligibles                            : <fg=green>%d</>', count($eligible)));

        if (empty($eligible)) {
            $io->warning(sprintf(
                'Aucun serveur eligible. Criteres : >= %d votes, cree >= %dh, owner connecte < %dh.',
                self::MIN_VOTES,
                self::ACTIVITY_HOURS,
                self::ACTIVITY_HOURS
            ));
            return;
        }

        // Step 3: Take top candidates sorted by votes desc
        usort($eligible, fn($a, $b) => $b->getMonthlyVotes() <=> $a->getMonthlyVotes());
        $candidates = array_slice($eligible, 0, self::MAX_CANDIDATES);

        $io->newLine();
        $io->writeln(sprintf(
            '<comment>Candidats (max %d, tries par votes desc) :</comment>',
            self::MAX_CANDIDATES
        ));

        $rows = [];
        foreach ($candidates as $s) {
            $rows[] = [
                sprintf('#%d', $s->getId()),
                $s->getName(),
                $s->getMonthlyVotes(),
                $s->getOwner()->getUsername(),
                $s->getOwner()->getLastLoginAt()?->format('d/m/Y H:i') ?? '-',
                $s->getCreatedAt()?->format('d/m/Y') ?? '-',
            ];
        }
        $io->table(
            ['ID', 'Serveur', 'Votes mois', 'Proprietaire', 'Derniere connexion', 'Cree le'],
            $rows
        );

        // Step 4: Random pick
        srand();
        $winnerIndex = array_rand($candidates);
        $winner = $candidates[$winnerIndex];
        $owner  = $winner->getOwner();

        $io->writeln(sprintf(
            'Serveur tire au sort : <fg=green>#%d - %s</> (proprietaire: <info>%s</info>)',
            $winner->getId(),
            $winner->getName(),
            $owner->getUsername()
        ));
        $io->writeln(sprintf(
            'Solde NexBoost actuel de %s : <info>%d</info>',
            $owner->getUsername(),
            $owner->getBoostTokenBalance()
        ));

        if (!$execute) {
            $io->note(sprintf(
                '[DRY-RUN] %s recevrait +%d jeton(s) NexBoost. Utilisez --execute-boost pour attribuer.',
                $owner->getUsername(),
                self::BOOST_TOKENS_REWARD
            ));
            return;
        }

        // Execute: award token + notify
        $owner->addBoostTokens(self::BOOST_TOKENS_REWARD);
        $this->em->flush();

        $monthLabel = $this->getMonthLabel((int) $today->format('n'));
        $this->notificationService->create(
            $owner,
            Notification::TYPE_REWARD,
            'Felicitations ! NexBoost offert',
            sprintf(
                'Votre serveur "%s" a ete tire au sort pour le tirage mensuel de %s. Vous avez recu %d jeton NexBoost !',
                $winner->getName(),
                $monthLabel,
                self::BOOST_TOKENS_REWARD
            ),
            '/serveur/' . $winner->getId() . '/gestion'
        );

        $io->success(sprintf(
            'Jeton NexBoost attribue ! %s : nouveau solde %d. Notification envoyee.',
            $owner->getUsername(),
            $owner->getBoostTokenBalance()
        ));
    }

    // ─── Reset preview ────────────────────────────────────────────────────────

    private function previewReset(SymfonyStyle $io): void
    {
        $io->section('Previsualisation du reset des votes mensuels');

        $allServers = $this->serverRepo->findAll();
        $withVotes  = array_filter($allServers, fn($s) => $s->getMonthlyVotes() > 0);

        $io->writeln(sprintf('Serveurs total                 : <info>%d</info>', count($allServers)));
        $io->writeln(sprintf('Serveurs avec votes mensuels   : <info>%d</info>', count($withVotes)));

        if (empty($withVotes)) {
            $io->note('Aucun serveur n\'a de votes mensuels. Rien a remettre a zero.');
            return;
        }

        $withVotesArr = array_values($withVotes);
        $totalVotes = array_sum(array_map(fn($s) => $s->getMonthlyVotes(), $withVotesArr));
        $io->writeln(sprintf('Total votes mensuels a reset   : <info>%d</info>', $totalVotes));

        // Show top 10 that would lose their votes
        usort($withVotesArr, fn($a, $b) => $b->getMonthlyVotes() <=> $a->getMonthlyVotes());
        $io->newLine();
        $io->writeln('<comment>Top 10 qui perdraient leurs votes :</comment>');

        $rows = [];
        foreach (array_slice($withVotesArr, 0, 10) as $s) {
            $rows[] = [
                sprintf('#%d', $s->getId()),
                $s->getName(),
                $s->getMonthlyVotes(),
                $s->getTotalVotes(),
                $s->getOwner()->getUsername(),
            ];
        }
        $io->table(['ID', 'Serveur', 'Votes mois (sera reset)', 'Votes total (conserve)', 'Proprietaire'], $rows);

        $io->note('[DRY-RUN] Aucune ecriture. Utilisez --execute-reset pour remettre a zero (irreversible).');
    }

    private function executeReset(SymfonyStyle $io): void
    {
        $io->section('Reset des votes mensuels (EXECUTION)');

        $count = $this->serverRepo->resetAllMonthlyVotes();

        $io->success(sprintf('Votes mensuels remis a zero pour %d serveur(s).', $count));
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function getMonthLabel(int $month): string
    {
        return [
            1 => 'janvier', 2 => 'fevrier', 3 => 'mars', 4 => 'avril',
            5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'aout',
            9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'decembre',
        ][$month] ?? '';
    }
}
