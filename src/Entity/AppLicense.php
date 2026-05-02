<?php

namespace App\Entity;

use App\Repository\AppLicenseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppLicenseRepository::class)]
#[ORM\HasLifecycleCallbacks]
class AppLicense
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $licenceStartAt;

    #[ORM\Column(type: "integer", options: ["default" => 30])]
    private int $licenceDuration = 30;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $licenceAmount = 0;

    #[ORM\Column(type: "string", length: 255)]
    private string $licenseHash;

    #[ORM\ManyToOne(targetEntity: School::class, inversedBy: 'licenses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?School $school = null;

    #[ORM\Column(type: "boolean", options: ["default" => false])]
    private bool $enabled = false;

    #[ORM\Column(type: "datetime_immutable", options: ["default" => "CURRENT_TIMESTAMP"])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: "string", length: 20, options: ["default" => "trial"])]
    private string $licenseType = 'trial';

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\OneToMany(mappedBy: 'license', targetEntity: SchoolLicensePayment::class, cascade: ['persist', 'remove'])]
    private Collection $payments;

    public function __construct(
        ?\DateTimeInterface $licenceStartAt = null,
        int $licenceDuration = 30,
        int $licenceAmount = 0,
        ?School $school = null
    ) {
        $this->licenceStartAt = $licenceStartAt ?: new \DateTime();
        $this->licenceDuration = $licenceDuration;
        $this->licenceAmount = $licenceAmount;
        $this->licenseHash = hash('sha256', uniqid('LST', true));
        $this->school = $school;
        $this->payments = new ArrayCollection();
    }
    // Getters et setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLicenceStartAt(): \DateTimeInterface
    {
        return $this->licenceStartAt;
    }

    public function setLicenceStartAt(\DateTimeInterface $licenceStartAt): self
    {
        $this->licenceStartAt = $licenceStartAt;
        return $this;
    }

    public function getLicenceDuration(): int
    {
        return $this->licenceDuration;
    }

    public function setLicenceDuration(int $licenceDuration): self
    {
        $this->licenceDuration = $licenceDuration;
        return $this;
    }

    public function getLicenceAmount(): int
    {
        return $this->licenceAmount;
    }

    public function setLicenceAmount(int $licenceAmount): self
    {
        $this->licenceAmount = $licenceAmount;
        return $this;
    }

    public function getLicenseHash(): string
    {
        return $this->licenseHash;
    }

    public function setLicenseHash(string $licenseHash): self
    {
        $this->licenseHash = hash('sha256', uniqid(str_replace(' ','',$licenseHash), true));
        return $this;
    }

    public function getSchool(): ?School
    {
        return $this->school;
    }

    public function setSchool(?School $school): self
    {
        $this->school = $school;
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(SchoolLicensePayment $payment): self
    {
        if (!$this->payments->contains($payment)) {
            $this->payments[] = $payment;
            $payment->setLicense($this);
        }
        return $this;
    }

    public function removePayment(SchoolLicensePayment $payment): self
    {
        if ($this->payments->removeElement($payment)) {
            if ($payment->getLicense() === $this) {
                $payment->setLicense(null);
            }
        }
        return $this;
    }

    public function onUse(): bool
    {
        $now = new \DateTime();
        $start = $this->licenceStartAt instanceof \DateTimeInterface ? \DateTime::createFromFormat('Y-m-d H:i:s', $this->licenceStartAt->format('Y-m-d H:i:s')) : new \DateTime();
        $trialEndDate = $start->modify("+{$this->licenceDuration} days");
        return $now < $trialEndDate && $this->enabled;
    }

    public function getLicenseType(): string
    {
        return $this->licenseType;
    }

    public function setLicenseType(string $licenseType): self
    {
        $allowed = ['trial', 'pro'];
        if (!in_array($licenseType, $allowed, true)) {
            throw new \InvalidArgumentException("Type de licence invalide.");
        }
        $this->licenseType = $licenseType;
        return $this;
    }

    public function isTrial(): bool
    {
        return $this->licenseType === 'trial';
    }

    public function isPro(): bool
    {
        return $this->licenseType === 'pro';
    }

    public function licenseEndDate(): ?\DateTimeInterface
    {
        if ($this->licenceStartAt) {
            $start = $this->licenceStartAt instanceof \DateTimeInterface ? \DateTime::createFromFormat('Y-m-d H:i:s', $this->licenceStartAt->format('Y-m-d H:i:s')) : new \DateTime();
            return $start->modify("+{$this->licenceDuration} days");
        }
        return null;
    }
}
