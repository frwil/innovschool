<?php

namespace App\Entity;

use App\Repository\TimetableDayRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TimetableDayRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_timetable_day', columns: ['timetable_id', 'day_of_week'])]
class TimetableDay
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Timetable::class, inversedBy: 'days')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Timetable $timetable = null;

    #[ORM\Column(name: 'day_of_week', type: 'integer')]
    private ?int $dayOfWeek = null; // 1 = lundi, 2 = mardi, etc.

    #[ORM\OneToMany(mappedBy: 'timetableDay', targetEntity: TimetableSlot::class, cascade: ['persist', 'remove'])]
    private Collection $slots;

    public function __construct()
    {
        $this->slots = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTimetable(): ?Timetable
    {
        return $this->timetable;
    }

    public function setTimetable(?Timetable $timetable): self
    {
        $this->timetable = $timetable;
        return $this;
    }

    public function getDayOfWeek(): ?int
    {
        return $this->dayOfWeek;
    }

    public function setDayOfWeek(int $dayOfWeek): self
    {
        $this->dayOfWeek = $dayOfWeek;
        return $this;
    }

    /**
     * @return Collection<int, TimetableSlot>
     */
    public function getSlots(): Collection
    {
        return $this->slots;
    }

    public function addSlot(TimetableSlot $slot): self
    {
        if (!$this->slots->contains($slot)) {
            $this->slots[] = $slot;
            $slot->setTimetableDay($this);
        }
        return $this;
    }

    public function removeSlot(TimetableSlot $slot): self
    {
        if ($this->slots->removeElement($slot)) {
            // set the owning side to null (unless already changed)
            if ($slot->getTimetableDay() === $this) {
                $slot->setTimetableDay(null);
            }
        }
        return $this;
    }
}