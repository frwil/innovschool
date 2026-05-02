<?php

namespace App\Entity;

use App\Repository\TimetableRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TimetableRepository::class)]
class Timetable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'timetables')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $teacher = null;

    #[ORM\ManyToOne(targetEntity: School::class, inversedBy: 'timetables')]
    #[ORM\JoinColumn(nullable: false)]
    private ?School $school = null;

    #[ORM\ManyToOne(targetEntity: SchoolPeriod::class, inversedBy: 'timetables')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SchoolPeriod $period = null;

    #[ORM\Column(type: 'string', length: 255,unique: true, nullable: true)]
    #[ORM\JoinColumn(nullable: true)]
    private ?string $label = null;

    #[ORM\OneToMany(mappedBy: 'timetable', targetEntity: TimetableDay::class, cascade: ['persist', 'remove'])]
    private Collection $days;

    public function __construct()
    {
        $this->days = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTeacher(): ?User
    {
        return $this->teacher;
    }

    public function setTeacher(?User $teacher): self
    {
        $this->teacher = $teacher;
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

    public function getPeriod(): ?SchoolPeriod
    {
        return $this->period;
    }

    public function setPeriod(?SchoolPeriod $period): self
    {
        $this->period = $period;
        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;
        return $this;
    }

    /**
     * @return Collection<int, TimetableDay>
     */
    public function getDays(): Collection
    {
        return $this->days;
    }

    public function addDay(TimetableDay $day): self
    {
        if (!$this->days->contains($day)) {
            $this->days[] = $day;
            $day->setTimetable($this);
        }
        return $this;
    }

    public function removeDay(TimetableDay $day): self
    {
        if ($this->days->removeElement($day)) {
            // set the owning side to null (unless already changed)
            if ($day->getTimetable() === $this) {
                $day->setTimetable(null);
            }
        }
        return $this;
    }
}