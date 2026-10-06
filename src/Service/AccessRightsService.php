<?php

namespace App\Service;

use App\Entity\Permission;
use App\Entity\Role;
use App\Entity\RolePermission;
use App\Entity\User;
use App\Repository\PermissionRepository;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Règles métier de la gestion des droits :
 * - un utilisateur ne peut pas modifier ses propres droits ;
 * - seul un superadmin peut modifier les droits d'un admin (ou d'un superadmin) ;
 * - un superadmin peut tout faire ;
 * - anti-escalation : on n'accorde que ce que l'on possède soi-même ;
 * - protection du dernier superadmin ;
 * - les rôles système ne sont modifiables/supprimables que par un superadmin ;
 * - les rôles verrouillés (profils, fantômes) sont modifiables par un
 *   superadmin (jamais supprimables) ;
 * - audit OperationLogger sur chaque application.
 */
final class AccessRightsService
{
    public function __construct(
        private PermissionRepository $permissionRepository,
        private RoleRepository $roleRepository,
        private UserRepository $userRepository,
        private PermissionCatalog $catalog,
        private OperationLogger $operationLogger,
    ) {
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Capacités de l'acteur
    // ─────────────────────────────────────────────────────────────────────────

    private function isSuperAdmin(User $actor): bool
    {
        return in_array('ROLE_SUPER_ADMIN', $actor->getRoles(), true);
    }

    /** Peut créer/modifier/supprimer des rôles (superadmin ou roles.manage). */
    public function canManageRights(User $actor): bool
    {
        return $this->isSuperAdmin($actor) || $this->actorHasPermission($actor, 'roles.manage');
    }

    /** Peut attribuer des rôles aux utilisateurs (superadmin ou roles.assign). */
    public function canAssignRoles(User $actor): bool
    {
        return $this->isSuperAdmin($actor) || $this->actorHasPermission($actor, 'roles.assign');
    }

    /**
     * Rôles que l'acteur peut éditer : un superadmin peut tout éditer, y
     * compris les profils verrouillés ; un non superadmin n'édite que les
     * rôles personnalisés (non verrouillés, non système).
     */
    public function canEditRole(User $actor, Role $role): bool
    {
        if ($this->isSuperAdmin($actor)) {
            return true;
        }

        return !$role->isLocked() && !$role->isSystem();
    }

    /**
     * Rôles attribuables via l'UI : hors profils (gérés automatiquement),
     * ROLE_SUPER_ADMIN réservé aux superadmins.
     *
     * @return list<Role>
     */
    public function getAssignableRoles(User $actor): array
    {
        try {
            $roles = $this->roleRepository->findAll();
        } catch (TableNotFoundException) {
            // Table access_role absente (seconde machine avant schema:update) :
            // aucun rôle assignable tant que le schéma n'est pas à jour.
            return [];
        }
        $assignable = [];
        foreach ($roles as $role) {
            if ($this->catalog->isProfileRole($role->getName())) {
                continue;
            }
            if ($role->getName() === 'ROLE_SUPER_ADMIN' && !$this->isSuperAdmin($actor)) {
                continue;
            }
            $assignable[] = $role;
        }

        usort($assignable, fn (Role $a, Role $b) => strcasecmp($a->getLabel(), $b->getLabel()));

        return $assignable;
    }

    /**
     * Noms des permissions que l'acteur possède ('*' = tout : superadmin ou
     * filet anti-lockout ROLE_ADMIN tant que la table access_role est vide).
     *
     * @return list<string>
     */
    public function getActorPermissionNames(User $actor): array
    {
        if ($this->isSuperAdmin($actor)) {
            return ['*'];
        }

        // Filet anti-lockout (même logique que PermissionVoter) : tant que le
        // seed n'a pas tourné, un admin garde ses droits historiques.
        if ($this->roleRepository->count([]) === 0 && in_array('ROLE_ADMIN', $actor->getRoles(), true)) {
            return ['*'];
        }

        return $this->permissionRepository->findNamesByRoleNames($actor->getRoles());
    }

    private function actorHasPermission(User $actor, string $permission): bool
    {
        $names = $this->getActorPermissionNames($actor);

        return in_array('*', $names, true) || in_array($permission, $names, true);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Assertions (lèvent AccessDeniedException avec message FR)
    // ─────────────────────────────────────────────────────────────────────────

    public function assertCanEditRole(User $actor, Role $role): void
    {
        if ($role->isLocked() && !$this->isSuperAdmin($actor)) {
            throw new AccessDeniedException('Les rôles gérés automatiquement par l\'application ne peuvent être modifiés que par un super-administrateur.');
        }
        if (!$this->isSuperAdmin($actor) && $role->isSystem()) {
            throw new AccessDeniedException('Les rôles système ne peuvent être modifiés que par un super-administrateur.');
        }
        if (!$this->canManageRights($actor)) {
            throw new AccessDeniedException('Vous n\'êtes pas autorisé à gérer les droits d\'accès.');
        }
    }

    public function assertCanDeleteRole(User $actor, Role $role): void
    {
        if ($role->isLocked() || $role->isSystem()) {
            throw new AccessDeniedException('Les rôles système ne peuvent pas être supprimés.');
        }
        if (!$this->canManageRights($actor)) {
            throw new AccessDeniedException('Vous n\'êtes pas autorisé à gérer les droits d\'accès.');
        }
        $carriers = $this->countRoleCarriers($role);
        if ($carriers > 0) {
            throw new AccessDeniedException(sprintf('Impossible de supprimer le rôle « %s » : il est attribué à %d utilisateur(s).', $role->getName(), $carriers));
        }
    }

    /**
     * Vérifie l'attribution de $roleNames à $target par $actor.
     *
     * @param list<string> $roleNames
     */
    public function assertCanAssignRoles(User $actor, User $target, array $roleNames): void
    {
        if (!$this->canAssignRoles($actor)) {
            throw new AccessDeniedException('Vous n\'êtes pas autorisé à attribuer des rôles.');
        }
        if ($target->getId() === $actor->getId()) {
            throw new AccessDeniedException('Vous ne pouvez pas modifier vos propres droits.');
        }
        if (!$this->isSuperAdmin($actor)) {
            if (in_array('ROLE_SUPER_ADMIN', $target->getRoles(), true) || in_array('ROLE_ADMIN', $target->getRoles(), true)) {
                throw new AccessDeniedException('Seul un super-administrateur peut modifier les droits d\'un administrateur.');
            }
            if (in_array('ROLE_SUPER_ADMIN', $roleNames, true)) {
                throw new AccessDeniedException('Seul un super-administrateur peut attribuer le rôle super-administrateur.');
            }
            if ($this->isLastSuperAdmin($target) && !in_array('ROLE_SUPER_ADMIN', $roleNames, true)) {
                throw new AccessDeniedException('Impossible de retirer les droits du dernier super-administrateur.');
            }

            // Anti-escalation : chaque rôle soumis ne doit conférer que des
            // permissions que l'acteur possède lui-même.
            $actorNames = $this->getActorPermissionNames($actor);
            foreach ($roleNames as $roleName) {
                $role = $this->roleRepository->findOneBy(['name' => $roleName]);
                if (!$role) {
                    continue;
                }
                foreach ($role->getGrantedPermissionNames() as $granted) {
                    if (!in_array('*', $actorNames, true) && !in_array($granted, $actorNames, true)) {
                        throw new AccessDeniedException(sprintf(
                            'Vous ne pouvez pas attribuer un rôle conférant des permissions que vous ne possédez pas vous-même (ex. : %s).',
                            $granted
                        ));
                    }
                }
            }
        }
    }

    public function isLastSuperAdmin(User $target): bool
    {
        if (!in_array('ROLE_SUPER_ADMIN', $target->getRoles(), true)) {
            return false;
        }

        return count($this->userRepository->findByRole('ROLE_SUPER_ADMIN')) <= 1;
    }

    /** Nombre d'utilisateurs porteurs du rôle. */
    public function countRoleCarriers(Role $role): int
    {
        return count($this->userRepository->findByRole($role->getName()));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Application (persist + flush + audit)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Applique les rôles soumis à $target :
     * (rôles actuels ∩ profils) ∪ soumis (profils filtrés) ∪ ROLE_USER.
     *
     * @param list<string>        $submittedRoleNames
     * @param array<string, mixed> $auditContext     ex. ['school' => id, 'period' => id]
     */
    public function applyRoles(User $actor, User $target, array $submittedRoleNames, array $auditContext = []): void
    {
        $this->assertCanAssignRoles($actor, $target, $submittedRoleNames);

        $current = array_values(array_diff($target->getRoles(), ['ROLE_USER']));
        $profiles = array_values(array_intersect($current, $this->catalog::PROFILE_ROLES));
        $submitted = array_values(array_filter(
            $submittedRoleNames,
            fn (string $name) => !$this->catalog->isProfileRole($name) && $name !== 'ROLE_USER'
        ));

        $target->setRoles(array_values(array_unique(array_merge($profiles, $submitted, ['ROLE_USER']))));

        $em = $this->userRepository->getEntityManager();
        $em->persist($target);
        $em->flush();

        $this->operationLogger->log(
            'Modification des rôles de l\'utilisateur ' . $target->getFullName(),
            'INFO',
            'User',
            $target->getId(),
            null,
            $auditContext
        );
    }

    /**
     * Applique la matrice : une ligne accordée par permission, obligatoire
     * seulement si accordée (invariant), anti-escalation pour les non
     * superadmins.
     *
     * @param list<string>        $grantedNames
     * @param list<string>        $mandatoryNames
     * @param array<string, mixed> $auditContext
     */
    public function applyRoleMatrix(User $actor, Role $role, array $grantedNames, array $mandatoryNames, array $auditContext = []): void
    {
        $this->assertCanEditRole($actor, $role);

        $actorNames = $this->getActorPermissionNames($actor);
        foreach ($grantedNames as $name) {
            if (!in_array('*', $actorNames, true) && !in_array($name, $actorNames, true)) {
                throw new AccessDeniedException(sprintf('Vous ne pouvez pas accorder la permission « %s » que vous ne possédez pas vous-même.', $name));
            }
        }

        $granted = array_values(array_unique($grantedNames));
        // Invariant : obligatoire ⇒ accordée.
        $mandatory = array_values(array_intersect(array_unique($mandatoryNames), $granted));

        // Retirer les lignes non accordées (orphanRemoval sur le rôle).
        foreach ($role->getRolePermissions()->toArray() as $row) {
            $name = $row->getPermission()?->getName();
            if ($name === null || !in_array($name, $granted, true)) {
                $role->removeRolePermission($row);
            }
        }

        // Upsert des lignes accordées.
        foreach ($granted as $name) {
            $permission = $this->permissionRepository->findOneBy(['name' => $name]);
            if (!$permission) {
                continue;
            }
            $row = $role->getPermissionRowByName($name);
            if (!$row) {
                $row = (new RolePermission())
                    ->setRole($role)
                    ->setPermission($permission);
                $role->addRolePermission($row);
            }
            $row->setIsMandatory(in_array($name, $mandatory, true));
        }

        $em = $this->roleRepository->getEntityManager();
        $em->persist($role);
        $em->flush();

        $this->operationLogger->log(
            'Modification des permissions du rôle ' . $role->getName(),
            'INFO',
            'Role',
            $role->getId(),
            null,
            $auditContext
        );
    }

    /**
     * Mémorise l'état courant comme valeurs par défaut (borné aux permissions
     * de l'acteur, anti-escalation sans erreur).
     *
     * @param array<string, mixed> $auditContext
     */
    public function saveDefaults(User $actor, Role $role, array $auditContext = []): void
    {
        $this->assertCanEditRole($actor, $role);

        $actorNames = $this->getActorPermissionNames($actor);
        foreach ($role->getRolePermissions() as $row) {
            $name = $row->getPermission()?->getName();
            if ($name === null) {
                continue;
            }
            if (!in_array('*', $actorNames, true) && !in_array($name, $actorNames, true)) {
                $row->setIsDefault(false);
                continue;
            }
            $row->setIsDefault(true);
        }

        $em = $this->roleRepository->getEntityManager();
        $em->persist($role);
        $em->flush();

        $this->operationLogger->log(
            'Mémorisation des permissions par défaut du rôle ' . $role->getName(),
            'INFO',
            'Role',
            $role->getId(),
            null,
            $auditContext
        );
    }

    /**
     * Réinitialise l'état courant aux valeurs par défaut ∪ obligatoires
     * (borné aux permissions de l'acteur).
     *
     * @param array<string, mixed> $auditContext
     */
    public function resetToDefaults(User $actor, Role $role, array $auditContext = []): void
    {
        $this->assertCanEditRole($actor, $role);

        $actorNames = $this->getActorPermissionNames($actor);
        $restore = [];
        foreach ($role->getRolePermissions() as $row) {
            $name = $row->getPermission()?->getName();
            if ($name === null) {
                continue;
            }
            if (!$row->isDefault() && !$row->isMandatory()) {
                continue;
            }
            if (!in_array('*', $actorNames, true) && !in_array($name, $actorNames, true)) {
                continue;
            }
            $restore[] = $name;
        }

        $this->applyRoleMatrix($actor, $role, $restore, array_values(array_map(
            fn (RolePermission $row): string => (string) $row->getPermission()?->getName(),
            array_filter($role->getRolePermissions()->toArray(), fn (RolePermission $row) => $row->isMandatory())
        )), $auditContext);

        $this->operationLogger->log(
            'Réinitialisation des permissions du rôle ' . $role->getName() . ' aux valeurs par défaut',
            'INFO',
            'Role',
            $role->getId(),
            null,
            $auditContext
        );
    }

    /** @return array<string, Permission> */
    public function getPermissionsByName(): array
    {
        $permissions = $this->permissionRepository->findAll();
        $byName = [];
        foreach ($permissions as $permission) {
            $byName[$permission->getName()] = $permission;
        }

        return $byName;
    }
}
