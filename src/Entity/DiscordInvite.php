<?php

namespace App\Entity;

use App\Repository\DiscordInviteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DiscordInviteRepository::class)]
#[ORM\Index(columns: ['inviter_discord_id'], name: 'idx_invite_inviter')]
class DiscordInvite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private ?string $inviterDiscordId = null;

    #[ORM\Column(length: 100)]
    private ?string $inviterUsername = null;

    #[ORM\Column(length: 20)]
    private ?string $invitedDiscordId = null;

    #[ORM\Column(length: 100)]
    private ?string $invitedUsername = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $inviteCode = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $joinedAt = null;

    public function __construct()
    {
        $this->joinedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInviterDiscordId(): ?string
    {
        return $this->inviterDiscordId;
    }

    public function setInviterDiscordId(string $inviterDiscordId): static
    {
        $this->inviterDiscordId = $inviterDiscordId;
        return $this;
    }

    public function getInviterUsername(): ?string
    {
        return $this->inviterUsername;
    }

    public function setInviterUsername(string $inviterUsername): static
    {
        $this->inviterUsername = $inviterUsername;
        return $this;
    }

    public function getInvitedDiscordId(): ?string
    {
        return $this->invitedDiscordId;
    }

    public function setInvitedDiscordId(string $invitedDiscordId): static
    {
        $this->invitedDiscordId = $invitedDiscordId;
        return $this;
    }

    public function getInvitedUsername(): ?string
    {
        return $this->invitedUsername;
    }

    public function setInvitedUsername(string $invitedUsername): static
    {
        $this->invitedUsername = $invitedUsername;
        return $this;
    }

    public function getInviteCode(): ?string
    {
        return $this->inviteCode;
    }

    public function setInviteCode(?string $inviteCode): static
    {
        $this->inviteCode = $inviteCode;
        return $this;
    }

    public function getJoinedAt(): ?\DateTimeImmutable
    {
        return $this->joinedAt;
    }

    public function setJoinedAt(\DateTimeImmutable $joinedAt): static
    {
        $this->joinedAt = $joinedAt;
        return $this;
    }
}
