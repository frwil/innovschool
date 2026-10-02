<?php

namespace App\Entity;

use App\Repository\PermissionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Permission d'accès (ex. users.view), définie par le catalogue.
 * L'accord à un rôle passe par RolePermission.
 */
#[ORM\Entity(repositoryClass: PermissionRepository::class)]
#[ORM\Table(name: 'access_permission')]
#[ORM\Index(columns: ['category'], name: 'idx_access_permission_category')]
class Permission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /** Code unique, ex. users.view */
    #[ORM\Column(length: 100, unique: true)]
    private string $name = '';

    /** Libellé français */
    #[ORM\Column(length: 255)]
    private string $label = '';

    /** Catégorie (module), ex. users */
    #[ORM\Column(length: 50)]
    private string $category = '';

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

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;

        return $this;
    }
}
