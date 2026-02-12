<?php

namespace App\Command;

use App\Service\PremiumService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:process-twitch-subscriptions',
    description: 'Expire or auto-renew Twitch Live subscriptions',
)]
class ProcessTwitchSubscriptionsCommand extends Command
{
    public function __construct(
        private PremiumService $premiumService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $results = $this->premiumService->processExpiredTwitchSubscriptions();

        $io->success(sprintf(
            'Twitch subscriptions: %d renewed, %d expired.',
            $results['renewed'],
            $results['expired']
        ));

        return Command::SUCCESS;
    }
}
