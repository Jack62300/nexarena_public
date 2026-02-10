<?php

namespace App\Entity;

use App\Repository\VoteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VoteRepository::class)]
#[ORM\Table(name: 'vote')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_vote_server_ip', columns: ['server_id', 'voter_ip'])]
#[ORM\Index(name: 'idx_vote_server_user', columns: ['server_id', 'user_id'])]
#[ORM\Index(name: 'idx_vote_voted_at', columns: ['voted_at'])]
#[ORM\Index(name: 'idx_vote_discord_id', columns: ['discord_id'])]
#[ORM\Index(name: 'idx_vote_steam_id', columns: ['steam_id'])]
class Vote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Server::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Server $server = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    #[ORM\Column(length: 45)]
    private ?string $voterIp = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $voterUsername = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $discordId = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $steamId = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $voteProvider = null;

    #[ORM\Column]
    private bool $vpnChecked = false;

    #[ORM\Column]
    private ?\DateTimeImmutable $votedAt = null;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getVoterIp(): ?string
    {
        return $this->voterIp;
    }

    public function setVoterIp(string $voterIp): static
    {
        $this->voterIp = $voterIp;

        return $this;
    }

    public function getVoterUsername(): ?string
    {
        return $this->voterUsername;
    }

    public function setVoterUsername(?string $voterUsername): static
    {
        $this->voterUsername = $voterUsername;

        return $this;
    }

    public function getVotedAt(): ?\DateTimeImmutable
    {
        return $this->votedAt;
    }

    public function setVotedAt(\DateTimeImmutable $votedAt): static
    {
        $this->votedAt = $votedAt;

        return $this;
    }

    public function getDiscordId(): ?string
    {
        return $this->discordId;
    }

    public function setDiscordId(?string $discordId): static
    {
        $this->discordId = $discordId;

        return $this;
    }

    public function getSteamId(): ?string
    {
        return $this->steamId;
    }

    public function setSteamId(?string $steamId): static
    {
        $this->steamId = $steamId;

        return $this;
    }

    public function getVoteProvider(): ?string
    {
        return $this->voteProvider;
    }

    public function setVoteProvider(?string $voteProvider): static
    {
        $this->voteProvider = $voteProvider;

        return $this;
    }

    public function isVpnChecked(): bool
    {
        return $this->vpnChecked;
    }

    public function setVpnChecked(bool $vpnChecked): static
    {
        $this->vpnChecked = $vpnChecked;

        return $this;
    }

    #[ORM\PrePersist]
    public function setVotedAtValue(): void
    {
        $this->votedAt = new \DateTimeImmutable();
    }
}
