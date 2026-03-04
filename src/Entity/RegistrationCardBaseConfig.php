<?php

namespace App\Entity;

use App\Repository\RegistrationCardBaseConfigRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RegistrationCardBaseConfigRepository::class)]
class RegistrationCardBaseConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $cardHeader = null;

    #[ORM\Column(length: 255)]
    private ?string $nationalMotto = null;

    #[ORM\Column(length: 255)]
    private ?string $headOfficerSign = null;

    #[ORM\Column(length: 255)]
    private ?string $cardBg = null;

    #[ORM\Column(length: 255,nullable: true)]
    private ?string $signTitle = null;

    #[ORM\Column(length: 255,nullable: true)]
    private ?string $cardHeaderA = null;

    #[ORM\Column(length: 255,nullable: true)]
    private ?string $nationalMottoA = null;
    
    #[ORM\OneToOne(mappedBy: 'registrationBaseConfig', targetEntity: School::class, cascade: ['persist', 'remove'])]
    private ?School $school = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $doubleHeaderLayout = false;

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCardHeader(): ?string
    {
        return $this->cardHeader;
    }

    public function setCardHeader(string $cardHeader): self
    {
        $this->cardHeader = $cardHeader;

        return $this;
    }

    public function getNationalMotto(): ?string
    {
        return $this->nationalMotto;
    }

    public function setNationalMotto(string $nationalMotto): self
    {
        $this->nationalMotto = $nationalMotto;

        return $this;
    }

    public function getHeadOfficerSign(): ?string
    {
        return $this->headOfficerSign;
    }

    public function setHeadOfficerSign(string $headOfficerSign): self
    {
        $this->headOfficerSign = $headOfficerSign;

        return $this;
    }

    public function getCardBg(): ?string
    {
        return $this->cardBg;
    }

    public function setCardBg(string $cardBg): self
    {
        $this->cardBg = $cardBg;

        return $this;
    }

    // Getter et Setter pour signTitle
    public function getSignTitle(): ?string
    {
        return $this->signTitle;
    }

    public function setSignTitle(?string $signTitle): self
    {
        $this->signTitle = $signTitle;

        return $this;
    }

    // Getter et Setter pour cardHeaderA
    public function getCardHeaderA(): ?string
    {
        return $this->cardHeaderA;
    }

    public function setCardHeaderA(?string $cardHeaderA): self
    {
        $this->cardHeaderA = $cardHeaderA;

        return $this;
    }

    // Getter et Setter pour nationalMottoA
    public function getNationalMottoA(): ?string
    {
        return $this->nationalMottoA;
    }

    public function setNationalMottoA(?string $nationalMottoA): self
    {
        $this->nationalMottoA = $nationalMottoA;

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

    // Getter et Setter pour doubleHeaderLayout
    public function isDoubleHeaderLayout(): bool
    {
        return $this->doubleHeaderLayout;
    }

    public function setDoubleHeaderLayout(bool $doubleHeaderLayout): self
    {
        $this->doubleHeaderLayout = $doubleHeaderLayout;

        return $this;
    }
}
