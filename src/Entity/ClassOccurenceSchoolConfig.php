<?php

namespace App\Entity;

use App\Repository\ClassOccurenceSchoolConfigRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Chaîne de promotion d'une occurrence de classe, propre à un établissement.
 * L'occurrence reste globale et partagée entre écoles ; chaque école définit
 * ici ses propres classes suivantes (ex. 2nde A → 1ere C, 1ere D) et son
 * niveau final.
 */
#[ORM\Entity(repositoryClass: ClassOccurenceSchoolConfigRepository::class)]
#[ORM\Table(name: 'class_occurence_school_config')]
#[ORM\UniqueConstraint(name: 'uniq_config_school_occurence', columns: ['school_id', 'class_occurence_id'])]
class ClassOccurenceSchoolConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: School::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?School $school = null;

    #[ORM\ManyToOne(targetEntity: ClassOccurence::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ClassOccurence $classOccurence = null;

    /**
     * Occurrences accessibles après promotion dans cet établissement
     * (ex. 2nde A → 1ere C, 1ere D). Côté propriétaire, unidirectionnel.
     */
    #[ORM\ManyToMany(targetEntity: ClassOccurence::class)]
    #[ORM\JoinTable(name: 'class_occurence_school_next',
        joinColumns: [new ORM\JoinColumn(name: 'config_id', referencedColumnName: 'id', onDelete: 'CASCADE')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'next_class_occurence_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    )]
    private Collection $nextOccurences;

    /** Classe terminale de l'établissement : ses élèves promus quittent l'école (aucune classe suivante requise). */
    #[ORM\Column(type: 'boolean')]
    private bool $isFinalLevel = false;

    public function __construct()
    {
        $this->nextOccurences = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSchool(): ?School
    {
        return $this->school;
    }

    public function setSchool(?School $school): self
    {
        $this->school = $school;

        return $this;
    }

    public function getClassOccurence(): ?ClassOccurence
    {
        return $this->classOccurence;
    }

    public function setClassOccurence(?ClassOccurence $classOccurence): self
    {
        $this->classOccurence = $classOccurence;

        return $this;
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
        }
        return $this;
    }

    public function removeNextOccurence(ClassOccurence $nextOccurence): self
    {
        $this->nextOccurences->removeElement($nextOccurence);

        return $this;
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
