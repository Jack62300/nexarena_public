<?php

namespace App\Entity;

use App\Repository\UserBadgeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserBadgeRepository::class)]
#[ORM\Table(name: 'user_badge')]
#[ORM\UniqueConstraint(name: 'unique_user_badge', columns: ['user_id', 'badge_id'])]
class UserBadge
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'userBadges')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Badge::class, inversedBy: 'userBadges')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Badge $badge = null;

    #[ORM\Column]
    private \DateTimeImmutable $awardedAt;

    public function __construct()
    {
        $this->awardedAt = new \DateTimeImmutable();
    }

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

    public function getBadge(): ?Badge
    {
        return $this->badge;
    }

    public function setBadge(?Badge $badge): static
    {
        $this->badge = $badge;
        return $this;
    }

    public function getAwardedAt(): \DateTimeImmutable
    {
        return $this->awardedAt;
    }
}
