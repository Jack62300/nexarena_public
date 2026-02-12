<?php

namespace App\Repository;

use App\Entity\FeaturedBooking;
use App\Entity\GameCategory;
use App\Entity\Server;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FeaturedBooking>
 */
class FeaturedBookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FeaturedBooking::class);
    }

    /**
     * @return FeaturedBooking[]
     */
    public function findActiveAt(\DateTimeInterface $now): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.server', 's')
            ->leftJoin('s.category', 'c')
            ->leftJoin('s.gameCategory', 'gc')
            ->addSelect('s', 'c', 'gc')
            ->where('b.startsAt <= :now')
            ->andWhere('b.endsAt > :now')
            ->andWhere('s.isActive = true')
            ->andWhere('s.isApproved = true')
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }

    public function countActiveAt(\DateTimeInterface $now): int
    {
        return (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->leftJoin('b.server', 's')
            ->where('b.startsAt <= :now')
            ->andWhere('b.endsAt > :now')
            ->andWhere('s.isActive = true')
            ->andWhere('s.isApproved = true')
            ->setParameter('now', $now)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function hasActiveBookingForServer(Server $server, \DateTimeInterface $startsAt, \DateTimeInterface $endsAt): bool
    {
        $count = (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.server = :server')
            ->andWhere('b.startsAt < :endsAt')
            ->andWhere('b.endsAt > :startsAt')
            ->setParameter('server', $server)
            ->setParameter('startsAt', $startsAt)
            ->setParameter('endsAt', $endsAt)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function countOverlapping(\DateTimeInterface $startsAt, \DateTimeInterface $endsAt): int
    {
        return (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.startsAt < :endsAt')
            ->andWhere('b.endsAt > :startsAt')
            ->setParameter('startsAt', $startsAt)
            ->setParameter('endsAt', $endsAt)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return FeaturedBooking[]
     */
    public function findByServer(Server $server): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.server = :server')
            ->setParameter('server', $server)
            ->orderBy('b.startsAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns slot keys (e.g. "2026-02-11 00:00") where this server already has a booking.
     * @return string[]
     */
    public function getServerBookedSlots(Server $server, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $results = $this->createQueryBuilder('b')
            ->select('b.startsAt, b.endsAt')
            ->where('b.server = :server')
            ->andWhere('b.startsAt < :to')
            ->andWhere('b.endsAt > :from')
            ->setParameter('server', $server)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();

        $bookedSlots = [];
        $current = clone $from;
        while ($current < $to) {
            $slotEnd = (clone $current)->modify('+12 hours');
            $key = $current->format('Y-m-d H:i');

            foreach ($results as $row) {
                $bStart = $row['startsAt'];
                $bEnd = $row['endsAt'];
                if ($bStart < $slotEnd && $bEnd > $current) {
                    $bookedSlots[] = $key;
                    break;
                }
            }

            $current = $slotEnd;
        }

        return $bookedSlots;
    }

    /**
     * @return array<string, int> slot key => count
     */
    public function getSlotCountsForRange(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $results = $this->createQueryBuilder('b')
            ->select('b.startsAt, b.endsAt')
            ->where('b.startsAt < :to')
            ->andWhere('b.endsAt > :from')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();

        $counts = [];
        $current = clone $from;
        while ($current < $to) {
            $slotEnd = (clone $current)->modify('+12 hours');
            $key = $current->format('Y-m-d H:i');
            $counts[$key] = 0;

            foreach ($results as $row) {
                $bStart = $row['startsAt'];
                $bEnd = $row['endsAt'];
                if ($bStart < $slotEnd && $bEnd > $current) {
                    $counts[$key]++;
                }
            }

            $current = $slotEnd;
        }

        return $counts;
    }

    /**
     * Returns active positions [1..5] => Booking|null for given scope.
     * @return array<int, FeaturedBooking|null>
     */
    public function findActivePositions(string $scope, \DateTimeInterface $now, ?GameCategory $gameCategory = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->leftJoin('b.server', 's')
            ->leftJoin('s.category', 'c')
            ->leftJoin('s.gameCategory', 'gc')
            ->addSelect('s', 'c', 'gc')
            ->where('b.scope = :scope')
            ->andWhere('b.startsAt <= :now')
            ->andWhere('b.endsAt > :now')
            ->andWhere('s.isActive = true')
            ->andWhere('s.isApproved = true')
            ->setParameter('scope', $scope)
            ->setParameter('now', $now)
            ->orderBy('b.position', 'ASC');

        if ($scope === FeaturedBooking::SCOPE_GAME && $gameCategory) {
            $qb->andWhere('b.gameCategory = :gc')
                ->setParameter('gc', $gameCategory);
        } elseif ($scope === FeaturedBooking::SCOPE_GAME) {
            $qb->andWhere('b.gameCategory IS NOT NULL');
        }

        $bookings = $qb->getQuery()->getResult();

        $positions = [];
        for ($i = 1; $i <= 5; $i++) {
            $positions[$i] = null;
        }
        foreach ($bookings as $booking) {
            $pos = $booking->getPosition();
            if ($pos >= 1 && $pos <= 5) {
                $positions[$pos] = $booking;
            }
        }

        return $positions;
    }

    public function findActiveForPosition(string $scope, int $position, \DateTimeInterface $now, ?GameCategory $gameCategory = null): ?FeaturedBooking
    {
        $qb = $this->createQueryBuilder('b')
            ->leftJoin('b.server', 's')
            ->addSelect('s')
            ->where('b.scope = :scope')
            ->andWhere('b.position = :position')
            ->andWhere('b.startsAt <= :now')
            ->andWhere('b.endsAt > :now')
            ->andWhere('s.isActive = true')
            ->andWhere('s.isApproved = true')
            ->setParameter('scope', $scope)
            ->setParameter('position', $position)
            ->setParameter('now', $now);

        if ($scope === FeaturedBooking::SCOPE_GAME && $gameCategory) {
            $qb->andWhere('b.gameCategory = :gc')
                ->setParameter('gc', $gameCategory);
        }

        return $qb->setMaxResults(1)->getQuery()->getOneOrNullResult();
    }

    public function isPositionAvailable(string $scope, int $position, \DateTimeInterface $startsAt, \DateTimeInterface $endsAt, ?GameCategory $gameCategory = null): bool
    {
        $qb = $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.scope = :scope')
            ->andWhere('b.position = :position')
            ->andWhere('b.startsAt < :endsAt')
            ->andWhere('b.endsAt > :startsAt')
            ->setParameter('scope', $scope)
            ->setParameter('position', $position)
            ->setParameter('startsAt', $startsAt)
            ->setParameter('endsAt', $endsAt);

        if ($scope === FeaturedBooking::SCOPE_GAME && $gameCategory) {
            $qb->andWhere('b.gameCategory = :gc')
                ->setParameter('gc', $gameCategory);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() === 0;
    }

    /**
     * Returns slot availability for a date range: each slot key => array of taken positions.
     * @return array<string, array<int, bool>> slot key => [position => taken]
     */
    public function getPositionAvailabilityForRange(string $scope, \DateTimeInterface $from, \DateTimeInterface $to, ?GameCategory $gameCategory = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->select('b.startsAt, b.endsAt, b.position')
            ->where('b.scope = :scope')
            ->andWhere('b.startsAt < :to')
            ->andWhere('b.endsAt > :from')
            ->setParameter('scope', $scope)
            ->setParameter('from', $from)
            ->setParameter('to', $to);

        if ($scope === FeaturedBooking::SCOPE_GAME && $gameCategory) {
            $qb->andWhere('b.gameCategory = :gc')
                ->setParameter('gc', $gameCategory);
        }

        $results = $qb->getQuery()->getResult();

        $availability = [];
        $current = clone $from;
        while ($current < $to) {
            $slotEnd = (clone $current)->modify('+12 hours');
            $key = $current->format('Y-m-d H:i');
            $availability[$key] = [];

            foreach ($results as $row) {
                $bStart = $row['startsAt'];
                $bEnd = $row['endsAt'];
                if ($bStart < $slotEnd && $bEnd > $current) {
                    $availability[$key][$row['position']] = true;
                }
            }

            $current = $slotEnd;
        }

        return $availability;
    }

    /**
     * @return FeaturedBooking[]
     */
    public function findByServerScoped(Server $server, string $scope, ?GameCategory $gameCategory = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->where('b.server = :server')
            ->andWhere('b.scope = :scope')
            ->setParameter('server', $server)
            ->setParameter('scope', $scope)
            ->orderBy('b.startsAt', 'ASC');

        if ($scope === FeaturedBooking::SCOPE_GAME && $gameCategory) {
            $qb->andWhere('b.gameCategory = :gc')
                ->setParameter('gc', $gameCategory);
        }

        return $qb->getQuery()->getResult();
    }
}
