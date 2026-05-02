<?php

namespace App\Entity;

use App\Repository\SubjectGroupRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;


#[ORM\Entity(repositoryClass: SubjectGroupRepository::class)]
class SubjectGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: School::class, inversedBy: 'subjectGroups')]
    #[ORM\JoinColumn(nullable: false)]
    private ?School $school = null;

    #[ORM\ManyToOne(targetEntity: SchoolPeriod::class, inversedBy: 'subjectGroups')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SchoolPeriod $period = null;

    #[ORM\Column(type: 'integer')]
    private ?int $posOrder = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true, unique: true)]
    private ?string $description = null;

    #[ORM\OneToMany(mappedBy: 'group', targetEntity: SchoolClassSubject::class)]
    private Collection $schoolClassSubjects;

    public function __construct()
    {
        $this->schoolClassSubjects = new ArrayCollection();
    }

    // Getters and Setters
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

    public function getPeriod(): ?SchoolPeriod
    {
        return $this->period;
    }

    public function setPeriod(?SchoolPeriod $period): self
    {
        $this->period = $period;

        return $this;
    }

    public function getPosOrder(): ?int
    {
        return $this->posOrder;
    }

    public function setPosOrder(int $posOrder): self
    {
        $this->posOrder = $posOrder;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }    

    public function getSchoolClassSubjects(): Collection
    {
        return $this->schoolClassSubjects;
    }
}