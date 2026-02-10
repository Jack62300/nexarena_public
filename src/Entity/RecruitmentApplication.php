<?php

namespace App\Entity;

use App\Repository\RecruitmentApplicationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RecruitmentApplicationRepository::class)]
#[ORM\Table(name: 'recruitment_application')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_application_listing', columns: ['listing_id'])]
class RecruitmentApplication
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RecruitmentListing::class, inversedBy: 'applications')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?RecruitmentListing $listing = null;

    #[ORM\Column(length: 255)]
    private ?string $applicantName = null;

    #[ORM\Column(length: 255)]
    private ?string $applicantEmail = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $applicantUser = null;

    #[ORM\Column(type: Types::JSON)]
    private array $responses = [];

    #[ORM\Column]
    private bool $isRead = false;

    #[ORM\Column(length: 20, options: ['default' => 'pending'])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $statusComment = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $reviewedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $reviewedAt = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $chatEnabled = false;

    /** @var Collection<int, RecruitmentMessage> */
    #[ORM\OneToMany(targetEntity: RecruitmentMessage::class, mappedBy: 'application', orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $messages;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->messages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getListing(): ?RecruitmentListing
    {
        return $this->listing;
    }

    public function setListing(?RecruitmentListing $listing): static
    {
        $this->listing = $listing;
        return $this;
    }

    public function getApplicantName(): ?string
    {
        return $this->applicantName;
    }

    public function setApplicantName(string $applicantName): static
    {
        $this->applicantName = $applicantName;
        return $this;
    }

    public function getApplicantEmail(): ?string
    {
        return $this->applicantEmail;
    }

    public function setApplicantEmail(string $applicantEmail): static
    {
        $this->applicantEmail = $applicantEmail;
        return $this;
    }

    public function getApplicantUser(): ?User
    {
        return $this->applicantUser;
    }

    public function setApplicantUser(?User $applicantUser): static
    {
        $this->applicantUser = $applicantUser;
        return $this;
    }

    public function getResponses(): array
    {
        return $this->responses;
    }

    public function setResponses(array $responses): static
    {
        $this->responses = $responses;
        return $this;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getStatusComment(): ?string
    {
        return $this->statusComment;
    }

    public function setStatusComment(?string $statusComment): static
    {
        $this->statusComment = $statusComment;
        return $this;
    }

    public function getReviewedBy(): ?User
    {
        return $this->reviewedBy;
    }

    public function setReviewedBy(?User $reviewedBy): static
    {
        $this->reviewedBy = $reviewedBy;
        return $this;
    }

    public function getReviewedAt(): ?\DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function setReviewedAt(?\DateTimeImmutable $reviewedAt): static
    {
        $this->reviewedAt = $reviewedAt;
        return $this;
    }

    public function isChatEnabled(): bool
    {
        return $this->chatEnabled;
    }

    public function setChatEnabled(bool $chatEnabled): static
    {
        $this->chatEnabled = $chatEnabled;
        return $this;
    }

    /** @return Collection<int, RecruitmentMessage> */
    public function getMessages(): Collection
    {
        return $this->messages;
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
