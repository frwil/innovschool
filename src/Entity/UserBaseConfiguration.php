<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\UserBaseConfigurationsRepository;

#[ORM\Entity(repositoryClass: UserBaseConfigurationsRepository::class)]
#[ORM\Table(name: 'user_base_configuration')]
#[ORM\UniqueConstraint(name: 'unique_user_school', columns: ['user_id', 'school_id'])]
class UserBaseConfiguration
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'json')]
    private array $class_list = []; // Liste des classes accessibles

    #[ORM\Column(type: 'json')]
    private array $section_list = []; // Liste des sections accessibles

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'baseConfigurations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null; // Référence à l'utilisateur

    #[ORM\ManyToOne(targetEntity: School::class, inversedBy: 'baseConfigurations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?School $school = null; // Référence à l'école

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClassList(): array
    {
        return $this->class_list;
    }

    public function setClassList(array $class_list): self
    {
        $this->class_list = $class_list;

        return $this;
    }

    public function getSectionList(): array
    {
        return $this->section_list;
    }

    public function setSectionList(array $section_list): self
    {
        $this->section_list = $section_list;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
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
}
