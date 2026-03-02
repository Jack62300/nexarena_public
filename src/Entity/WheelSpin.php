<?php

namespace App\Entity;

use App\Repository\WheelSpinRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WheelSpinRepository::class)]
#[ORM\Table(name: 'wheel_spin')]
#[ORM\Index(columns: ['user_id'], name: 'idx_wheel_spin_user')]
#[ORM\Index(columns: ['created_at'], name: 'idx_wheel_spin_created')]
#[ORM\HasLifecycleCallbacks]
class WheelSpin
{
    public const TYPE_FREE = 'free';
    public const TYPE_PAID = 'paid';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 10)]
    private string $type = self::TYPE_FREE;

    #[ORM\Column]
    private int $sectionIndex = 0;

    #[ORM\Column(length: 50)]
    private string $prizeLabel = '';

    #[ORM\Column]
    private int $nexbitsWon = 0;

    #[ORM\Column]
    private int $nexboostWon = 0;

    #[ORM\Column]
    private bool $isJackpot = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getSectionIndex(): int
    {
        return $this->sectionIndex;
    }

    public function setSectionIndex(int $sectionIndex): static
    {
        $this->sectionIndex = $sectionIndex;
        return $this;
    }

    public function getPrizeLabel(): string
    {
        return $this->prizeLabel;
    }

    public function setPrizeLabel(string $prizeLabel): static
    {
        $this->prizeLabel = $prizeLabel;
        return $this;
    }

    public function getNexbitsWon(): int
    {
        return $this->nexbitsWon;
    }

    public function setNexbitsWon(int $nexbitsWon): static
    {
        $this->nexbitsWon = $nexbitsWon;
        return $this;
    }

    public function getNexboostWon(): int
    {
        return $this->nexboostWon;
    }

    public function setNexboostWon(int $nexboostWon): static
    {
        $this->nexboostWon = $nexboostWon;
        return $this;
    }

    public function isJackpot(): bool
    {
        return $this->isJackpot;
    }

    public function setIsJackpot(bool $isJackpot): static
    {
        $this->isJackpot = $isJackpot;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
