<?php

namespace App\Entity;

use App\Repository\AchievementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AchievementRepository::class)]
#[ORM\Table(name: 'achievement')]
#[ORM\HasLifecycleCallbacks]
class Achievement
{
    const RARITY_COMMON    = 'common';
    const RARITY_UNCOMMON  = 'uncommon';
    const RARITY_RARE      = 'rare';
    const RARITY_EPIC      = 'epic';
    const RARITY_LEGENDARY = 'legendary';

    const RARITIES = [
        self::RARITY_COMMON    => ['label' => 'Commun',      'color' => '#95a5a6'],
        self::RARITY_UNCOMMON  => ['label' => 'Peu commun',  'color' => '#2ecc71'],
        self::RARITY_RARE      => ['label' => 'Rare',        'color' => '#3498db'],
        self::RARITY_EPIC      => ['label' => 'Épique',      'color' => '#9b59b6'],
        self::RARITY_LEGENDARY => ['label' => 'Légendaire',  'color' => '#f39c12'],
    ];

    const CRITERIA_LABELS = [
        'vote_count'       => 'Votes reçus',
        'server_count'     => 'Serveurs créés',
        'account_age'      => 'Ancienneté du compte (jours)',
        'comment_count'    => 'Commentaires postés',
        'premium_purchase' => 'Achat premium',
        'votes_given'      => 'Votes donnés',
        'custom'           => 'Manuel uniquement',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 120, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $iconFileName = null;

    #[ORM\Column(length: 20, options: ['default' => 'common'])]
    private string $rarity = self::RARITY_COMMON;

    #[ORM\Column(nullable: true)]
    private ?array $criteria = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $rewardNexbits = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $rewardNexboost = 0;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /** @var Collection<int, UserAchievement> */
    #[ORM\OneToMany(targetEntity: UserAchievement::class, mappedBy: 'achievement', orphanRemoval: true)]
    private Collection $userAchievements;

    public function __construct()
    {
        $this->userAchievements = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = $slug; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getIconFileName(): ?string { return $this->iconFileName; }
    public function setIconFileName(?string $iconFileName): static { $this->iconFileName = $iconFileName; return $this; }

    public function getRarity(): string { return $this->rarity; }
    public function setRarity(string $rarity): static { $this->rarity = $rarity; return $this; }

    public function getRarityLabel(): string
    {
        return self::RARITIES[$this->rarity]['label'] ?? $this->rarity;
    }

    public function getRarityColor(): string
    {
        return self::RARITIES[$this->rarity]['color'] ?? '#95a5a6';
    }

    public function getCriteria(): ?array { return $this->criteria; }
    public function setCriteria(?array $criteria): static { $this->criteria = $criteria; return $this; }

    public function getRewardNexbits(): int { return $this->rewardNexbits; }
    public function setRewardNexbits(int $rewardNexbits): static { $this->rewardNexbits = max(0, $rewardNexbits); return $this; }

    public function getRewardNexboost(): int { return $this->rewardNexboost; }
    public function setRewardNexboost(int $rewardNexboost): static { $this->rewardNexboost = max(0, $rewardNexboost); return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }

    #[ORM\PrePersist]
    public function onPrePersist(): void { $this->createdAt = new \DateTimeImmutable(); }

    /** @return Collection<int, UserAchievement> */
    public function getUserAchievements(): Collection { return $this->userAchievements; }

    public function __toString(): string { return $this->name ?? ''; }
}
