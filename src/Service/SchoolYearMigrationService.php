<?php

namespace App\Service;

use App\Entity\ClassSubjectModule;
use App\Entity\Evaluation;
use App\Entity\MigrationLog;
use App\Entity\School;
use App\Entity\SchoolClassPaymentModal;
use App\Entity\SchoolClassPeriod;
use App\Entity\SchoolClassSubject;
use App\Entity\SchoolPeriod;
use App\Entity\StudentClass;
use App\Entity\SubjectGroup;
use Doctrine\ORM\EntityManagerInterface;

class SchoolYearMigrationService
{
    public function __construct(private EntityManagerInterface $em) {}

    // ─────────────────────────────────────────────────────────────────────────
    // PREVIEW
    // ─────────────────────────────────────────────────────────────────────────

    public function previewStudentMigration(
        School $school,
        SchoolPeriod $sourcePeriod,
        float $passingGrade
    ): array {
        $results = [];
        $sourceClasses = $this->em->getRepository(SchoolClassPeriod::class)->findBy([
            'school' => $school,
            'period' => $sourcePeriod,
        ]);

        foreach ($sourceClasses as $scp) {
            $eligible = [];
            $nonEligible = [];
            foreach ($scp->getStudentClasses() as $studentClass) {
                $avg  = $this->calculateStudentAverage($studentClass, $scp);
                $stat = [
                    'student'  => $studentClass->getStudent(),
                    'average'  => $avg,
                    'eligible' => $avg !== null && $avg >= $passingGrade,
                ];
                $stat['eligible'] ? $eligible[] = $stat : $nonEligible[] = $stat;
            }
            $results[] = [
                'schoolClassPeriod' => $scp,
                'className'         => $scp->getClassOccurence()?->getName() ?? '—',
                'total'             => count($eligible) + count($nonEligible),
                'eligible'          => count($eligible),
                'nonEligible'       => count($nonEligible),
                'studentStats'      => array_merge($eligible, $nonEligible),
            ];
        }

        return $results;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EXECUTE (remplace migrateConfiguration + migrateStudents)
    // Enveloppe tout dans une transaction et crée le MigrationLog.
    // ─────────────────────────────────────────────────────────────────────────

    public function executeMigration(
        School $school,
        SchoolPeriod $sourcePeriod,
        SchoolPeriod $targetPeriod,
        float $passingGrade,
        array $options,
        array $classMapping,
        string $executedBy
    ): MigrationLog {
        $conn = $this->em->getConnection();
        $conn->beginTransaction();

        try {
            $created = [
                'subjectGroups'      => [],
                'schoolClassPeriods' => [],
                'schoolClassSubjects'=> [],
                'classSubjectModules'=> [],
                'paymentModals'      => [],
                'studentClasses'     => [],
            ];

            // ── Configuration ────────────────────────────────────────────────
            $configSummary = [
                'subject_groups'  => 0,
                'classes'         => 0,
                'subjects'        => 0,
                'modules'         => 0,
                'payment_modals'  => 0,
            ];

            $groupMap = [];
            if ($options['subject_groups'] ?? true) {
                [$groupMap, $groupEntities] = $this->cloneSubjectGroups($school, $sourcePeriod, $targetPeriod);
                $configSummary['subject_groups'] = count($groupMap);
            }

            $classMap = [];
            if ($options['classes'] ?? true) {
                [$classMap, $classEntities] = $this->cloneClasses($school, $sourcePeriod, $targetPeriod);
                $configSummary['classes'] = count($classMap);
            }

            $subjectEntities = [];
            if ($options['subjects'] ?? true) {
                [$count, $subjectEntities] = $this->cloneSubjects($classMap, $groupMap);
                $configSummary['subjects'] = $count;
            }

            $moduleEntities = [];
            if ($options['modules'] ?? true) {
                [$count, $moduleEntities] = $this->cloneModules($school, $sourcePeriod, $targetPeriod, $classMap);
                $configSummary['modules'] = $count;
            }

            $paymentEntities = [];
            if ($options['payment_modals'] ?? true) {
                [$count, $paymentEntities] = $this->clonePaymentModals($school, $sourcePeriod, $targetPeriod, $classMap);
                $configSummary['payment_modals'] = $count;
            }

            // Premier flush → IDs disponibles pour config
            $this->em->flush();

            foreach ($groupEntities   ?? [] as $e) { $created['subjectGroups'][]       = $e->getId(); }
            foreach ($classEntities   ?? [] as $e) { $created['schoolClassPeriods'][]   = $e->getId(); }
            foreach ($subjectEntities         as $e) { $created['schoolClassSubjects'][] = $e->getId(); }
            foreach ($moduleEntities          as $e) { $created['classSubjectModules'][] = $e->getId(); }
            foreach ($paymentEntities         as $e) { $created['paymentModals'][]       = $e->getId(); }

            // ── Élèves ───────────────────────────────────────────────────────
            $studentStats    = ['promoted' => 0, 'repeated' => 0, 'skipped' => 0];
            $studentEntities = [];

            $sourceClasses      = $this->em->getRepository(SchoolClassPeriod::class)->findBy([
                'school' => $school, 'period' => $sourcePeriod,
            ]);
            $repeaterTargetMap  = $this->buildRepeaterTargetMap($school, $targetPeriod);

            foreach ($sourceClasses as $sourceSCP) {
                $sourceId       = $sourceSCP->getId();
                $promotedTarget = isset($classMapping[$sourceId])
                    ? $this->em->getRepository(SchoolClassPeriod::class)->find($classMapping[$sourceId])
                    : null;
                $occId          = $sourceSCP->getClassOccurence()?->getId();
                $repeaterTarget = $occId ? ($repeaterTargetMap[$occId] ?? null) : null;

                foreach ($sourceSCP->getStudentClasses() as $studentClass) {
                    $avg        = $this->calculateStudentAverage($studentClass, $sourceSCP);
                    $isEligible = $avg !== null && $avg >= $passingGrade;

                    if ($isEligible && $promotedTarget) {
                        $sc = $this->enrollStudent($studentClass->getStudent(), $promotedTarget);
                        if ($sc) { $studentEntities[] = $sc; }
                        $studentStats['promoted']++;
                    } elseif (!$isEligible && $repeaterTarget) {
                        $sc = $this->enrollStudent($studentClass->getStudent(), $repeaterTarget);
                        if ($sc) { $studentEntities[] = $sc; }
                        $studentStats['repeated']++;
                    } else {
                        $studentStats['skipped']++;
                    }
                }
            }

            // Deuxième flush → IDs élèves disponibles
            $this->em->flush();

            foreach ($studentEntities as $e) { $created['studentClasses'][] = $e->getId(); }

            // ── MigrationLog ─────────────────────────────────────────────────
            $log = new MigrationLog();
            $log->setSchool($school)
                ->setSourcePeriod($sourcePeriod)
                ->setTargetPeriod($targetPeriod)
                ->setPassingGrade($passingGrade)
                ->setOptions($options)
                ->setCreatedIds($created)
                ->setConfigSummary($configSummary)
                ->setStudentStats($studentStats)
                ->setExecutedBy($executedBy)
                ->setStatus('executed');

            $this->em->persist($log);
            $this->em->flush();

            $conn->commit();

            return $log;

        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ÉTAT D'UNE MIGRATION
    // ─────────────────────────────────────────────────────────────────────────

    public function checkMigrationState(MigrationLog $log): array
    {
        $studentClassIds  = $log->getCreatedIds()['studentClasses'] ?? [];
        $evaluationCount  = 0;
        $lockedIds        = [];
        $unlockableIds    = [];

        foreach ($studentClassIds as $scId) {
            $sc = $this->em->getRepository(StudentClass::class)->find($scId);
            if (!$sc) { continue; }

            $evals = $sc->getEvaluations()->count();
            if ($evals > 0) {
                $evaluationCount += $evals;
                $lockedIds[]      = $scId;
            } else {
                $unlockableIds[]  = $scId;
            }
        }

        $canCancel = $evaluationCount === 0;

        return [
            'canCancel'        => $canCancel,
            'canCorrect'       => !$canCancel,   // correction partielle si notes saisies
            'evaluationCount'  => $evaluationCount,
            'lockedIds'        => $lockedIds,     // élèves avec notes → intouchables
            'unlockableIds'    => $unlockableIds, // élèves sans notes → modifiables
            'totalStudents'    => count($studentClassIds),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ANNULATION COMPLÈTE (seulement si 0 notes)
    // ─────────────────────────────────────────────────────────────────────────

    public function cancelMigration(MigrationLog $log): void
    {
        $state = $this->checkMigrationState($log);
        if (!$state['canCancel']) {
            throw new \LogicException('Annulation impossible : des notes ont été saisies.');
        }

        $ids     = $log->getCreatedIds();
        $conn    = $this->em->getConnection();
        $conn->beginTransaction();

        try {
            // Ordre : enfants avant parents pour respecter les FK
            $this->deleteByIds(StudentClass::class,           $ids['studentClasses']      ?? []);
            $this->deleteByIds(ClassSubjectModule::class,     $ids['classSubjectModules'] ?? []);
            $this->deleteByIds(SchoolClassSubject::class,     $ids['schoolClassSubjects'] ?? []);
            $this->deleteByIds(SchoolClassPaymentModal::class,$ids['paymentModals']       ?? []);
            $this->deleteByIds(SchoolClassPeriod::class,      $ids['schoolClassPeriods']  ?? []);
            $this->deleteByIds(SubjectGroup::class,           $ids['subjectGroups']       ?? []);

            $this->em->flush();

            $log->setStatus('cancelled');
            $this->em->flush();

            $conn->commit();

        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CORRECTION PARTIELLE (quand des notes existent déjà)
    // Recalcule qui devrait être promu/redoublant avec la nouvelle moyenne,
    // et applique les changements uniquement sur les élèves sans notes.
    // Retourne un rapport des changements.
    // ─────────────────────────────────────────────────────────────────────────

    public function previewCorrection(MigrationLog $log, float $newPassingGrade): array
    {
        $sourcePeriod = $log->getSourcePeriod();
        $targetPeriod = $log->getTargetPeriod();
        $school       = $log->getSchool();
        $oldGrade     = $log->getPassingGrade();

        $createdScIds      = array_flip($log->getCreatedIds()['studentClasses'] ?? []);
        $state             = $this->checkMigrationState($log);
        $lockedSet         = array_flip($state['lockedIds']);

        $sourceClasses = $this->em->getRepository(SchoolClassPeriod::class)->findBy([
            'school' => $school, 'period' => $sourcePeriod,
        ]);
        $repeaterTargetMap = $this->buildRepeaterTargetMap($school, $targetPeriod);

        $changes = [
            'toPromote'   => [], // redoublants → promus (safe si pas de notes)
            'toDemote'    => [], // promus → redoublants (safe si pas de notes)
            'toAdd'       => [], // ignorés → à inscrire
            'locked'      => [], // ont des notes, aucun changement possible
        ];

        foreach ($sourceClasses as $sourceSCP) {
            foreach ($sourceSCP->getStudentClasses() as $sourceStudentClass) {
                $student      = $sourceStudentClass->getStudent();
                $avg          = $this->calculateStudentAverage($sourceStudentClass, $sourceSCP);
                $wasEligible  = $avg !== null && $avg >= $oldGrade;
                $nowEligible  = $avg !== null && $avg >= $newPassingGrade;

                if ($wasEligible === $nowEligible) { continue; } // pas de changement

                // Trouver le StudentClass créé pour cet élève dans la période cible
                $targetSC = $this->findStudentClassInTarget($student, $targetPeriod, $createdScIds);

                if ($targetSC && isset($lockedSet[$targetSC->getId()])) {
                    $changes['locked'][] = [
                        'student'    => $student,
                        'average'    => $avg,
                        'wasStatus'  => $wasEligible ? 'promu' : 'redoublant',
                        'nowStatus'  => $nowEligible ? 'promu' : 'redoublant',
                    ];
                    continue;
                }

                if ($wasEligible && !$nowEligible) {
                    $changes['toDemote'][] = [
                        'student'      => $student,
                        'average'      => $avg,
                        'targetSC'     => $targetSC,
                        'repeaterSCP'  => ($sourceSCP->getClassOccurence()?->getId())
                            ? ($repeaterTargetMap[$sourceSCP->getClassOccurence()->getId()] ?? null)
                            : null,
                    ];
                } elseif (!$wasEligible && $nowEligible) {
                    if ($targetSC) {
                        $changes['toPromote'][] = [
                            'student'   => $student,
                            'average'   => $avg,
                            'targetSC'  => $targetSC,
                            'sourceSCP' => $sourceSCP,
                        ];
                    } else {
                        $changes['toAdd'][] = [
                            'student'   => $student,
                            'average'   => $avg,
                            'sourceSCP' => $sourceSCP,
                        ];
                    }
                }
            }
        }

        return $changes;
    }

    public function applyCorrection(MigrationLog $log, float $newPassingGrade, array $classMapping): array
    {
        $conn = $this->em->getConnection();
        $conn->beginTransaction();

        try {
            $preview   = $this->previewCorrection($log, $newPassingGrade);
            $applied   = ['demoted' => 0, 'promoted' => 0, 'added' => 0];
            $createdIds = $log->getCreatedIds();

            // Rétrograder les promus sans notes → les déplacer vers la classe redoublant
            foreach ($preview['toDemote'] as $item) {
                if ($item['targetSC'] && $item['repeaterSCP']) {
                    $this->em->remove($item['targetSC']);
                    $key = array_search($item['targetSC']->getId(), $createdIds['studentClasses']);
                    if ($key !== false) { unset($createdIds['studentClasses'][$key]); }

                    $newSC = $this->enrollStudent($item['student'], $item['repeaterSCP']);
                    if ($newSC) { $createdIds['studentClasses'][] = 0; } // sera mis à jour après flush
                    $applied['demoted']++;
                }
            }

            // Promouvoir les redoublants sans notes → les déplacer vers la classe promu
            foreach ($preview['toPromote'] as $item) {
                $sourceId    = $item['sourceSCP']->getId();
                $newTargetId = $classMapping[$sourceId] ?? null;
                $newTargetSCP = $newTargetId
                    ? $this->em->getRepository(SchoolClassPeriod::class)->find($newTargetId)
                    : null;

                if ($item['targetSC'] && $newTargetSCP) {
                    $this->em->remove($item['targetSC']);
                    $key = array_search($item['targetSC']->getId(), $createdIds['studentClasses']);
                    if ($key !== false) { unset($createdIds['studentClasses'][$key]); }

                    $newSC = $this->enrollStudent($item['student'], $newTargetSCP);
                    if ($newSC) { $createdIds['studentClasses'][] = 0; }
                    $applied['promoted']++;
                }
            }

            // Ajouter les ignorés devenus éligibles
            foreach ($preview['toAdd'] as $item) {
                $sourceId    = $item['sourceSCP']->getId();
                $newTargetId = $classMapping[$sourceId] ?? null;
                $newTargetSCP = $newTargetId
                    ? $this->em->getRepository(SchoolClassPeriod::class)->find($newTargetId)
                    : null;

                if ($newTargetSCP) {
                    $newSC = $this->enrollStudent($item['student'], $newTargetSCP);
                    if ($newSC) { $createdIds['studentClasses'][] = 0; }
                    $applied['added']++;
                }
            }

            $this->em->flush();

            // Reconstruire les IDs corrects après flush
            $finalIds = array_values(array_filter($createdIds['studentClasses'], fn($id) => $id > 0));
            // Récupérer les nouveaux IDs des SC fraîchement créés
            foreach ($this->em->getUnitOfWork()->getScheduledEntityInsertions() as $entity) {
                if ($entity instanceof StudentClass && $entity->getId()) {
                    $finalIds[] = $entity->getId();
                }
            }
            $createdIds['studentClasses'] = array_values(array_unique($finalIds));

            $log->setPassingGrade($newPassingGrade)
                ->setCreatedIds($createdIds)
                ->setStatus('corrected');
            $this->em->flush();

            $conn->commit();

            return $applied;

        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS PUBLICS
    // ─────────────────────────────────────────────────────────────────────────

    public function getTargetClassOptions(School $school, SchoolPeriod $targetPeriod): array
    {
        $classes = $this->em->getRepository(SchoolClassPeriod::class)->findBy([
            'school' => $school, 'period' => $targetPeriod,
        ]);
        $options = [];
        foreach ($classes as $scp) {
            $options[$scp->getId()] = $scp->getClassOccurence()?->getName() ?? '(ID ' . $scp->getId() . ')';
        }
        return $options;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS PRIVÉS
    // ─────────────────────────────────────────────────────────────────────────

    private function calculateStudentAverage(StudentClass $studentClass, SchoolClassPeriod $scp): ?float
    {
        $evaluations = $studentClass->getEvaluations();
        if ($evaluations->isEmpty()) { return null; }

        $coeffMap = [];
        foreach ($scp->getSchoolClassSubjects() as $scs) {
            $subjectId = $scs->getStudySubject()?->getId();
            if ($subjectId !== null) { $coeffMap[$subjectId] = $scs->getCoefficient(); }
        }

        $subjectNotes = [];
        foreach ($evaluations as $eval) {
            $module    = $eval->getClassSubjectModule();
            if (!$module) { continue; }
            $subjectId = $module->getSubject()?->getId();
            if ($subjectId === null) { continue; }
            $subjectNotes[$subjectId][] = $eval->getEvaluationNote();
        }

        if (empty($subjectNotes)) { return null; }

        $totalWeighted = 0.0;
        $totalCoeff    = 0;
        foreach ($subjectNotes as $subjectId => $notes) {
            $subjectAvg     = array_sum($notes) / count($notes);
            $coeff          = $coeffMap[$subjectId] ?? 1;
            $totalWeighted += $subjectAvg * $coeff;
            $totalCoeff    += $coeff;
        }

        return $totalCoeff > 0 ? round($totalWeighted / $totalCoeff, 2) : null;
    }

    /** @return array{0: array<int,SubjectGroup>, 1: SubjectGroup[]} [map oldId→entity, list] */
    private function cloneSubjectGroups(School $school, SchoolPeriod $source, SchoolPeriod $target): array
    {
        $map      = [];
        $entities = [];
        foreach ($this->em->getRepository(SubjectGroup::class)->findBy(['school' => $school, 'period' => $source]) as $group) {
            $new = new SubjectGroup();
            $new->setSchool($school)->setPeriod($target)->setPosOrder($group->getPosOrder());
            if ($group->getDescription() !== null) {
                $new->setDescription($group->getDescription() . ' (' . $target->getName() . ')');
            }
            $this->em->persist($new);
            $map[$group->getId()] = $new;
            $entities[]           = $new;
        }
        return [$map, $entities];
    }

    /** @return array{0: array<int,SchoolClassPeriod>, 1: SchoolClassPeriod[]} */
    private function cloneClasses(School $school, SchoolPeriod $source, SchoolPeriod $target): array
    {
        $map      = [];
        $entities = [];
        foreach ($this->em->getRepository(SchoolClassPeriod::class)->findBy(['school' => $school, 'period' => $source]) as $scp) {
            $new = new SchoolClassPeriod();
            $new->setSchool($school)->setPeriod($target)
                ->setClassOccurence($scp->getClassOccurence())
                ->setClassMaster($scp->getClassMaster())
                ->setEvaluationAppreciationTemplate($scp->getEvaluationAppreciationTemplate())
                ->setReportCardTemplate($scp->getReportCardTemplate());
            $this->em->persist($new);
            $map[$scp->getId()] = $new;
            $entities[]         = $new;
        }
        return [$map, $entities];
    }

    /** @return array{0: int, 1: SchoolClassSubject[]} */
    private function cloneSubjects(array $classMap, array $groupMap): array
    {
        $entities = [];
        foreach ($classMap as $oldSCPId => $newSCP) {
            $oldSCP = $this->em->getRepository(SchoolClassPeriod::class)->find($oldSCPId);
            if (!$oldSCP) { continue; }
            foreach ($oldSCP->getSchoolClassSubjects() as $scs) {
                $new = new SchoolClassSubject();
                $new->setSchoolClassPeriod($newSCP)
                    ->setStudySubject($scs->getStudySubject())
                    ->setTeacher($scs->getTeacher())
                    ->setCoefficient($scs->getCoefficient())
                    ->setAwaitedSkills($scs->getAwaitedSkills());
                if ($scs->getGroup() !== null) {
                    $new->setGroup($groupMap[$scs->getGroup()->getId()] ?? null);
                }
                $this->em->persist($new);
                $entities[] = $new;
            }
        }
        return [count($entities), $entities];
    }

    /** @return array{0: int, 1: ClassSubjectModule[]} */
    private function cloneModules(School $school, SchoolPeriod $source, SchoolPeriod $target, array $classMap): array
    {
        $entities = [];
        foreach ($classMap as $oldSCPId => $newSCP) {
            $oldSCP = $this->em->getRepository(SchoolClassPeriod::class)->find($oldSCPId);
            if (!$oldSCP) { continue; }
            foreach ($oldSCP->getClassSubjectModules() as $csm) {
                $new = new ClassSubjectModule();
                $new->setSchool($school)->setPeriod($target)->setClass($newSCP)
                    ->setSubject($csm->getSubject())->setModule($csm->getModule())
                    ->setModuleNotation($csm->getModuleNotation());
                $this->em->persist($new);
                $entities[] = $new;
            }
        }
        return [count($entities), $entities];
    }

    /** @return array{0: int, 1: SchoolClassPaymentModal[]} */
    private function clonePaymentModals(School $school, SchoolPeriod $source, SchoolPeriod $target, array $classMap): array
    {
        $entities = [];
        foreach ($classMap as $oldSCPId => $newSCP) {
            $oldSCP = $this->em->getRepository(SchoolClassPeriod::class)->find($oldSCPId);
            if (!$oldSCP) { continue; }
            foreach ($oldSCP->getPaymentModals() as $modal) {
                $new = new SchoolClassPaymentModal();
                $new->setSchool($school)->setSchoolPeriod($target)->setSchoolClassPeriod($newSCP)
                    ->setLabel($modal->getLabel())->setAmount($modal->getAmount())
                    ->setDueDate($modal->getDueDate())->setModalType($modal->getModalType())
                    ->setModalPriority($modal->getModalPriority());
                $this->em->persist($new);
                $entities[] = $new;
            }
        }
        return [count($entities), $entities];
    }

    private function buildRepeaterTargetMap(School $school, SchoolPeriod $targetPeriod): array
    {
        $map = [];
        foreach ($this->em->getRepository(SchoolClassPeriod::class)->findBy(['school' => $school, 'period' => $targetPeriod]) as $scp) {
            $occId = $scp->getClassOccurence()?->getId();
            if ($occId !== null) { $map[$occId] = $scp; }
        }
        return $map;
    }

    private function enrollStudent(\App\Entity\User $student, SchoolClassPeriod $targetSCP): ?StudentClass
    {
        foreach ($targetSCP->getStudentClasses() as $sc) {
            if ($sc->getStudent() === $student) { return null; }
        }
        $sc = new StudentClass();
        $sc->setStudent($student)->setSchoolClassPeriod($targetSCP);
        $this->em->persist($sc);
        return $sc;
    }

    private function deleteByIds(string $entityClass, array $ids): void
    {
        foreach ($ids as $id) {
            $entity = $this->em->getRepository($entityClass)->find($id);
            if ($entity) { $this->em->remove($entity); }
        }
    }

    private function findStudentClassInTarget(
        \App\Entity\User $student,
        SchoolPeriod $targetPeriod,
        array $createdScIdSet
    ): ?StudentClass {
        $repo = $this->em->getRepository(StudentClass::class);
        $all  = $repo->createQueryBuilder('sc')
            ->join('sc.schoolClassPeriod', 'scp')
            ->where('sc.student = :student')
            ->andWhere('scp.period = :period')
            ->setParameter('student', $student)
            ->setParameter('period', $targetPeriod)
            ->getQuery()
            ->getResult();

        foreach ($all as $sc) {
            if (isset($createdScIdSet[$sc->getId()])) { return $sc; }
        }
        return null;
    }
}
