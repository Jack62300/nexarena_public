<?php

namespace App\Entity;

use App\Repository\MonthlyBattleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MonthlyBattleRepository::class)]
#[ORM\Table(name: 'monthly_battle')]
#[ORM\UniqueConstraint(name: 'uniq_monthly_battle_month_year', columns: ['month', 'year'])]
class MonthlyBattle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $month = null;

    #[ORM\Column]
    private ?int $year = null;

    #[ORM\Column(type: 'json')]
    private array $serversData = [];

    #[ORM\ManyToOne(targetEntity: Server::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Server $winner = null;

    #[ORM\Column]
    private bool $premiumAwarded = false;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMonth(): ?int
    {
        return $this->month;
    }

    public function setMonth(int $month): static
    {
        $this->month = $month;

        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(int $year): static
    {
        $this->year = $year;

        return $this;
    }

    public function getServersData(): array
    {
        return $this->serversData;
    }

    public function setServersData(array $serversData): static
    {
        $this->serversData = $serversData;

        return $this;
    }

    public function getWinner(): ?Server
    {
        return $this->winner;
    }

    public function setWinner(?Server $winner): static
    {
        $this->winner = $winner;

        return $this;
    }

    public function isPremiumAwarded(): bool
    {
        return $this->premiumAwarded;
    }

    public function setPremiumAwarded(bool $premiumAwarded): static
    {
        $this->premiumAwarded = $premiumAwarded;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
