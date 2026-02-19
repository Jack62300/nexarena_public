<?php

namespace App\Command;

use App\Entity\BlacklistEntry;
use App\Repository\BlacklistEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:init-blacklist',
    description: 'Seeds initial blacklist entries (usernames + disposable email domains). Idempotent.',
)]
class InitBlacklistCommand extends Command
{
    private const USERNAMES = [
        'admin', 'administrateur', 'moderateur', 'staff', 'support',
        'root', 'system', 'nexarena', 'superadmin', 'owner',
        'bot', 'api', 'test', 'null', 'undefined',
        'anonymous', 'guest', 'nobody', 'webmaster', 'postmaster',
        'abuse', 'security', 'help', 'contact', 'info',
        'service', 'equipe', 'team', 'official', 'verified',
    ];

    private const EMAIL_DOMAINS = [
        'mailinator.com', 'guerrillamail.com', 'tempmail.com', '10minutemail.com',
        'throwam.com', 'sharklasers.com', 'guerrillamailblock.com', 'grr.la',
        'spam4.me', 'trashmail.com', 'trashmail.net', 'trashmail.io',
        'fakeinbox.com', 'yopmail.com', 'yopmail.fr', 'jetable.fr',
        'dispostable.com', 'mailnull.com', 'maildrop.cc', 'spamgourmet.com',
        'spamgourmet.net', 'spamgourmet.org', 'dodgit.com', 'mailnesia.com',
        'trbvm.com', 'discard.email', 'spamhereplease.com', 'spamherelots.com',
        'getonemail.com', 'mailexpire.com', 'mohmal.com', 'spamdecoy.net',
        'boximail.com', 'spamfree24.org', 'inboxalias.com', 'spamevader.com',
        'gowikibooks.com', 'spamobox.com', 'guerrillamail.net', 'guerrillamail.org',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BlacklistEntryRepository $repo,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Initialisation des listes noires');

        $addedUsernames = 0;
        $addedDomains   = 0;

        foreach (self::USERNAMES as $value) {
            if (!$this->repo->isValueBlacklisted(BlacklistEntry::TYPE_USERNAME, $value)) {
                $entry = new BlacklistEntry();
                $entry->setType(BlacklistEntry::TYPE_USERNAME);
                $entry->setValue($value);
                $entry->setReason('Mot réservé (seed initial)');
                $this->em->persist($entry);
                $addedUsernames++;
            }
        }

        foreach (self::EMAIL_DOMAINS as $value) {
            if (!$this->repo->isValueBlacklisted(BlacklistEntry::TYPE_EMAIL_DOMAIN, $value)) {
                $entry = new BlacklistEntry();
                $entry->setType(BlacklistEntry::TYPE_EMAIL_DOMAIN);
                $entry->setValue($value);
                $entry->setReason('Email jetable (seed initial)');
                $this->em->persist($entry);
                $addedDomains++;
            }
        }

        $this->em->flush();

        $io->success("Pseudos ajoutés : $addedUsernames | Domaines ajoutés : $addedDomains");

        return Command::SUCCESS;
    }
}
