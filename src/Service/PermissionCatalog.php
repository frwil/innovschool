<?php

namespace App\Service;

use App\Entity\Role;

/**
 * Source de vérité unique du catalogue des permissions et des ensembles
 * système (rôles profil, rôles legacy, ROLE_ADMIN). Utilisée par le sync,
 * l'UI des rôles et le branchement des contrôleurs.
 */
final class PermissionCatalog
{
    /** @var array<string, array<string, string>> catégorie => [code => libellé FR] */
    public const PERMISSIONS = [
        'users' => [
            'users.view' => 'Voir les utilisateurs',
            'users.create' => 'Créer des utilisateurs',
            'users.edit' => 'Modifier les utilisateurs',
            'users.delete' => 'Supprimer des utilisateurs',
            'users.import' => 'Importer des utilisateurs',
            'users.export' => 'Exporter des utilisateurs',
            'users.reset_password' => 'Réinitialiser les mots de passe',
        ],
        'roles' => [
            'roles.view' => 'Voir les rôles et leurs permissions',
            'roles.manage' => 'Créer, modifier, supprimer les rôles',
            'roles.assign' => 'Attribuer des rôles aux utilisateurs',
        ],
        'schools' => [
            'schools.view' => 'Voir les établissements',
            'schools.create' => 'Créer un établissement',
            'schools.edit' => 'Modifier un établissement',
            'schools.delete' => 'Supprimer un établissement',
        ],
        'periods' => [
            'periods.view' => 'Voir les périodes scolaires',
            'periods.create' => 'Créer une période scolaire',
            'periods.edit' => 'Modifier une période scolaire',
            'periods.delete' => 'Supprimer une période scolaire',
            'periods.set_default' => 'Définir la période active',
        ],
        'classes' => [
            'classes.view' => 'Voir les classes',
            'classes.create' => 'Créer des classes',
            'classes.edit' => 'Modifier des classes',
            'classes.delete' => 'Supprimer des classes',
            'classes.import' => 'Importer des classes',
            'classes.assign_subjects' => 'Gérer les affectations matières/classes',
        ],
        'subjects' => [
            'subjects.view' => 'Voir les matières',
            'subjects.create' => 'Créer des matières',
            'subjects.edit' => 'Modifier des matières',
            'subjects.delete' => 'Supprimer des matières',
            'subjects.assign' => 'Affecter matières, groupes et enseignants',
        ],
        'evaluations' => [
            'evaluations.view' => 'Voir la configuration et les bordereaux',
            'evaluations.create' => 'Créer évaluations et modules',
            'evaluations.edit' => 'Modifier évaluations et configurations',
            'evaluations.delete' => 'Supprimer évaluations et configurations',
            'evaluations.save' => 'Saisir et modifier les notes',
            'evaluations.cancel' => 'Annuler des saisies',
            'evaluations.templates' => 'Gérer les modèles d\'appréciation et barèmes',
            'evaluations.report_card' => 'Gérer les modèles de bulletins',
        ],
        'presence' => [
            'presence.view' => 'Voir les présences',
            'presence.save' => 'Pointer et modifier les présences',
            'presence.lock' => 'Verrouiller/déverrouiller les présences',
            'presence.delete' => 'Supprimer des pointages',
        ],
        'discipline' => [
            'discipline.view' => 'Voir la discipline',
            'discipline.save' => 'Enregistrer la discipline',
        ],
        'bulletins' => [
            'bulletins.view' => 'Voir les bulletins',
            'bulletins.generate' => 'Générer les bulletins PDF (en masse)',
        ],
        'timetable' => [
            'timetable.view' => 'Voir les emplois du temps',
            'timetable.manage' => 'Gérer les emplois du temps',
        ],
        'payments' => [
            'payments.view' => 'Voir paiements et modalités',
            'payments.create' => 'Créer modalités et réductions',
            'payments.edit' => 'Modifier modalités et paiements',
            'payments.delete' => 'Supprimer modalités et réductions',
            'payments.save' => 'Encaisser et modifier les paiements',
            'payments.cancel' => 'Annuler paiements et abonnements',
            'payments.validate' => 'Valider/refuser les réductions',
        ],
        'admission' => [
            'admission.view' => 'Voir inscription et admission',
            'admission.create' => 'Enregistrer une inscription',
            'admission.reports' => 'Rapports d\'admission',
            'admission.transfer' => 'Transferts massifs d\'élèves',
            'admission.finance' => 'Rapport financier d\'admission',
        ],
        'registration_card' => [
            'registration_card.view' => 'Voir les cartes scolaires',
            'registration_card.configure' => 'Configurer les cartes scolaires',
            'registration_card.print' => 'Imprimer les cartes scolaires',
            'registration_card.upload' => 'Importer les photos',
            'registration_card.import' => 'Importer les cartes (JSON)',
        ],
        'year_migration' => [
            'year_migration.view' => 'Voir la migration annuelle',
            'year_migration.execute' => 'Exécuter la migration annuelle',
            'year_migration.correct' => 'Corriger une migration',
            'year_migration.cancel' => 'Annuler une migration',
        ],
        'modules' => [
            'modules.manage' => 'Activer/désactiver les modules',
        ],
        'reports' => [
            'reports.view' => 'Voir les rapports généraux',
        ],
        'update' => [
            'update.view' => 'Voir l\'état des mises à jour',
            'system.update' => 'Appliquer une mise à jour du cœur',
        ],
        'licence' => [
            'licence.view' => 'Voir la licence',
            'licence.renew' => 'Renouveler la licence',
            'licence.payments' => 'Paiements de licence',
        ],
    ];

    /** @var array<string, string> */
    public const CATEGORY_LABELS = [
        'users' => 'Utilisateurs',
        'roles' => 'Droits d\'accès',
        'schools' => 'Établissements',
        'periods' => 'Périodes scolaires',
        'classes' => 'Classes',
        'subjects' => 'Matières',
        'evaluations' => 'Évaluations',
        'presence' => 'Présences',
        'discipline' => 'Discipline',
        'bulletins' => 'Bulletins',
        'timetable' => 'Emploi du temps',
        'payments' => 'Paiements',
        'admission' => 'Admissions',
        'registration_card' => 'Cartes scolaires',
        'year_migration' => 'Migration annuelle',
        'modules' => 'Modules',
        'reports' => 'Rapports',
        'update' => 'Mise à jour',
        'licence' => 'Licence',
    ];

    /** Rôles profil : hors assignation manuelle, sync écrasant (verrouillés). */
    public const PROFILE_ROLES = [
        'ROLE_STUDENT',
        'ROLE_TEACHER',
        'ROLE_TUTOR',
        'ROLE_FATHER',
        'ROLE_MOTHER',
        'ROLE_EMPLOYEE',
        'ROLE_GUEST',
    ];

    /** Rôles fantômes legacy : créés une fois, verrouillés. */
    public const LEGACY_ROLES = [
        'ROLE_VIEW_USER',
        'ROLE_EDIT_USER',
        'ROLE_ADD_USER',
    ];

    /** @var array<string, array{label: string, granted: list<string>, default: list<string>, mandatory: list<string>, dataScope: string}> */
    public const SYSTEM_ROLE_SETS = [
        'ROLE_TEACHER' => [
            'label' => 'Enseignant',
            'granted' => ['evaluations.view', 'evaluations.save', 'presence.view', 'discipline.view', 'bulletins.view', 'timetable.view'],
            'default' => ['evaluations.view', 'evaluations.save', 'presence.view', 'discipline.view', 'bulletins.view', 'timetable.view'],
            // L'EDT concerne directement les enseignants : non retirable.
            'mandatory' => ['timetable.view'],
            'dataScope' => Role::SCOPE_OWN,
        ],
        'ROLE_STUDENT' => [
            'label' => 'Élève',
            'granted' => ['bulletins.view'],
            'default' => ['bulletins.view'],
            'mandatory' => [],
            'dataScope' => Role::SCOPE_NONE,
        ],
        'ROLE_TUTOR' => [
            'label' => 'Tuteur',
            'granted' => ['bulletins.view'],
            'default' => ['bulletins.view'],
            'mandatory' => [],
            'dataScope' => Role::SCOPE_NONE,
        ],
        'ROLE_FATHER' => [
            'label' => 'Père',
            'granted' => ['bulletins.view'],
            'default' => ['bulletins.view'],
            'mandatory' => [],
            'dataScope' => Role::SCOPE_NONE,
        ],
        'ROLE_MOTHER' => [
            'label' => 'Mère',
            'granted' => ['bulletins.view'],
            'default' => ['bulletins.view'],
            'mandatory' => [],
            'dataScope' => Role::SCOPE_NONE,
        ],
        'ROLE_EMPLOYEE' => [
            'label' => 'Employé',
            'granted' => [],
            'default' => [],
            'mandatory' => [],
            'dataScope' => Role::SCOPE_NONE,
        ],
        'ROLE_GUEST' => [
            'label' => 'Invité',
            'granted' => [],
            'default' => [],
            'mandatory' => [],
            'dataScope' => Role::SCOPE_NONE,
        ],
        'ROLE_VIEW_USER' => [
            'label' => 'Consultation des utilisateurs (legacy)',
            'granted' => ['users.view'],
            'default' => ['users.view'],
            'mandatory' => [],
            'dataScope' => Role::SCOPE_NONE,
        ],
        'ROLE_EDIT_USER' => [
            'label' => 'Édition des utilisateurs (legacy)',
            'granted' => ['users.edit'],
            'default' => ['users.edit'],
            'mandatory' => [],
            'dataScope' => Role::SCOPE_NONE,
        ],
        'ROLE_ADD_USER' => [
            'label' => 'Création d\'utilisateurs (legacy)',
            'granted' => ['users.create'],
            'default' => ['users.create'],
            'mandatory' => [],
            'dataScope' => Role::SCOPE_NONE,
        ],
    ];

    /** Permissions exclues du seed ROLE_ADMIN (réservées au superadmin). */
    public const ADMIN_EXCLUDED_PREFIXES = ['schools.', 'periods.', 'year_migration.', 'licence.'];
    public const ADMIN_EXCLUDED = ['modules.manage', 'system.update'];

    /** @return array<string, array<string, string>> */
    public function getPermissions(): array
    {
        return self::PERMISSIONS;
    }

    /** @return list<string> */
    public function getAllPermissionNames(): array
    {
        $names = [];
        foreach (self::PERMISSIONS as $permissions) {
            foreach ($permissions as $name => $label) {
                $names[] = $name;
            }
        }

        return $names;
    }

    public function getPermissionLabel(string $name): ?string
    {
        foreach (self::PERMISSIONS as $permissions) {
            if (isset($permissions[$name])) {
                return $permissions[$name];
            }
        }

        return null;
    }

    public function getPermissionCategory(string $name): ?string
    {
        foreach (self::PERMISSIONS as $category => $permissions) {
            if (isset($permissions[$name])) {
                return $category;
            }
        }

        return null;
    }

    /** @return array<string, string> */
    public function getCategoryLabels(): array
    {
        return self::CATEGORY_LABELS;
    }

    public function isProfileRole(string $roleName): bool
    {
        return in_array($roleName, self::PROFILE_ROLES, true);
    }

    public function isLegacyRole(string $roleName): bool
    {
        return in_array($roleName, self::LEGACY_ROLES, true);
    }

    /** Rôle verrouillé : matrice en lecture seule (profils + fantômes). */
    public function isLockedRole(string $roleName): bool
    {
        return $this->isProfileRole($roleName) || $this->isLegacyRole($roleName);
    }

    /** @return array{label: string, granted: list<string>, default: list<string>, mandatory: list<string>, dataScope: string}|null */
    public function getSystemRoleSet(string $roleName): ?array
    {
        return self::SYSTEM_ROLE_SETS[$roleName] ?? null;
    }

    /** @return list<string> noms du catalogue que ROLE_ADMIN n'obtient pas au seed */
    public function getAdminExcludedNames(): array
    {
        $excluded = self::ADMIN_EXCLUDED;
        foreach ($this->getAllPermissionNames() as $name) {
            foreach (self::ADMIN_EXCLUDED_PREFIXES as $prefix) {
                if (str_starts_with($name, $prefix)) {
                    $excluded[] = $name;
                }
            }
        }

        return $excluded;
    }
}
