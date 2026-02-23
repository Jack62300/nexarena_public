<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'Un compte existe deja avec cette adresse email.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, TwoFactorInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column(length: 50)]
    private ?string $username = null;

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column(nullable: true)]
    private ?string $password = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $googleId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $discordId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $twitchId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $steamId = null;

    #[ORM\Column]
    private bool $isDiscordGuildMember = false;

    #[ORM\Column]
    private bool $isVerified = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $totpSecret = null;

    #[ORM\Column]
    private bool $isTwoFactorEnabled = false;

    #[ORM\Column]
    private int $tokenBalance = 0;

    #[ORM\Column]
    private int $boostTokenBalance = 0;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, options: ['default' => '0.00'])]
    private string $pendingVoteTokens = '0.00';

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $discordUsername = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $steamUsername = null;

    #[ORM\Column]
    private bool $isEmailVerified = true;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $emailVerificationToken = null;

    #[ORM\Column(nullable: true)]
    private ?array $trustedIps = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $deviceVerificationToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deviceVerificationTokenExpiry = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $pendingDeviceIp = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $twitchUsername = null;

    #[ORM\Column(nullable: true)]
    private ?array $gameUsernames = null;

    #[ORM\Column]
    private bool $isBanned = false;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $banReason = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $bannedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $banExpiresAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $bannedBy = null;

    #[ORM\Column(options: ['default' => '{"email":false,"discord":false,"steam":false,"twitch":false,"games":false,"servers":true}'])]
    private array $profileVisibility = [
        'email' => false,
        'discord' => false,
        'steam' => false,
        'twitch' => false,
        'games' => false,
        'servers' => true,
    ];

    /** @var Collection<int, UserBadge> */
    #[ORM\OneToMany(targetEntity: UserBadge::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $userBadges;

    /** @var Collection<int, Server> */
    #[ORM\OneToMany(targetEntity: Server::class, mappedBy: 'owner')]
    private Collection $servers;

    /** @var Collection<int, ServerCollaborator> */
    #[ORM\OneToMany(targetEntity: ServerCollaborator::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $serverCollaborations;

    public function __construct()
    {
        $this->servers = new ArrayCollection();
        $this->serverCollaborations = new ArrayCollection();
        $this->userBadges = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;

        return $this;
    }

    public function getGoogleId(): ?string
    {
        return $this->googleId;
    }

    public function setGoogleId(?string $googleId): static
    {
        $this->googleId = $googleId;

        return $this;
    }

    public function getDiscordId(): ?string
    {
        return $this->discordId;
    }

    public function setDiscordId(?string $discordId): static
    {
        $this->discordId = $discordId;

        return $this;
    }

    public function getTwitchId(): ?string
    {
        return $this->twitchId;
    }

    public function setTwitchId(?string $twitchId): static
    {
        $this->twitchId = $twitchId;

        return $this;
    }

    public function getSteamId(): ?string
    {
        return $this->steamId;
    }

    public function setSteamId(?string $steamId): static
    {
        $this->steamId = $steamId;

        return $this;
    }

    public function isDiscordGuildMember(): bool
    {
        return $this->isDiscordGuildMember;
    }

    public function setIsDiscordGuildMember(bool $isDiscordGuildMember): static
    {
        $this->isDiscordGuildMember = $isDiscordGuildMember;
        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
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

    /** @return Collection<int, Server> */
    public function getServers(): Collection
    {
        return $this->servers;
    }

    /** @return Collection<int, ServerCollaborator> */
    public function getServerCollaborations(): Collection
    {
        return $this->serverCollaborations;
    }

    public function getTokenBalance(): int
    {
        return $this->tokenBalance;
    }

    public function setTokenBalance(int $tokenBalance): static
    {
        $this->tokenBalance = $tokenBalance;
        return $this;
    }

    public function addTokens(int $amount): static
    {
        $this->tokenBalance += $amount;
        return $this;
    }

    public function removeTokens(int $amount): static
    {
        $this->tokenBalance -= $amount;
        return $this;
    }

    public function hasEnoughTokens(int $amount): bool
    {
        return $this->tokenBalance >= $amount;
    }

    public function getBoostTokenBalance(): int
    {
        return $this->boostTokenBalance;
    }

    public function setBoostTokenBalance(int $boostTokenBalance): static
    {
        $this->boostTokenBalance = $boostTokenBalance;
        return $this;
    }

    public function addBoostTokens(int $amount): static
    {
        $this->boostTokenBalance += $amount;
        return $this;
    }

    public function removeBoostTokens(int $amount): static
    {
        $this->boostTokenBalance -= $amount;
        return $this;
    }

    public function hasEnoughBoostTokens(int $amount): bool
    {
        return $this->boostTokenBalance >= $amount;
    }

    public function getPendingVoteTokens(): float
    {
        return (float) $this->pendingVoteTokens;
    }

    public function setPendingVoteTokens(float $amount): static
    {
        $this->pendingVoteTokens = number_format($amount, 2, '.', '');
        return $this;
    }

    public function addPendingVoteTokens(float $amount): static
    {
        $this->pendingVoteTokens = number_format((float) $this->pendingVoteTokens + $amount, 2, '.', '');
        return $this;
    }

    public function getTotpSecret(): ?string
    {
        return $this->totpSecret;
    }

    public function setTotpSecret(?string $totpSecret): static
    {
        $this->totpSecret = $totpSecret;

        return $this;
    }

    public function isTwoFactorEnabled(): bool
    {
        return $this->isTwoFactorEnabled;
    }

    public function setIsTwoFactorEnabled(bool $isTwoFactorEnabled): static
    {
        $this->isTwoFactorEnabled = $isTwoFactorEnabled;

        return $this;
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return $this->isTwoFactorEnabled && null !== $this->totpSecret;
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->getUserIdentifier();
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        if ($this->totpSecret === null) {
            return null;
        }

        return new TotpConfiguration($this->totpSecret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;
        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;
        return $this;
    }

    public function getDiscordUsername(): ?string
    {
        return $this->discordUsername;
    }

    public function setDiscordUsername(?string $discordUsername): static
    {
        $this->discordUsername = $discordUsername;
        return $this;
    }

    public function getSteamUsername(): ?string
    {
        return $this->steamUsername;
    }

    public function setSteamUsername(?string $steamUsername): static
    {
        $this->steamUsername = $steamUsername;
        return $this;
    }

    public function getTwitchUsername(): ?string
    {
        return $this->twitchUsername;
    }

    public function setTwitchUsername(?string $twitchUsername): static
    {
        $this->twitchUsername = $twitchUsername;
        return $this;
    }

    public function getGameUsernames(): ?array
    {
        return $this->gameUsernames;
    }

    public function setGameUsernames(?array $gameUsernames): static
    {
        $this->gameUsernames = $gameUsernames;
        return $this;
    }

    public function getProfileVisibility(): array
    {
        return $this->profileVisibility;
    }

    public function setProfileVisibility(array $profileVisibility): static
    {
        $this->profileVisibility = $profileVisibility;
        return $this;
    }

    public function isFieldVisible(string $field): bool
    {
        return $this->profileVisibility[$field] ?? false;
    }

    /** @return Collection<int, UserBadge> */
    public function getUserBadges(): Collection
    {
        return $this->userBadges;
    }

    public function isEmailVerified(): bool
    {
        return $this->isEmailVerified;
    }

    public function setIsEmailVerified(bool $isEmailVerified): static
    {
        $this->isEmailVerified = $isEmailVerified;
        return $this;
    }

    public function getEmailVerificationToken(): ?string
    {
        return $this->emailVerificationToken;
    }

    public function setEmailVerificationToken(?string $token): static
    {
        $this->emailVerificationToken = $token;
        return $this;
    }

    public function getTrustedIps(): ?array
    {
        return $this->trustedIps;
    }

    public function setTrustedIps(?array $trustedIps): static
    {
        $this->trustedIps = $trustedIps;
        return $this;
    }

    public function addTrustedIp(string $ip): static
    {
        $ips = $this->trustedIps ?? [];
        if (!in_array($ip, $ips, true)) {
            $ips[] = $ip;
        }
        $this->trustedIps = $ips;
        return $this;
    }

    public function isTrustedIp(string $ip): bool
    {
        return in_array($ip, $this->trustedIps ?? [], true);
    }

    public function getDeviceVerificationToken(): ?string
    {
        return $this->deviceVerificationToken;
    }

    public function setDeviceVerificationToken(?string $token): static
    {
        $this->deviceVerificationToken = $token;
        return $this;
    }

    public function getDeviceVerificationTokenExpiry(): ?\DateTimeImmutable
    {
        return $this->deviceVerificationTokenExpiry;
    }

    public function setDeviceVerificationTokenExpiry(?\DateTimeImmutable $expiry): static
    {
        $this->deviceVerificationTokenExpiry = $expiry;
        return $this;
    }

    public function getPendingDeviceIp(): ?string
    {
        return $this->pendingDeviceIp;
    }

    public function setPendingDeviceIp(?string $ip): static
    {
        $this->pendingDeviceIp = $ip;
        return $this;
    }

    public function isBanned(): bool
    {
        return $this->isBanned;
    }

    public function isCurrentlyBanned(): bool
    {
        if (!$this->isBanned) {
            return false;
        }

        if ($this->banExpiresAt !== null && $this->banExpiresAt <= new \DateTimeImmutable()) {
            return false;
        }

        return true;
    }

    public function getBanReason(): ?string
    {
        return $this->banReason;
    }

    public function getBannedAt(): ?\DateTimeImmutable
    {
        return $this->bannedAt;
    }

    public function getBanExpiresAt(): ?\DateTimeImmutable
    {
        return $this->banExpiresAt;
    }

    public function getBannedBy(): ?User
    {
        return $this->bannedBy;
    }

    public function ban(?string $reason, ?\DateTimeImmutable $expiresAt, User $bannedBy): void
    {
        $this->isBanned = true;
        $this->banReason = $reason;
        $this->bannedAt = new \DateTimeImmutable();
        $this->banExpiresAt = $expiresAt;
        $this->bannedBy = $bannedBy;
    }

    public function unban(): void
    {
        $this->isBanned = false;
        $this->banReason = null;
        $this->bannedAt = null;
        $this->banExpiresAt = null;
        $this->bannedBy = null;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
