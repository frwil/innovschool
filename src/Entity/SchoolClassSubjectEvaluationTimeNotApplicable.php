<?php

namespace App\Entity;

use App\Repository\SchoolClassSubjectEvaluationTimeNotApplicableRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SchoolClassSubjectEvaluationTimeNotApplicableRepository::class)]
class SchoolClassSubjectEvaluationTimeNotApplicable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SchoolClassSubject::class, inversedBy: 'evaluationTimeNotApplicables')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SchoolClassSubject $schoolClassSubject = null;

    #[ORM\ManyToOne(targetEntity: SchoolEvaluationTime::class, inversedBy: 'subjectNotApplicables')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SchoolEvaluationTime $schoolEvaluationTime = null;

    #[ORM\Column(type: 'boolean')]
    private bool $notApplicable = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSchoolClassSubject(): ?SchoolClassSubject
    {
        return $this->schoolClassSubject;
    }

    public function setSchoolClassSubject(?SchoolClassSubject $schoolClassSubject): self
    {
        $this->schoolClassSubject = $schoolClassSubject;
        return $this;
    }

    public function getSchoolEvaluationTime(): ?SchoolEvaluationTime
    {
        return $this->schoolEvaluationTime;
    }

    public function setSchoolEvaluationTime(?SchoolEvaluationTime $schoolEvaluationTime): self
    {
        $this->schoolEvaluationTime = $schoolEvaluationTime;
        return $this;
    }

    public function isNotApplicable(): bool
    {
        return $this->notApplicable;
    }

    public function setNotApplicable(bool $notApplicable): self
    {
        $this->notApplicable = $notApplicable;
        return $this;
    }
}