<?php

namespace App\Service;

use App\Entity\Permission;
use App\Entity\Role;
use App\Entity\RolePermission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Synchronise le catalogue (permissions + ensembles système) vers la base.
 * Idempotent : relançable à volonté (app:sync-permissions, app:pinstall).
 * - Permissions : upsert ; les absentes du catalogue sont supprimées (CASCADE).
 * - Profils (PROFILE_ROLES) : écrasement conforme au catalogue (verrouillés).
 * - Fantômes legacy : créés une fois, verrouillés.
 * - ROLE_ADMIN : seed « tout sauf superadmin » puis ajouts seuls, jamais de retrait.
 * - ROLE_SUPER_ADMIN : aucune ligne (bypass du voter).
 */
class PermissionSyncService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PermissionCatalog $catalog,
    ) {
    }

    public function sync(?SymfonyStyle $io = null, bool $dryRun = false): void
    {
        $permissionRepository = $this->entityManager->getRepository(Permission::class);
        $roleRepository = $this->entityManager->getRepository(Role::class);

        $createdPermissions = 0;
        $updatedPermissions = 0;
        $deletedPermissions = 0;
        $roleChanges = 0;

        // 1. Permissions du catalogue : upsert (map nom => entité, nouvelles comprises)
        $permissionsByName = [];
        foreach ($permissionRepository->findAll() as $permission) {
            $permissionsByName[$permission->getName()] = $permission;
        }

        foreach ($this->catalog->getPermissions() as $category => $permissions) {
            foreach ($permissions as $name => $label) {
                $permission = $permissionsByName[$name] ?? null;
                if (null === $permission) {
                    $permission = (new Permission())->setName($name)->setLabel($label)->setCategory($category);
                    if (!$dryRun) {
                        $this->entityManager->persist($permission);
                    }
                    $permissionsByName[$name] = $permission;
                    $createdPermissions++;
                } elseif ($permission->getLabel() !== $label || $permission->getCategory() !== $category) {
                    $permission->setLabel($label)->setCategory($category);
                    $updatedPermissions++;
                }
            }
        }

        // Rôles en base : map nom => entité (utilisée par les étapes suivantes)
        $rolesByName = [];
        foreach ($roleRepository->findAll() as $role) {
            $rolesByName[$role->getName()] = $role;
        }

        // 2. Permissions hors catalogue : suppression (les liens sont d'abord détachés)
        foreach ($permissionsByName as $name => $permission) {
            if (null !== $this->catalog->getPermissionLabel($name)) {
                continue;
            }
            foreach ($rolesByName as $role) {
                $row = $role->getPermissionRowByName($name);
                if (null !== $row) {
                    $role->removeRolePermission($row);
                }
            }
            if (!$dryRun) {
                $this->entityManager->remove($permission);
            }
            unset($permissionsByName[$name]);
            $deletedPermissions++;
        }

        // 3. Profils : écrasement conforme au catalogue
        foreach (PermissionCatalog::PROFILE_ROLES as $roleName) {
            $set = $this->catalog->getSystemRoleSet($roleName);
            if (null === $set) {
                continue;
            }
            $role = $rolesByName[$roleName] ?? null;
            if (null === $role) {
                $role = (new Role())->setName($roleName)->setIsSystem(true)->setLocked(true);
                if (!$dryRun) {
                    $this->entityManager->persist($role);
                }
                $rolesByName[$roleName] = $role;
            }
            $role->setLabel($set['label'])->setDataScope($set['dataScope'])->setIsSystem(true)->setLocked(true);
            $roleChanges += $this->applySet($role, $permissionsByName, $set, $dryRun);
        }

        // 4. Fantômes legacy : créés une fois, jamais écrasés
        foreach (PermissionCatalog::LEGACY_ROLES as $roleName) {
            if (isset($rolesByName[$roleName])) {
                continue;
            }
            $set = $this->catalog->getSystemRoleSet($roleName);
            if (null === $set) {
                continue;
            }
            $role = (new Role())
                ->setName($roleName)
                ->setLabel($set['label'])
                ->setDataScope($set['dataScope'])
                ->setIsSystem(true)
                ->setLocked(true);
            $this->applySet($role, $permissionsByName, $set, $dryRun);
            if (!$dryRun) {
                $this->entityManager->persist($role);
            }
            $rolesByName[$roleName] = $role;
            $roleChanges++;
        }

        // 5. ROLE_ADMIN : seed « tout sauf superadmin » puis ajouts seuls
        $adminRole = $rolesByName['ROLE_ADMIN'] ?? null;
        if (null === $adminRole) {
            $adminRole = (new Role())->setName('ROLE_ADMIN')->setLabel('Administrateur')->setIsSystem(true);
            if (!$dryRun) {
                $this->entityManager->persist($adminRole);
            }
            $rolesByName['ROLE_ADMIN'] = $adminRole;
        } else {
            $adminRole->setLabel('Administrateur')->setIsSystem(true);
        }
        $roleChanges += $this->applyAdminSet($adminRole, $permissionsByName, $dryRun);

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        if (null !== $io) {
            $io->success(sprintf(
                '%s : %d permission(s) créée(s), %d mise(s) à jour, %d supprimée(s), %d rôle(s) système traité(s) (%d changement(s) de liens).',
                $dryRun ? 'Simulation' : 'Synchronisation',
                $createdPermissions,
                $updatedPermissions,
                $deletedPermissions,
                count($rolesByName),
                $roleChanges,
            ));
        }
    }

    /**
     * Applique (écrasement) un ensemble catalogue à un rôle verrouillé.
     *
     * @param array<string, Permission> $permissionsByName
     * @param array{label: string, granted: list<string>, default: list<string>, mandatory: list<string>, dataScope: string} $set
     */
    private function applySet(Role $role, array $permissionsByName, array $set, bool $dryRun): int
    {
        $granted = array_flip($set['granted']);
        $defaults = array_flip($set['default']);
        $mandatory = array_flip($set['mandatory']);
        $changes = 0;

        foreach ($this->catalog->getAllPermissionNames() as $permissionName) {
            $row = $role->getPermissionRowByName($permissionName);
            if (!isset($granted[$permissionName])) {
                if (null !== $row) {
                    $role->removeRolePermission($row);
                    $changes++;
                }
                continue;
            }
            if (null === $row) {
                $permission = $permissionsByName[$permissionName] ?? null;
                if (null === $permission) {
                    continue;
                }
                $role->addRolePermission((new RolePermission())->setPermission($permission));
                $changes++;
                $row = $role->getPermissionRowByName($permissionName);
            }
            $isDefault = isset($defaults[$permissionName]);
            $isMandatory = isset($mandatory[$permissionName]);
            if ($row->isDefault() !== $isDefault || $row->isMandatory() !== $isMandatory) {
                $row->setIsDefault($isDefault)->setIsMandatory($isMandatory);
                $changes++;
            }
        }

        return $changes;
    }

    /**
     * Seed/add-only de ROLE_ADMIN : à la création tout est accordé+défaut sauf
     * les exclusions ; les syncs suivants ajoutent les nouvelles permissions
     * du catalogue sans jamais retirer.
     *
     * @param array<string, Permission> $permissionsByName
     */
    private function applyAdminSet(Role $role, array $permissionsByName, bool $dryRun): int
    {
        $excluded = array_flip($this->catalog->getAdminExcludedNames());
        $existingNames = array_flip($role->getGrantedPermissionNames());
        $changes = 0;

        foreach ($this->catalog->getAllPermissionNames() as $permissionName) {
            if (isset($excluded[$permissionName]) || isset($existingNames[$permissionName])) {
                continue;
            }
            $permission = $permissionsByName[$permissionName] ?? null;
            if (null === $permission) {
                continue;
            }
            $role->addRolePermission(
                (new RolePermission())->setPermission($permission)->setIsDefault(true)
            );
            $changes++;
        }

        return $changes;
    }
}
