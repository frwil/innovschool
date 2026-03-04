<?php
namespace App\Entity;

use App\Repository\SchoolClassNumberingTypeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: SchoolClassNumberingTypeRepository::class)]
#[UniqueEntity(fields: ["classe", "school", "numberingType"])]
#[ORM\Table(name: "school_class_numbering_type")]
#[ORM\UniqueConstraint(name: "unique_numbering_type", columns: ["classe_id", "school_id", "numbering_type"])]
class SchoolClassNumberingType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 20)]
    private ?string $numberingType = null;

    #[ORM\ManyToOne(targetEntity: Classe::class, inversedBy: 'schoolClassNumberingTypes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Classe $classe = null;

    #[ORM\ManyToOne(targetEntity: School::class, inversedBy: 'schoolClassNumberingTypes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?School $school = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumberingType(): ?string
    {
        return $this->numberingType;
    }

    public function setNumberingType(string $numberingType): self
    {
        $this->numberingType = $numberingType;
        return $this;
    }

    public function getClasse(): ?Classe
    {
        return $this->classe;
    }

    public function setClasse(?Classe $classe): self
    {
        $this->classe = $classe;
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
}