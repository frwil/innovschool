<?php

namespace App\Entity;

use App\Repository\EvaluationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EvaluationRepository::class)]
#[ORM\Table(name: 'evaluation')]
#[ORM\UniqueConstraint(columns: ['student_id', 'class_subject_module_id', 'time_id'])]
#[ORM\HasLifecycleCallbacks]
class Evaluation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: StudentClass::class, inversedBy: 'evaluations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?StudentClass $student = null;

    #[ORM\ManyToOne(targetEntity: ClassSubjectModule::class, inversedBy: 'evaluations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ClassSubjectModule $classSubjectModule = null;

    #[ORM\ManyToOne(targetEntity: SchoolEvaluationTime::class, inversedBy: 'evaluations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SchoolEvaluationTime $time = null;

    #[ORM\Column(type: 'float')]
    private ?float $evaluationNote = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTime $updatedAt = null;

    // Getters and setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStudent(): ?StudentClass
    {
        return $this->student;
    }

    public function setStudent(?StudentClass $student): self
    {
        $this->student = $student;

        return $this;
    }

    public function getClassSubjectModule(): ?ClassSubjectModule
    {
        return $this->classSubjectModule;
    }

    public function setClassSubjectModule(?ClassSubjectModule $classSubjectModule): self
    {
        $this->classSubjectModule = $classSubjectModule;

        return $this;
    }

    public function getTime(): ?SchoolEvaluationTime
    {
        return $this->time;
    }

    public function setTime(?SchoolEvaluationTime $time): self
    {
        $this->time = $time;

        return $this;
    }

    public function getEvaluationNote(): ?float
    {
        return $this->evaluationNote;
    }

    public function setEvaluationNote(float $evaluationNote): self
    {
        $this->evaluationNote = $evaluationNote;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }
}