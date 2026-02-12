<?php

namespace App\Entity;

use App\Repository\VoteRewardRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VoteRewardRepository::class)]
#[ORM\Table(name: 'vote_reward')]
#[ORM\HasLifecycleCallbacks]
class VoteReward
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Vote::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Vote $vote = null;

    #[ORM\ManyToOne(targetEntity: Server::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Server $server = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private string $tokensEarned = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 4, scale: 2)]
    private string $multiplier = '1.00';

    #[ORM\Column(length: 100)]
    private ?string $reason = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getVote(): ?Vote
    {
        return $this->vote;
    }

    public function setVote(?Vote $vote): static
    {
        $this->vote = $vote;
        return $this;
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

    public function getTokensEarned(): float
    {
        return (float) $this->tokensEarned;
    }

    public function setTokensEarned(float $amount): static
    {
        $this->tokensEarned = number_format($amount, 2, '.', '');
        return $this;
    }

    public function getMultiplier(): float
    {
        return (float) $this->multiplier;
    }

    public function setMultiplier(float $multiplier): static
    {
        $this->multiplier = number_format($multiplier, 2, '.', '');
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(string $reason): static
    {
        $this->reason = $reason;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
