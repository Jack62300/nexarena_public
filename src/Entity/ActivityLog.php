<?php

namespace App\Entity;

use App\Repository\ActivityLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]
#[ORM\Table(name: 'activity_log')]
#[ORM\Index(columns: ['category'], name: 'idx_log_category')]
#[ORM\Index(columns: ['created_at'], name: 'idx_log_created_at')]
#[ORM\Index(columns: ['user_id'], name: 'idx_log_user_id')]
class ActivityLog
{
    // Category constants
    public const CAT_SETTINGS    = 'settings';
    public const CAT_USER        = 'user';
    public const CAT_SERVER      = 'server';
    public const CAT_COMMENT     = 'comment';
    public const CAT_RECRUITMENT = 'recruitment';
    public const CAT_CONTENT     = 'content';
    public const CAT_AUTH        = 'auth';
    public const CAT_PREMIUM     = 'premium';
    public const CAT_SYSTEM      = 'system';
    public const CAT_PROFILE     = 'profile';
    public const CAT_VOTE        = 'vote';
    public const CAT_PLUGIN      = 'plugin';
    public const CAT_PARTNER     = 'partner';
    public const CAT_SECURITY    = 'security';
    public const CAT_DISCORD     = 'discord';

    public const CATEGORIES = [
        self::CAT_SETTINGS    => ['label' => 'Parametres',  'color' => '#9b59b6'],
        self::CAT_USER        => ['label' => 'Utilisateur', 'color' => '#3498db'],
        self::CAT_SERVER      => ['label' => 'Serveur',     'color' => '#2ecc71'],
        self::CAT_COMMENT     => ['label' => 'Commentaire', 'color' => '#e67e22'],
        self::CAT_RECRUITMENT => ['label' => 'Recrutement', 'color' => '#1abc9c'],
        self::CAT_CONTENT     => ['label' => 'Contenu',     'color' => '#f1c40f'],
        self::CAT_AUTH        => ['label' => 'Auth',        'color' => '#95a5a6'],
        self::CAT_PREMIUM     => ['label' => 'Premium',     'color' => '#f39c12'],
        self::CAT_SYSTEM      => ['label' => 'Systeme',     'color' => '#e74c3c'],
        self::CAT_PROFILE     => ['label' => 'Profil',      'color' => '#27ae60'],
        self::CAT_VOTE        => ['label' => 'Vote',        'color' => '#e91e63'],
        self::CAT_PLUGIN      => ['label' => 'Plugin',      'color' => '#00bcd4'],
        self::CAT_PARTNER     => ['label' => 'Partenaire',  'color' => '#607d8b'],
        self::CAT_SECURITY    => ['label' => 'Securite',    'color' => '#c0392b'],
        self::CAT_DISCORD     => ['label' => 'Discord',     'color' => '#5865f2'],
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Null if user was deleted */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    /** Snapshot of the username at log time */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $username = null;

    /** e.g. "settings.save", "server.delete", "user.ban" */
    #[ORM\Column(length: 80)]
    private string $action = '';

    /** One of the CAT_* constants */
    #[ORM\Column(length: 30)]
    private string $category = self::CAT_SYSTEM;

    /** e.g. "Server", "User", "Setting" */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $objectType = null;

    /** DB id of the affected object */
    #[ORM\Column(nullable: true)]
    private ?int $objectId = null;

    /** Human-readable label (name, title, key…) */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $objectLabel = null;

    /** Extra contextual data */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $details = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getUsername(): ?string { return $this->username; }
    public function setUsername(?string $username): static { $this->username = $username; return $this; }

    public function getAction(): string { return $this->action; }
    public function setAction(string $action): static { $this->action = $action; return $this; }

    public function getCategory(): string { return $this->category; }
    public function setCategory(string $category): static { $this->category = $category; return $this; }

    public function getObjectType(): ?string { return $this->objectType; }
    public function setObjectType(?string $objectType): static { $this->objectType = $objectType; return $this; }

    public function getObjectId(): ?int { return $this->objectId; }
    public function setObjectId(?int $objectId): static { $this->objectId = $objectId; return $this; }

    public function getObjectLabel(): ?string { return $this->objectLabel; }
    public function setObjectLabel(?string $objectLabel): static { $this->objectLabel = $objectLabel; return $this; }

    public function getDetails(): ?array { return $this->details; }
    public function setDetails(?array $details): static { $this->details = $details; return $this; }

    public function getIpAddress(): ?string { return $this->ipAddress; }
    public function setIpAddress(?string $ipAddress): static { $this->ipAddress = $ipAddress; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getCategoryLabel(): string
    {
        return self::CATEGORIES[$this->category]['label'] ?? $this->category;
    }

    public function getCategoryColor(): string
    {
        return self::CATEGORIES[$this->category]['color'] ?? '#95a5a6';
    }
}
