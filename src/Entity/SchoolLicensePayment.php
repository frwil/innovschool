<?php

namespace App\Entity;

use App\Repository\SchoolLicensePaymentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SchoolLicensePaymentRepository::class)]
class SchoolLicensePayment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "datetime", options: ["default" => "CURRENT_TIMESTAMP"])]
    private ?\DateTimeInterface $datePayment = null;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $paymentAmount = 0;

    #[ORM\ManyToOne(targetEntity: AppLicense::class, inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?AppLicense $license = null;

    #[ORM\Column(type: "string", length: 100)]
    private string $paymentMethod;

    public function __construct()
    {
        $this->datePayment = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDatePayment(): ?\DateTimeInterface
    {
        return $this->datePayment;
    }

    public function setDatePayment(\DateTimeInterface $datePayment): self
    {
        $this->datePayment = $datePayment;
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

    public function getLicense(): ?AppLicense
    {
        return $this->license;
    }

    public function setLicense(?AppLicense $license): self
    {
        $this->license = $license;
        return $this;
    }

    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(string $paymentMethod): self
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }
}