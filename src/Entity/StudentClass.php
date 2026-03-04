<?php

namespace App\Entity;

use App\Repository\StudentClassRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: StudentClassRepository::class)]
class StudentClass
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'studentClasses')]
    private ?User $student = null;

    #[ORM\ManyToOne(inversedBy: 'studentClasses')]
    private ?SchoolClassPeriod $schoolClassPeriod = null;

    #[ORM\OneToMany(mappedBy: 'student', targetEntity: Evaluation::class, cascade: ['persist', 'remove'])]
    private Collection $evaluations;

    #[ORM\OneToMany(mappedBy: 'studentClass', targetEntity: StudentClassAttendance::class)]
    private Collection $attendances;
    
    #[ORM\OneToMany(mappedBy: 'studentClass', targetEntity: StudentClassTimetablePresence::class, cascade: ['remove'])]
    private Collection $presences;

    public function getPresences(): Collection
    {
        return $this->presences ?? new ArrayCollection();
    }

    public function __construct()
    {
        $this->evaluations = new ArrayCollection();
        $this->attendances = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getSchoolClassPeriod(): ?SchoolClassPeriod
    {
        return $this->schoolClassPeriod;
    }

    public function setSchoolClassPeriod(?SchoolClassPeriod $schoolClassPeriod): self
    {
        $this->schoolClassPeriod = $schoolClassPeriod;

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
            $evaluation->setStudent($this);
        }

        return $this;
    }

    public function removeEvaluation(Evaluation $evaluation): self
    {
        if ($this->evaluations->removeElement($evaluation)) {
            // Set the owning side to null (unless already changed)
            if ($evaluation->getStudent() === $this) {
                $evaluation->setStudent(null);
            }
        }

        return $this;
    }

    public function getAttendances(): Collection
    {
        return $this->attendances;
    }

    public function addAttendance(StudentClassAttendance $attendance): self
    {
        if (!$this->attendances->contains($attendance)) {
            $this->attendances[] = $attendance;
            $attendance->setStudentClass($this);
        }

        return $this;
    }

    public function removeAttendance(StudentClassAttendance $attendance): self
    {
        if ($this->attendances->removeElement($attendance)) {
            // Set the owning side to null (unless already changed)
            if ($attendance->getStudentClass() === $this) {
                $attendance->setStudentClass(null);
            }
        }

        return $this;
    }
}
