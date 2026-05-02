<?php

namespace App\Entity;

use App\Repository\ModalitiesSubscriptionsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ModalitiesSubscriptionsRepository::class)]
#[ORM\Table(name: 'modalities_subscriptions')]
class ModalitiesSubscriptions
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'subscriptions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $student = null;

    #[ORM\ManyToOne(targetEntity: SchoolClassPeriod::class, inversedBy: 'subscriptions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SchoolClassPeriod $schoolClassPeriod = null;

    #[ORM\ManyToOne(targetEntity: SchoolPeriod::class, inversedBy: 'subscriptions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SchoolPeriod $schoolPeriod = null;

    #[ORM\ManyToOne(targetEntity: SchoolClassPaymentModal::class, inversedBy: 'subscriptions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SchoolClassPaymentModal $paymentModal = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $subscriptionDate = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $enabled = false;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isFull = false;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $isFullPeriod = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    // Getters et setters pour chaque champ...

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStudent(): ?User
    {
        return $this->student;
    }

    public function setStudent(?User $student): self
    {
        $this->student = $student;

        return $this;
    }

    public function getSchoolClassPeriod(): ?SchoolClassPeriod
    {
        return $this->schoolClassPeriod;
    }

    public function setSchoolClass(?SchoolClassPeriod $schoolClassPeriod): self
    {
        $this->schoolClassPeriod = $schoolClassPeriod;

        return $this;
    }

    public function getSchoolPeriod(): ?SchoolPeriod
    {
        return $this->schoolPeriod;
    }

    public function setSchoolPeriod(?SchoolPeriod $schoolPeriod): self
    {
        $this->schoolPeriod = $schoolPeriod;

        return $this;
    }

    public function getPaymentModal(): ?SchoolClassPaymentModal
    {
        return $this->paymentModal;
    }

    public function setPaymentModal(?SchoolClassPaymentModal $paymentModal): self
    {
        $this->paymentModal = $paymentModal;

        return $this;
    }

    public function getSubscriptionDate(): ?\DateTimeInterface
    {
        return $this->subscriptionDate;
    }

    public function setSubscriptionDate(\DateTimeInterface $subscriptionDate): self
    {
        $this->subscriptionDate = $subscriptionDate;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getIsFull(): bool
    {
        return $this->isFull;
    }

    public function setIsFull(bool $isFull): self
    {
        $this->isFull = $isFull;

        return $this;
    }

    public function getIsFullPeriod(): ?string
    {
        return $this->isFullPeriod;
    }

    public function setIsFullPeriod(?string $isFullPeriod): self
    {
        if (!in_array($isFullPeriod, ['full', 'morning only', 'evening only'], true)) {
            throw new \InvalidArgumentException('Invalid value for isFullPeriod. Allowed values are: "full", "morning only", "evening only".');
        }

        $this->isFullPeriod = $isFullPeriod;

        return $this;
    }
}
