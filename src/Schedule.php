<?php

namespace App;

use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule]
class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        return (new SymfonySchedule())
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)

            // --- Chaque minute ---
            // Envoie les annonces Discord programmees dont l'heure est passee
            ->add(RecurringMessage::cron(
                '* * * * *',
                new RunCommandMessage('app:send-scheduled-announcements')
            ))

            // --- Quotidien (04:00) ---
            // Gere l'expiration et le renouvellement des abonnements Twitch Live
            ->add(RecurringMessage::cron(
                '0 4 * * *',
                new RunCommandMessage('app:process-twitch-subscriptions')
            ))

            // --- 1er du mois ---
            // 00:05 - Tirage mensuel NexBoost
            ->add(RecurringMessage::cron(
                '5 0 1 * *',
                new RunCommandMessage('app:monthly-random-boost --force')
            ))
            // 00:30 - Archive le top 10 et designe le gagnant
            ->add(RecurringMessage::cron(
                '30 0 1 * *',
                new RunCommandMessage('app:monthly-battle')
            ))
        ;
    }
}
