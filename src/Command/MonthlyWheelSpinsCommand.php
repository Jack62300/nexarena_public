<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:monthly-wheel-spins',
    description: 'Attribue 1 tour de roue gratuit a chaque utilisateur actif (mensuel)',
)]
class MonthlyWheelSpinsCommand extends Command
{
    private const BATCH_SIZE = 100;

    public function __construct(
        private UserRepository $userRepo,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simuler sans attribuer les tours')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Forcer l\'execution meme si ce n\'est pas le 1er du mois');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');

        $io->title('Attribution mensuelle des tours de roue gratuits');

        $today = new \DateTimeImmutable();
        if ((int) $today->format('j') !== 1 && !$force) {
            $io->note('Ce n\'est pas le 1er du mois. Utilisez --force pour forcer.');
            return Command::SUCCESS;
        }

        $currentMonth = $today->format('Y-m');

        // Get all non-banned users who haven't received their free spin this month
        $qb = $this->em->createQueryBuilder()
            ->select('u')
            ->from(\App\Entity\User::class, 'u')
            ->where('u.isBanned = false')
            ->andWhere('u.lastFreeSpinMonth IS NULL OR u.lastFreeSpinMonth != :month')
            ->setParameter('month', $currentMonth);

        $users = $qb->getQuery()->toIterable();
        $count = 0;

        foreach ($users as $user) {
            $user->addFreeSpins(1);
            $user->setLastFreeSpinMonth($currentMonth);
            $count++;

            if ($count % self::BATCH_SIZE === 0) {
                if (!$dryRun) {
                    $this->em->flush();
                    $this->em->clear();
                }
                $io->writeln(sprintf('  ... %d utilisateurs traites', $count));
            }
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        if ($dryRun) {
            $io->note(sprintf('[DRY-RUN] %d utilisateur(s) auraient recu 1 tour gratuit pour %s.', $count, $currentMonth));
        } else {
            $io->success(sprintf('%d utilisateur(s) ont recu 1 tour de roue gratuit pour %s.', $count, $currentMonth));
        }

        return Command::SUCCESS;
    }
}
