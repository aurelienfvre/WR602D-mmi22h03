<?php

namespace App\Entity;

use App\Repository\SubscriptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
class Subscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?int $max_pdf = null;

    #[ORM\Column]
    private ?float $price = null;

    #[ORM\Column(nullable: true)]
    private ?float $special_price = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $special_price_from = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $special_price_to = null;

    #[ORM\OneToMany(mappedBy: 'subscription', targetEntity: User::class)]
    private Collection $users;

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

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getMaxPdf(): ?int
    {
        return $this->max_pdf;
    }

    public function setMaxPdf(int $max_pdf): static
    {
        $this->max_pdf = $max_pdf;

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getSpecialPrice(): ?float
    {
        return $this->special_price;
    }

    public function setSpecialPrice(?float $special_price): static
    {
        $this->special_price = $special_price;

        return $this;
    }

    public function getSpecialPriceFrom(): ?\DateTimeInterface
    {
        return $this->special_price_from;
    }

    public function setSpecialPriceFrom(?\DateTimeInterface $special_price_from): static
    {
        $this->special_price_from = $special_price_from;

        return $this;
    }

    public function getSpecialPriceTo(): ?\DateTimeInterface
    {
        return $this->special_price_to;
    }

    public function setSpecialPriceTo(?\DateTimeInterface $special_price_to): static
    {
        $this->special_price_to = $special_price_to;

        return $this;
    }
}
