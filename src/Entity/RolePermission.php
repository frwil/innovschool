<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Lien rôle × permission (entité de jointure enrichie).
 * Une ligne = permission accordée. isDefault : fait partie du jeu restauré
 * par « Réinitialiser aux valeurs par défaut ». isMandatory : non retirable.
 * Invariants garantis à l'enregistrement : obligatoire ⇒ accordée ; défaut ⇒ accordé.
 */
#[ORM\Entity]
#[ORM\Table(name: 'access_role_permission')]
#[ORM\UniqueConstraint(name: 'uniq_role_permission', columns: ['role_id', 'permission_id'])]
class RolePermission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Role::class, inversedBy: 'rolePermissions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Role $role = null;

    #[ORM\ManyToOne(targetEntity: Permission::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Permission $permission = null;

    /** Fait partie des valeurs par défaut du rôle */
    #[ORM\Column(type: 'boolean')]
    private bool $isDefault = false;

    /** Permission obligatoire : non retirable via l'interface */
    #[ORM\Column(type: 'boolean')]
    private bool $isMandatory = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRole(): ?Role
    {
        return $this->role;
    }

    public function setRole(?Role $role): self
    {
        $this->role = $role;

        return $this;
    }

    public function getPermission(): ?Permission
    {
        return $this->permission;
    }

    public function setPermission(?Permission $permission): self
    {
        $this->permission = $permission;

        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): self
    {
        $this->isDefault = $isDefault;

        return $this;
    }

    public function isMandatory(): bool
    {
        return $this->isMandatory;
    }

    public function setIsMandatory(bool $isMandatory): self
    {
        $this->isMandatory = $isMandatory;

        return $this;
    }
}
