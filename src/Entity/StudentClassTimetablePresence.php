<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\StudentClassTimetablePresenceRepository;

#[ORM\Entity(repositoryClass: StudentClassTimetablePresenceRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_presence_per_studentclass_slot_datepresence', columns: ['student_class_id', 'time_table_slot_id', 'date_presence'])]
class StudentClassTimetablePresence
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private $id;

    #[ORM\ManyToOne(targetEntity: StudentClass::class, inversedBy: 'presences')]
    #[ORM\JoinColumn(nullable: false)]
    private $studentClass;

    #[ORM\ManyToOne(targetEntity: TimetableSlot::class, inversedBy: 'presences')]
    #[ORM\JoinColumn(nullable: false)]
    private $timeTableSlot;

    #[ORM\Column(type: "string", length: 10)]
    private $status; // 'present' or 'absent'

    #[ORM\Column(name: 'date_presence', type: 'date')]
    private ?\DateTimeInterface $datePresence = null;

    #[ORM\ManyToOne(targetEntity: SchoolEvaluationTime::class, inversedBy: 'presences')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SchoolEvaluationTime $schoolEvaluationTime = null;

    #[ORM\Column(type: "boolean", options: ["default" => false])]
    private bool $locked = false;

    public function isLocked(): bool
    {
        return $this->locked;
    }

    public function setLocked(bool $locked): self
    {
        $this->locked = $locked;
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


    public function getDatePresence(): ?\DateTimeInterface
    {
        return $this->datePresence;
    }

    public function setDatePresence(?\DateTimeInterface $datePresence): self
    {
        $this->datePresence = $datePresence;
        return $this;
    }

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

    public function getTimeTableSlot(): ?TimeTableSlot
    {
        return $this->timeTableSlot;
    }

    public function setTimeTableSlot(?TimeTableSlot $timeTableSlot): self
    {
        $this->timeTableSlot = $timeTableSlot;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }
}
