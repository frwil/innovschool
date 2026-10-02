<?php

namespace App\Entity;

use App\Repository\RoleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Rôle d'accès modulable. Le lien avec User reste la string ROLE_*
 * dans User::$roles (JSON existant) : cette table décrit les permissions
 * accordées au rôle et son périmètre de données (dataScope).
 */
#[ORM\Entity(repositoryClass: RoleRepository::class)]
#[ORM\Table(name: 'access_role')]
class Role
{
    public const SCOPE_NONE = 'none';
    public const SCOPE_OWN = 'own';
    public const SCOPE_OWN_CLASSES = 'own_classes';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /** Nom du rôle, ex. ROLE_CAISSIER — la valeur stockée dans User::$roles */
    #[ORM\Column(length: 100, unique: true)]
    private string $name = '';

    /** Libellé français */
    #[ORM\Column(length: 255)]
    private string $label = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    /** Rôle piloté par le sync du catalogue (ADMIN, profils, fantômes) */
    #[ORM\Column(type: 'boolean')]
    private bool $isSystem = false;

    /** Rôle verrouillé : matrice en lecture seule (profils et fantômes) */
    #[ORM\Column(type: 'boolean')]
    private bool $locked = false;

    /** Périmètre de données : none | own | own_classes */
    #[ORM\Column(length: 20)]
    private string $dataScope = self::SCOPE_NONE;

    /**
     * @var Collection<int, RolePermission>
     */
    #[ORM\OneToMany(targetEntity: RolePermission::class, mappedBy: 'role', cascade: ['persist'], orphanRemoval: true)]
    private Collection $rolePermissions;

    public function __construct()
    {
        $this->rolePermissions = new ArrayCollection();
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

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function isSystem(): bool
    {
        return $this->isSystem;
    }

    public function setIsSystem(bool $isSystem): self
    {
        $this->isSystem = $isSystem;

        return $this;
    }

    public function isLocked(): bool
    {
        return $this->locked;
    }

    public function setLocked(bool $locked): self
    {
        $this->locked = $locked;

        return $this;
    }

    public function getDataScope(): string
    {
        return $this->dataScope;
    }

    public function setDataScope(string $dataScope): self
    {
        $this->dataScope = $dataScope;

        return $this;
    }

    /**
     * @return Collection<int, RolePermission>
     */
    public function getRolePermissions(): Collection
    {
        return $this->rolePermissions;
    }

    public function addRolePermission(RolePermission $rolePermission): self
    {
        if (!$this->rolePermissions->contains($rolePermission)) {
            $this->rolePermissions->add($rolePermission);
            $rolePermission->setRole($this);
        }

        return $this;
    }

    public function removeRolePermission(RolePermission $rolePermission): self
    {
        if ($this->rolePermissions->removeElement($rolePermission)) {
            // set the owning side to null (unless already changed)
            if ($rolePermission->getRole() === $this) {
                $rolePermission->setRole(null);
            }
        }

        return $this;
    }

    /** Ligne (permission accordée) de la permission nommée, null si absente. */
    public function getPermissionRowByName(string $permissionName): ?RolePermission
    {
        foreach ($this->rolePermissions as $rolePermission) {
            $permission = $rolePermission->getPermission();
            if ($permission && $permission->getName() === $permissionName) {
                return $rolePermission;
            }
        }

        return null;
    }

    /** Noms des permissions accordées au rôle. */
    public function getGrantedPermissionNames(): array
    {
        $names = [];
        foreach ($this->rolePermissions as $rolePermission) {
            $permission = $rolePermission->getPermission();
            if ($permission) {
                $names[] = $permission->getName();
            }
        }

        return $names;
    }
}
