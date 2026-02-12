<?php

namespace App\Entity;

use App\Repository\PremiumPlanRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PremiumPlanRepository::class)]
#[ORM\Table(name: 'premium_plan')]
#[ORM\HasLifecycleCallbacks]
class PremiumPlan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 120, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $price = null;

    #[ORM\Column(length: 3, options: ['default' => 'EUR'])]
    private string $currency = 'EUR';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $iconFileName = null;

    #[ORM\Column]
    private int $tokensGiven = 0;

    #[ORM\Column]
    private int $boostTokensGiven = 0;

    public const TYPE_DEFAULT = 'default';
    public const TYPE_CUSTOM = 'custom';

    #[ORM\Column(length: 20, options: ['default' => 'default'])]
    private string $planType = self::TYPE_DEFAULT;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(options: ['default' => 0])]
    private int $nexbitsPrice = 0;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;
        return $this;
    }

    public function getIconFileName(): ?string
    {
        return $this->iconFileName;
    }

    public function setIconFileName(?string $iconFileName): static
    {
        $this->iconFileName = $iconFileName;
        return $this;
    }

    public function getTokensGiven(): int
    {
        return $this->tokensGiven;
    }

    public function setTokensGiven(int $tokensGiven): static
    {
        $this->tokensGiven = $tokensGiven;
        return $this;
    }

    public function getBoostTokensGiven(): int
    {
        return $this->boostTokensGiven;
    }

    public function setBoostTokensGiven(int $boostTokensGiven): static
    {
        $this->boostTokensGiven = $boostTokensGiven;
        return $this;
    }

    public function getPlanType(): string
    {
        return $this->planType;
    }

    public function setPlanType(string $planType): static
    {
        $this->planType = $planType;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getNexbitsPrice(): int
    {
        return $this->nexbitsPrice;
    }

    public function setNexbitsPrice(int $nexbitsPrice): static
    {
        $this->nexbitsPrice = $nexbitsPrice;
        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
