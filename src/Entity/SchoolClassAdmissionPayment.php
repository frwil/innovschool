<?php

namespace App\Entity;

use App\Repository\SchoolClassAdmissionPaymentRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity(repositoryClass: SchoolClassAdmissionPaymentRepository::class)]
#[ORM\Table(name: 'school_class_admission_payments')]
class SchoolClassAdmissionPayment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SchoolClassPeriod::class, inversedBy: 'admissionPayments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SchoolClassPeriod $schoolClassPeriod = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'admissionPayments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $student = null;

    #[ORM\ManyToOne(targetEntity: School::class, inversedBy: 'admissionPayments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?School $school = null;

    #[ORM\ManyToOne(targetEntity: SchoolPeriod::class, inversedBy: 'admissionPayments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SchoolPeriod $schoolPeriod = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $paymentDate = null;

    #[ORM\Column(type: 'bigint', options: ['default' => 0])]
    private int $paymentAmount = 0;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: false)]
    private ?string $modalType = null;

    #[ORM\ManyToOne(targetEntity: SchoolClassPaymentModal::class, inversedBy: 'admissionPayments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SchoolClassPaymentModal $paymentModal = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
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

    public function getStudent(): ?User
    {
        return $this->student;
    }

    public function setStudent(?User $student): self
    {
        $this->student = $student;

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

    public function getSchoolPeriod(): ?SchoolPeriod
    {
        return $this->schoolPeriod;
    }

    public function setSchoolPeriod(?SchoolPeriod $schoolPeriod): self
    {
        $this->schoolPeriod = $schoolPeriod;

        return $this;
    }

    public function getPaymentDate(): ?\DateTimeInterface
    {
        return $this->paymentDate;
    }

    public function setPaymentDate(\DateTimeInterface $paymentDate): self
    {
        $this->paymentDate = $paymentDate;

        return $this;
    }

    public function getPaymentAmount(): int
    {
        return $this->paymentAmount;
    }

    public function setPaymentAmount(int $paymentAmount): self
    {
        $this->paymentAmount = $paymentAmount;

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

    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeInterface $deletedAt): self
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function getModalType(): ?string
    {
        return $this->modalType;
    }

    public function setModalType(string $modalType): self
    {
        $this->modalType = $modalType;

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
}
