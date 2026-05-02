<?php

namespace App\Entity;

use App\Repository\StudiesTypeRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: StudiesTypeRepository::class)]
#[ORM\HasLifecycleCallbacks]
class StudiesType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 255, unique: true)]
    private string $name;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(mappedBy: 'studiesType', targetEntity: SchoolStudyType::class, cascade: ['persist', 'remove'])]
    private Collection $schoolStudyTypes;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __construct()
    {
        $this->schoolStudyTypes = new ArrayCollection();
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getSchoolStudyTypes(): Collection
    {
        return $this->schoolStudyTypes;
    }

    public function addSchoolStudyType(SchoolStudyType $schoolStudyType): self
    {
        if (!$this->schoolStudyTypes->contains($schoolStudyType)) {
            $this->schoolStudyTypes[] = $schoolStudyType;
            $schoolStudyType->setStudiesType($this);
        }
        return $this;
    }

    public function removeSchoolStudyType(SchoolStudyType $schoolStudyType): self
    {
        if ($this->schoolStudyTypes->removeElement($schoolStudyType)) {
            if ($schoolStudyType->getStudiesType() === $this) {
                $schoolStudyType->setStudiesType(null);
            }
        }
        return $this;
    }
}