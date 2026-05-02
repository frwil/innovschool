<?php 
namespace App\Entity;

use App\Repository\AdmissionReductionsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdmissionReductionsRepository::class)]
class AdmissionReductions
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: School::class, inversedBy: 'admissionReductions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?School $school = null;

    #[ORM\ManyToOne(targetEntity: SchoolClassPeriod::class, inversedBy: 'admissionReductions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SchoolClassPeriod $schoolClassPeriod = null;

    #[ORM\ManyToOne(targetEntity: SchoolPeriod::class, inversedBy: 'admissionReductions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SchoolPeriod $schoolPeriod = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'admissionReductions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $student = null;

    #[ORM\ManyToOne(targetEntity: SchoolClassPaymentModal::class, inversedBy: 'admissionReductions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SchoolClassPaymentModal $reductionModal = null;

    #[ORM\Column(type: 'bigint')]
    private ?int $reductionAmount = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateCreated = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateUpdated = null;
    #[ORM\HasLifecycleCallbacks]
    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable();
        $this->dateCreated = $now;
        $this->dateUpdated = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->dateUpdated = new \DateTimeImmutable();
    }

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $pendingApproval = true;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $approvalOwners = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $approved = false;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'admissionReductionsRequested')]
    private ?User $requestedBy = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'admissionReductionsApproved')]
    private ?User $approvedBy = null;

    public function __construct()
    {
        $this->approvalOwners = [];
    }
    /**
     * Getters and Setters
     */
    public function getId(): ?int
    {
        return $this->id;
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

    public function getSchoolClass(): ?SchoolClassPeriod
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

    public function getStudent(): ?User
    {
        return $this->student;
    }

    public function setStudent(?User $student): self
    {
        $this->student = $student;

        return $this;
    }

    public function getReductionModal(): ?SchoolClassPaymentModal
    {
        return $this->reductionModal;
    }

    public function setReductionModal(?SchoolClassPaymentModal $reductionModal): self
    {
        $this->reductionModal = $reductionModal;

        return $this;
    }

    public function getReductionAmount(): ?int
    {
        return $this->reductionAmount;
    }

    public function setReductionAmount(?int $reductionAmount): self
    {
        $this->reductionAmount = $reductionAmount;

        return $this;
    }

    public function getDateCreated(): ?\DateTimeInterface
    {
        return $this->dateCreated;
    }

    public function setDateCreated(?\DateTimeInterface $dateCreated): self
    {
        $this->dateCreated = $dateCreated;

        return $this;
    }

    public function getDateUpdated(): ?\DateTimeInterface
    {
        return $this->dateUpdated;
    }

    public function setDateUpdated(?\DateTimeInterface $dateUpdated): self
    {
        $this->dateUpdated = $dateUpdated;
        return $this;
    }

    public function isPendingApproval(): bool
    {
        return $this->pendingApproval;
    }

    public function setPendingApproval(bool $pendingApproval): self
    {
        $this->pendingApproval = $pendingApproval;
        return $this;
    }

    public function getApprovalOwners(): ?array
    {
        return $this->approvalOwners;
    }

    public function setApprovalOwners(?array $approvalOwners): self
    {
        $this->approvalOwners = $approvalOwners;
        return $this;
    }

    public function isApproved(): bool
    {
        return $this->approved;
    }

    public function setApproved(bool $approved): self
    {
        $this->approved = $approved;
        return $this;
    }

    public function getRequestedBy(): ?User
    {
        return $this->requestedBy;
    }

    public function setRequestedBy(?User $requestedBy): self
    {
        $this->requestedBy = $requestedBy;
        return $this;
    }

    public function getApprovedBy(): ?User
    {
        return $this->approvedBy;
    }

    public function setApprovedBy(?User $approvedBy): self
    {
        $this->approvedBy = $approvedBy;
        return $this;
    }
}