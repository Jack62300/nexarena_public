<?php

namespace App\Command;

use App\Repository\DailyRandomBoostRepository;
use App\Repository\FeaturedBookingRepository;
use App\Repository\ServerRepository;
use App\Service\DailyRandomBoostService;
use App\Service\SettingsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-random-boost',
    description: 'Tester le systeme de boost aleatoire (daily boost existant)',
)]
class TestRandomBoostCommand extends Command
{
    public function __construct(
        private DailyRandomBoostService $dailyBoostService,
        private DailyRandomBoostRepository $dailyBoostRepo,
        private FeaturedBookingRepository $bookingRepo,
        private ServerRepository $serverRepo,
        private SettingsService $settings,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('run', null, InputOption::VALUE_NONE, 'Executer le boost (sinon dry-run)')
            ->addOption('status', null, InputOption::VALUE_NONE, 'Voir le statut actuel du boost du jour');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Test du systeme de Boost Aleatoire');

        // Check setting
        $enabled = $this->settings->getBool('premium_daily_random_boost_enabled', true);
        $io->writeln(sprintf(
            'Setting <info>premium_daily_random_boost_enabled</info> : %s',
            $enabled ? '<fg=green>ACTIVE</>' : '<fg=red>DESACTIVE</>'
        ));
        $io->newLine();

        // Status mode
        if ($input->getOption('status')) {
            return $this->showStatus($io);
        }

        // Show candidates (dry-run info)
        $servers = $this->serverRepo->findAllApprovedActive();
        $io->writeln(sprintf('Serveurs actifs et approuves : <info>%d</info>', count($servers)));

        if (empty($servers)) {
            $io->error('Aucun serveur actif. Impossible de tester.');
            return Command::FAILURE;
        }

        // Show bottom 30%
        usort($servers, fn($a, $b) => $a->getMonthlyVotes() <=> $b->getMonthlyVotes());
        $bottomCount = max(1, (int) ceil(count($servers) * 0.3));
        $candidates = array_slice($servers, 0, $bottomCount);

        $io->writeln(sprintf('Candidats (bottom 30%%) : <info>%d</info> serveurs', count($candidates)));
        $io->newLine();

        $io->section('Candidats eligibles');
        $rows = [];
        foreach ($candidates as $s) {
            $rows[] = [$s->getId(), $s->getName(), $s->getMonthlyVotes(), $s->getOwner()->getUsername()];
        }
        $io->table(['ID', 'Serveur', 'Votes mensuels', 'Proprietaire'], $rows);

        // Check existing daily boost
        $today = new \DateTime('today');
        $existing = $this->dailyBoostRepo->findByDate($today);
        if ($existing) {
            $io->warning(sprintf(
                'Un boost existe deja pour aujourd\'hui : serveur #%d "%s"',
                $existing->getServer()->getId(),
                $existing->getServer()->getName()
            ));
        }

        // Check available homepage positions
        $io->section('Positions homepage disponibles');
        $startsAt = new \DateTimeImmutable('today 00:00');
        $endsAt = new \DateTimeImmutable('today 12:00');
        $availablePositions = [];
        for ($pos = 5; $pos >= 1; $pos--) {
            $available = $this->bookingRepo->isPositionAvailable('homepage', $pos, $startsAt, $endsAt);
            $io->writeln(sprintf(
                '  Position %d : %s',
                $pos,
                $available ? '<fg=green>DISPONIBLE</>' : '<fg=red>OCCUPEE</>'
            ));
            if ($available) {
                $availablePositions[] = $pos;
            }
        }

        if (empty($availablePositions)) {
            $io->warning('Aucune position disponible. Le boost ne pourra pas etre attribue.');
        }

        // Execute if --run
        if ($input->getOption('run')) {
            $io->newLine();
            $io->section('Execution du boost');

            if ($existing) {
                $io->warning('Boost deja existant pour aujourd\'hui. Aucune action.');
                return Command::SUCCESS;
            }

            if (!$enabled) {
                $io->warning('Le setting est desactive. Activez premium_daily_random_boost_enabled pour executer.');
                return Command::SUCCESS;
            }

            $result = $this->dailyBoostService->ensureTodayBoost();

            if ($result) {
                $io->success(sprintf(
                    'Boost attribue ! Serveur #%d "%s" (proprietaire: %s)',
                    $result->getServer()->getId(),
                    $result->getServer()->getName(),
                    $result->getServer()->getOwner()->getUsername()
                ));
            } else {
                $io->error('Le boost n\'a pas pu etre attribue (positions occupees ou aucun candidat).');
            }
        } else {
            $io->newLine();
            $io->note('Mode dry-run. Utilisez --run pour executer le boost.');
        }

        return Command::SUCCESS;
    }

    private function showStatus(SymfonyStyle $io): int
    {
        $today = new \DateTime('today');
        $existing = $this->dailyBoostRepo->findByDate($today);

        if (!$existing) {
            $io->warning('Aucun boost aleatoire pour aujourd\'hui.');
            return Command::SUCCESS;
        }

        $server = $existing->getServer();
        $io->success('Boost actif aujourd\'hui');
        $io->table(
            ['Champ', 'Valeur'],
            [
                ['Serveur', sprintf('#%d - %s', $server->getId(), $server->getName())],
                ['Proprietaire', $server->getOwner()->getUsername()],
                ['Votes mensuels', $server->getMonthlyVotes()],
                ['Categorie', $server->getCategory()->getName()],
                ['Sous-categorie', $server->getGameCategory()?->getName() ?? '-'],
                ['Date du boost', $existing->getDate()->format('d/m/Y')],
                ['Cree le', $existing->getCreatedAt()->format('d/m/Y H:i:s')],
            ]
        );

        return Command::SUCCESS;
    }
}
