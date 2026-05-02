<?php

namespace App\Entity;

use App\Repository\ClassSubjectModuleRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: ClassSubjectModuleRepository::class)]
#[ORM\Table(name: 'class_subject_module')]
#[ORM\UniqueConstraint(columns: ['subject_id', 'class_id', 'period_id', 'school_id', 'module_id'])]
#[ORM\HasLifecycleCallbacks]
class ClassSubjectModule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: StudySubject::class, inversedBy: 'classSubjectModules')]
    #[ORM\JoinColumn(nullable: false)]
    private ?StudySubject $subject = null;

    #[ORM\ManyToOne(targetEntity: SchoolClassPeriod::class, inversedBy: 'classSubjectModules')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SchoolClassPeriod $class = null;

    #[ORM\ManyToOne(targetEntity: SchoolPeriod::class, inversedBy: 'classSubjectModules')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SchoolPeriod $period = null;

    #[ORM\ManyToOne(targetEntity: SubjectsModules::class, inversedBy: 'classSubjectModules')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SubjectsModules $module = null;

    #[ORM\ManyToOne(targetEntity: School::class, inversedBy: 'classSubjectModules')]
    #[ORM\JoinColumn(nullable: false)]
    private ?School $school = null;

    #[ORM\Column(type: 'float')]
    private ?float $moduleNotation = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTime $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'classSubjectModule', targetEntity: Evaluation::class, cascade: ['persist', 'remove'])]
    private Collection $evaluations;

    public function __construct()
    {
        $this->evaluations = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubject(): ?StudySubject
    {
        return $this->subject;
    }

    public function setSubject(?StudySubject $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function getClass(): ?SchoolClassPeriod
    {
        return $this->class;
    }

    public function setClass(?SchoolClassPeriod $class): self
    {
        $this->class = $class;

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

    public function getModule(): ?SubjectsModules
    {
        return $this->module;
    }

    public function setModule(?SubjectsModules $module): self
    {
        $this->module = $module;

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

    public function getModuleNotation(): ?float
    {
        return $this->moduleNotation;
    }

    public function setModuleNotation($moduleNotation): self
    {

        if (is_string($moduleNotation)) {
            $moduleNotation = (float) str_replace(',', '.', $moduleNotation);
        }
        
        $this->moduleNotation = round((float)$moduleNotation, 2);

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getEvaluations(): Collection
    {
        return $this->evaluations;
    }

    public function addEvaluation(Evaluation $evaluation): self
    {
        if (!$this->evaluations->contains($evaluation)) {
            $this->evaluations[] = $evaluation;
            $evaluation->setClassSubjectModule($this);
        }

        return $this;
    }

    public function removeEvaluation(Evaluation $evaluation): self
    {
        if ($this->evaluations->removeElement($evaluation)) {
            // Set the owning side to null (unless already changed)
            if ($evaluation->getClassSubjectModule() === $this) {
                $evaluation->setClassSubjectModule(null);
            }
        }

        return $this;
    }
}
