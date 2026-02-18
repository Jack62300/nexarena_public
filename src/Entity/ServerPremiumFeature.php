<?php

namespace App\Entity;

use App\Repository\ServerPremiumFeatureRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ServerPremiumFeatureRepository::class)]
#[ORM\Table(name: 'server_premium_feature')]
#[ORM\UniqueConstraint(name: 'uniq_server_feature', columns: ['server_id', 'feature'])]
#[ORM\HasLifecycleCallbacks]
class ServerPremiumFeature
{
    public const FEATURE_THEME = 'theme';
    public const FEATURE_WIDGET = 'widget';
    public const FEATURE_TWITCH_LIVE = 'twitch_live';
    public const FEATURE_STATS = 'stats';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Server::class, inversedBy: 'premiumFeatures')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Server $server = null;

    #[ORM\Column(length: 30)]
    private ?string $feature = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $unlockedBy = null;

    #[ORM\Column]
    private int $tokensSpent = 0;

    #[ORM\Column]
    private ?\DateTimeImmutable $unlockedAt = null;

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

    public function getFeature(): ?string
    {
        return $this->feature;
    }

    public function setFeature(string $feature): static
    {
        $this->feature = $feature;
        return $this;
    }

    public function getUnlockedBy(): ?User
    {
        return $this->unlockedBy;
    }

    public function setUnlockedBy(?User $unlockedBy): static
    {
        $this->unlockedBy = $unlockedBy;
        return $this;
    }

    public function getTokensSpent(): int
    {
        return $this->tokensSpent;
    }

    public function setTokensSpent(int $tokensSpent): static
    {
        $this->tokensSpent = $tokensSpent;
        return $this;
    }

    public function getUnlockedAt(): ?\DateTimeImmutable
    {
        return $this->unlockedAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->unlockedAt = new \DateTimeImmutable();
    }
}
