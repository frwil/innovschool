<?php

namespace App\Service;

use App\Entity\SchoolPeriod;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Vérification et suppression « smart » d'une période scolaire.
 *
 * - getLinkedDataCounts() : comptage des données liées à la période (affichage du check avant suppression).
 * - deletePeriodCascade() : suppression de la période et de tous ses objets liés, dans l'ordre
 *   imposé par les contraintes de clés étrangères (toutes en NO ACTION), sans toucher aux
 *   entités partagées (élèves, matières de référence, templates, occurrences de classes…).
 */
class SchoolPeriodDeletionService
{
    public function __construct(private EntityManagerInterface $em) {}

    // ─────────────────────────────────────────────────────────────────────────
    // COMPTAGES (vérification préalable)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, int> libellés français → nombre de lignes liées
     */
    public function getLinkedDataCounts(SchoolPeriod $period): array
    {
        $conn = $this->em->getConnection();
        $ids  = $this->collectIds($conn, (int) $period->getId());
        $p    = (int) $period->getId();

        $presences = 0;
        $presences += $this->countWhere($conn, 'student_class_attendance', 'student_class_id', $ids['sc']);
        $presences += $this->countWhere($conn, 'student_class_timetable_presence', 'student_class_id', $ids['sc']);
        $presences += $this->countWhere($conn, 'student_attendance', 'school_class_period_id', $ids['scp']);
        $presences += $this->countWhereAny($conn, 'school_class_attendance', [
            ['school_class_period_id', $ids['scp']],
            ['evaluation_id', $ids['se']],
        ]);

        $notes = $this->countWhereAny($conn, 'evaluation', [
            ['student_id', $ids['sc']],
            ['class_subject_module_id', $ids['csm']],
        ]);

        $paiements = 0;
        $paiements += count($ids['pm']);
        foreach (['school_class_admission_payments', 'modalities_subscriptions', 'admission_reductions'] as $table) {
            $paiements += $this->countWhereAny($conn, $table, [
                ['school_period_id', [$p]],
                ['school_class_period_id', $ids['scp']],
            ]);
        }

        return [
            'Notes'                 => $notes,
            'Inscriptions'          => count($ids['sc']),
            'Présences'             => $presences,
            'Classes'               => count($ids['scp']),
            'Matières'              => count($ids['scs']),
            'Modules'               => count($ids['csm']),
            'Paiements'             => $paiements,
            'Évaluations'           => count($ids['se']),
            'Groupes de matières'   => count($ids['sg']),
            'Emplois du temps'      => count($ids['tt']),
            'Logs de migration'     => (int) $conn->fetchOne(
                'SELECT COUNT(*) FROM migration_log WHERE source_period_id = :p OR target_period_id = :p',
                ['p' => $p]
            ),
        ];
    }

    public function hasData(SchoolPeriod $period): bool
    {
        return array_sum($this->getLinkedDataCounts($period)) > 0;
    }

    /**
     * Vrai si la période précédente contient des éléments que le wizard
     * de migration annuelle peut reconduire (classes, inscriptions, notes,
     * groupes de matières, modules, paiements).
     */
    public function hasMigrableData(SchoolPeriod $period): bool
    {
        $counts = $this->getLinkedDataCounts($period);

        return ($counts['Classes'] + $counts['Inscriptions'] + $counts['Notes']
            + $counts['Groupes de matières'] + $counts['Modules'] + $counts['Paiements']) > 0;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SUPPRESSION EN CASCADE
    // ─────────────────────────────────────────────────────────────────────────

    public function deletePeriodCascade(SchoolPeriod $period): void
    {
        $conn = $this->em->getConnection();
        $conn->beginTransaction();

        try {
            $ids = $this->collectIds($conn, (int) $period->getId());
            $p   = (int) $period->getId();

            // Ordre : enfants avant parents, imposé par les FK (toutes NO ACTION).
            // 1-2. Présences liées aux inscriptions
            $this->deleteWhere($conn, 'student_class_timetable_presence', 'student_class_id', $ids['sc']);
            $this->deleteWhere($conn, 'student_class_attendance', 'student_class_id', $ids['sc']);
            // 3-4. Autres présences/absences
            $this->deleteWhere($conn, 'student_attendance', 'school_class_period_id', $ids['scp']);
            $this->deleteWhereAny($conn, 'school_class_attendance', [
                ['school_class_period_id', $ids['scp']],
                ['evaluation_id', $ids['se']],
            ]);
            // 5. Notes
            $this->deleteWhereAny($conn, 'evaluation', [
                ['student_id', $ids['sc']],
                ['class_subject_module_id', $ids['csm']],
            ]);
            // 6. Matières non applicables
            $this->deleteWhere($conn, 'school_class_subject_evaluation_time_not_applicable', 'school_class_subject_id', $ids['scs']);
            // 7-8. Emplois du temps
            $this->deleteWhereAny($conn, 'timetable_slot', [
                ['timetable_day_id', $ids['day']],
                ['school_class_period_id', $ids['scp']],
            ]);
            $this->deleteWhere($conn, 'timetable_day', 'timetable_id', $ids['tt']);
            // 9. Évaluations de la période
            $this->deleteWhere($conn, 'school_evaluation', 'period_id', [$p]);
            // 10-12. Paiements et réductions
            foreach (['admission_reductions', 'modalities_subscriptions', 'school_class_admission_payments'] as $table) {
                $this->deleteWhereAny($conn, $table, [
                    ['school_period_id', [$p]],
                    ['school_class_period_id', $ids['scp']],
                ]);
            }
            // 13. Modules
            $this->deleteWhereAny($conn, 'class_subject_module', [
                ['period_id', [$p]],
                ['class_id', $ids['scp']],
            ]);
            // 14. Matières de classe
            $this->deleteWhere($conn, 'school_class_subject', 'school_class_period_id', $ids['scp']);
            // 15. Modalités de paiement
            $this->deleteWhereAny($conn, 'school_class_payment_modals', [
                ['school_period_id', [$p]],
                ['school_class_period_id', $ids['scp']],
            ]);
            // 16. Inscriptions
            $this->deleteWhere($conn, 'student_class', 'school_class_period_id', $ids['scp']);
            // 17-18. Emplois du temps et groupes de matières
            $this->deleteWhere($conn, 'timetable', 'period_id', [$p]);
            $this->deleteWhere($conn, 'subject_group', 'period_id', [$p]);
            // 19. Classes de la période
            $this->deleteWhere($conn, 'school_class_period', 'period_id', [$p]);
            // 20. Historique de migration
            $conn->executeStatement(
                'DELETE FROM migration_log WHERE source_period_id = :p OR target_period_id = :p',
                ['p' => $p]
            );
            // 21. La période elle-même
            $conn->executeStatement('DELETE FROM school_period WHERE id = :p', ['p' => $p]);

            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Collecte tous les ids liés à la période (utilisés par les comptages ET la suppression).
     */
    private function collectIds(Connection $conn, int $periodId): array
    {
        $scp = $conn->executeQuery(
            'SELECT id FROM school_class_period WHERE period_id = :p',
            ['p' => $periodId]
        )->fetchFirstColumn();

        $sc = $scp ? $conn->executeQuery(
            'SELECT id FROM student_class WHERE ' . $this->inList('school_class_period_id', $scp)
        )->fetchFirstColumn() : [];

        $csm = $conn->executeQuery(
            'SELECT id FROM class_subject_module WHERE period_id = :p OR ' . ($scp ? $this->inList('class_id', $scp) : '1 = 0'),
            ['p' => $periodId]
        )->fetchFirstColumn();

        $scs = $scp ? $conn->executeQuery(
            'SELECT id FROM school_class_subject WHERE ' . $this->inList('school_class_period_id', $scp)
        )->fetchFirstColumn() : [];

        $pm = $conn->executeQuery(
            'SELECT id FROM school_class_payment_modals WHERE school_period_id = :p OR ' . ($scp ? $this->inList('school_class_period_id', $scp) : '1 = 0'),
            ['p' => $periodId]
        )->fetchFirstColumn();

        $tt = $conn->executeQuery(
            'SELECT id FROM timetable WHERE period_id = :p',
            ['p' => $periodId]
        )->fetchFirstColumn();

        $day = $tt ? $conn->executeQuery(
            'SELECT id FROM timetable_day WHERE ' . $this->inList('timetable_id', $tt)
        )->fetchFirstColumn() : [];

        $se = $conn->executeQuery(
            'SELECT id FROM school_evaluation WHERE period_id = :p',
            ['p' => $periodId]
        )->fetchFirstColumn();

        $sg = $conn->executeQuery(
            'SELECT id FROM subject_group WHERE period_id = :p',
            ['p' => $periodId]
        )->fetchFirstColumn();

        return [
            'scp' => $scp, 'sc' => $sc, 'csm' => $csm, 'scs' => $scs,
            'pm' => $pm, 'tt' => $tt, 'day' => $day, 'se' => $se, 'sg' => $sg,
        ];
    }

    private function countWhere(Connection $conn, string $table, string $column, array $ids): int
    {
        if (!$ids) {
            return 0;
        }

        return (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM {$table} WHERE " . $this->inList($column, $ids)
        );
    }

    private function countWhereAny(Connection $conn, string $table, array $conditions): int
    {
        $parts = [];
        foreach ($conditions as [$column, $ids]) {
            if ($ids) {
                $parts[] = $this->inList($column, $ids);
            }
        }
        if (!$parts) {
            return 0;
        }

        return (int) $conn->fetchOne("SELECT COUNT(*) FROM {$table} WHERE " . implode(' OR ', $parts));
    }

    private function deleteWhere(Connection $conn, string $table, string $column, array $ids): void
    {
        if (!$ids) {
            return;
        }

        $conn->executeStatement("DELETE FROM {$table} WHERE " . $this->inList($column, $ids));
    }

    private function deleteWhereAny(Connection $conn, string $table, array $conditions): void
    {
        $parts = [];
        foreach ($conditions as [$column, $ids]) {
            if ($ids) {
                $parts[] = $this->inList($column, $ids);
            }
        }
        if (!$parts) {
            return;
        }

        $conn->executeStatement("DELETE FROM {$table} WHERE " . implode(' OR ', $parts));
    }

    private function inList(string $column, array $ids): string
    {
        return $column . ' IN (' . implode(',', array_map('intval', $ids)) . ')';
    }
}
