<?php

namespace App\Service;

use App\Entity\ClassSubjectModule;
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

    /**
     * Preview: returns per-class stats for student eligibility.
     * Returns array of ['classOccurenceName', 'schoolClassPeriod', 'total', 'eligible', 'nonEligible', 'studentStats']
     */
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
                $avg = $this->calculateStudentAverage($studentClass, $scp);
                $student = $studentClass->getStudent();
                $studentStat = [
                    'student' => $student,
                    'average' => $avg,
                    'eligible' => $avg !== null && $avg >= $passingGrade,
                ];
                if ($studentStat['eligible']) {
                    $eligible[] = $studentStat;
                } else {
                    $nonEligible[] = $studentStat;
                }
            }

            $results[] = [
                'schoolClassPeriod' => $scp,
                'className' => $scp->getClassOccurence()?->getName() ?? '—',
                'total' => count($eligible) + count($nonEligible),
                'eligible' => count($eligible),
                'nonEligible' => count($nonEligible),
                'studentStats' => array_merge($eligible, $nonEligible),
            ];
        }

        return $results;
    }

    /**
     * Step 1: Clone all configuration from source to target period.
     * Returns a summary of what was created.
     */
    public function migrateConfiguration(
        School $school,
        SchoolPeriod $sourcePeriod,
        SchoolPeriod $targetPeriod,
        array $options
    ): array {
        $summary = [
            'subject_groups' => 0,
            'classes' => 0,
            'subjects' => 0,
            'modules' => 0,
            'payment_modals' => 0,
        ];

        // Map old SubjectGroup id → new SubjectGroup
        $groupMap = [];
        if ($options['subject_groups'] ?? true) {
            $groupMap = $this->cloneSubjectGroups($school, $sourcePeriod, $targetPeriod);
            $summary['subject_groups'] = count($groupMap);
        }

        // Map old SchoolClassPeriod id → new SchoolClassPeriod
        $classMap = [];
        if ($options['classes'] ?? true) {
            $classMap = $this->cloneClasses($school, $sourcePeriod, $targetPeriod);
            $summary['classes'] = count($classMap);
        }

        if ($options['subjects'] ?? true) {
            $summary['subjects'] = $this->cloneSubjects($classMap, $groupMap);
        }

        if ($options['modules'] ?? true) {
            $summary['modules'] = $this->cloneModules($school, $sourcePeriod, $targetPeriod, $classMap);
        }

        if ($options['payment_modals'] ?? true) {
            $summary['payment_modals'] = $this->clonePaymentModals($school, $sourcePeriod, $targetPeriod, $classMap);
        }

        $this->em->flush();

        return $summary;
    }

    /**
     * Step 2: Migrate students from source to target period.
     * $classMapping: [sourceSchoolClassPeriodId => targetSchoolClassPeriodId] for promoted students.
     * Repeaters are placed in the same ClassOccurence in the target period.
     * Returns array ['promoted' => int, 'repeated' => int, 'skipped' => int]
     */
    public function migrateStudents(
        School $school,
        SchoolPeriod $sourcePeriod,
        SchoolPeriod $targetPeriod,
        float $passingGrade,
        array $classMapping
    ): array {
        $stats = ['promoted' => 0, 'repeated' => 0, 'skipped' => 0];

        $sourceClasses = $this->em->getRepository(SchoolClassPeriod::class)->findBy([
            'school' => $school,
            'period' => $sourcePeriod,
        ]);

        // Build map: classOccurenceId → targetSchoolClassPeriod (for repeaters)
        $repeaterTargetMap = $this->buildRepeaterTargetMap($school, $targetPeriod);

        foreach ($sourceClasses as $sourceSCP) {
            $sourceId = $sourceSCP->getId();
            $promotedTargetId = $classMapping[$sourceId] ?? null;
            $promotedTarget = $promotedTargetId
                ? $this->em->getRepository(SchoolClassPeriod::class)->find($promotedTargetId)
                : null;

            $occId = $sourceSCP->getClassOccurence()?->getId();
            $repeaterTarget = $occId ? ($repeaterTargetMap[$occId] ?? null) : null;

            foreach ($sourceSCP->getStudentClasses() as $studentClass) {
                $avg = $this->calculateStudentAverage($studentClass, $sourceSCP);
                $isEligible = $avg !== null && $avg >= $passingGrade;

                if ($isEligible && $promotedTarget) {
                    $this->enrollStudent($studentClass->getStudent(), $promotedTarget);
                    $stats['promoted']++;
                } elseif (!$isEligible && $repeaterTarget) {
                    $this->enrollStudent($studentClass->getStudent(), $repeaterTarget);
                    $stats['repeated']++;
                } else {
                    $stats['skipped']++;
                }
            }
        }

        $this->em->flush();

        return $stats;
    }

    /**
     * Returns target SchoolClassPeriods for a given school+period, keyed by classOccurenceId.
     */
    public function getTargetClassOptions(School $school, SchoolPeriod $targetPeriod): array
    {
        $classes = $this->em->getRepository(SchoolClassPeriod::class)->findBy([
            'school' => $school,
            'period' => $targetPeriod,
        ]);

        $options = [];
        foreach ($classes as $scp) {
            $options[$scp->getId()] = $scp->getClassOccurence()?->getName() ?? '(ID ' . $scp->getId() . ')';
        }

        return $options;
    }

    private function calculateStudentAverage(StudentClass $studentClass, SchoolClassPeriod $scp): ?float
    {
        $evaluations = $studentClass->getEvaluations();
        if ($evaluations->isEmpty()) {
            return null;
        }

        // Build coefficient map: studySubjectId → coefficient
        $coeffMap = [];
        foreach ($scp->getSchoolClassSubjects() as $scs) {
            $subjectId = $scs->getStudySubject()?->getId();
            if ($subjectId !== null) {
                $coeffMap[$subjectId] = $scs->getCoefficient();
            }
        }

        // Group evaluations by subject, then average per subject
        $subjectNotes = [];
        foreach ($evaluations as $eval) {
            $module = $eval->getClassSubjectModule();
            if (!$module) continue;
            $subjectId = $module->getSubject()?->getId();
            if ($subjectId === null) continue;
            $subjectNotes[$subjectId][] = $eval->getEvaluationNote();
        }

        if (empty($subjectNotes)) {
            return null;
        }

        $totalWeighted = 0.0;
        $totalCoeff = 0;

        foreach ($subjectNotes as $subjectId => $notes) {
            $subjectAvg = array_sum($notes) / count($notes);
            $coeff = $coeffMap[$subjectId] ?? 1;
            $totalWeighted += $subjectAvg * $coeff;
            $totalCoeff += $coeff;
        }

        return $totalCoeff > 0 ? round($totalWeighted / $totalCoeff, 2) : null;
    }

    private function cloneSubjectGroups(School $school, SchoolPeriod $source, SchoolPeriod $target): array
    {
        $map = [];
        $sourceGroups = $this->em->getRepository(SubjectGroup::class)->findBy([
            'school' => $school,
            'period' => $source,
        ]);

        foreach ($sourceGroups as $group) {
            $newGroup = new SubjectGroup();
            $newGroup->setSchool($school);
            $newGroup->setPeriod($target);
            $newGroup->setPosOrder($group->getPosOrder());
            // Append period name to avoid unique constraint violation on description
            if ($group->getDescription() !== null) {
                $newGroup->setDescription($group->getDescription() . ' (' . $target->getName() . ')');
            }
            $this->em->persist($newGroup);
            $map[$group->getId()] = $newGroup;
        }

        return $map;
    }

    private function cloneClasses(School $school, SchoolPeriod $source, SchoolPeriod $target): array
    {
        $map = [];
        $sourceClasses = $this->em->getRepository(SchoolClassPeriod::class)->findBy([
            'school' => $school,
            'period' => $source,
        ]);

        foreach ($sourceClasses as $scp) {
            $newSCP = new SchoolClassPeriod();
            $newSCP->setSchool($school);
            $newSCP->setPeriod($target);
            $newSCP->setClassOccurence($scp->getClassOccurence());
            $newSCP->setClassMaster($scp->getClassMaster());
            $newSCP->setEvaluationAppreciationTemplate($scp->getEvaluationAppreciationTemplate());
            $newSCP->setReportCardTemplate($scp->getReportCardTemplate());
            $this->em->persist($newSCP);
            $map[$scp->getId()] = $newSCP;
        }

        return $map;
    }

    private function cloneSubjects(array $classMap, array $groupMap): int
    {
        $count = 0;
        foreach ($classMap as $oldSCPId => $newSCP) {
            $oldSCP = $this->em->getRepository(SchoolClassPeriod::class)->find($oldSCPId);
            if (!$oldSCP) continue;

            foreach ($oldSCP->getSchoolClassSubjects() as $scs) {
                $newSCS = new SchoolClassSubject();
                $newSCS->setSchoolClassPeriod($newSCP);
                $newSCS->setStudySubject($scs->getStudySubject());
                $newSCS->setTeacher($scs->getTeacher());
                $newSCS->setCoefficient($scs->getCoefficient());
                $newSCS->setAwaitedSkills($scs->getAwaitedSkills());

                if ($scs->getGroup() !== null) {
                    $oldGroupId = $scs->getGroup()->getId();
                    $newGroup = $groupMap[$oldGroupId] ?? null;
                    $newSCS->setGroup($newGroup);
                }

                $this->em->persist($newSCS);
                $count++;
            }
        }

        return $count;
    }

    private function cloneModules(School $school, SchoolPeriod $source, SchoolPeriod $target, array $classMap): int
    {
        $count = 0;
        foreach ($classMap as $oldSCPId => $newSCP) {
            $oldSCP = $this->em->getRepository(SchoolClassPeriod::class)->find($oldSCPId);
            if (!$oldSCP) continue;

            foreach ($oldSCP->getClassSubjectModules() as $csm) {
                $newCSM = new ClassSubjectModule();
                $newCSM->setSchool($school);
                $newCSM->setPeriod($target);
                $newCSM->setClass($newSCP);
                $newCSM->setSubject($csm->getSubject());
                $newCSM->setModule($csm->getModule());
                $newCSM->setModuleNotation($csm->getModuleNotation());
                $this->em->persist($newCSM);
                $count++;
            }
        }

        return $count;
    }

    private function clonePaymentModals(School $school, SchoolPeriod $source, SchoolPeriod $target, array $classMap): int
    {
        $count = 0;
        foreach ($classMap as $oldSCPId => $newSCP) {
            $oldSCP = $this->em->getRepository(SchoolClassPeriod::class)->find($oldSCPId);
            if (!$oldSCP) continue;

            foreach ($oldSCP->getPaymentModals() as $modal) {
                $newModal = new SchoolClassPaymentModal();
                $newModal->setSchool($school);
                $newModal->setSchoolPeriod($target);
                $newModal->setSchoolClassPeriod($newSCP);
                $newModal->setLabel($modal->getLabel());
                $newModal->setAmount($modal->getAmount());
                $newModal->setDueDate($modal->getDueDate());
                $newModal->setModalType($modal->getModalType());
                $newModal->setModalPriority($modal->getModalPriority());
                $this->em->persist($newModal);
                $count++;
            }
        }

        return $count;
    }

    private function buildRepeaterTargetMap(School $school, SchoolPeriod $targetPeriod): array
    {
        $targetClasses = $this->em->getRepository(SchoolClassPeriod::class)->findBy([
            'school' => $school,
            'period' => $targetPeriod,
        ]);

        $map = [];
        foreach ($targetClasses as $scp) {
            $occId = $scp->getClassOccurence()?->getId();
            if ($occId !== null) {
                $map[$occId] = $scp;
            }
        }

        return $map;
    }

    private function enrollStudent(\App\Entity\User $student, SchoolClassPeriod $targetSCP): void
    {
        // Avoid duplicate enrollment
        foreach ($targetSCP->getStudentClasses() as $sc) {
            if ($sc->getStudent() === $student) {
                return;
            }
        }

        $studentClass = new StudentClass();
        $studentClass->setStudent($student);
        $studentClass->setSchoolClassPeriod($targetSCP);
        $this->em->persist($studentClass);
    }
}
