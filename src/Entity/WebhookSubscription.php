<?php

namespace App\Entity;

use App\Repository\WebhookSubscriptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WebhookSubscriptionRepository::class)]
#[ORM\Table(name: 'webhook_subscription')]
#[ORM\UniqueConstraint(name: 'uniq_webhook_sub_server', columns: ['server_id'])]
#[ORM\HasLifecycleCallbacks]
class WebhookSubscription
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Server::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Server $server = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $subscribedBy = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column]
    private bool $autoRenew = false;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $renewedAt = null;

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

    public function getSubscribedBy(): ?User
    {
        return $this->subscribedBy;
    }

    public function setSubscribedBy(?User $subscribedBy): static
    {
        $this->subscribedBy = $subscribedBy;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function isAutoRenew(): bool
    {
        return $this->autoRenew;
    }

    public function setAutoRenew(bool $autoRenew): static
    {
        $this->autoRenew = $autoRenew;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getRenewedAt(): ?\DateTimeImmutable
    {
        return $this->renewedAt;
    }

    public function setRenewedAt(?\DateTimeImmutable $renewedAt): static
    {
        $this->renewedAt = $renewedAt;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->expiresAt > new \DateTimeImmutable();
    }

    public function getDaysRemaining(): int
    {
        if (!$this->isActive()) {
            return 0;
        }
        $now = new \DateTimeImmutable();
        return max(0, (int) $now->diff($this->expiresAt)->days);
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
