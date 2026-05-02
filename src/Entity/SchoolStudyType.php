<?php

namespace App\Entity;

use App\Repository\SchoolStudyTypeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SchoolStudyTypeRepository::class)]
#[ORM\Table(name: 'school_study_type')]
#[ORM\UniqueConstraint(name: 'unique_school_studies_type', columns: ['school_id', 'studies_type_id'])]
class SchoolStudyType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: School::class, inversedBy: 'schoolStudyTypes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?School $school = null;

    #[ORM\ManyToOne(targetEntity: StudiesType::class, inversedBy: 'schoolStudyTypes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?StudiesType $studiesType = null;

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

    public function getStudiesType(): ?StudiesType
    {
        return $this->studiesType;
    }

    public function setStudiesType(?StudiesType $studiesType): self
    {
        $this->studiesType = $studiesType;
        return $this;
    }
}