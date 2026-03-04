<?php

namespace App\Entity;

use App\Repository\ReferralRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReferralRepository::class)]
#[ORM\Table(name: 'referral')]
class Referral
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $referrer = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $referred = null;

    #[ORM\Column]
    private int $rewardAmount = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReferrer(): ?User
    {
        return $this->referrer;
    }

    public function setReferrer(?User $referrer): static
    {
        $this->referrer = $referrer;
        return $this;
    }

    public function getReferred(): ?User
    {
        return $this->referred;
    }

    public function setReferred(?User $referred): static
    {
        $this->referred = $referred;
        return $this;
    }

    public function getRewardAmount(): int
    {
        return $this->rewardAmount;
    }

    public function setRewardAmount(int $rewardAmount): static
    {
        $this->rewardAmount = $rewardAmount;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
