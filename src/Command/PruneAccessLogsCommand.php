<?php

namespace App\Command;

use App\Repository\AccessLogRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:prune-access-logs',
    description: 'Supprime les logs d\'accès plus vieux que N jours (défaut : 30).',
)]
class PruneAccessLogsCommand extends Command
{
    public function __construct(
        private AccessLogRepository $repo,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', 'd', InputOption::VALUE_OPTIONAL, 'Nombre de jours à conserver', 30);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io   = new SymfonyStyle($input, $output);
        $days = max(1, (int) $input->getOption('days'));

        $deleted = $this->repo->deleteOlderThan($days);

        $io->success("Purge terminée : {$deleted} entrée(s) supprimée(s) (plus vieilles que {$days} jours).");

        return Command::SUCCESS;
    }
}
