<?php

namespace App\Entity;

use App\Repository\SchoolEvaluationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SchoolEvaluationRepository::class)]
class SchoolEvaluation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'schoolEvaluations')]
    private ?SchoolEvaluationFrame $frame = null;

    #[ORM\ManyToOne(inversedBy: 'schoolEvaluations')]
    private ?SchoolEvaluationTime $time = null;

    #[ORM\ManyToOne(inversedBy: 'schoolEvaluations')]
    private ?SchoolPeriod $period = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $cretedAt = null;

    
    
    /**
     * @var Collection<int, SchoolClassAttendance>
     */
    #[ORM\OneToMany(targetEntity: SchoolClassAttendance::class, mappedBy: 'evaluation')]
    private Collection $schoolClassAttendances;

    public function __construct() 
    {
        $this->cretedAt = new \DateTime();
        $this->schoolClassAttendances = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFrame(): ?SchoolEvaluationFrame
    {
        return $this->frame;
    }

    public function setFrame(?SchoolEvaluationFrame $frame): self
    {
        $this->frame = $frame;

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

    public function getPeriod(): ?SchoolPeriod
    {
        return $this->period;
    }

    public function setPeriod(?SchoolPeriod $period): self
    {
        $this->period = $period;

        return $this;
    }

    public function getCretedAt(): ?\DateTimeInterface
    {
        return $this->cretedAt;
    }

    public function setCretedAt(\DateTimeInterface $cretedAt): self
    {
        $this->cretedAt = $cretedAt;

        return $this;
    }

    public function __toString(): string
    {
        return $this->frame->getName() . ' - ' . $this->time->getName() . ' - ' . $this->period->getName();
    }

    
    /**
     * @return Collection<int, SchoolClassAttendance>
     */
    public function getSchoolClassAttendances(): Collection
    {
        return $this->schoolClassAttendances;
    }

    public function addSchoolClassAttendance(SchoolClassAttendance $schoolClassAttendance): self
    {
        if (!$this->schoolClassAttendances->contains($schoolClassAttendance)) {
            $this->schoolClassAttendances->add($schoolClassAttendance);
            $schoolClassAttendance->setEvaluation($this);
        }

        return $this;
    }

    public function removeSchoolClassAttendance(SchoolClassAttendance $schoolClassAttendance): self
    {
        if ($this->schoolClassAttendances->removeElement($schoolClassAttendance)) {
            // set the owning side to null (unless already changed)
            if ($schoolClassAttendance->getEvaluation() === $this) {
                $schoolClassAttendance->setEvaluation(null);
            }
        }

        return $this;
    }

    public function getEvaluationName(): string
    {
        $name = $this->getTime()->getSlug();
        switch ($name) {
            case 'Premiere-sequence':
                return 'EVAL1';
            case 'Deuxieme-sequence':
                return 'EVAL2';
            case 'Troisieme-sequence':
                return 'EVAL3';
            case 'Quatrieme-sequence':
                return 'EVAL4';
            case 'Cinquieme-sequence':
                return 'EVAL5';
            case 'Sixieme-sequence':
                return 'EVAL6';
            case 'Septieme-sequence':
                return 'EVAL7';
            case 'Huitieme-sequence':
                return 'EVAL8';
            case 'Neuvieme-sequence':
                return 'EVAL9';
            default:
                return 'EVAL';
        }
    }

}
