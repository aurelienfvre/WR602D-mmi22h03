<?php

namespace App\Entity;

use App\Repository\SubscriptionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
    private ?int $maxPdf = null;

    #[ORM\Column]
    private ?float $price = null;

    #[ORM\Column(nullable: true)]
    private ?float $specialPrice = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $specialPriceFrom = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $specialPriceTo = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'subscription')]
    private Collection $users;

    public function __construct()
    {
        $this->users = new ArrayCollection();
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

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getMaxPdf(): ?int
    {
        return $this->maxPdf;
    }

    public function setMaxPdf(int $maxPdf): static
    {
        $this->maxPdf = $maxPdf;

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
        return $this->specialPrice;
    }

    public function setSpecialPrice(?float $specialPrice): static
    {
        $this->specialPrice = $specialPrice;

        return $this;
    }

    public function getSpecialPriceFrom(): ?\DateTimeInterface
    {
        return $this->specialPriceFrom;
    }

    public function setSpecialPriceFrom(?\DateTimeInterface $specialPriceFrom): static
    {
        $this->specialPriceFrom = $specialPriceFrom;

        return $this;
    }

    public function getSpecialPriceTo(): ?\DateTimeInterface
    {
        return $this->specialPriceTo;
    }

    public function setSpecialPriceTo(?\DateTimeInterface $specialPriceTo): static
    {
        $this->specialPriceTo = $specialPriceTo;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->setSubscription($this);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        if ($this->users->removeElement($user)) {
            // set the owning side to null (unless already changed)
            if ($user->getSubscription() === $this) {
                $user->setSubscription(null);
            }
        }

        return $this;
    }
}
