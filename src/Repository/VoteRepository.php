<?php

namespace App\Repository;

use App\Entity\Server;
use App\Entity\User;
use App\Entity\Vote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Vote>
 */
class VoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vote::class);
    }

    public function hasUserVotedRecently(Server $server, User $user, int $intervalMinutes): bool
    {
        $since = new \DateTimeImmutable("-{$intervalMinutes} minutes");

        return (bool) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.server = :server')
            ->andWhere('v.user = :user')
            ->andWhere('v.votedAt > :since')
            ->setParameter('server', $server)
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function hasIpVotedRecently(Server $server, string $ip, int $intervalMinutes): bool
    {
        $since = new \DateTimeImmutable("-{$intervalMinutes} minutes");

        return (bool) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.server = :server')
            ->andWhere('v.voterIp = :ip')
            ->andWhere('v.votedAt > :since')
            ->setParameter('server', $server)
            ->setParameter('ip', $ip)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countVotesByIpInInterval(string $ip, int $intervalMinutes): int
    {
        $since = new \DateTimeImmutable("-{$intervalMinutes} minutes");

        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.voterIp = :ip')
            ->andWhere('v.votedAt > :since')
            ->setParameter('ip', $ip)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function hasDiscordIdVotedRecently(Server $server, string $discordId, int $intervalMinutes): bool
    {
        $since = new \DateTimeImmutable("-{$intervalMinutes} minutes");

        return (bool) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.server = :server')
            ->andWhere('v.discordId = :discordId')
            ->andWhere('v.votedAt > :since')
            ->setParameter('server', $server)
            ->setParameter('discordId', $discordId)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function hasSteamIdVotedRecently(Server $server, string $steamId, int $intervalMinutes): bool
    {
        $since = new \DateTimeImmutable("-{$intervalMinutes} minutes");

        return (bool) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.server = :server')
            ->andWhere('v.steamId = :steamId')
            ->andWhere('v.votedAt > :since')
            ->setParameter('server', $server)
            ->setParameter('steamId', $steamId)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getLastVoteTimeByDiscordId(Server $server, string $discordId): ?\DateTimeImmutable
    {
        $result = $this->createQueryBuilder('v')
            ->select('v.votedAt')
            ->where('v.server = :server')
            ->andWhere('v.discordId = :discordId')
            ->setParameter('server', $server)
            ->setParameter('discordId', $discordId)
            ->orderBy('v.votedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result ? $result['votedAt'] : null;
    }

    public function getLastVoteTimeBySteamId(Server $server, string $steamId): ?\DateTimeImmutable
    {
        $result = $this->createQueryBuilder('v')
            ->select('v.votedAt')
            ->where('v.server = :server')
            ->andWhere('v.steamId = :steamId')
            ->setParameter('server', $server)
            ->setParameter('steamId', $steamId)
            ->orderBy('v.votedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result ? $result['votedAt'] : null;
    }

    public function getLastVoteTimeByIp(Server $server, string $ip): ?\DateTimeImmutable
    {
        $result = $this->createQueryBuilder('v')
            ->select('v.votedAt')
            ->where('v.server = :server')
            ->andWhere('v.voterIp = :ip')
            ->setParameter('server', $server)
            ->setParameter('ip', $ip)
            ->orderBy('v.votedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result ? $result['votedAt'] : null;
    }

    public function getLastVoteTime(Server $server, ?User $user, string $ip): ?\DateTimeImmutable
    {
        $qb = $this->createQueryBuilder('v')
            ->select('v.votedAt')
            ->where('v.server = :server')
            ->setParameter('server', $server)
            ->orderBy('v.votedAt', 'DESC')
            ->setMaxResults(1);

        if ($user) {
            $qb->andWhere('v.user = :user')->setParameter('user', $user);
        } else {
            $qb->andWhere('v.voterIp = :ip')->setParameter('ip', $ip);
        }

        $result = $qb->getQuery()->getOneOrNullResult();

        return $result ? $result['votedAt'] : null;
    }

    /**
     * @return array<array{username: string, votes: int, last_vote: \DateTimeImmutable}>
     */
    public function getTopVotersByServer(Server $server, int $limit = 20, int $page = 1): array
    {
        $offset = ($page - 1) * $limit;

        return $this->createQueryBuilder('v')
            ->select('COALESCE(v.voterUsername, \'Anonyme\') AS username, COUNT(v.id) AS votes, MAX(v.votedAt) AS last_vote')
            ->where('v.server = :server')
            ->setParameter('server', $server)
            ->groupBy('username')
            ->orderBy('votes', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countUniqueVotersByServer(Server $server): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(DISTINCT COALESCE(v.voterUsername, v.voterIp))')
            ->where('v.server = :server')
            ->setParameter('server', $server)
            ->getQuery()
            ->getSingleScalarResult();
    }

    // API: Check by username
    public function findRecentVoteByUsername(Server $server, string $username, int $intervalMinutes): ?Vote
    {
        $since = new \DateTimeImmutable("-{$intervalMinutes} minutes");

        return $this->createQueryBuilder('v')
            ->where('v.server = :server')
            ->andWhere('v.voterUsername = :username')
            ->andWhere('v.votedAt > :since')
            ->setParameter('server', $server)
            ->setParameter('username', $username)
            ->setParameter('since', $since)
            ->orderBy('v.votedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // API: Check by IP
    public function findRecentVoteByIp(Server $server, string $ip, int $intervalMinutes): ?Vote
    {
        $since = new \DateTimeImmutable("-{$intervalMinutes} minutes");

        return $this->createQueryBuilder('v')
            ->where('v.server = :server')
            ->andWhere('v.voterIp = :ip')
            ->andWhere('v.votedAt > :since')
            ->setParameter('server', $server)
            ->setParameter('ip', $ip)
            ->setParameter('since', $since)
            ->orderBy('v.votedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // API: Check by Discord ID (join User table)
    public function findRecentVoteByDiscordId(Server $server, string $discordId, int $intervalMinutes): ?Vote
    {
        $since = new \DateTimeImmutable("-{$intervalMinutes} minutes");

        return $this->createQueryBuilder('v')
            ->join('v.user', 'u')
            ->where('v.server = :server')
            ->andWhere('u.discordId = :discordId')
            ->andWhere('v.votedAt > :since')
            ->setParameter('server', $server)
            ->setParameter('discordId', $discordId)
            ->setParameter('since', $since)
            ->orderBy('v.votedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // API: Check by user ID
    public function findRecentVoteByUserId(Server $server, int $userId, int $intervalMinutes): ?Vote
    {
        $since = new \DateTimeImmutable("-{$intervalMinutes} minutes");

        return $this->createQueryBuilder('v')
            ->where('v.server = :server')
            ->andWhere('v.user = :userId')
            ->andWhere('v.votedAt > :since')
            ->setParameter('server', $server)
            ->setParameter('userId', $userId)
            ->setParameter('since', $since)
            ->orderBy('v.votedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countDistinctIpsByFingerprint(string $fingerprint, int $hours): int
    {
        $since = new \DateTimeImmutable("-{$hours} hours");

        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(DISTINCT v.voterIp)')
            ->where('v.browserFingerprint = :fp')
            ->andWhere('v.votedAt > :since')
            ->setParameter('fp', $fingerprint)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countDistinctFingerprintsByIp(string $ip, int $hours): int
    {
        $since = new \DateTimeImmutable("-{$hours} hours");

        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(DISTINCT v.browserFingerprint)')
            ->where('v.voterIp = :ip')
            ->andWhere('v.browserFingerprint IS NOT NULL')
            ->andWhere('v.votedAt > :since')
            ->setParameter('ip', $ip)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByUserInDay(User $user): int
    {
        $startOfDay = new \DateTimeImmutable('today 00:00:00');

        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.user = :user')
            ->andWhere('v.votedAt >= :start')
            ->setParameter('user', $user)
            ->setParameter('start', $startOfDay)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByServerInDay(Server $server): int
    {
        $startOfDay = new \DateTimeImmutable('today 00:00:00');

        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.server = :server')
            ->andWhere('v.votedAt >= :start')
            ->setParameter('server', $server)
            ->setParameter('start', $startOfDay)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countTotalVotesForUserServers(User $user): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->join('v.server', 's')
            ->where('s.owner = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByFingerprintInInterval(string $fingerprint, int $minutes): int
    {
        $since = new \DateTimeImmutable("-{$minutes} minutes");

        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.browserFingerprint = :fp')
            ->andWhere('v.votedAt > :since')
            ->setParameter('fp', $fingerprint)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Votes grouped by date for last 30 days.
     * Returns all dates filled with 0 if missing.
     * @return array<array{date: string, votes: int}>
     */
    public function getDailyVotesForRange(Server $server, \DateTime $from, \DateTime $to): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT DATE(v.voted_at) AS date, COUNT(v.id) AS cnt
                FROM vote v
                WHERE v.server_id = :server AND v.voted_at BETWEEN :from AND :to
                GROUP BY DATE(v.voted_at)
                ORDER BY DATE(v.voted_at) ASC';

        $rows = $conn->fetchAllAssociative($sql, [
            'server' => $server->getId(),
            'from'   => $from->format('Y-m-d H:i:s'),
            'to'     => $to->format('Y-m-d H:i:s'),
        ]);

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['date']] = (int) $row['cnt'];
        }

        $result = [];
        $current = clone $from;
        while ($current <= $to) {
            $key = $current->format('Y-m-d');
            $result[] = ['date' => $key, 'votes' => $indexed[$key] ?? 0];
            $current->modify('+1 day');
        }

        return $result;
    }

    /**
     * Votes grouped by hour (0-23) for the given period.
     * @return array<array{hour: int, votes: int}>
     */
    public function getVotesByHour(Server $server, \DateTime $from): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT HOUR(v.voted_at) AS h, COUNT(v.id) AS cnt
                FROM vote v
                WHERE v.server_id = :server AND v.voted_at >= :from
                GROUP BY HOUR(v.voted_at)
                ORDER BY h ASC';

        $rows = $conn->fetchAllAssociative($sql, [
            'server' => $server->getId(),
            'from'   => $from->format('Y-m-d H:i:s'),
        ]);

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int) $row['h']] = (int) $row['cnt'];
        }

        $result = [];
        for ($h = 0; $h <= 23; $h++) {
            $result[] = ['hour' => $h, 'votes' => $indexed[$h] ?? 0];
        }

        return $result;
    }

    /**
     * Votes grouped by provider (discord/steam/null) for the given period.
     * @return array<array{provider: string, votes: int}>
     */
    public function getVotesByProvider(Server $server, \DateTime $from): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT COALESCE(v.vote_provider, \'unknown\') AS p, COUNT(v.id) AS cnt
                FROM vote v
                WHERE v.server_id = :server AND v.voted_at >= :from
                GROUP BY v.vote_provider
                ORDER BY cnt DESC';

        $rows = $conn->fetchAllAssociative($sql, [
            'server' => $server->getId(),
            'from'   => $from->format('Y-m-d H:i:s'),
        ]);

        $result = [];
        foreach ($rows as $row) {
            $result[] = ['provider' => $row['p'], 'votes' => (int) $row['cnt']];
        }

        return $result;
    }

    /**
     * @return Vote[]
     */
    public function findForAdminList(?Server $server = null, int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('v')
            ->leftJoin('v.server', 's')
            ->leftJoin('v.user', 'u')
            ->addSelect('s', 'u')
            ->orderBy('v.votedAt', 'DESC')
            ->setMaxResults($limit);

        if ($server) {
            $qb->where('v.server = :server')->setParameter('server', $server);
        }

        return $qb->getQuery()->getResult();
    }
}
