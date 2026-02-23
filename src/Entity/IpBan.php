<?php

namespace App\Entity;

use App\Repository\IpBanRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IpBanRepository::class)]
#[ORM\Table(name: 'ip_ban')]
#[ORM\Index(columns: ['ip_address'], name: 'idx_ip_ban_ip')]
#[ORM\Index(columns: ['expires_at'], name: 'idx_ip_ban_expires')]
class IpBan
{
    public const TYPE_PERMANENT  = 'permanent';
    public const TYPE_TEMPORARY  = 'temporary';

    public const UNIT_HOURS = 'hours';
    public const UNIT_DAYS  = 'days';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 45)]
    private string $ipAddress;

    #[ORM\Column(length: 10)]
    private string $type = self::TYPE_PERMANENT;

    #[ORM\Column(nullable: true)]
    private ?int $duration = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $durationUnit = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $reason = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $bannedBy = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $revokedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getIpAddress(): string { return $this->ipAddress; }
    public function setIpAddress(string $ipAddress): static { $this->ipAddress = $ipAddress; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }

    public function getDuration(): ?int { return $this->duration; }
    public function setDuration(?int $duration): static { $this->duration = $duration; return $this; }

    public function getDurationUnit(): ?string { return $this->durationUnit; }
    public function setDurationUnit(?string $unit): static { $this->durationUnit = $unit; return $this; }

    public function getExpiresAt(): ?\DateTimeImmutable { return $this->expiresAt; }
    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static { $this->expiresAt = $expiresAt; return $this; }

    public function getReason(): ?string { return $this->reason; }
    public function setReason(?string $reason): static { $this->reason = $reason; return $this; }

    public function getBannedBy(): ?User { return $this->bannedBy; }
    public function setBannedBy(?User $bannedBy): static { $this->bannedBy = $bannedBy; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getRevokedBy(): ?User { return $this->revokedBy; }
    public function setRevokedBy(?User $revokedBy): static { $this->revokedBy = $revokedBy; return $this; }

    public function getRevokedAt(): ?\DateTimeImmutable { return $this->revokedAt; }
    public function setRevokedAt(?\DateTimeImmutable $revokedAt): static { $this->revokedAt = $revokedAt; return $this; }

    /**
     * Retourne true si le ban est actuellement actif (pas révoqué, pas expiré).
     */
    public function isCurrent(): bool
    {
        if (!$this->isActive) {
            return false;
        }

        if ($this->type === self::TYPE_TEMPORARY && $this->expiresAt !== null) {
            return $this->expiresAt > new \DateTimeImmutable();
        }

        return true; // permanent
    }

    /**
     * Construit la date d'expiration à partir de duration + durationUnit.
     */
    public function computeExpiresAt(): void
    {
        if ($this->type === self::TYPE_TEMPORARY && $this->duration !== null && $this->durationUnit !== null) {
            $interval = $this->durationUnit === self::UNIT_HOURS
                ? new \DateInterval('PT' . $this->duration . 'H')
                : new \DateInterval('P' . $this->duration . 'D');

            $this->expiresAt = (new \DateTimeImmutable())->add($interval);
        } else {
            $this->expiresAt = null;
        }
    }

    public function getFormattedDuration(): string
    {
        if ($this->type === self::TYPE_PERMANENT) {
            return 'Permanent';
        }

        if ($this->duration === null || $this->durationUnit === null) {
            return '—';
        }

        $label = $this->durationUnit === self::UNIT_HOURS ? 'heure' : 'jour';
        return $this->duration . ' ' . $label . ($this->duration > 1 ? 's' : '');
    }
}
