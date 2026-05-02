<?php

namespace App\Entity;

use App\Repository\StudentClassAttendanceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StudentClassAttendanceRepository::class)]
class StudentClassAttendance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: StudentClass::class,inversedBy:"attendances")]
    #[ORM\JoinColumn(nullable: false)]
    private ?StudentClass $studentClass = null;

    #[ORM\ManyToOne(targetEntity: SchoolEvaluationTime::class, inversedBy: "attendances")]
    #[ORM\JoinColumn(nullable: false)]
    private ?SchoolEvaluationTime $time = null;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $heuresAbsence = 0;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $absencesJustifiee = 0;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $retard = 0;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $retardInjustifie = 0;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $retenue = 0;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $avertissementDiscipline = 0;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $blame = 0;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $jourExclusion = 0;

    #[ORM\Column(type: "boolean", options: ["default" => false])]
    private bool $exclusionDefinitive = false;

    // Getters et setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStudentClass(): ?StudentClass
    {
        return $this->studentClass;
    }

    public function setStudentClass(?StudentClass $studentClass): self
    {
        $this->studentClass = $studentClass;
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

    public function getHeuresAbsence(): int
    {
        return $this->heuresAbsence;
    }

    public function setHeuresAbsence(int $heuresAbsence): self
    {
        $this->heuresAbsence = $heuresAbsence;
        return $this;
    }

    public function getAbsencesJustifiee(): int
    {
        return $this->absencesJustifiee;
    }

    public function setAbsencesJustifiee(int $absencesJustifiee): self
    {
        $this->absencesJustifiee = $absencesJustifiee;
        return $this;
    }

    public function getRetard(): int
    {
        return $this->retard;
    }

    public function setRetard(int $retard): self
    {
        $this->retard = $retard;
        return $this;
    }

    public function getRetardInjustifie(): int
    {
        return $this->retardInjustifie;
    }

    public function setRetardInjustifie(int $retardInjustifie): self
    {
        $this->retardInjustifie = $retardInjustifie;
        return $this;
    }

    public function getRetenue(): int
    {
        return $this->retenue;
    }

    public function setRetenue(int $retenue): self
    {
        $this->retenue = $retenue;
        return $this;
    }

    public function getAvertissementDiscipline(): int
    {
        return $this->avertissementDiscipline;
    }

    public function setAvertissementDiscipline(int $avertissementDiscipline): self
    {
        $this->avertissementDiscipline = $avertissementDiscipline;
        return $this;
    }

    public function getBlame(): int
    {
        return $this->blame;
    }

    public function setBlame(int $blame): self
    {
        $this->blame = $blame;
        return $this;
    }

    public function getJourExclusion(): int
    {
        return $this->jourExclusion;
    }

    public function setJourExclusion(int $jourExclusion): self
    {
        $this->jourExclusion = $jourExclusion;
        return $this;
    }

    public function isExclusionDefinitive(): bool
    {
        return $this->exclusionDefinitive;
    }

    public function setExclusionDefinitive(bool $exclusionDefinitive): self
    {
        $this->exclusionDefinitive = $exclusionDefinitive;
        return $this;
    }
}