<?php

namespace App\Entity;

use App\Repository\EvaluationAppreciationTemplateRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: EvaluationAppreciationTemplateRepository::class)]
class EvaluationAppreciationTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private string $name;

    #[ORM\Column(type: 'boolean')]
    private bool $enabled = false;

    #[ORM\OneToMany(mappedBy: 'evaluationAppreciationTemplate', targetEntity: School::class)]
    private Collection $schools;

    #[ORM\OneToMany(mappedBy: 'evaluationAppreciationTemplate', targetEntity: EvaluationAppreciationBareme::class, cascade: ['persist', 'remove'])]
    private Collection $baremes;

    #[ORM\OneToMany(mappedBy: 'evaluationAppreciationTemplate', targetEntity: SchoolClassPeriod::class)]
    private Collection $schoolClassPeriods;

    public function __construct()
    {
        $this->schools = new ArrayCollection();
        $this->baremes = new ArrayCollection();
        $this->schoolClassPeriods = new ArrayCollection();
        $this->enabled = false;
        // ...autres initialisations si besoin...
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function getSchools(): Collection
    {
        return $this->schools;
    }

    public function addSchool(School $school): self
    {
        if (!$this->schools->contains($school)) {
            $this->schools[] = $school;
            $school->setEvaluationAppreciationTemplate($this);
        }
        return $this;
    }

    public function removeSchool(School $school): self
    {
        if ($this->schools->removeElement($school)) {
            if ($school->getEvaluationAppreciationTemplate() === $this) {
                $school->setEvaluationAppreciationTemplate(null);
            }
        }
        return $this;
    }

    public function getBaremes(): Collection
    {
        return $this->baremes;
    }

    public function addBareme(EvaluationAppreciationBareme $bareme): self
    {
        if (!$this->baremes->contains($bareme)) {
            $this->baremes[] = $bareme;
            $bareme->setEvaluationAppreciationTemplate($this);
        }
        return $this;
    }

    public function removeBareme(EvaluationAppreciationBareme $bareme): self
    {
        if ($this->baremes->removeElement($bareme)) {
            if ($bareme->getEvaluationAppreciationTemplate() === $this) {
                $bareme->setEvaluationAppreciationTemplate(null);
            }
        }
        return $this;
    }

    public function getSchoolClasses(): Collection
    {
        return $this->schoolClassPeriods;
    }

    public function addSchoolClass(SchoolClassPeriod $schoolClassPeriod): self
    {
        if (!$this->schoolClassPeriods->contains($schoolClassPeriod)) {
            $this->schoolClassPeriods[] = $schoolClassPeriod;
            $schoolClassPeriod->setEvaluationAppreciationTemplate($this);
        }
        return $this;
    }

    public function removeSchoolClass(SchoolClassPeriod $schoolClassPeriod): self
    {
        if ($this->schoolClassPeriods->removeElement($schoolClassPeriod)) {
            if ($schoolClassPeriod->getEvaluationAppreciationTemplate() === $this) {
                $schoolClassPeriod->setEvaluationAppreciationTemplate(null);
            }
        }
        return $this;
    }
}