<?php

namespace App\Entity;

use App\Repository\ClasseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClasseRepository::class)]
// Annotation UniqueEntity supprimée
class Classe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $name = null;

    #[ORM\ManyToOne(inversedBy: 'classes')]
    private ?StudyLevel $studyLevel = null;

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

   
    #[ORM\OneToMany(mappedBy: 'classe', targetEntity: SchoolClassNumberingType::class)]
    private Collection $schoolClassNumberingTypes;

    #[ORM\OneToMany(mappedBy: 'classe', targetEntity: ClassOccurence::class)]
    private Collection $classeOccurences;

    /**
     * @var Collection<int, SectionCategorySubject>
     */
    #[ORM\OneToMany(mappedBy: 'sectionCategory', targetEntity: SectionCategorySubject::class)]
    private Collection $sectionCategorySubjects;


    public function __construct()
    {
        $this->schoolClassNumberingTypes = new ArrayCollection();
        $this->classeOccurences = new ArrayCollection();
        $this->sectionCategorySubjects = new ArrayCollection();
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

    public function getStudyLevel(): ?StudyLevel
    {
        return $this->studyLevel;
    }
    

    public function setStudyLevel(?StudyLevel $studyLevel): self
    {
        $this->studyLevel = $studyLevel;

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

    public function getClassOccurences(): Collection
    {
        return $this->classeOccurences;
    }

    public function addClassOccurence(ClassOccurence $classeOccurence): self
    {
        if (!$this->classeOccurences->contains($classeOccurence)) {
            $this->classeOccurences[] = $classeOccurence;
            $classeOccurence->setClasse($this);
        }
        return $this;
    }

    public function removeClassOccurence(ClassOccurence $classeOccurence): self
    {
        if ($this->classeOccurences->removeElement($classeOccurence)) {
            // set the owning side to null (unless already changed)
            if ($classeOccurence->getClasse() === $this) {
                $classeOccurence->setClasse(null);
            }
        }
        return $this;
    }

    public function getSchoolClassNumberingTypes(): Collection
    {
        return $this->schoolClassNumberingTypes;
    }

    public function addSchoolClassNumberingType(SchoolClassNumberingType $numberingType): self
    {
        if (!$this->schoolClassNumberingTypes->contains($numberingType)) {
            $this->schoolClassNumberingTypes[] = $numberingType;
            $numberingType->setClasse($this);
        }
        return $this;
    }

    public function removeSchoolClassNumberingType(SchoolClassNumberingType $numberingType): self
    {
        if ($this->schoolClassNumberingTypes->removeElement($numberingType)) {
            if ($numberingType->getClasse() === $this) {
                $numberingType->setClasse(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, SectionCategorySubject>
     */
    public function getSectionCategorySubjects(): Collection
    {
        return $this->sectionCategorySubjects;
    }

    public function addSectionCategorySubject(SectionCategorySubject $sectionCategorySubject): self
    {
        if (!$this->sectionCategorySubjects->contains($sectionCategorySubject)) {
            $this->sectionCategorySubjects[] = $sectionCategorySubject;
            $sectionCategorySubject->setSectionCategory($this);
        }
        return $this;
    }

    public function removeSectionCategorySubject(SectionCategorySubject $sectionCategorySubject): self
    {
        if ($this->sectionCategorySubjects->removeElement($sectionCategorySubject)) {
            if ($sectionCategorySubject->getSectionCategory() === $this) {
                $sectionCategorySubject->setSectionCategory(null);
            }
        }
        return $this;
    }
}
