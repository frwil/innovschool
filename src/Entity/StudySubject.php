<?php

namespace App\Entity;

use App\Repository\StudySubjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StudySubjectRepository::class)]
#[ORM\HasLifecycleCallbacks]
class StudySubject
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $name = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'studySubject', targetEntity: SchoolClassSubject::class)]
    private Collection $schoolClassSubjects;

    #[ORM\OneToMany(mappedBy: 'subject', targetEntity: ClassSubjectModule::class)]
    private Collection $classSubjectModules;

    public function __construct()
    {
        $this->schoolClassSubjects = new ArrayCollection();
        $this->classSubjectModules = new ArrayCollection();
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

    public function getSlug(): ?string
    {
        return $this->slug;
    }
    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getSchoolClassSubjects(): Collection
    {
        return $this->schoolClassSubjects;
    }

    public function addSchoolClassSubject(SchoolClassSubject $schoolClassSubject): self
    {
        if (!$this->schoolClassSubjects->contains($schoolClassSubject)) {
            $this->schoolClassSubjects[] = $schoolClassSubject;
            $schoolClassSubject->setStudySubject($this);
        }
        return $this;
    }

    public function removeSchoolClassSubject(SchoolClassSubject $schoolClassSubject): self
    {
        if ($this->schoolClassSubjects->removeElement($schoolClassSubject)) {
            if ($schoolClassSubject->getStudySubject() === $this) {
                $schoolClassSubject->setStudySubject(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, \App\Entity\ClassSubjectModule>
     */
    public function getClassSubjectModules(): Collection
    {
        return $this->classSubjectModules;
    }

    public function addClassSubjectModule(ClassSubjectModule $module): self
    {
        if (!$this->classSubjectModules->contains($module)) {
            $this->classSubjectModules[] = $module;
            $module->setSubject($this);
        }
        return $this;
    }

    public function removeClassSubjectModule(ClassSubjectModule $module): self
    {
        if ($this->classSubjectModules->removeElement($module)) {
            if ($module->getSubject() === $this) {
                $module->setSubject(null);
            }
        }
        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
