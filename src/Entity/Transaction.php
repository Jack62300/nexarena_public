<?php

namespace App\Entity;

use App\Repository\TransactionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\Table(name: 'transaction')]
#[ORM\HasLifecycleCallbacks]
class Transaction
{
    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_SPEND = 'spend';
    public const TYPE_REFUND = 'refund';
    public const TYPE_ADMIN_CREDIT = 'admin_credit';
    public const TYPE_VOTE_REWARD = 'vote_reward';
    public const TYPE_DEPOSIT = 'deposit';

    public const PAYPAL_STATUS_COMPLETED = 'COMPLETED';
    public const PAYPAL_STATUS_PENDING = 'PENDING';

    public const CRYPTO_STATUS_CAPTURED  = 'captured';
    public const CRYPTO_STATUS_SUCCEEDED = 'succeeded'; // mode test Crypto.com
    public const CRYPTO_STATUS_PENDING   = 'pending';
    public const CRYPTO_STATUS_CANCELLED = 'cancelled';

    public const STRIPE_STATUS_COMPLETE = 'complete';
    public const STRIPE_STATUS_OPEN     = 'open';
    public const STRIPE_STATUS_EXPIRED  = 'expired';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: PremiumPlan::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?PremiumPlan $plan = null;

    #[ORM\ManyToOne(targetEntity: Server::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Server $server = null;

    #[ORM\Column(length: 20)]
    private ?string $type = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $amount = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $currency = null;

    #[ORM\Column]
    private int $tokensAmount = 0;

    #[ORM\Column]
    private int $boostTokensAmount = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $paypalOrderId = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $paypalStatus = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cryptoPaymentId = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $cryptoStatus = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeSessionId = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $stripeStatus = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private bool $isCredited = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $creditedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPlan(): ?PremiumPlan
    {
        return $this->plan;
    }

    public function setPlan(?PremiumPlan $plan): static
    {
        $this->plan = $plan;
        return $this;
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(?string $amount): static
    {
        $this->amount = $amount;
        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): static
    {
        $this->currency = $currency;
        return $this;
    }

    public function getTokensAmount(): int
    {
        return $this->tokensAmount;
    }

    public function setTokensAmount(int $tokensAmount): static
    {
        $this->tokensAmount = $tokensAmount;
        return $this;
    }

    public function getBoostTokensAmount(): int
    {
        return $this->boostTokensAmount;
    }

    public function setBoostTokensAmount(int $boostTokensAmount): static
    {
        $this->boostTokensAmount = $boostTokensAmount;
        return $this;
    }

    public function getPaypalOrderId(): ?string
    {
        return $this->paypalOrderId;
    }

    public function setPaypalOrderId(?string $paypalOrderId): static
    {
        $this->paypalOrderId = $paypalOrderId;
        return $this;
    }

    public function getPaypalStatus(): ?string
    {
        return $this->paypalStatus;
    }

    public function setPaypalStatus(?string $paypalStatus): static
    {
        $this->paypalStatus = $paypalStatus;
        return $this;
    }

    public function getCryptoPaymentId(): ?string
    {
        return $this->cryptoPaymentId;
    }

    public function setCryptoPaymentId(?string $cryptoPaymentId): static
    {
        $this->cryptoPaymentId = $cryptoPaymentId;
        return $this;
    }

    public function getCryptoStatus(): ?string
    {
        return $this->cryptoStatus;
    }

    public function setCryptoStatus(?string $cryptoStatus): static
    {
        $this->cryptoStatus = $cryptoStatus;
        return $this;
    }

    public function getStripeSessionId(): ?string
    {
        return $this->stripeSessionId;
    }

    public function setStripeSessionId(?string $stripeSessionId): static
    {
        $this->stripeSessionId = $stripeSessionId;
        return $this;
    }

    public function getStripeStatus(): ?string
    {
        return $this->stripeStatus;
    }

    public function setStripeStatus(?string $stripeStatus): static
    {
        $this->stripeStatus = $stripeStatus;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function isCredited(): bool
    {
        return $this->isCredited;
    }

    public function setIsCredited(bool $isCredited): static
    {
        $this->isCredited = $isCredited;
        return $this;
    }

    public function getCreditedAt(): ?\DateTimeImmutable
    {
        return $this->creditedAt;
    }

    public function setCreditedAt(?\DateTimeImmutable $creditedAt): static
    {
        $this->creditedAt = $creditedAt;
        return $this;
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
