<?php

namespace App\Entity;

use App\Repository\PluginSubmissionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PluginSubmissionRepository::class)]
class PluginSubmission
{
    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $pluginName;

    #[ORM\Column(length: 100)]
    private string $creatorName;

    #[ORM\Column(length: 500)]
    private string $description;

    #[ORM\Column(length: 150)]
    private string $gameDescription;

    /** Filename as stored on disk (in public/uploads/plugin-submissions/) */
    #[ORM\Column(length: 255)]
    private string $fileName;

    /** Original filename uploaded by the user */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $originalFileName = null;

    #[ORM\Column(nullable: true)]
    private ?int $fileSize = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $reviewedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $reviewedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $submitterUser = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $submitterIp = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Plugin $linkedPlugin = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getPluginName(): string { return $this->pluginName; }
    public function setPluginName(string $pluginName): static { $this->pluginName = $pluginName; return $this; }

    public function getCreatorName(): string { return $this->creatorName; }
    public function setCreatorName(string $creatorName): static { $this->creatorName = $creatorName; return $this; }

    public function getDescription(): string { return $this->description; }
    public function setDescription(string $description): static { $this->description = $description; return $this; }

    public function getGameDescription(): string { return $this->gameDescription; }
    public function setGameDescription(string $gameDescription): static { $this->gameDescription = $gameDescription; return $this; }

    public function getFileName(): string { return $this->fileName; }
    public function setFileName(string $fileName): static { $this->fileName = $fileName; return $this; }

    public function getOriginalFileName(): ?string { return $this->originalFileName; }
    public function setOriginalFileName(?string $originalFileName): static { $this->originalFileName = $originalFileName; return $this; }

    public function getFileSize(): ?int { return $this->fileSize; }
    public function setFileSize(?int $fileSize): static { $this->fileSize = $fileSize; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getRejectionReason(): ?string { return $this->rejectionReason; }
    public function setRejectionReason(?string $rejectionReason): static { $this->rejectionReason = $rejectionReason; return $this; }

    public function getReviewedBy(): ?User { return $this->reviewedBy; }
    public function setReviewedBy(?User $reviewedBy): static { $this->reviewedBy = $reviewedBy; return $this; }

    public function getReviewedAt(): ?\DateTimeImmutable { return $this->reviewedAt; }
    public function setReviewedAt(?\DateTimeImmutable $reviewedAt): static { $this->reviewedAt = $reviewedAt; return $this; }

    public function getSubmitterUser(): ?User { return $this->submitterUser; }
    public function setSubmitterUser(?User $submitterUser): static { $this->submitterUser = $submitterUser; return $this; }

    public function getSubmitterIp(): ?string { return $this->submitterIp; }
    public function setSubmitterIp(?string $submitterIp): static { $this->submitterIp = $submitterIp; return $this; }

    public function getLinkedPlugin(): ?Plugin { return $this->linkedPlugin; }
    public function setLinkedPlugin(?Plugin $linkedPlugin): static { $this->linkedPlugin = $linkedPlugin; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
