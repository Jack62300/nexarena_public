<?php

namespace App\Entity;

use App\Repository\FeaturedBookingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FeaturedBookingRepository::class)]
#[ORM\Table(name: 'featured_booking')]
#[ORM\Index(name: 'idx_featured_starts_at', columns: ['starts_at'])]
#[ORM\Index(name: 'idx_featured_ends_at', columns: ['ends_at'])]
#[ORM\Index(name: 'idx_featured_scope_pos_range', columns: ['scope', 'position', 'starts_at', 'ends_at'])]
#[ORM\HasLifecycleCallbacks]
class FeaturedBooking
{
    public const SCOPE_HOMEPAGE = 'homepage';
    public const SCOPE_GAME = 'game';
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Server::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Server $server = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\Column(length: 20, options: ['default' => 'homepage'])]
    private string $scope = self::SCOPE_HOMEPAGE;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 1])]
    private int $position = 1;

    #[ORM\ManyToOne(targetEntity: GameCategory::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?GameCategory $gameCategory = null;

    #[ORM\Column]
    private int $boostTokensUsed = 1;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

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

    public function getStartsAt(): ?\DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(\DateTimeImmutable $startsAt): static
    {
        $this->startsAt = $startsAt;
        return $this;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(\DateTimeImmutable $endsAt): static
    {
        $this->endsAt = $endsAt;
        return $this;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function setScope(string $scope): static
    {
        $this->scope = $scope;
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

    public function getGameCategory(): ?GameCategory
    {
        return $this->gameCategory;
    }

    public function setGameCategory(?GameCategory $gameCategory): static
    {
        $this->gameCategory = $gameCategory;
        return $this;
    }

    public function getBoostTokensUsed(): int
    {
        return $this->boostTokensUsed;
    }

    public function setBoostTokensUsed(int $boostTokensUsed): static
    {
        $this->boostTokensUsed = $boostTokensUsed;
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
}
