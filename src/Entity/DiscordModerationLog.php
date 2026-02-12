<?php

namespace App\Entity;

use App\Repository\DiscordModerationLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DiscordModerationLogRepository::class)]
#[ORM\Index(columns: ['action'], name: 'idx_modlog_action')]
#[ORM\Index(columns: ['discord_user_id'], name: 'idx_modlog_discord_user')]
#[ORM\Index(columns: ['created_at'], name: 'idx_modlog_created_at')]
class DiscordModerationLog
{
    public const ACTION_MESSAGE_DELETED = 'message_deleted';
    public const ACTION_USER_WARNED = 'user_warned';
    public const ACTION_USER_MUTED = 'user_muted';
    public const ACTION_USER_KICKED = 'user_kicked';
    public const ACTION_USER_BANNED = 'user_banned';
    public const ACTION_SPAM_DETECTED = 'spam_detected';

    public const VALID_ACTIONS = [
        self::ACTION_MESSAGE_DELETED,
        self::ACTION_USER_WARNED,
        self::ACTION_USER_MUTED,
        self::ACTION_USER_KICKED,
        self::ACTION_USER_BANNED,
        self::ACTION_SPAM_DETECTED,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30)]
    private ?string $action = null;

    #[ORM\Column(length: 20)]
    private ?string $discordUserId = null;

    #[ORM\Column(length: 100)]
    private ?string $discordUsername = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $channelId = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $channelName = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $messageContent = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $triggeredWord = null;

    #[ORM\Column(nullable: true)]
    private ?array $metadata = null;

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

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;
        return $this;
    }

    public function getDiscordUserId(): ?string
    {
        return $this->discordUserId;
    }

    public function setDiscordUserId(string $discordUserId): static
    {
        $this->discordUserId = $discordUserId;
        return $this;
    }

    public function getDiscordUsername(): ?string
    {
        return $this->discordUsername;
    }

    public function setDiscordUsername(string $discordUsername): static
    {
        $this->discordUsername = $discordUsername;
        return $this;
    }

    public function getChannelId(): ?string
    {
        return $this->channelId;
    }

    public function setChannelId(?string $channelId): static
    {
        $this->channelId = $channelId;
        return $this;
    }

    public function getChannelName(): ?string
    {
        return $this->channelName;
    }

    public function setChannelName(?string $channelName): static
    {
        $this->channelName = $channelName;
        return $this;
    }

    public function getMessageContent(): ?string
    {
        return $this->messageContent;
    }

    public function setMessageContent(?string $messageContent): static
    {
        $this->messageContent = $messageContent;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;
        return $this;
    }

    public function getTriggeredWord(): ?string
    {
        return $this->triggeredWord;
    }

    public function setTriggeredWord(?string $triggeredWord): static
    {
        $this->triggeredWord = $triggeredWord;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
