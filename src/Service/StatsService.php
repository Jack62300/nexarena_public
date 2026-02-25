<?php

namespace App\Service;

use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Repository\GameCategoryRepository;
use App\Repository\ServerRepository;
use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use App\Repository\VoteRepository;
use Doctrine\DBAL\Types\Types;

class StatsService
{
    /** @var array<string, array{label: string, icon: string, color: string, provider: callable}> */
    private array $statProviders = [];

    public function __construct(
        private UserRepository $userRepository,
        private ArticleRepository $articleRepository,
        private GameCategoryRepository $gameCategoryRepository,
        private CategoryRepository $categoryRepository,
        private ServerRepository $serverRepository,
        private VoteRepository $voteRepository,
        private TransactionRepository $transactionRepository,
    ) {
        $this->registerDefaultStats();
    }

    /**
     * Enregistre un nouveau provider de stat.
     * Permet d'ajouter des stats au fur et a mesure du developpement.
     */
    public function registerStat(string $key, string $label, string $icon, string $color, callable $valueProvider): void
    {
        $this->statProviders[$key] = [
            'label' => $label,
            'icon' => $icon,
            'color' => $color,
            'provider' => $valueProvider,
        ];
    }

    /**
     * @return array<string, array{label: string, icon: string, color: string, value: mixed}>
     */
    public function getAllStats(): array
    {
        $stats = [];
        foreach ($this->statProviders as $key => $config) {
            $stats[$key] = [
                'label' => $config['label'],
                'icon' => $config['icon'],
                'color' => $config['color'],
                'value' => ($config['provider'])(),
            ];
        }

        return $stats;
    }

    /**
     * Recupere une seule stat par sa cle.
     */
    public function getStat(string $key): ?array
    {
        if (!isset($this->statProviders[$key])) {
            return null;
        }

        $config = $this->statProviders[$key];

        return [
            'label' => $config['label'],
            'icon' => $config['icon'],
            'color' => $config['color'],
            'value' => ($config['provider'])(),
        ];
    }

    private function registerDefaultStats(): void
    {
        $this->registerStat(
            'total_users',
            'Utilisateurs',
            'fas fa-users',
            '#4e73df',
            fn () => $this->userRepository->count([]),
        );

        $this->registerStat(
            'new_users_today',
            'Nouveaux aujourd\'hui',
            'fas fa-user-plus',
            '#36b9cc',
            function () {
                $today = new \DateTimeImmutable('today');
                return $this->userRepository->createQueryBuilder('u')
                    ->select('COUNT(u.id)')
                    ->where('u.createdAt >= :today')
                    ->setParameter('today', $today)
                    ->getQuery()
                    ->getSingleScalarResult();
            },
        );

        $this->registerStat(
            'approved_servers',
            'Serveurs actifs',
            'fas fa-server',
            '#45f882',
            fn () => $this->serverRepository->count(['isApproved' => true, 'isActive' => true]),
        );

        $this->registerStat(
            'votes_this_month',
            'Votes ce mois',
            'fas fa-vote-yea',
            '#fd7e14',
            function () {
                $start = new \DateTimeImmutable('first day of this month midnight');
                return $this->voteRepository->createQueryBuilder('v')
                    ->select('COUNT(v.id)')
                    ->where('v.votedAt >= :start')
                    ->setParameter('start', $start)
                    ->getQuery()
                    ->getSingleScalarResult();
            },
        );

        $this->registerStat(
            'total_revenue',
            'Revenus total',
            'fas fa-coins',
            '#f6c23e',
            fn () => number_format($this->transactionRepository->getTotalRevenue(), 2, ',', ' ') . ' €',
        );

        $this->registerStat(
            'sales_this_month',
            'Ventes ce mois',
            'fas fa-shopping-cart',
            '#6f42c1',
            fn () => $this->transactionRepository->getMonthlyPurchaseCount(),
        );
    }

    /**
     * @return array<int, array{month: string, total: float}>
     */
    public function getRevenueByMonth(int $months = 12): array
    {
        return $this->transactionRepository->getRevenueByMonth($months);
    }

    /**
     * @return array<int, array{name: string, count: int}>
     */
    public function getServerCountByCategory(): array
    {
        return $this->serverRepository->createQueryBuilder('s')
            ->select('c.name AS name, COUNT(s.id) AS count')
            ->join('s.category', 'c')
            ->groupBy('c.id')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, array{month: string, count: int}>
     */
    public function getUserRegistrationsByMonth(int $months = 12): array
    {
        $since = new \DateTimeImmutable("-{$months} months");
        $since = $since->modify('first day of this month midnight');

        $rows = $this->userRepository->createQueryBuilder('u')
            ->select('u.createdAt')
            ->where('u.createdAt >= :since')
            ->setParameter('since', $since, Types::DATETIME_IMMUTABLE)
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($rows as $row) {
            $key = $row['createdAt']->format('Y-m');
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $this->fillEmptyMonths($counts, $months);
    }

    /**
     * @return array<int, array{month: string, count: int}>
     */
    public function getVotesByMonth(int $months = 12): array
    {
        $since = new \DateTimeImmutable("-{$months} months");
        $since = $since->modify('first day of this month midnight');

        $rows = $this->voteRepository->createQueryBuilder('v')
            ->select('v.votedAt')
            ->where('v.votedAt >= :since')
            ->setParameter('since', $since, Types::DATETIME_IMMUTABLE)
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($rows as $row) {
            $key = $row['votedAt']->format('Y-m');
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $this->fillEmptyMonths($counts, $months);
    }

    /**
     * @param array<string, int> $counts
     * @return array<int, array{month: string, count: int}>
     */
    private function fillEmptyMonths(array $counts, int $months): array
    {
        $result = [];
        $date = new \DateTimeImmutable("-{$months} months");
        $date = $date->modify('first day of this month');

        for ($i = 0; $i <= $months; $i++) {
            $key = $date->format('Y-m');
            $result[] = ['month' => $key, 'count' => $counts[$key] ?? 0];
            $date = $date->modify('+1 month');
        }

        return $result;
    }
}
