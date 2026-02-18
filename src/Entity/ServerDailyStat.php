<?php

namespace App\Entity;

use App\Repository\ServerDailyStatRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ServerDailyStatRepository::class)]
#[ORM\Table(name: 'server_daily_stat')]
#[ORM\UniqueConstraint(name: 'uniq_server_date', columns: ['server_id', 'stat_date'])]
class ServerDailyStat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Server::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Server $server = null;

    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $statDate = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $pageViews = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getServer(): ?Server
    {
        return $this->server;
    }

    public function setServer(?Server $server): static
    {
        $this->server = $server;
        return $this;
    }

    public function getStatDate(): ?\DateTimeImmutable
    {
        return $this->statDate;
    }

    public function setStatDate(\DateTimeImmutable $statDate): static
    {
        $this->statDate = $statDate;
        return $this;
    }

    public function getPageViews(): int
    {
        return $this->pageViews;
    }

    public function setPageViews(int $pageViews): static
    {
        $this->pageViews = $pageViews;
        return $this;
    }

    public function incrementPageViews(): static
    {
        $this->pageViews++;
        return $this;
    }
}
