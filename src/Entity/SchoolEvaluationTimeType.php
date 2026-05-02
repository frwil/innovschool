<?php

namespace App\Entity;

use App\Repository\SchoolEvaluationTimeTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SchoolEvaluationTimeTypeRepository::class)]
#[ORM\HasLifecycleCallbacks]
class SchoolEvaluationTimeType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private ?string $name = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'type', targetEntity: SchoolEvaluationTime::class, cascade: ['persist', 'remove'])]
    private Collection $evaluationTimes;

    public function __construct()
    {
        $this->evaluationTimes = new ArrayCollection();
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

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function setCreatedAt(): void
    {
        $this->createdAt = new \DateTime();
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAt(): void
    {
        $this->updatedAt = new \DateTime();
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
            $evaluationTime->setType($this);
        }

        return $this;
    }

    public function removeEvaluationTime(SchoolEvaluationTime $evaluationTime): self
    {
        if ($this->evaluationTimes->removeElement($evaluationTime)) {
            // Set the owning side to null (unless already changed)
            if ($evaluationTime->getType() === $this) {
                $evaluationTime->setType(null);
            }
        }

        return $this;
    }
}