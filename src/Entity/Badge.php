<?php

namespace App\Entity;

use App\Repository\BadgeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BadgeRepository::class)]
#[ORM\Table(name: 'badge')]
#[ORM\HasLifecycleCallbacks]
class Badge
{
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

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $color = null;

    #[ORM\Column(nullable: true)]
    private ?array $criteria = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /** @var Collection<int, UserBadge> */
    #[ORM\OneToMany(targetEntity: UserBadge::class, mappedBy: 'badge', orphanRemoval: true)]
    private Collection $userBadges;

    public function __construct()
    {
        $this->userBadges = new ArrayCollection();
    }

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

    public function setDescription(?string $description): static
    {
        $this->description = $description;
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

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): static
    {
        $this->color = $color;
        return $this;
    }

    public function getCriteria(): ?array
    {
        return $this->criteria;
    }

    public function setCriteria(?array $criteria): static
    {
        $this->criteria = $criteria;
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    /** @return Collection<int, UserBadge> */
    public function getUserBadges(): Collection
    {
        return $this->userBadges;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
