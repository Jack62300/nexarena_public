<?php

namespace App\Entity;

use App\Repository\UserAchievementRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserAchievementRepository::class)]
#[ORM\Table(name: 'user_achievement')]
#[ORM\UniqueConstraint(name: 'unique_user_achievement', columns: ['user_id', 'achievement_id'])]
class UserAchievement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'userAchievements')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Achievement::class, inversedBy: 'userAchievements')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Achievement $achievement = null;

    #[ORM\Column]
    private \DateTimeImmutable $awardedAt;

    #[ORM\Column]
    private bool $isViewed = false;

    public function __construct()
    {
        $this->awardedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getAchievement(): ?Achievement { return $this->achievement; }
    public function setAchievement(?Achievement $achievement): static { $this->achievement = $achievement; return $this; }

    public function getAwardedAt(): \DateTimeImmutable { return $this->awardedAt; }

    public function isViewed(): bool { return $this->isViewed; }
    public function setIsViewed(bool $isViewed): static { $this->isViewed = $isViewed; return $this; }
}
