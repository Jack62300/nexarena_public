<?php

namespace App\Entity;

use App\Repository\WheelPrizeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WheelPrizeRepository::class)]
#[ORM\Table(name: 'wheel_prize')]
class WheelPrize
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(unique: true)]
    private int $position = 0;

    #[ORM\Column(length: 50)]
    private string $label = '';

    #[ORM\Column]
    private int $nexbits = 0;

    #[ORM\Column]
    private int $nexboost = 0;

    #[ORM\Column]
    private int $weight = 0;

    #[ORM\Column(length: 7)]
    private string $color = '#45f882';

    #[ORM\Column]
    private bool $isJackpot = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;
        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    public function getNexbits(): int
    {
        return $this->nexbits;
    }

    public function setNexbits(int $nexbits): static
    {
        $this->nexbits = $nexbits;
        return $this;
    }

    public function getNexboost(): int
    {
        return $this->nexboost;
    }

    public function setNexboost(int $nexboost): static
    {
        $this->nexboost = $nexboost;
        return $this;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function setWeight(int $weight): static
    {
        $this->weight = $weight;
        return $this;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;
        return $this;
    }

    public function isJackpot(): bool
    {
        return $this->isJackpot;
    }

    public function setIsJackpot(bool $isJackpot): static
    {
        $this->isJackpot = $isJackpot;
        return $this;
    }

    public function __toString(): string
    {
        return $this->label;
    }
}
