<?php

namespace App\Entity;

use App\Repository\SchoolClassPaymentModalRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SchoolClassPaymentModalRepository::class)]
#[ORM\Table(name: "school_class_payment_modals")]
#[ORM\UniqueConstraint(
            name: "unique_class_school_period",
            columns: ["school_class_period_id", "school_id", "school_period_id", "modal_type","label"]
        )]
class SchoolClassPaymentModal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SchoolClassPeriod::class, inversedBy: 'paymentModals')]
    #[ORM\JoinColumn(nullable: true)]
    private ?SchoolClassPeriod $schoolClassPeriod = null;

    #[ORM\ManyToOne(targetEntity: SchoolPeriod::class, inversedBy: 'paymentModals')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SchoolPeriod $schoolPeriod = null;

    #[ORM\ManyToOne(targetEntity: School::class, inversedBy: 'paymentModals')]
    #[ORM\JoinColumn(nullable: false)]
    private ?School $school = null;

    #[ORM\Column(length: 255)]
    private ?string $label = null;

    #[ORM\Column(type: Types::BIGINT)]
    private ?int $amount = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dueDate = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private ?string $modalType = null;

    #[ORM\Column(type: Types::INTEGER, options: ["default" => 0])]
    private ?int $modalPriority = 100;

    #[ORM\OneToMany(mappedBy: 'paymentModal', targetEntity: ModalitiesSubscriptions::class, cascade: ['persist', 'remove'])]
    private Collection $subscriptions;

    #[ORM\OneToMany(mappedBy: 'paymentModal', targetEntity: SchoolClassAdmissionPayment::class, cascade: ['persist', 'remove'])]
    private Collection $admissionPayments;

    #[ORM\OneToMany(mappedBy: 'reductionModal', targetEntity: AdmissionReductions::class, orphanRemoval: true)]
    private Collection $admissionReductions;

    public function __construct()
    {
        $this->subscriptions = new ArrayCollection();
        $this->admissionPayments = new ArrayCollection();
        $this->admissionReductions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSchoolClassPeriod(): ?SchoolClassPeriod
    {
        return $this->schoolClassPeriod;
    }

    public function setSchoolClassPeriod(?SchoolClassPeriod $schoolClassPeriod): self
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

    public function getSchool(): ?School
    {
        return $this->school;
    }

    public function setSchool(?School $school): self
    {
        $this->school = $school;
        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;
        return $this;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function getDueDate(): ?\DateTimeInterface
    {
        return $this->dueDate;
    }

    public function setDueDate(\DateTimeInterface $dueDate): self
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    public function getModalType(): ?string
    {
        return $this->modalType;
    }

    public function setModalType(string $modalType): self
    {
        if (!in_array($modalType, ['base', 'additional', 'transport'], true)) {
            throw new \InvalidArgumentException('Invalid modal type. Allowed values are: base, additional, transport.');
        }
        $this->modalType = $modalType;
        return $this;
    }

    public function getModalPriority(): ?int
    {
        return $this->modalPriority;
    }

    public function setModalPriority(int $modalPriority): self
    {
        $this->modalPriority = $modalPriority;
        return $this;
    }

    public function getSubscriptions(): Collection
    {
        return $this->subscriptions;
    }

    public function addSubscription(ModalitiesSubscriptions $subscription): self
    {
        if (!$this->subscriptions->contains($subscription)) {
            $this->subscriptions[] = $subscription;
            $subscription->setPaymentModal($this);
        }
        return $this;
    }

    public function removeSubscription(ModalitiesSubscriptions $subscription): self
    {
        if ($this->subscriptions->removeElement($subscription)) {
            if ($subscription->getPaymentModal() === $this) {
                $subscription->setPaymentModal(null);
            }
        }
        return $this;
    }

    public function getAdmissionPayments(): Collection
    {
        return $this->admissionPayments;
    }

    public function addAdmissionPayment(SchoolClassAdmissionPayment $admissionPayment): self
    {
        if (!$this->admissionPayments->contains($admissionPayment)) {
            $this->admissionPayments->add($admissionPayment);
            $admissionPayment->setPaymentModal($this);
        }
        return $this;
    }

    public function removeAdmissionPayment(SchoolClassAdmissionPayment $admissionPayment): self
    {
        if ($this->admissionPayments->removeElement($admissionPayment)) {
            if ($admissionPayment->getPaymentModal() === $this) {
                $admissionPayment->setPaymentModal(null);
            }
        }
        return $this;
    }

    public function getAdmissionReductions(): Collection
    {
        return $this->admissionReductions;
    }

    public function addAdmissionReduction(AdmissionReductions $admissionReduction): self
    {
        if (!$this->admissionReductions->contains($admissionReduction)) {
            $this->admissionReductions[] = $admissionReduction;
            $admissionReduction->setReductionModal($this);
        }
        return $this;
    }

    public function removeAdmissionReduction(AdmissionReductions $admissionReduction): self
    {
        if ($this->admissionReductions->removeElement($admissionReduction)) {
            if ($admissionReduction->getReductionModal() === $this) {
                $admissionReduction->setReductionModal(null);
            }
        }
        return $this;
    }
}
