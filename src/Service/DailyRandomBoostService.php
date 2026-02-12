<?php

namespace App\Service;

use App\Entity\DailyRandomBoost;
use App\Entity\FeaturedBooking;
use App\Repository\DailyRandomBoostRepository;
use App\Repository\FeaturedBookingRepository;
use App\Repository\ServerRepository;
use Doctrine\ORM\EntityManagerInterface;

class DailyRandomBoostService
{
    public function __construct(
        private EntityManagerInterface $em,
        private SettingsService $settings,
        private DailyRandomBoostRepository $dailyBoostRepo,
        private FeaturedBookingRepository $bookingRepo,
        private ServerRepository $serverRepo,
    ) {
    }

    public function ensureTodayBoost(): ?DailyRandomBoost
    {
        if (!$this->settings->getBool('premium_daily_random_boost_enabled', true)) {
            return null;
        }

        $today = new \DateTime('today');

        $existing = $this->dailyBoostRepo->findByDate($today);
        if ($existing) {
            return $existing;
        }

        // Get all active+approved servers
        $servers = $this->serverRepo->findAllApprovedActive();
        if (empty($servers)) {
            return null;
        }

        // Sort by monthly votes ascending, take bottom 30%
        usort($servers, fn($a, $b) => $a->getMonthlyVotes() <=> $b->getMonthlyVotes());
        $bottomCount = max(1, (int) ceil(count($servers) * 0.3));
        $candidates = array_slice($servers, 0, $bottomCount);

        // Exclude servers already boosted today
        $now = new \DateTime('now');
        $todayEnd = new \DateTime('tomorrow');
        $candidates = array_filter($candidates, function ($server) use ($now, $todayEnd) {
            return !$this->bookingRepo->hasActiveBookingForServer($server, new \DateTime('today'), $todayEnd);
        });

        $candidates = array_values($candidates);
        if (empty($candidates)) {
            return null;
        }

        // Pick random
        $server = $candidates[array_rand($candidates)];

        // Find first available homepage position (5→1, cheapest first)
        $startsAt = new \DateTimeImmutable('today 00:00');
        $endsAt = new \DateTimeImmutable('today 12:00');
        $assignedPosition = null;

        for ($pos = 5; $pos >= 1; $pos--) {
            if ($this->bookingRepo->isPositionAvailable(FeaturedBooking::SCOPE_HOMEPAGE, $pos, $startsAt, $endsAt)) {
                $assignedPosition = $pos;
                break;
            }
        }

        if ($assignedPosition === null) {
            return null;
        }

        $booking = new FeaturedBooking();
        $booking->setServer($server);
        $booking->setUser($server->getOwner());
        $booking->setScope(FeaturedBooking::SCOPE_HOMEPAGE);
        $booking->setPosition($assignedPosition);
        $booking->setStartsAt($startsAt);
        $booking->setEndsAt($endsAt);
        $booking->setBoostTokensUsed(0);

        $this->em->persist($booking);

        $dailyBoost = new DailyRandomBoost();
        $dailyBoost->setServer($server);
        $dailyBoost->setDate($today);

        $this->em->persist($dailyBoost);
        $this->em->flush();

        return $dailyBoost;
    }
}
