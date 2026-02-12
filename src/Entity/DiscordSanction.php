<?php

namespace App\Entity;

use App\Repository\DiscordSanctionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DiscordSanctionRepository::class)]
#[ORM\Index(columns: ['discord_user_id'], name: 'idx_sanction_discord_user')]
class DiscordSanction
{
    public const TYPE_WARN = 'warn';
    public const TYPE_MUTE = 'mute';
    public const TYPE_KICK = 'kick';
    public const TYPE_BAN = 'ban';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private ?string $discordUserId = null;

    #[ORM\Column(length: 100)]
    private ?string $discordUsername = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $siteUser = null;

    #[ORM\Column(length: 20)]
    private ?string $type = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(length: 100)]
    private ?string $issuedBy = null;

    #[ORM\Column(length: 20)]
    private ?string $issuedByDiscordId = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column]
    private bool $isRevoked = false;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $revokedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

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

    public function getDiscordUserId(): ?string
    {
        return $this->discordUserId;
    }

    public function setDiscordUserId(string $discordUserId): static
    {
        $this->discordUserId = $discordUserId;
        return $this;
    }

    public function getDiscordUsername(): ?string
    {
        return $this->discordUsername;
    }

    public function setDiscordUsername(string $discordUsername): static
    {
        $this->discordUsername = $discordUsername;
        return $this;
    }

    public function getSiteUser(): ?User
    {
        return $this->siteUser;
    }

    public function setSiteUser(?User $siteUser): static
    {
        $this->siteUser = $siteUser;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;
        return $this;
    }

    public function getIssuedBy(): ?string
    {
        return $this->issuedBy;
    }

    public function setIssuedBy(string $issuedBy): static
    {
        $this->issuedBy = $issuedBy;
        return $this;
    }

    public function getIssuedByDiscordId(): ?string
    {
        return $this->issuedByDiscordId;
    }

    public function setIssuedByDiscordId(string $issuedByDiscordId): static
    {
        $this->issuedByDiscordId = $issuedByDiscordId;
        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function isRevoked(): bool
    {
        return $this->isRevoked;
    }

    public function setIsRevoked(bool $isRevoked): static
    {
        $this->isRevoked = $isRevoked;
        return $this;
    }

    public function getRevokedBy(): ?User
    {
        return $this->revokedBy;
    }

    public function setRevokedBy(?User $revokedBy): static
    {
        $this->revokedBy = $revokedBy;
        return $this;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function setRevokedAt(?\DateTimeImmutable $revokedAt): static
    {
        $this->revokedAt = $revokedAt;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isActive(): bool
    {
        if ($this->isRevoked) {
            return false;
        }
        if ($this->expiresAt && $this->expiresAt < new \DateTimeImmutable()) {
            return false;
        }
        return true;
    }
}
