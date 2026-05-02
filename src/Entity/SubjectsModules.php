<?php

namespace App\Entity;

use App\Repository\SubjectsModulesRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: SubjectsModulesRepository::class)]
#[ORM\Table(name: 'subjects_modules')]
#[ORM\UniqueConstraint(columns: ['module_name', 'module_slug'])]
#[ORM\HasLifecycleCallbacks]
class SubjectsModules
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private ?string $moduleName = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private ?string $moduleSlug = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(type: 'datetime')]
    
    private ?\DateTime $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'module', targetEntity: ClassSubjectModule::class, cascade: ['persist', 'remove'])]
    private Collection $classSubjectModules;

    public function __construct()
    {
        $this->classSubjectModules = new ArrayCollection();
    }

    // Getters and setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getModuleName(): ?string
    {
        return $this->moduleName;
    }

    public function setModuleName(string $moduleName): self
    {
        $this->moduleName = $moduleName;

        return $this;
    }

    public function getModuleSlug(): ?string
    {
        return $this->moduleSlug;
    }

    public function setModuleSlug(string $moduleSlug): self
    {
        $this->moduleSlug = $moduleSlug;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getClassSubjectModules(): Collection
    {
        return $this->classSubjectModules;
    }

    public function addClassSubjectModule(ClassSubjectModule $classSubjectModule): self
    {
        if (!$this->classSubjectModules->contains($classSubjectModule)) {
            $this->classSubjectModules[] = $classSubjectModule;
            $classSubjectModule->setModule($this);
        }

        return $this;
    }

    public function removeClassSubjectModule(ClassSubjectModule $classSubjectModule): self
    {
        if ($this->classSubjectModules->removeElement($classSubjectModule)) {
            // Set the owning side to null (unless already changed)
            if ($classSubjectModule->getModule() === $this) {
                $classSubjectModule->setModule(null);
            }
        }

        return $this;
    }
}