<?php

namespace App\Service;

use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Repository\GameCategoryRepository;
use App\Repository\ServerRepository;
use App\Repository\UserRepository;
use App\Repository\VoteRepository;

class StatsService
{
    /** @var array<string, callable> */
    private array $statProviders = [];

    public function __construct(
        private UserRepository $userRepository,
        private ArticleRepository $articleRepository,
        private GameCategoryRepository $gameCategoryRepository,
        private CategoryRepository $categoryRepository,
        private ServerRepository $serverRepository,
        private VoteRepository $voteRepository,
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
            'total_articles',
            'Articles',
            'fas fa-newspaper',
            '#1cc88a',
            fn () => $this->articleRepository->count([]),
        );

        $this->registerStat(
            'published_articles',
            'Articles publies',
            'fas fa-check-circle',
            '#36b9cc',
            fn () => $this->articleRepository->count(['isPublished' => true]),
        );

        $this->registerStat(
            'total_categories',
            'Categories de jeux',
            'fas fa-gamepad',
            '#f6c23e',
            fn () => $this->gameCategoryRepository->count([]),
        );

        $this->registerStat(
            'active_categories',
            'Categories actives',
            'fas fa-fire',
            '#e74a3b',
            fn () => $this->gameCategoryRepository->count(['isActive' => true]),
        );

        $this->registerStat(
            'new_users_today',
            'Nouveaux aujourd\'hui',
            'fas fa-user-plus',
            '#5a5c69',
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
            'total_servers',
            'Serveurs',
            'fas fa-server',
            '#45f882',
            fn () => $this->serverRepository->count([]),
        );

        $this->registerStat(
            'approved_servers',
            'Serveurs approuves',
            'fas fa-check-double',
            '#1cc88a',
            fn () => $this->serverRepository->count(['isApproved' => true, 'isActive' => true]),
        );

        $this->registerStat(
            'total_votes',
            'Votes total',
            'fas fa-vote-yea',
            '#e74a3b',
            fn () => $this->voteRepository->count([]),
        );
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
            ->setParameter('since', $since)
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
            ->setParameter('since', $since)
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
