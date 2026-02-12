<?php

namespace App\Entity;

use App\Repository\ServerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ServerRepository::class)]
#[ORM\Table(name: 'server')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_server_monthly_votes', columns: ['monthly_votes'])]
#[ORM\Index(name: 'idx_server_total_votes', columns: ['total_votes'])]
#[ORM\Index(name: 'idx_server_click_count', columns: ['click_count'])]
class Server
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Category $category = null;

    #[ORM\ManyToOne(targetEntity: GameCategory::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?GameCategory $gameCategory = null;

    #[ORM\ManyToOne(targetEntity: ServerType::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?ServerType $serverType = null;

    #[ORM\Column(length: 255)]
    private ?string $shortDescription = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $fullDescription = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $presentationImage = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(nullable: true)]
    private ?int $port = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $connectUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $website = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $discordUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $twitterUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $twitchChannel = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $youtubeUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $instagramUrl = null;

    #[ORM\Column]
    private int $slots = 0;

    #[ORM\Column]
    private bool $isPrivate = false;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private bool $isApproved = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $banner = null;

    #[ORM\Column(length: 32, options: ['default' => 'default'])]
    private string $pageTemplate = 'default';

    #[ORM\Column(length: 64, unique: true)]
    private ?string $apiToken = null;

    #[ORM\Column]
    private bool $webhookEnabled = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $webhookUrl = null;

    #[ORM\Column]
    private bool $statusCheckEnabled = false;

    #[ORM\Column]
    private bool $isFeatured = false;

    #[ORM\Column]
    private int $featuredPosition = 0;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $allowedApiIps = null;

    #[ORM\Column]
    private int $totalVotes = 0;

    #[ORM\Column]
    private int $monthlyVotes = 0;

    #[ORM\Column]
    private int $clickCount = 0;

    /** @var Collection<int, Tag> */
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'servers')]
    #[ORM\JoinTable(name: 'server_tag')]
    private Collection $tags;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'servers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @var Collection<int, ServerCollaborator> */
    #[ORM\OneToMany(targetEntity: ServerCollaborator::class, mappedBy: 'server', orphanRemoval: true)]
    private Collection $collaborators;

    /** @var Collection<int, ServerPremiumFeature> */
    #[ORM\OneToMany(targetEntity: ServerPremiumFeature::class, mappedBy: 'server', orphanRemoval: true)]
    private Collection $premiumFeatures;

    public function __construct()
    {
        $this->collaborators = new ArrayCollection();
        $this->premiumFeatures = new ArrayCollection();
        $this->tags = new ArrayCollection();
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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getGameCategory(): ?GameCategory
    {
        return $this->gameCategory;
    }

    public function setGameCategory(?GameCategory $gameCategory): static
    {
        $this->gameCategory = $gameCategory;

        return $this;
    }

    public function getServerType(): ?ServerType
    {
        return $this->serverType;
    }

    public function setServerType(?ServerType $serverType): static
    {
        $this->serverType = $serverType;

        return $this;
    }

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(string $shortDescription): static
    {
        $this->shortDescription = $shortDescription;

        return $this;
    }

    public function getFullDescription(): ?string
    {
        return $this->fullDescription;
    }

    public function setFullDescription(?string $fullDescription): static
    {
        $this->fullDescription = $fullDescription;

        return $this;
    }

    public function getPresentationImage(): ?string
    {
        return $this->presentationImage;
    }

    public function setPresentationImage(?string $presentationImage): static
    {
        $this->presentationImage = $presentationImage;

        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): static
    {
        $this->ip = $ip;

        return $this;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function setPort(?int $port): static
    {
        $this->port = $port;

        return $this;
    }

    public function getConnectUrl(): ?string
    {
        return $this->connectUrl;
    }

    public function setConnectUrl(?string $connectUrl): static
    {
        $this->connectUrl = $connectUrl;

        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): static
    {
        $this->website = $website;

        return $this;
    }

    public function getDiscordUrl(): ?string
    {
        return $this->discordUrl;
    }

    public function setDiscordUrl(?string $discordUrl): static
    {
        $this->discordUrl = $discordUrl;

        return $this;
    }

    public function getTwitterUrl(): ?string
    {
        return $this->twitterUrl;
    }

    public function setTwitterUrl(?string $twitterUrl): static
    {
        $this->twitterUrl = $twitterUrl;

        return $this;
    }

    public function getTwitchChannel(): ?string
    {
        return $this->twitchChannel;
    }

    public function setTwitchChannel(?string $twitchChannel): static
    {
        $this->twitchChannel = $twitchChannel;

        return $this;
    }

    public function getYoutubeUrl(): ?string
    {
        return $this->youtubeUrl;
    }

    public function setYoutubeUrl(?string $youtubeUrl): static
    {
        $this->youtubeUrl = $youtubeUrl;

        return $this;
    }

    public function getInstagramUrl(): ?string
    {
        return $this->instagramUrl;
    }

    public function setInstagramUrl(?string $instagramUrl): static
    {
        $this->instagramUrl = $instagramUrl;

        return $this;
    }

    public function getSlots(): int
    {
        return $this->slots;
    }

    public function setSlots(int $slots): static
    {
        $this->slots = $slots;

        return $this;
    }

    public function isPrivate(): bool
    {
        return $this->isPrivate;
    }

    public function setIsPrivate(bool $isPrivate): static
    {
        $this->isPrivate = $isPrivate;

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

    public function isApproved(): bool
    {
        return $this->isApproved;
    }

    public function setIsApproved(bool $isApproved): static
    {
        $this->isApproved = $isApproved;

        return $this;
    }

    public function getBanner(): ?string
    {
        return $this->banner;
    }

    public function setBanner(?string $banner): static
    {
        $this->banner = $banner;

        return $this;
    }

    public function getPageTemplate(): string
    {
        return $this->pageTemplate;
    }

    public function setPageTemplate(string $pageTemplate): static
    {
        $this->pageTemplate = $pageTemplate;

        return $this;
    }

    public function getApiToken(): ?string
    {
        return $this->apiToken;
    }

    public function setApiToken(string $apiToken): static
    {
        $this->apiToken = $apiToken;

        return $this;
    }

    public function isWebhookEnabled(): bool
    {
        return $this->webhookEnabled;
    }

    public function setWebhookEnabled(bool $webhookEnabled): static
    {
        $this->webhookEnabled = $webhookEnabled;

        return $this;
    }

    public function getWebhookUrl(): ?string
    {
        return $this->webhookUrl;
    }

    public function setWebhookUrl(?string $webhookUrl): static
    {
        $this->webhookUrl = $webhookUrl;

        return $this;
    }

    public function isStatusCheckEnabled(): bool
    {
        return $this->statusCheckEnabled;
    }

    public function setStatusCheckEnabled(bool $statusCheckEnabled): static
    {
        $this->statusCheckEnabled = $statusCheckEnabled;

        return $this;
    }

    public function getAllowedApiIps(): ?array
    {
        return $this->allowedApiIps;
    }

    public function setAllowedApiIps(?array $allowedApiIps): static
    {
        $this->allowedApiIps = $allowedApiIps;

        return $this;
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function setIsFeatured(bool $isFeatured): static
    {
        $this->isFeatured = $isFeatured;
        return $this;
    }

    public function getFeaturedPosition(): int
    {
        return $this->featuredPosition;
    }

    public function setFeaturedPosition(int $featuredPosition): static
    {
        $this->featuredPosition = $featuredPosition;
        return $this;
    }

    public function getTotalVotes(): int
    {
        return $this->totalVotes;
    }

    public function setTotalVotes(int $totalVotes): static
    {
        $this->totalVotes = $totalVotes;

        return $this;
    }

    public function incrementTotalVotes(): static
    {
        $this->totalVotes++;

        return $this;
    }

    public function getMonthlyVotes(): int
    {
        return $this->monthlyVotes;
    }

    public function setMonthlyVotes(int $monthlyVotes): static
    {
        $this->monthlyVotes = $monthlyVotes;

        return $this;
    }

    public function incrementMonthlyVotes(): static
    {
        $this->monthlyVotes++;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

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

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        if ($this->apiToken === null) {
            $this->apiToken = bin2hex(random_bytes(32));
        }
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** @return Collection<int, ServerCollaborator> */
    public function getCollaborators(): Collection
    {
        return $this->collaborators;
    }

    /** @return Collection<int, ServerPremiumFeature> */
    public function getPremiumFeatures(): Collection
    {
        return $this->premiumFeatures;
    }

    public function getClickCount(): int
    {
        return $this->clickCount;
    }

    public function incrementClickCount(): static
    {
        $this->clickCount++;
        return $this;
    }

    /** @return Collection<int, Tag> */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }
        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);
        return $this;
    }

    public function clearTags(): static
    {
        $this->tags->clear();
        return $this;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
