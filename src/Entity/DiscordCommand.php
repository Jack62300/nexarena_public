<?php

namespace App\Entity;

use App\Repository\DiscordCommandRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DiscordCommandRepository::class)]
class DiscordCommand
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32, unique: true)]
    private ?string $name = null;

    #[ORM\Column(length: 100)]
    private ?string $description = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $response = null;

    #[ORM\Column(length: 256, nullable: true)]
    private ?string $embedTitle = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $embedDescription = null;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $embedColor = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $embedImage = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $requiredRole = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getResponse(): ?string
    {
        return $this->response;
    }

    public function setResponse(?string $response): static
    {
        $this->response = $response;
        return $this;
    }

    public function getEmbedTitle(): ?string
    {
        return $this->embedTitle;
    }

    public function setEmbedTitle(?string $embedTitle): static
    {
        $this->embedTitle = $embedTitle;
        return $this;
    }

    public function getEmbedDescription(): ?string
    {
        return $this->embedDescription;
    }

    public function setEmbedDescription(?string $embedDescription): static
    {
        $this->embedDescription = $embedDescription;
        return $this;
    }

    public function getEmbedColor(): ?string
    {
        return $this->embedColor;
    }

    public function setEmbedColor(?string $embedColor): static
    {
        $this->embedColor = $embedColor;
        return $this;
    }

    public function getEmbedImage(): ?string
    {
        return $this->embedImage;
    }

    public function setEmbedImage(?string $embedImage): static
    {
        $this->embedImage = $embedImage;
        return $this;
    }

    public function getRequiredRole(): ?string
    {
        return $this->requiredRole;
    }

    public function setRequiredRole(?string $requiredRole): static
    {
        $this->requiredRole = $requiredRole;
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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
