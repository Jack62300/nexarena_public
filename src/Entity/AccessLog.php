<?php

namespace App\Entity;

use App\Repository\AccessLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AccessLogRepository::class)]
#[ORM\Table(name: 'access_log')]
#[ORM\Index(columns: ['created_at'], name: 'idx_al_created')]
#[ORM\Index(columns: ['ip'], name: 'idx_al_ip')]
#[ORM\Index(columns: ['blocked', 'created_at'], name: 'idx_al_blocked_created')]
class AccessLog
{
    public const REASON_VPN     = 'vpn';
    public const REASON_COUNTRY = 'country';
    public const REASON_IP_BAN  = 'ip_ban';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** IP résolue par Symfony (tient compte de TRUSTED_PROXIES) */
    #[ORM\Column(length: 45)]
    private string $ip = '';

    /** REMOTE_ADDR brut (IP du proxy LiteSpeed/Nginx si présent) */
    #[ORM\Column(length: 45, nullable: true)]
    private ?string $remoteAddr = null;

    #[ORM\Column(length: 255)]
    private string $path = '';

    #[ORM\Column(length: 10)]
    private string $method = 'GET';

    /** Code pays ISO 2 lettres (FR, US…) ou null si pas vérifié */
    #[ORM\Column(length: 2, nullable: true)]
    private ?string $countryCode = null;

    /** VPN/proxy détecté selon IPQS, null si API non configurée */
    #[ORM\Column(nullable: true)]
    private ?bool $vpnDetected = null;

    /** Score de fraude IPQS (0-100) ou null si API non configurée */
    #[ORM\Column(nullable: true)]
    private ?int $fraudScore = null;

    /** IP dans la liste blanche trusted_ips → bypass total */
    #[ORM\Column]
    private bool $trusted = false;

    /** Accès bloqué ? */
    #[ORM\Column]
    private bool $blocked = false;

    /** Raison du blocage : vpn | country | ip_ban | null si autorisé */
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $blockReason = null;

    /** User-Agent tronqué à 500 caractères */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getIp(): string { return $this->ip; }
    public function setIp(string $ip): static { $this->ip = $ip; return $this; }

    public function getRemoteAddr(): ?string { return $this->remoteAddr; }
    public function setRemoteAddr(?string $v): static { $this->remoteAddr = $v; return $this; }

    public function getPath(): string { return $this->path; }
    public function setPath(string $path): static { $this->path = $path; return $this; }

    public function getMethod(): string { return $this->method; }
    public function setMethod(string $method): static { $this->method = $method; return $this; }

    public function getCountryCode(): ?string { return $this->countryCode; }
    public function setCountryCode(?string $v): static { $this->countryCode = $v; return $this; }

    public function isVpnDetected(): ?bool { return $this->vpnDetected; }
    public function setVpnDetected(?bool $v): static { $this->vpnDetected = $v; return $this; }

    public function getFraudScore(): ?int { return $this->fraudScore; }
    public function setFraudScore(?int $v): static { $this->fraudScore = $v; return $this; }

    public function isTrusted(): bool { return $this->trusted; }
    public function setTrusted(bool $v): static { $this->trusted = $v; return $this; }

    public function isBlocked(): bool { return $this->blocked; }
    public function setBlocked(bool $v): static { $this->blocked = $v; return $this; }

    public function getBlockReason(): ?string { return $this->blockReason; }
    public function setBlockReason(?string $v): static { $this->blockReason = $v; return $this; }

    public function getUserAgent(): ?string { return $this->userAgent; }
    public function setUserAgent(?string $v): static { $this->userAgent = $v; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
