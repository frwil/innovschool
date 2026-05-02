<?php

namespace App\Entity;

use App\Repository\SchoolEvaluationFrameRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\SchoolEvaluationTime;

#[ORM\Entity(repositoryClass: SchoolEvaluationFrameRepository::class)]
class SchoolEvaluationFrame
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    #[ORM\Column(length: 20)]
    private ?string $shortName = null;

    /**
     * @var Collection<int, SchoolEvaluation>
     */
    #[ORM\OneToMany(targetEntity: SchoolEvaluation::class, mappedBy: 'frame')]
    private Collection $schoolEvaluations;

    #[ORM\OneToMany(mappedBy: 'evaluationFrame', targetEntity: SchoolEvaluationTime::class, cascade: ['persist', 'remove'])]
    private Collection $evaluationTimes;

    public function __construct()
    {
        $this->schoolEvaluations = new ArrayCollection();
        $this->evaluationTimes = new ArrayCollection();
        if ($this->shortName === null && $this->name !== null) {
            $this->shortName = $this->generateInitials($this->name);
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        // Générer les initiales si shortName n'est pas déjà défini
        if ($this->shortName === null) {
            $this->shortName = $this->generateInitials($name);
        }
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getShortName(): ?string
    {
        return $this->shortName;
    }

    public function setShortName(string $shortName): self
    {
        $this->shortName = $shortName;
        return $this;
    }

    /**
     * @return Collection<int, SchoolEvaluation>
     */
    public function getSchoolEvaluations(): Collection
    {
        return $this->schoolEvaluations;
    }

    public function addSchoolEvaluation(SchoolEvaluation $schoolEvaluation): self
    {
        if (!$this->schoolEvaluations->contains($schoolEvaluation)) {
            $this->schoolEvaluations[] = $schoolEvaluation;
            $schoolEvaluation->setFrame($this);
        }

        return $this;
    }

    public function removeSchoolEvaluation(SchoolEvaluation $schoolEvaluation): self
    {
        if ($this->schoolEvaluations->removeElement($schoolEvaluation)) {
            // set the owning side to null (unless already changed)
            if ($schoolEvaluation->getFrame() === $this) {
                $schoolEvaluation->setFrame(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, SchoolEvaluationTime>
     */
    public function getEvaluationTimes(): Collection
    {
        return $this->evaluationTimes;
    }

    public function addEvaluationTime(SchoolEvaluationTime $evaluationTime): self
    {
        if (!$this->evaluationTimes->contains($evaluationTime)) {
            $this->evaluationTimes[] = $evaluationTime;
            $evaluationTime->setEvaluationFrame($this);
        }

        return $this;
    }

    public function removeEvaluationTime(SchoolEvaluationTime $evaluationTime): self
    {
        if ($this->evaluationTimes->removeElement($evaluationTime)) {
            // set the owning side to null (unless already changed)
            if ($evaluationTime->getEvaluationFrame() === $this) {
                $evaluationTime->setEvaluationFrame(null);
            }
        }

        return $this;
    }

    public function __toString()
    {
        return $this->name;
    }

    private function generateInitials(string $name): string
    {
        $words = preg_split('/\s+/', trim($name));
        $initials = '';
        foreach ($words as $word) {
            if (isset($word[0])) {
                $initials .= strtoupper($word[0]);
            }
        }
        return $initials;
    }
}
