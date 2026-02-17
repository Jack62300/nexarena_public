<?php

namespace App\Command;

use App\Entity\FeaturedBooking;
use App\Repository\FeaturedBookingRepository;
use App\Repository\ServerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-give-boost',
    description: 'Test : donne un boost a un des 10 derniers serveurs actifs (sans deduire de tokens)',
)]
class TestGiveBoostCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private ServerRepository $serverRepo,
        private FeaturedBookingRepository $bookingRepo,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Forcer un serveur specifique par son ID')
            ->addOption('duration', null, InputOption::VALUE_REQUIRED, 'Duree du boost en heures (defaut: 12)', 12)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Afficher sans enregistrer en base');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $duration = max(1, (int) $input->getOption('duration'));

        $io->title('Test Give Boost');

        // --- 10 derniers serveurs actifs + approuves ---
        $servers = $this->serverRepo->createQueryBuilder('s')
            ->where('s.isActive = true')
            ->andWhere('s.isApproved = true')
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        if (empty($servers)) {
            $io->error('Aucun serveur actif et approuve trouve en base.');
            return Command::FAILURE;
        }

        // --- Afficher la liste ---
        $io->section(sprintf('10 derniers serveurs actifs (%d trouves)', count($servers)));
        $rows = [];
        foreach ($servers as $s) {
            $rows[] = [
                $s->getId(),
                $s->getName(),
                $s->getOwner()->getUsername(),
                $s->getBoostTokenBalance() . ' NexBoost',
                $s->getCreatedAt()->format('d/m/Y'),
            ];
        }
        $io->table(['ID', 'Serveur', 'Proprietaire', 'Boost tokens', 'Cree le'], $rows);

        // --- Choisir le serveur ---
        $forcedId = $input->getOption('id');
        if ($forcedId !== null) {
            $server = null;
            foreach ($servers as $s) {
                if ($s->getId() === (int) $forcedId) {
                    $server = $s;
                    break;
                }
            }
            if ($server === null) {
                $io->error(sprintf('Serveur #%d introuvable dans les 10 derniers serveurs actifs.', (int) $forcedId));
                return Command::FAILURE;
            }
        } else {
            // Prendre le plus recent (premier de la liste)
            $server = $servers[0];
        }

        $io->writeln(sprintf(
            'Serveur selectionne : <info>#%d %s</info> (proprietaire : %s)',
            $server->getId(),
            $server->getName(),
            $server->getOwner()->getUsername()
        ));

        // --- Trouver une position homepage disponible (5 -> 1) ---
        $startsAt = new \DateTimeImmutable('now');
        $endsAt   = $startsAt->modify("+{$duration} hours");

        $io->newLine();
        $io->section('Positions homepage disponibles maintenant');
        $assignedPosition = null;
        for ($pos = 5; $pos >= 1; $pos--) {
            $available = $this->bookingRepo->isPositionAvailable(FeaturedBooking::SCOPE_HOMEPAGE, $pos, $startsAt, $endsAt);
            $io->writeln(sprintf(
                '  Position %d : %s',
                $pos,
                $available ? '<fg=green>DISPONIBLE</>' : '<fg=red>OCCUPEE</>'
            ));
            if ($available && $assignedPosition === null) {
                $assignedPosition = $pos;
            }
        }

        if ($assignedPosition === null) {
            $io->error('Aucune position homepage disponible. Impossible de booster.');
            return Command::FAILURE;
        }

        $io->newLine();
        $io->writeln(sprintf(
            '%s Position <info>%d</info> choisie | Creneau : %s -> %s (%dh)',
            $dryRun ? '<fg=yellow>[DRY-RUN]</>' : '',
            $assignedPosition,
            $startsAt->format('d/m/Y H:i'),
            $endsAt->format('d/m/Y H:i'),
            $duration
        ));

        if ($dryRun) {
            $io->note('Mode dry-run : aucune modification en base. Relancez sans --dry-run pour appliquer.');
            return Command::SUCCESS;
        }

        // --- Creer le FeaturedBooking (0 tokens debites, commande de test) ---
        $booking = new FeaturedBooking();
        $booking->setServer($server);
        $booking->setUser($server->getOwner());
        $booking->setScope(FeaturedBooking::SCOPE_HOMEPAGE);
        $booking->setPosition($assignedPosition);
        $booking->setStartsAt($startsAt);
        $booking->setEndsAt($endsAt);
        $booking->setBoostTokensUsed(0);

        $this->em->persist($booking);
        $this->em->flush();

        $io->success(sprintf(
            'Boost applique ! Serveur "%s" (#%d) en position %d sur la homepage jusqu\'a %s. (0 tokens debites)',
            $server->getName(),
            $server->getId(),
            $assignedPosition,
            $endsAt->format('d/m/Y H:i')
        ));

        return Command::SUCCESS;
    }
}
