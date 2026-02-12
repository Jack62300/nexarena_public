<?php

namespace App\Entity;

use App\Repository\DiscordTicketMessageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DiscordTicketMessageRepository::class)]
class DiscordTicketMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?DiscordTicket $ticket = null;

    #[ORM\Column(length: 20)]
    private ?string $authorDiscordId = null;

    #[ORM\Column(length: 100)]
    private ?string $authorUsername = null;

    #[ORM\Column]
    private bool $authorIsStaff = false;

    #[ORM\Column(type: 'text')]
    private ?string $content = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTicket(): ?DiscordTicket
    {
        return $this->ticket;
    }

    public function setTicket(?DiscordTicket $ticket): static
    {
        $this->ticket = $ticket;
        return $this;
    }

    public function getAuthorDiscordId(): ?string
    {
        return $this->authorDiscordId;
    }

    public function setAuthorDiscordId(string $authorDiscordId): static
    {
        $this->authorDiscordId = $authorDiscordId;
        return $this;
    }

    public function getAuthorUsername(): ?string
    {
        return $this->authorUsername;
    }

    public function setAuthorUsername(string $authorUsername): static
    {
        $this->authorUsername = $authorUsername;
        return $this;
    }

    public function isAuthorIsStaff(): bool
    {
        return $this->authorIsStaff;
    }

    public function setAuthorIsStaff(bool $authorIsStaff): static
    {
        $this->authorIsStaff = $authorIsStaff;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
