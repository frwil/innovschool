<?php

namespace App\Entity;

use App\Repository\SchoolClassAttendanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SchoolClassAttendanceRepository::class)]
class SchoolClassAttendance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'schoolClassAttendances')]
    private ?SchoolEvaluation $evaluation = null;

    #[ORM\ManyToOne(inversedBy: 'schoolClassAttendances')]
    private ?SchoolClassPeriod $schoolClassPeriod = null;

    #[ORM\Column]
    private array $attendancJson = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEvaluation(): ?SchoolEvaluation
    {
        return $this->evaluation;
    }

    public function setEvaluation(?SchoolEvaluation $evaluation): self
    {
        $this->evaluation = $evaluation;

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

    public function getAttendancJson(): array
    {
        return $this->attendancJson;
    }

    public function setAttendancJson(array $attendancJson): self
    {
        $this->attendancJson = $attendancJson;

        return $this;
    }
}
