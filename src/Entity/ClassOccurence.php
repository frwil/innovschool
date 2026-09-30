<?php

namespace App\Entity;

use App\Repository\ClassOccurenceRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: ClassOccurenceRepository::class)]
class ClassOccurence
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $name;

    #[ORM\Column(type: 'string', length: 255)]
    private string $slug;

    #[ORM\ManyToOne(targetEntity: Classe::class, inversedBy: 'classeOccurences')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Classe $classe = null;

    #[ORM\OneToMany(mappedBy: 'classOccurence', targetEntity: SchoolClassPeriod::class)]
    private Collection $schoolClassPeriods;

    /**
     * Occurrences de classes accessibles après promotion (ex. 2nde A → 1ere C, 1ere D).
     * Côté propriétaire de la relation auto-référencée.
     */
    #[ORM\ManyToMany(targetEntity: self::class, inversedBy: 'previousOccurences')]
    #[ORM\JoinTable(name: 'class_occurence_next',
        joinColumns: [new ORM\JoinColumn(name: 'class_occurence_id', referencedColumnName: 'id', onDelete: 'CASCADE')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'next_class_occurence_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    )]
    private Collection $nextOccurences;

    /** Côté inverse : occurrences dont celle-ci est une classe suivante. */
    #[ORM\ManyToMany(targetEntity: self::class, mappedBy: 'nextOccurences')]
    private Collection $previousOccurences;

    /** Classe terminale : ses élèves promus quittent l'établissement (aucune classe suivante requise). */
    #[ORM\Column(type: 'boolean')]
    private bool $isFinalLevel = false;

    public function __construct()
    {
        $this->schoolClassPeriods = new ArrayCollection();
        $this->nextOccurences = new ArrayCollection();
        $this->previousOccurences = new ArrayCollection();
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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    public function getClasse(): ?Classe
    {
        return $this->classe;
    }

    public function setClasse(?Classe $classe): self
    {
        $this->classe = $classe;
        return $this;
    }

    public function getSchoolClassPeriods(): Collection
    {
        return $this->schoolClassPeriods;
    }

    /** @return Collection<int, ClassOccurence> */
    public function getNextOccurences(): Collection
    {
        return $this->nextOccurences;
    }

    public function addNextOccurence(ClassOccurence $nextOccurence): self
    {
        if (!$this->nextOccurences->contains($nextOccurence)) {
            $this->nextOccurences[] = $nextOccurence;
            // Synchronise le côté inverse
            if (!$nextOccurence->previousOccurences->contains($this)) {
                $nextOccurence->previousOccurences[] = $this;
            }
        }
        return $this;
    }

    public function removeNextOccurence(ClassOccurence $nextOccurence): self
    {
        if ($this->nextOccurences->removeElement($nextOccurence)) {
            if ($nextOccurence->previousOccurences->contains($this)) {
                $nextOccurence->previousOccurences->removeElement($this);
            }
        }
        return $this;
    }

    /** @return Collection<int, ClassOccurence> */
    public function getPreviousOccurences(): Collection
    {
        return $this->previousOccurences;
    }

    public function isFinalLevel(): bool
    {
        return $this->isFinalLevel;
    }

    public function setIsFinalLevel(bool $isFinalLevel): self
    {
        $this->isFinalLevel = $isFinalLevel;

        return $this;
    }
}