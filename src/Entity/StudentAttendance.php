<?php

namespace App\Entity;

use App\Repository\StudentAttendanceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StudentAttendanceRepository::class)]
class StudentAttendance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'studentAttendances')]
    private ?User $student = null;

    #[ORM\Column]
    private ?int $abscence = null;

    #[ORM\ManyToOne(inversedBy: 'studentAttendances')]
    private ?SchoolClassPeriod $schoolClassPeriod = null;

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

    public function getAbscence(): ?int
    {
        return $this->abscence;
    }

    public function setAbscence(int $abscence): self
    {
        $this->abscence = $abscence;

        return $this;
    }

    public function getSchoolClass(): ?SchoolClassPeriod
    {
        return $this->schoolClassPeriod;
    }

    public function setSchoolClass(?SchoolClassPeriod $schoolClassPeriod): self
    {
        $this->schoolClassPeriod = $schoolClassPeriod;

        return $this;
    }
}
