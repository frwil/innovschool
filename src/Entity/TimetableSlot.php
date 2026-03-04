<?php

namespace App\Entity;

use App\Repository\TimetableSlotRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TimetableSlotRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_timetableday_starttime_schoolclass', columns: ["timetable_day_id", "start_time", "school_class_period_id"])]
#[ORM\UniqueConstraint(name: 'unique_timetableday_endtime_schoolclass', columns: ["timetable_day_id", "end_time", "school_class_period_id"])]
#[ORM\UniqueConstraint(name: 'unique_timetableday_subject_schoolclass', columns: ["timetable_day_id", "subject_id", "school_class_period_id"])]
#[ORM\UniqueConstraint(name: 'unique_timetableday_starttime_endtime', columns: ["timetable_day_id", "start_time", "end_time", "school_class_period_id"])]
#[ORM\UniqueConstraint(name: 'unique_timetableday_subject_starttime_endtime', columns: ["timetable_day_id", "subject_id", "start_time", "end_time", "school_class_period_id"])]
class TimetableSlot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TimetableDay::class, inversedBy: 'slots')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TimetableDay $timetableDay = null;

    #[ORM\Column(type: 'time')]
    private ?\DateTimeInterface $startTime = null;

    #[ORM\Column(type: 'time')]
    private ?\DateTimeInterface $endTime = null;

    #[ORM\ManyToOne(targetEntity: StudySubject::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?StudySubject $subject = null;

    #[ORM\ManyToOne(targetEntity: SchoolClassPeriod::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?SchoolClassPeriod $schoolClassPeriod = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\OneToMany(mappedBy: 'timeTableSlot', targetEntity: StudentClassTimetablePresence::class, cascade: ['remove'])]
    private \Doctrine\Common\Collections\Collection $presences;

    public function __construct()
    {
        $this->presences = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function getPresences(): \Doctrine\Common\Collections\Collection
    {
        return $this->presences;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTimetableDay(): ?TimetableDay
    {
        return $this->timetableDay;
    }

    public function setTimetableDay(?TimetableDay $timetableDay): self
    {
        $this->timetableDay = $timetableDay;
        return $this;
    }

    public function getStartTime(): ?\DateTimeInterface
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTimeInterface $startTime): self
    {
        $this->startTime = $startTime;
        return $this;
    }

    public function getEndTime(): ?\DateTimeInterface
    {
        return $this->endTime;
    }

    public function setEndTime(\DateTimeInterface $endTime): self
    {
        $this->endTime = $endTime;
        return $this;
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

    public function getSchoolClassPeriod(): ?SchoolClassPeriod
    {
        return $this->schoolClassPeriod;
    }

    public function setSchoolClassPeriod(?SchoolClassPeriod $schoolClassPeriod): self
    {
        $this->schoolClassPeriod = $schoolClassPeriod;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }
}