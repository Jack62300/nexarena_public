<?php

namespace App\Command;

use App\Repository\ServerRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:reset-monthly-votes',
    description: 'Remet a zero les votes mensuels de tous les serveurs',
)]
class ResetMonthlyVotesCommand extends Command
{
    public function __construct(
        private ServerRepository $serverRepo,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $count = $this->serverRepo->resetAllMonthlyVotes();

        $io->success(sprintf('Votes mensuels remis a zero pour %d serveur(s).', $count));

        return Command::SUCCESS;
    }
}
