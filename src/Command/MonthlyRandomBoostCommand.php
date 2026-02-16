<?php

namespace App\Command;

use App\Entity\Notification;
use App\Repository\ServerRepository;
use App\Service\NotificationService;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:monthly-random-boost',
    description: 'Tire au sort un serveur eligible et lui offre un jeton NexBoost (1er du mois)',
)]
class MonthlyRandomBoostCommand extends Command
{
    private const MIN_VOTES = 15;
    private const MAX_CANDIDATES = 10;
    private const ACTIVITY_HOURS = 48;
    private const TOKENS_REWARD = 1;

    public function __construct(
        private ServerRepository $serverRepo,
        private EntityManagerInterface $em,
        private NotificationService $notificationService,
        private SettingsService $settings,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simuler sans attribuer le jeton')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Forcer l\'execution meme si on n\'est pas le 1er du mois');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');

        $io->title('Tirage mensuel NexBoost');

        // Check day of month
        $today = new \DateTimeImmutable();
        if ((int) $today->format('j') !== 1 && !$force) {
            $io->note('Ce n\'est pas le 1er du mois. Utilisez --force pour forcer.');
            return Command::SUCCESS;
        }

        // Step 1: Get all active+approved servers
        $allServers = $this->serverRepo->findAllApprovedActive();
        $io->writeln(sprintf('Serveurs actifs et approuves : <info>%d</info>', count($allServers)));

        if (empty($allServers)) {
            $io->warning('Aucun serveur actif.');
            return Command::SUCCESS;
        }

        // Step 2: Filter by criteria
        $cutoff = new \DateTimeImmutable(sprintf('-%d hours', self::ACTIVITY_HOURS));
        $minVotes = self::MIN_VOTES;

        $eligible = [];
        foreach ($allServers as $server) {
            $owner = $server->getOwner();

            // At least MIN_VOTES monthly votes
            if ($server->getMonthlyVotes() < $minVotes) {
                continue;
            }

            // Server must exist for at least 48h (established server)
            if ($server->getCreatedAt() === null || $server->getCreatedAt() > $cutoff) {
                continue;
            }

            // Owner logged in within last 48h
            if ($owner->getLastLoginAt() === null || $owner->getLastLoginAt() < $cutoff) {
                continue;
            }

            $eligible[] = $server;
        }

        $io->writeln(sprintf('Serveurs eligibles (>=%d votes, cree >=48h, owner connecte <48h) : <info>%d</info>', $minVotes, count($eligible)));

        if (empty($eligible)) {
            $io->warning('Aucun serveur ne remplit les criteres. Pas de tirage ce mois.');
            return Command::SUCCESS;
        }

        // Step 3: Take the last 10 (most recent by monthly votes)
        usort($eligible, fn($a, $b) => $b->getMonthlyVotes() <=> $a->getMonthlyVotes());
        $candidates = array_slice($eligible, 0, self::MAX_CANDIDATES);

        $io->section(sprintf('Candidats (max %d plus recents)', self::MAX_CANDIDATES));
        $rows = [];
        foreach ($candidates as $s) {
            $rows[] = [
                $s->getId(),
                $s->getName(),
                $s->getMonthlyVotes(),
                $s->getOwner()->getUsername(),
                $s->getCreatedAt()->format('d/m/Y H:i'),
                $s->getOwner()->getLastLoginAt()?->format('d/m/Y H:i') ?? '-',
            ];
        }
        $io->table(['ID', 'Serveur', 'Votes', 'Proprietaire', 'Cree le', 'Derniere connexion'], $rows);

        // Step 4: Random pick
        $winner = $candidates[array_rand($candidates)];
        $owner = $winner->getOwner();

        $io->section('Resultat du tirage');
        $io->writeln(sprintf(
            'Serveur gagnant : <fg=green>#%d - %s</> (proprietaire: <info>%s</info>)',
            $winner->getId(),
            $winner->getName(),
            $owner->getUsername()
        ));

        if ($dryRun) {
            $io->note(sprintf(
                '[DRY-RUN] %s recevrait %d jeton(s) NexBoost. Solde actuel : %d',
                $owner->getUsername(),
                self::TOKENS_REWARD,
                $owner->getBoostTokenBalance()
            ));
            return Command::SUCCESS;
        }

        // Step 5: Give boost token
        $owner->addBoostTokens(self::TOKENS_REWARD);
        $this->em->flush();

        $io->writeln(sprintf(
            'Jeton NexBoost attribue ! Nouveau solde : <info>%d</info>',
            $owner->getBoostTokenBalance()
        ));

        // Step 6: Notify owner
        $monthLabel = $this->getMonthLabel((int) $today->format('n'));
        $this->notificationService->create(
            $owner,
            Notification::TYPE_REWARD,
            'Felicitations ! NexBoost offert',
            sprintf(
                'Votre serveur "%s" a ete tire au sort pour le tirage mensuel de %s. Vous avez recu %d jeton NexBoost !',
                $winner->getName(),
                $monthLabel,
                self::TOKENS_REWARD
            ),
            '/serveur/' . $winner->getId() . '/gestion'
        );

        $io->success(sprintf(
            'Tirage termine ! %s a recu %d NexBoost pour "%s". Notification envoyee.',
            $owner->getUsername(),
            self::TOKENS_REWARD,
            $winner->getName()
        ));

        return Command::SUCCESS;
    }

    private function getMonthLabel(int $month): string
    {
        $months = [
            1 => 'janvier', 2 => 'fevrier', 3 => 'mars', 4 => 'avril',
            5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'aout',
            9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'decembre',
        ];

        return $months[$month] ?? '';
    }
}
