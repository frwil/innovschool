<?php

namespace App\Entity;

use App\Repository\SectionCategorySubjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SectionCategorySubjectRepository::class)]
class SectionCategorySubject
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    #[ORM\ManyToOne(inversedBy: 'sectionCategorySubjects')]
    private ?Classe $sectionCategory = null;

    /**
     * @var Collection<int, SchoolClassSubject>
     */
    #[ORM\OneToMany(targetEntity: SchoolClassSubject::class, mappedBy: 'sectionCategorySubject')]
    private Collection $schoolClassSubjects;

    public function __construct()
    {
        $this->schoolClassSubjects = new ArrayCollection();
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

    public function getSectionCategory(): ?Classe
    {
        return $this->sectionCategory;
    }

    public function setSectionCategory(?Classe $sectionCategory): self
    {
        $this->sectionCategory = $sectionCategory;

        return $this;
    }

    /**
     * @return Collection<int, SchoolClassSubject>
     */
    public function getSchoolClassSubjects(): Collection
    {
        return $this->schoolClassSubjects;
    }

    public function addSchoolClassSubject(SchoolClassSubject $schoolClassSubject): self
    {
        if (!$this->schoolClassSubjects->contains($schoolClassSubject)) {
            $this->schoolClassSubjects->add($schoolClassSubject);
            // $schoolClassSubject->setSectionCategorySubject($this); // à commenter/supprimer si la méthode n'existe pas
        }

        return $this;
    }

    public function removeSchoolClassSubject(SchoolClassSubject $schoolClassSubject): self
    {
        if ($this->schoolClassSubjects->removeElement($schoolClassSubject)) {
            // if ($schoolClassSubject->getSectionCategorySubject() === $this) {
            //     $schoolClassSubject->setSectionCategorySubject(null);
            // }
        }

        return $this;
    }
}
