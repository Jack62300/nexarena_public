<?php

namespace App\Command;

use App\Entity\MonthlyBattle;
use App\Repository\MonthlyBattleRepository;
use App\Repository\ServerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:monthly-battle',
    description: 'Archive le top 10 du mois et designe le gagnant (les votes sont conserves)',
)]
class MonthlyBattleCommand extends Command
{
    public function __construct(
        private ServerRepository $serverRepo,
        private MonthlyBattleRepository $battleRepo,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Archive previous month
        $now = new \DateTimeImmutable();
        $previousMonth = $now->modify('-1 month');
        $month = (int) $previousMonth->format('n');
        $year = (int) $previousMonth->format('Y');

        // Check if already archived
        $existing = $this->battleRepo->findByMonthYear($month, $year);
        if ($existing) {
            $io->warning(sprintf('Le Monthly Battle pour %02d/%d existe deja.', $month, $year));
            return Command::SUCCESS;
        }

        // Get top 10 servers by monthly votes
        $topServers = $this->serverRepo->findTopByMonthlyVotes(10);

        if (empty($topServers)) {
            $io->warning('Aucun serveur trouve. Pas de Monthly Battle cree.');
            return Command::SUCCESS;
        }

        // Build servers data
        $serversData = [];
        $rank = 1;
        foreach ($topServers as $server) {
            $serversData[] = [
                'serverId' => $server->getId(),
                'serverName' => $server->getName(),
                'monthlyVotes' => $server->getMonthlyVotes(),
                'rank' => $rank,
            ];
            $rank++;
        }

        // Create MonthlyBattle
        $battle = new MonthlyBattle();
        $battle->setMonth($month);
        $battle->setYear($year);
        $battle->setServersData($serversData);
        $battle->setWinner($topServers[0]);

        $this->em->persist($battle);
        $this->em->flush();

        $io->success(sprintf(
            'Monthly Battle %02d/%d cree. Gagnant : %s (%d votes)',
            $month,
            $year,
            $topServers[0]->getName(),
            $topServers[0]->getMonthlyVotes(),
        ));

        return Command::SUCCESS;
    }
}
