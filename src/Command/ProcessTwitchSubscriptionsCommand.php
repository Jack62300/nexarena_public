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

        $serverResults = $this->premiumService->processExpiredTwitchSubscriptions();
        $userResults   = $this->premiumService->processExpiredUserTwitchSubscriptions();

        $io->success(sprintf(
            'Server Twitch: %d renewed, %d expired. | Profile Twitch: %d renewed, %d expired.',
            $serverResults['renewed'], $serverResults['expired'],
            $userResults['renewed'],   $userResults['expired']
        ));

        return Command::SUCCESS;
    }
}
