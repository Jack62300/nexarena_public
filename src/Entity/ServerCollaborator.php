<?php

namespace App\Entity;

use App\Repository\ServerCollaboratorRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ServerCollaboratorRepository::class)]
#[ORM\Table(name: 'server_collaborator')]
#[ORM\UniqueConstraint(name: 'uniq_server_user', columns: ['server_id', 'user_id'])]
#[ORM\HasLifecycleCallbacks]
class ServerCollaborator
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Server::class, inversedBy: 'collaborators')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Server $server = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'serverCollaborations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: 'json')]
    private array $permissions = [];

    #[ORM\Column]
    private ?\DateTimeImmutable $addedAt = null;

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

    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function setPermissions(array $permissions): static
    {
        $this->permissions = $permissions;

        return $this;
    }

    public function hasPermission(string $code): bool
    {
        return in_array($code, $this->permissions, true);
    }

    public function getAddedAt(): ?\DateTimeImmutable
    {
        return $this->addedAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->addedAt = new \DateTimeImmutable();
    }
}
