<?php

namespace App\Service;

use App\Entity\ClassOccurence;
use App\Entity\ClassOccurenceSchoolConfig;
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
    /** Étapes du wizard, dans l'ordre d'exécution. Les clés correspondent aux options de la config. */
    public const STEPS = [
        'subject_groups' => 'Groupes de matières',
        'classes'        => 'Classes',
        'subjects'       => 'Matières & enseignants',
        'modules'        => 'Modules',
        'payment_modals' => 'Modalités de paiement',
        'students'       => 'Élèves',
    ];

    /** Étapes qui nécessitent des classes dans la période cible pour être exécutées. */
    private const CLASS_DEPENDENT_STEPS = ['subjects', 'modules', 'payment_modals', 'students'];

    public function __construct(private EntityManagerInterface $em) {}

    // ─────────────────────────────────────────────────────────────────────────
    // PREVIEW
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param array<int, float> $grades moyennes de passage individuelles (occId => moyenne) ;
     *                                 les occurrences absentes retombent sur la note globale.
     */
    public function previewStudentMigration(
        School $school,
        SchoolPeriod $sourcePeriod,
        float $passingGrade,
        array $grades = []
    ): array {
        $results = [];
        $sourceClasses = $this->em->getRepository(SchoolClassPeriod::class)->findBy([
            'school' => $school,
            'period' => $sourcePeriod,
        ]);

        foreach ($sourceClasses as $scp) {
            $occId = $scp->getClassOccurence()?->getId();
            $grade = $occId !== null ? ($grades[$occId] ?? $passingGrade) : $passingGrade;

            $eligible = [];
            $nonEligible = [];
            foreach ($scp->getStudentClasses() as $studentClass) {
                $avg  = $this->calculateStudentAverage($studentClass, $scp);
                $stat = [
                    'student'        => $studentClass->getStudent(),
                    'studentClassId' => $studentClass->getId(),
                    'average'        => $avg,
                    'eligible'       => $avg !== null && $avg >= $grade,
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
                'grade'             => $grade,
            ];
        }

        return $results;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // WIZARD PAR ÉTAPES
    // Le brouillon (MigrationLog en statut « in_progress ») porte tout l'état :
    // options, createdIds cumulés (base de l'annulation), stepsState par étape
    // et les maps internes (_group_map / _class_map) nécessaires aux étapes suivantes.
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Crée un brouillon in_progress, ou reprend le plus récent pour le même
     * école/source/cible (la config soumise est alors ignorée).
     */
    public function startMigration(
        School $school,
        SchoolPeriod $sourcePeriod,
        SchoolPeriod $targetPeriod,
        float $passingGrade,
        array $options,
        string $executedBy
    ): MigrationLog {
        $existing = $this->em->getRepository(MigrationLog::class)->findOneBy([
            'school'       => $school,
            'sourcePeriod' => $sourcePeriod,
            'targetPeriod' => $targetPeriod,
            'status'       => 'in_progress',
        ], ['id' => 'DESC']);

        if ($existing) {
            return $existing;
        }

        $log = new MigrationLog();
        $log->setSchool($school)
            ->setSourcePeriod($sourcePeriod)
            ->setTargetPeriod($targetPeriod)
            ->setPassingGrade($passingGrade)
            ->setOptions($options)
            ->setExecutedBy($executedBy)
            ->setStatus('in_progress')
            // Les 6 clés toujours initialisées (manage.html.twig et checkMigrationState les lisent)
            ->setCreatedIds([
                'subjectGroups'       => [],
                'schoolClassPeriods'  => [],
                'schoolClassSubjects' => [],
                'classSubjectModules' => [],
                'paymentModals'       => [],
                'studentClasses'      => [],
            ]);

        $stepsState = [];
        foreach (self::STEPS as $key => $label) {
            // L'étape élèves n'a pas d'option : toujours en attente.
            $stepsState[$key] = ($key !== 'students' && empty($options[$key] ?? null))
                ? $this->initStepState('skipped', 'Option non sélectionnée.')
                : $this->initStepState('pending');
        }
        $log->setStepsState($stepsState);

        $this->em->persist($log);
        $this->em->flush();

        return $log;
    }

    /** Prochaine étape à exécuter (pending ou failed) ; null si le wizard est terminé. Les étapes bloquées sont sautées. */
    public function getNextStepKey(MigrationLog $log): ?string
    {
        $state = $log->getStepsState() ?? [];
        foreach (self::STEPS as $key => $label) {
            $status = $state[$key]['status'] ?? 'pending';
            if ($status === 'pending' || $status === 'failed') {
                return $this->isStepBlocked($log, $key) ? null : $key;
            }
        }
        return null;
    }

    /** Entrée stepsState d'une étape (compteurs + message), pour afficher le résultat de la dernière exécution. */
    public function getStepResult(MigrationLog $log, string $stepKey): ?array
    {
        $state = $log->getStepsState() ?? [];
        return $state[$stepKey] ?? null;
    }

    /** true si l'étape dépend des classes et qu'aucune classe n'existe dans la période cible. */
    public function isStepBlocked(MigrationLog $log, string $stepKey): bool
    {
        if (!in_array($stepKey, self::CLASS_DEPENDENT_STEPS, true)) {
            return false;
        }
        return !$this->hasTargetClasses($log->getSchool(), $log->getTargetPeriod());
    }

    /** Données de rendu de l'étape courante (page wizard). */
    public function getStepContext(MigrationLog $log, string $stepKey): array
    {
        $school = $log->getSchool();

        if ($stepKey === 'students') {
            $grades  = $this->loadPassingGrades($log);
            $preview = $this->previewStudentMigration($school, $log->getSourcePeriod(), $log->getPassingGrade(), $grades);
            $targetByOccurence = $this->getTargetSCPsByOccurence($school, $log->getTargetPeriod());
            $rows = [];
            foreach ($preview as $row) {
                $sourceSCP = $row['schoolClassPeriod'];
                $occurence = $sourceSCP->getClassOccurence();
                $config    = $this->getSchoolConfig($school, $occurence);
                // Candidats = classes de la période cible dont l'occurrence est une
                // occurrence suivante configurée pour CETTE école.
                $candidates = [];
                if ($config !== null) {
                    foreach ($config->getNextOccurences() as $nextOccurence) {
                        foreach ($targetByOccurence[$nextOccurence->getId()] ?? [] as $targetSCP) {
                            $candidates[$targetSCP->getId()] = $targetSCP->getClassOccurence()?->getName() ?? '(ID ' . $targetSCP->getId() . ')';
                        }
                    }
                }
                $rows[] = [
                    'preview'     => $row,
                    'sourceSCPId' => $sourceSCP->getId(),
                    'occId'       => $occurence?->getId(),
                    'occName'     => $occurence?->getName(),
                    'candidates'  => $candidates,
                    // Pré-sélection uniquement s'il n'y a qu'une seule occurrence suivante.
                    'selected'    => count($candidates) === 1 ? array_key_first($candidates) : null,
                    'missingLink' => $config === null || $config->getNextOccurences()->isEmpty(),
                    'finalLevel'  => $config !== null && $config->isFinalLevel(),
                    'linkedIds'   => $config !== null ? array_map(fn(ClassOccurence $o) => $o->getId(), $config->getNextOccurences()->toArray()) : [],
                    'grade'       => $row['grade'],
                    'hasOverride' => $occurence !== null && array_key_exists($occurence->getId(), $grades),
                ];
            }
            // Occurrences liées à l'année source de l'école : seules les classes
            // existant dans l'année source peuvent être proposées comme classes suivantes.
            $occurencesForModal = [];
            foreach ($this->em->getRepository(SchoolClassPeriod::class)->findBy(['school' => $school, 'period' => $log->getSourcePeriod()]) as $scp) {
                $o = $scp->getClassOccurence();
                if ($o !== null) {
                    $occurencesForModal[$o->getId()] = $o->getName();
                }
            }
            return [
                'rows'               => $rows,
                'occurencesForModal' => $occurencesForModal,
                'hasTargetClasses'   => $this->hasTargetClasses($school, $log->getTargetPeriod()),
            ];
        }

        return ['sourceCount' => $this->countSourceItems($school, $log->getSourcePeriod(), $stepKey)];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MOYENNE DE PASSAGE PAR CLASSE (étape Élèves)
    // Deltas stockés sous la clé privée « _passing_grades » du stepsState
    // (occId => moyenne) : les clés absentes retombent sur log.passingGrade.
    // ─────────────────────────────────────────────────────────────────────────

    /** Map « _passing_grades » : occId => moyenne de passage propre à la classe. */
    private function loadPassingGrades(MigrationLog $log): array
    {
        return ($log->getStepsState() ?? [])['_passing_grades'] ?? [];
    }

    /** Définit (ou supprime avec null) la moyenne de passage individuelle d'une occurrence. */
    public function setClassPassingGrade(MigrationLog $log, int $occId, ?float $grade): void
    {
        $state  = $log->getStepsState() ?? [];
        $grades = $state['_passing_grades'] ?? [];
        if ($grade === null) {
            unset($grades[$occId]);
        } else {
            $grades[$occId] = $grade;
        }
        $state['_passing_grades'] = $grades;
        $log->setStepsState($state);
    }

    /**
     * Aperçu recalculé des lignes Élèves partageant une occurrence — réponse AJAX
     * du champ « moyenne de passage » individuel (compteurs + détail par élève).
     */
    public function getClassGradePreview(MigrationLog $log, int $occId): array
    {
        $grades = $this->loadPassingGrades($log);
        $rows   = [];
        foreach ($this->previewStudentMigration($log->getSchool(), $log->getSourcePeriod(), $log->getPassingGrade(), $grades) as $row) {
            $scp = $row['schoolClassPeriod'];
            if ($scp->getClassOccurence()?->getId() !== $occId) {
                continue;
            }
            $rows[] = [
                'sourceSCPId'  => $scp->getId(),
                'grade'        => $row['grade'],
                'hasOverride'  => array_key_exists($occId, $grades),
                'total'        => $row['total'],
                'eligible'     => $row['eligible'],
                'nonEligible'  => $row['nonEligible'],
                'studentStats' => array_map(function (array $stat) {
                    $student = $stat['student'];
                    return [
                        'studentClassId' => $stat['studentClassId'],
                        'name'           => $student->getUsername() ?? $student->getFullName(),
                        'average'        => $stat['average'],
                        'eligible'       => $stat['eligible'],
                    ];
                }, $row['studentStats']),
            ];
        }
        return $rows;
    }

    /**
     * Exécute UNE étape dans sa propre transaction, met à jour createdIds +
     * stepsState, puis retourne le résultat (compteurs + message).
     *
     * @param array<int, mixed> $classMapping mapping explicite classe source => classe cible (étape classes/students)
     * @return array{status: string, created: int, existing: int, errors: int, message: string}
     */
    public function executeStep(MigrationLog $log, string $stepKey, array $classMapping = [], array $studentMapping = []): array
    {
        // Gardes avant transaction (anti double-clic / rejeu)
        if ($log->getStatus() !== 'in_progress') {
            throw new \LogicException('La migration n\'est plus modifiable.');
        }
        if (!isset(self::STEPS[$stepKey])) {
            throw new \InvalidArgumentException('Étape inconnue : ' . $stepKey);
        }
        $state   = $log->getStepsState() ?? [];
        $current = $state[$stepKey]['status'] ?? 'pending';
        if ($current !== 'pending' && $current !== 'failed') {
            throw new \LogicException('Cette étape a déjà été exécutée.');
        }
        if ($this->isStepBlocked($log, $stepKey)) {
            throw new \LogicException('Aucune classe cible disponible : impossible d\'exécuter cette étape.');
        }

        $school       = $log->getSchool();
        $sourcePeriod = $log->getSourcePeriod();
        $targetPeriod = $log->getTargetPeriod();

        // Garde liens de succession : toute occurrence source ayant au moins un élève
        // éligible doit avoir ses classes suivantes configurées POUR CETTE ÉCOLE
        // (sauf niveau final). Classe à plusieurs classes suivantes : chaque élève
        // promu doit en plus avoir un choix individuel valide.
        if ($stepKey === 'students') {
            $grades            = $this->loadPassingGrades($log);
            $targetByOccurence = $this->getTargetSCPsByOccurence($school, $targetPeriod);
            $missing   = [];
            $unchosen  = [];
            foreach ($this->previewStudentMigration($school, $sourcePeriod, $log->getPassingGrade(), $grades) as $row) {
                $occurence = $row['schoolClassPeriod']->getClassOccurence();
                if ($occurence === null || $row['eligible'] === 0) {
                    continue;
                }
                $config = $this->getSchoolConfig($school, $occurence);
                if ($config === null || (!$config->isFinalLevel() && $config->getNextOccurences()->isEmpty())) {
                    $missing[] = $occurence->getName();
                    continue;
                }
                if ($config->isFinalLevel()) {
                    continue;
                }
                // Nombre de candidats réels (occurrences suivantes présentes dans la période cible).
                $candidateCount = 0;
                foreach ($config->getNextOccurences() as $nextOccurence) {
                    $candidateCount += count($targetByOccurence[$nextOccurence->getId()] ?? []);
                }
                if ($candidateCount <= 1) {
                    continue;
                }
                foreach ($row['studentStats'] as $stat) {
                    if (!$stat['eligible']) {
                        continue;
                    }
                    $mapped = $studentMapping[$stat['studentClassId']] ?? null;
                    if (!$this->resolveExplicitTarget($school, $targetPeriod, $mapped)) {
                        $student = $stat['student'];
                        $unchosen[] = ($student->getFullName() ?? $student->getUsername()) . ' (' . $row['className'] . ')';
                    }
                }
            }
            if ($missing) {
                throw new \LogicException('Configurez les classes suivantes pour : ' . implode(', ', array_unique($missing)));
            }
            if ($unchosen) {
                throw new \LogicException('Choisissez la classe cible des élèves suivants : ' . implode(', ', $unchosen));
            }
        }

        $conn = $this->em->getConnection();
        $conn->beginTransaction();

        try {
            $sourceTotal   = 0;
            $entities      = [];   // entités de configuration créées par cette étape
            $groupMap      = null; // map matière-groupe produite par l'étape (persistée après flush)
            $classMap      = null; // map classes produite par l'étape (persistée après flush)
            $studentResult = null; // compteurs élèves (étape students uniquement)

            switch ($stepKey) {
                case 'subject_groups':
                    $sourceTotal = count($this->em->getRepository(SubjectGroup::class)->findBy(['school' => $school, 'period' => $sourcePeriod]));
                    [$groupMap, $entities] = $this->cloneSubjectGroups($school, $sourcePeriod, $targetPeriod);
                    break;
                case 'classes':
                    $sourceTotal = count($this->em->getRepository(SchoolClassPeriod::class)->findBy(['school' => $school, 'period' => $sourcePeriod]));
                    [$classMap, $entities] = $this->cloneClasses($school, $sourcePeriod, $targetPeriod, $classMapping);
                    break;
                case 'subjects':
                    $sourceTotal = $this->sumSourceRelation($school, $sourcePeriod, 'getSchoolClassSubjects');
                    [, $entities] = $this->cloneSubjects($this->loadClassMap($log), $this->loadGroupMap($log));
                    break;
                case 'modules':
                    $sourceTotal = $this->sumSourceRelation($school, $sourcePeriod, 'getClassSubjectModules');
                    [, $entities] = $this->cloneModules($school, $sourcePeriod, $targetPeriod, $this->loadClassMap($log));
                    break;
                case 'payment_modals':
                    $sourceTotal = $this->sumSourceRelation($school, $sourcePeriod, 'getPaymentModals');
                    [, $entities] = $this->clonePaymentModals($school, $sourcePeriod, $targetPeriod, $this->loadClassMap($log));
                    break;
                case 'students':
                    $studentResult = $this->runStudentsStep($log, $classMapping, $studentMapping);
                    break;
            }

            $this->em->flush(); // IDs disponibles

            $createdIds = $log->getCreatedIds();
            foreach ($entities as $e) {
                $key = match (true) {
                    $e instanceof SubjectGroup             => 'subjectGroups',
                    $e instanceof SchoolClassPeriod        => 'schoolClassPeriods',
                    $e instanceof SchoolClassSubject       => 'schoolClassSubjects',
                    $e instanceof ClassSubjectModule       => 'classSubjectModules',
                    $e instanceof SchoolClassPaymentModal  => 'paymentModals',
                    default                                => null,
                };
                if ($key !== null) { $createdIds[$key][] = $e->getId(); }
            }

            // Persistance des maps internes pour les étapes suivantes
            if ($groupMap !== null) {
                $s = $log->getStepsState() ?? [];
                $s['_group_map'] = array_map(fn(SubjectGroup $g) => $g->getId(), $groupMap);
                $log->setStepsState($s);
            }
            if ($classMap !== null) {
                $s = $log->getStepsState() ?? [];
                $s['classes']['_class_map'] = array_map(fn(SchoolClassPeriod $c) => $c->getId(), $classMap);
                $log->setStepsState($s);
            }

            if ($studentResult !== null) {
                foreach ($studentResult['studentClasses'] as $sc) {
                    $createdIds['studentClasses'][] = $sc->getId();
                }
                $stats = $log->getStudentStats();
                $log->setStudentStats([
                    'promoted'        => ($stats['promoted'] ?? 0) + $studentResult['promoted'],
                    'repeated'        => ($stats['repeated'] ?? 0) + $studentResult['repeated'],
                    'skipped'         => ($stats['skipped'] ?? 0) + $studentResult['skipped'],
                    'existing'        => ($stats['existing'] ?? 0) + $studentResult['existing'],
                    // Détail des non affectés (nom, classe source, moyenne, raison) pour la page de résultat.
                    'skippedStudents' => array_merge($stats['skippedStudents'] ?? [], $studentResult['skippedStudents']),
                ]);
                $this->setStepStatus($log, $stepKey, 'done', [
                    'created'  => $studentResult['created'],
                    'existing' => $studentResult['existing'],
                    'errors'   => $studentResult['errors'],
                    'message'  => $studentResult['message'],
                ]);
            } else {
                $createdCount = count($entities);
                $existingCount = max(0, $sourceTotal - $createdCount);
                $summary = $log->getConfigSummary();
                $summary[$stepKey] = ($summary[$stepKey] ?? 0) + $createdCount;
                $log->setConfigSummary($summary);
                if ($sourceTotal === 0) {
                    // Rien à cloner dans la source : étape ignorée automatiquement.
                    $this->setStepStatus($log, $stepKey, 'skipped', [
                        'created'  => 0,
                        'existing' => 0,
                        'errors'   => 0,
                        'message'  => '0 élément à traiter.',
                    ]);
                } else {
                    $this->setStepStatus($log, $stepKey, 'done', [
                        'created'  => $createdCount,
                        'existing' => $existingCount,
                        'errors'   => 0,
                        'message'  => sprintf('%d créé(s), %d déjà existant(s).', $createdCount, $existingCount),
                    ]);
                }
            }

            // Les étapes suivantes sans élément à traiter sont ignorées automatiquement :
            // le wizard saute directement à la prochaine étape ayant du contenu.
            if ($stepKey !== 'students') {
                $this->autoSkipEmptySteps($log);
            }

            $log->setCreatedIds($createdIds);
            $this->em->persist($log);
            $this->em->flush();

            $conn->commit();

            return $log->getStepsState()[$stepKey] ?? [];

        } catch (\Throwable $e) {
            $conn->rollBack();
            // Vider l'UOW : le rollback ne touche que la base ; sans clear(), les
            // INSERT du pas échoué seraient rejoués au flush suivant.
            $this->em->clear();
            $log = $this->em->find(MigrationLog::class, $log->getId());
            $this->setStepStatus($log, $stepKey, 'failed', [
                'created' => 0,
                'existing'=> 0,
                'errors'  => 1,
                'message' => $e->getMessage(),
            ]);
            $this->em->flush();
            return ['status' => 'failed', 'created' => 0, 'existing' => 0, 'errors' => 1, 'message' => $e->getMessage()];
        }
    }

    /** « Continuer avec ce qui a réussi » : marque l'étape en échec comme ignorée. */
    public function skipFailedStep(MigrationLog $log, string $stepKey): void
    {
        $state   = $log->getStepsState() ?? [];
        $current = $state[$stepKey]['status'] ?? null;
        if ($current !== 'failed') {
            throw new \LogicException('Seule une étape en échec peut être ignorée.');
        }
        $this->setStepStatus($log, $stepKey, 'skipped', [
            'message' => 'Étape ignorée : la migration continue sans les données de cette étape.',
        ]);
        $this->em->flush();
    }

    /**
     * Ignore automatiquement les étapes en attente qui n'ont rien à traiter
     * (0 élément dans la période source), jusqu'à la première étape avec du
     * contenu. L'étape Élèves n'est jamais ignorée automatiquement
     * (prévisualisation et choix explicites). Retourne le nombre d'étapes ignorées.
     */
    public function autoSkipEmptySteps(MigrationLog $log): int
    {
        if ($log->getStatus() !== 'in_progress') {
            return 0;
        }
        $skipped = 0;
        foreach (self::STEPS as $key => $label) {
            if ($key === 'students') {
                break;
            }
            $state  = $log->getStepsState() ?? [];
            $status = $state[$key]['status'] ?? 'pending';
            if ($status !== 'pending') {
                continue;
            }
            if ($this->countStepSourceElements($log->getSchool(), $log->getSourcePeriod(), $key) > 0) {
                break;
            }
            $this->setStepStatus($log, $key, 'skipped', ['message' => '0 élément à traiter.']);
            $skipped++;
        }
        if ($skipped > 0) {
            $this->em->flush();
        }
        return $skipped;
    }

    /** Nombre d'éléments sources à cloner pour une étape (0 = rien à traiter). */
    private function countStepSourceElements(School $school, SchoolPeriod $sourcePeriod, string $stepKey): int
    {
        return match ($stepKey) {
            'subject_groups' => count($this->em->getRepository(SubjectGroup::class)->findBy(['school' => $school, 'period' => $sourcePeriod])),
            'classes'        => count($this->em->getRepository(SchoolClassPeriod::class)->findBy(['school' => $school, 'period' => $sourcePeriod])),
            'subjects'       => $this->sumSourceRelation($school, $sourcePeriod, 'getSchoolClassSubjects'),
            'modules'        => $this->sumSourceRelation($school, $sourcePeriod, 'getClassSubjectModules'),
            'payment_modals' => $this->sumSourceRelation($school, $sourcePeriod, 'getPaymentModals'),
            default          => 0,
        };
    }

    /** Clôture : statut executed, executedAt = maintenant, étapes encore pending marquées skipped. */
    public function finishMigration(MigrationLog $log): void
    {
        if ($log->getStatus() !== 'in_progress') {
            throw new \LogicException('La migration n\'est pas en cours.');
        }

        $state = $log->getStepsState() ?? [];
        foreach (self::STEPS as $key => $label) {
            $status = $state[$key]['status'] ?? 'pending';
            if ($status === 'failed') {
                throw new \LogicException('Une étape est en échec : réessayez-la ou ignorez-la avant de terminer.');
            }
            if ($status === 'pending') {
                $this->setStepStatus($log, $key, 'skipped', ['message' => 'Non exécutée avant la clôture.']);
            }
        }

        $log->setStatus('executed')->setExecutedAt(new \DateTimeImmutable());
        $this->em->flush();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS DU WIZARD
    // ─────────────────────────────────────────────────────────────────────────

    private function initStepState(string $status, string $message = ''): array
    {
        return ['status' => $status, 'created' => 0, 'existing' => 0, 'errors' => 0, 'message' => $message];
    }

    /** Écrit (ou fusionne) l'entrée stepsState d'une étape en préservant les clés internes (_class_map…). */
    private function setStepStatus(MigrationLog $log, string $stepKey, string $status, array $extra = []): void
    {
        $state = $log->getStepsState() ?? [];
        $entry = array_merge($this->initStepState($status), $state[$stepKey] ?? [], $extra);
        $entry['status'] = $status;
        $state[$stepKey] = $entry;
        $log->setStepsState($state);
    }

    /** @return array<int, SchoolClassPeriod> map sourceId => classe cible persistée par l'étape classes */
    private function loadClassMap(MigrationLog $log): array
    {
        $state = $log->getStepsState() ?? [];
        $map   = [];
        foreach (($state['classes']['_class_map'] ?? []) as $oldId => $newId) {
            $scp = $this->em->getRepository(SchoolClassPeriod::class)->find($newId);
            if ($scp) { $map[(int) $oldId] = $scp; }
        }
        return $map;
    }

    /** @return array<int, SubjectGroup> map oldId => groupe cible persisté par l'étape subject_groups */
    private function loadGroupMap(MigrationLog $log): array
    {
        $state = $log->getStepsState() ?? [];
        $map   = [];
        foreach (($state['_group_map'] ?? []) as $oldId => $newId) {
            $group = $this->em->getRepository(SubjectGroup::class)->find($newId);
            if ($group) { $map[(int) $oldId] = $group; }
        }
        return $map;
    }

    private function hasTargetClasses(School $school, SchoolPeriod $targetPeriod): bool
    {
        return count($this->em->getRepository(SchoolClassPeriod::class)->findBy([
            'school' => $school, 'period' => $targetPeriod,
        ])) > 0;
    }

    /** Total d'éléments sources concernés par une étape (pour le compteur « déjà existants »). */
    private function countSourceItems(School $school, SchoolPeriod $sourcePeriod, string $stepKey): int
    {
        return match ($stepKey) {
            'subject_groups' => count($this->em->getRepository(SubjectGroup::class)->findBy(['school' => $school, 'period' => $sourcePeriod])),
            'classes'        => count($this->em->getRepository(SchoolClassPeriod::class)->findBy(['school' => $school, 'period' => $sourcePeriod])),
            'subjects'       => $this->sumSourceRelation($school, $sourcePeriod, 'getSchoolClassSubjects'),
            'modules'        => $this->sumSourceRelation($school, $sourcePeriod, 'getClassSubjectModules'),
            'payment_modals' => $this->sumSourceRelation($school, $sourcePeriod, 'getPaymentModals'),
            default          => 0,
        };
    }

    private function sumSourceRelation(School $school, SchoolPeriod $sourcePeriod, string $getter): int
    {
        $total = 0;
        foreach ($this->em->getRepository(SchoolClassPeriod::class)->findBy(['school' => $school, 'period' => $sourcePeriod]) as $scp) {
            $total += $scp->$getter()->count();
        }
        return $total;
    }

    /**
     * Classe cible issue du mapping explicite, validée (école + période cibles).
     * null, '' ou 0 = pas de mapping ; id introuvable ou hors période cible = invalide.
     */
    private function resolveExplicitTarget(School $school, SchoolPeriod $targetPeriod, mixed $targetId): ?SchoolClassPeriod
    {
        if (!$targetId) { return null; }
        $scp = $this->em->getRepository(SchoolClassPeriod::class)->find($targetId);
        if ($scp && $scp->getSchool() === $school && $scp->getPeriod() === $targetPeriod) {
            return $scp;
        }
        return null;
    }

    /**
     * ÉTAPE ÉLÈVES : inscrit chaque élève dans sa classe cible.
     * Promus : choix individuel de l'élève prioritaire (classes à plusieurs suivantes),
     * sinon mapping explicite du formulaire, sinon l'occurrence suivante configurée sur
     * l'occurrence source (exactement une → automatique ; plusieurs ou aucune → non affecté).
     * Redoublants : classe de même occurrence (inchangé).
     */
    private function runStudentsStep(MigrationLog $log, array $classMapping, array $studentMapping = []): array
    {
        $school       = $log->getSchool();
        $sourcePeriod = $log->getSourcePeriod();
        $targetPeriod = $log->getTargetPeriod();
        $passingGrade = $log->getPassingGrade();
        $grades       = $this->loadPassingGrades($log);

        $promoted = 0; $repeated = 0; $skipped = 0; $existingCount = 0; $errors = 0;
        $studentClasses = [];
        $skippedStudents = [];

        $sourceClasses     = $this->em->getRepository(SchoolClassPeriod::class)->findBy([
            'school' => $school, 'period' => $sourcePeriod,
        ]);
        $repeaterTargetMap = $this->buildRepeaterTargetMap($school, $targetPeriod);
        $targetByOccurence = $this->getTargetSCPsByOccurence($school, $targetPeriod);

        foreach ($sourceClasses as $sourceSCP) {
            $sourceId   = $sourceSCP->getId();
            $occurence  = $sourceSCP->getClassOccurence();
            $occId      = $occurence?->getId();
            $sourceName = $occurence?->getName() ?? '—';
            $config     = $occurence !== null ? $this->getSchoolConfig($school, $occurence) : null;
            $finalClass = $config !== null && $config->isFinalLevel();

            // Mapping explicite valide → prioritaire ; invalide (hors période cible) →
            // signalé, l'élève reste non affecté.
            $explicitId     = $classMapping[$sourceId] ?? null;
            $invalidMapping = false;
            $promotedTarget = null;
            if (!empty($explicitId)) {
                $promotedTarget = $this->resolveExplicitTarget($school, $targetPeriod, $explicitId);
                if ($promotedTarget === null) { $invalidMapping = true; }
            }
            // Sans mapping explicite : occurrences suivantes configurées pour CETTE école
            // sur l'occurrence source. Exactement une occurrence suivante → affectation
            // automatique ; plusieurs ou aucune → les promus restent non affectés
            // (choix explicite requis / niveau final).
            if (!$invalidMapping && $promotedTarget === null && $config !== null) {
                $candidates = [];
                foreach ($config->getNextOccurences() as $nextOccurence) {
                    foreach ($targetByOccurence[$nextOccurence->getId()] ?? [] as $targetSCP) {
                        $candidates[] = $targetSCP;
                    }
                }
                if (count($candidates) === 1) { $promotedTarget = $candidates[0]; }
            }

            $repeaterTarget = $occId ? ($repeaterTargetMap[$occId] ?? null) : null;

            foreach ($sourceSCP->getStudentClasses() as $studentClass) {
                $avg        = $this->calculateStudentAverage($studentClass, $sourceSCP);
                $grade      = $occId !== null ? ($grades[$occId] ?? $passingGrade) : $passingGrade;
                $isEligible = $avg !== null && $avg >= $grade;

                // Choix individuel (classes à plusieurs suivantes) : prioritaire sur le reste.
                $studentChoiceId      = $studentMapping[$studentClass->getId()] ?? null;
                $studentChoiceTarget  = null;
                $invalidStudentChoice = false;
                if (!empty($studentChoiceId)) {
                    $studentChoiceTarget = $this->resolveExplicitTarget($school, $targetPeriod, $studentChoiceId);
                    if ($studentChoiceTarget === null) { $invalidStudentChoice = true; }
                }

                $student = $studentClass->getStudent();
                $name    = $student->getFullName() ?? $student->getUsername();

                if ($isEligible && $invalidStudentChoice) {
                    $errors++; $skipped++;
                    $skippedStudents[] = ['name' => $name, 'className' => $sourceName, 'average' => $avg, 'reason' => 'Classe cible invalide.'];
                } elseif ($isEligible && $studentChoiceTarget) {
                    $sc = $this->enrollStudent($student, $studentChoiceTarget);
                    if ($sc) { $studentClasses[] = $sc; $promoted++; }
                    else { $existingCount++; } // déjà inscrit dans la classe cible
                } elseif ($isEligible && $invalidMapping) {
                    $errors++; $skipped++;
                    $skippedStudents[] = ['name' => $name, 'className' => $sourceName, 'average' => $avg, 'reason' => 'Classe cible invalide.'];
                } elseif ($isEligible && $promotedTarget) {
                    $sc = $this->enrollStudent($student, $promotedTarget);
                    if ($sc) { $studentClasses[] = $sc; $promoted++; }
                    else { $existingCount++; }
                } elseif (!$isEligible && $repeaterTarget) {
                    $sc = $this->enrollStudent($student, $repeaterTarget);
                    if ($sc) { $studentClasses[] = $sc; $repeated++; }
                    else { $existingCount++; }
                } else {
                    $skipped++;
                    $reason = match (true) {
                        $isEligible && $finalClass => 'Classe terminale : l\'élève quitte l\'établissement.',
                        $isEligible               => 'Aucune classe cible définie pour la classe source.',
                        $avg === null             => 'Moyenne indisponible (aucune évaluation).',
                        default                   => 'Aucune classe équivalente pour les redoublants.',
                    };
                    $skippedStudents[] = ['name' => $name, 'className' => $sourceName, 'average' => $avg, 'reason' => $reason];
                }
            }
        }

        return [
            'created'         => count($studentClasses),
            'existing'        => $existingCount,
            'errors'          => $errors,
            'promoted'        => $promoted,
            'repeated'        => $repeated,
            'skipped'         => $skipped,
            'message'         => sprintf('%d inscrit(s), %d déjà inscrit(s), %d non affecté(s).', count($studentClasses), $existingCount, $skipped),
            'studentClasses'  => $studentClasses,
            'skippedStudents' => $skippedStudents,
        ];
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
        $school       = $log->getSchool();
        $targetPeriod = $log->getTargetPeriod();
        $conn = $this->em->getConnection();
        $conn->beginTransaction();

        try {
            $preview       = $this->previewCorrection($log, $newPassingGrade);
            $applied       = ['demoted' => 0, 'promoted' => 0, 'added' => 0];
            $createdIds    = $log->getCreatedIds();
            $newStudentClasses = []; // inscriptions créées pendant la correction (IDs collectés après flush)
            $repeaterTargetMap = $this->buildRepeaterTargetMap($school, $targetPeriod);

            // Classe cible d'une promotion : mapping explicite validé, sinon classe de même
            // occurrence dans la période cible (BUG1 : retrouve les classes auto-créées par la migration).
            $resolvePromotionTarget = function (SchoolClassPeriod $sourceSCP) use ($school, $targetPeriod, $classMapping, $repeaterTargetMap): ?SchoolClassPeriod {
                $target = $this->resolveExplicitTarget($school, $targetPeriod, $classMapping[$sourceSCP->getId()] ?? null);
                if ($target !== null) { return $target; }
                $occId = $sourceSCP->getClassOccurence()?->getId();
                return $occId !== null ? ($repeaterTargetMap[$occId] ?? null) : null;
            };

            // Rétrograder les promus sans notes → les déplacer vers la classe redoublant
            foreach ($preview['toDemote'] as $item) {
                if ($item['targetSC'] && $item['repeaterSCP']) {
                    $this->em->remove($item['targetSC']);
                    $key = array_search($item['targetSC']->getId(), $createdIds['studentClasses']);
                    if ($key !== false) { unset($createdIds['studentClasses'][$key]); }

                    $newSC = $this->enrollStudent($item['student'], $item['repeaterSCP']);
                    if ($newSC) { $newStudentClasses[] = $newSC; $applied['demoted']++; }
                }
            }

            // Promouvoir les redoublants sans notes → les déplacer vers la classe promu
            foreach ($preview['toPromote'] as $item) {
                $newTargetSCP = $resolvePromotionTarget($item['sourceSCP']);

                if ($item['targetSC'] && $newTargetSCP) {
                    $this->em->remove($item['targetSC']);
                    $key = array_search($item['targetSC']->getId(), $createdIds['studentClasses']);
                    if ($key !== false) { unset($createdIds['studentClasses'][$key]); }

                    $newSC = $this->enrollStudent($item['student'], $newTargetSCP);
                    if ($newSC) { $newStudentClasses[] = $newSC; $applied['promoted']++; }
                }
            }

            // Ajouter les ignorés devenus éligibles
            foreach ($preview['toAdd'] as $item) {
                $newTargetSCP = $resolvePromotionTarget($item['sourceSCP']);

                if ($newTargetSCP) {
                    $newSC = $this->enrollStudent($item['student'], $newTargetSCP);
                    if ($newSC) { $newStudentClasses[] = $newSC; $applied['added']++; }
                }
            }

            $this->em->flush();

            // IDs des nouvelles inscriptions collectés APRÈS flush (plus de marqueurs 0 ni de
            // getScheduledEntityInsertions, toujours vide après flush : BUG d)
            foreach ($newStudentClasses as $sc) {
                $createdIds['studentClasses'][] = $sc->getId();
            }
            $createdIds['studentClasses'] = array_values(array_unique(array_filter(
                $createdIds['studentClasses'],
                fn($id) => is_int($id) && $id > 0
            )));

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

    /** @var array<int, array|null> Contexte de calcul par classe (mémo valable par requête) */
    private array $classAverageContextCache = [];

    /**
     * Moyenne annuelle d'un élève, fidèle au PV annuel (EvaluationController::bordereauGeneral,
     * evaluationFrameId='all') : moyenne des moyennes par période d'évaluation > 0 — la
     * sémantique « partielle » que le PV affiche en réalité pour tous les élèves (finalAverages
     * pour les classés, qui est identique car ils ont une moyenne > 0 à chaque période ;
     * partialAverages pour les non classés). Toute évolution du calcul du PV doit être
     * répercutée ici : voir EvaluationController lignes ~1017-1018 et ~1324-1451.
     */
    private function calculateStudentAverage(StudentClass $studentClass, SchoolClassPeriod $scp): ?float
    {
        $evaluations = $studentClass->getEvaluations();
        if ($evaluations->isEmpty()) { return null; }

        $ctx = $this->getClassAverageContext($scp);
        if ($ctx === null) { return null; } // classe sans module ou sans aucune évaluation

        // Notes de l'élève : timeId => moduleId => note (modules de la classe uniquement,
        // le PV ignore les évaluations hors modules de la classe)
        $notes = [];
        foreach ($evaluations as $eval) {
            $module = $eval->getClassSubjectModule();
            $time   = $eval->getTime();
            if ($module === null || $time === null) { continue; }
            $moduleId = $module->getId();
            if (!isset($ctx['moduleIds'][$moduleId])) { continue; }
            $notes[$time->getId()][$moduleId] = $eval->getEvaluationNote() ?? 0;
        }

        $sumOfAverages   = 0.0;
        $positivePeriods = 0;
        foreach ($ctx['timeIds'] as $timeId) {
            $periodAvg = $this->calculatePeriodAverage($timeId, $notes, $ctx);
            $sumOfAverages += $periodAvg;
            if ($periodAvg > 0) { $positivePeriods++; }
        }

        return $positivePeriods > 0 ? round($sumOfAverages / $positivePeriods, 2) : 0.0;
    }

    /**
     * Contexte de calcul commun à tous les élèves d'une classe (mémorisé par classe) :
     * - timeIds : times distincts de TOUTES les évaluations des modules de la classe
     *   (équivalent de BulletinDataService::getAllTimesGroupedByFrame) ;
     * - subjects : groupement des modules par matière + coefficient = PREMIÈRE ligne
     *   SchoolClassSubject du couple (classe, matière), 0 si absente
     *   (fidèle à EvaluationController::calculateBordereauData) ;
     * - totalNotation : somme des moduleNotation de TOUS les modules de la classe
     *   (dénominateur du mode sans coefficients, notes à 0 incluses) ;
     * - coefUsed : template présent && nom != 'D' (EvaluationController 1017-1018).
     *
     * @return array{timeIds:int[],moduleIds:array<int,true>,subjects:array<int,array{moduleIds:int[],coef:int}>,totalNotation:float,coefUsed:bool}|null
     */
    private function getClassAverageContext(SchoolClassPeriod $scp): ?array
    {
        $scpId = $scp->getId();
        if (isset($this->classAverageContextCache[$scpId])) {
            return $this->classAverageContextCache[$scpId];
        }

        $modules = $scp->getClassSubjectModules()->getValues();
        if (empty($modules)) {
            return $this->classAverageContextCache[$scpId] = null;
        }

        $moduleIds = array_map(fn (ClassSubjectModule $m) => $m->getId(), $modules);

        $timeIds = $this->em->createQueryBuilder()
            ->select('DISTINCT IDENTITY(e.time)')
            ->from(Evaluation::class, 'e')
            ->where('IDENTITY(e.classSubjectModule) IN (:moduleIds)')
            ->setParameter('moduleIds', $moduleIds)
            ->getQuery()
            ->getSingleColumnResult();

        if (empty($timeIds)) {
            return $this->classAverageContextCache[$scpId] = null;
        }

        $subjects      = [];
        $totalNotation = 0.0;
        foreach ($modules as $module) {
            $subject = $module->getSubject();
            if ($subject === null) { continue; } // défensif : colonne NOT NULL en base
            $subjects[$subject->getId()]['moduleIds'][] = $module->getId();
            $totalNotation += $module->getModuleNotation() ?? 0;
        }

        foreach ($subjects as $subjectId => &$subjectData) {
            $schoolClassSubjects = $this->em->getRepository(SchoolClassSubject::class)->findBy([
                'schoolClassPeriod' => $scp,
                'studySubject'      => $subjectId,
            ]);
            // Le PV prend findBy(...)[0] ; getCoefficient() est NOT NULL (défaut 1) ; 0 si aucune ligne
            $subjectData['coef'] = !empty($schoolClassSubjects) ? ($schoolClassSubjects[0]->getCoefficient() ?? 1) : 0;
        }
        unset($subjectData);

        $template = $scp->getReportCardTemplate();

        return $this->classAverageContextCache[$scpId] = [
            'timeIds'       => array_map('intval', $timeIds),
            'moduleIds'     => array_fill_keys($moduleIds, true),
            'subjects'      => $subjects,
            'totalNotation' => $totalNotation,
            'coefUsed'      => $template !== null && $template->getName() !== 'D',
        ];
    }

    /**
     * Moyenne de l'élève sur UNE période d'évaluation, fidèle à
     * EvaluationController::calculateBordereauData (lignes ~1369-1451).
     *
     * @param array<int, array<int, float>> $notes notes[timeId][moduleId] de l'élève
     * @param array{timeIds:int[],moduleIds:array<int,true>,subjects:array<int,array{moduleIds:int[],coef:int}>,totalNotation:float,coefUsed:bool} $ctx
     */
    private function calculatePeriodAverage(int $timeId, array $notes, array $ctx): float
    {
        if ($ctx['coefUsed']) {
            // Mode coefficient : moyenne matière = moyenne arithmétique des notes > 0,
            // pondérée par le coefficient SchoolClassSubject
            $weightedSum = 0.0;
            $totalCoef   = 0;
            foreach ($ctx['subjects'] as $subjectData) {
                $gradedTotal = 0.0;
                $gradedCount = 0;
                foreach ($subjectData['moduleIds'] as $moduleId) {
                    $note = $notes[$timeId][$moduleId] ?? 0;
                    if ($note > 0) {
                        $gradedTotal += $note;
                        $gradedCount++;
                    }
                }
                $subjectAvg    = $gradedCount > 0 ? $gradedTotal / $gradedCount : 0;
                $weightedSum  += $subjectAvg * $subjectData['coef'];
                $totalCoef    += $subjectData['coef'];
            }
            return $totalCoef > 0 ? $weightedSum / $totalCoef : 0;
        }

        // Mode par défaut (template 'D') : somme des notes de tous les modules
        // (notes à 0 incluses), divisée par la notation totale de la classe, ramenée sur 20
        $weightedSum = 0.0;
        foreach ($ctx['subjects'] as $subjectData) {
            foreach ($subjectData['moduleIds'] as $moduleId) {
                $weightedSum += $notes[$timeId][$moduleId] ?? 0;
            }
        }
        return $ctx['totalNotation'] > 0 ? $weightedSum / $ctx['totalNotation'] * 20 : 0;
    }

    /** @return array{0: array<int,SubjectGroup>, 1: SubjectGroup[]} [map oldId→entity, list] */
    private function cloneSubjectGroups(School $school, SchoolPeriod $source, SchoolPeriod $target): array
    {
        $map      = [];
        $entities = [];

        // Groupes déjà présents dans la période cible, indexés par description :
        // on ne migre que ce qui n'existe pas encore.
        $existingByDescription = [];
        foreach ($this->em->getRepository(SubjectGroup::class)->findBy(['school' => $school, 'period' => $target]) as $existing) {
            $desc = $existing->getDescription();
            if ($desc !== null) { $existingByDescription[$desc] = $existing; }
        }
        $reusedNullDesc = [];

        foreach ($this->em->getRepository(SubjectGroup::class)->findBy(['school' => $school, 'period' => $source]) as $group) {
            $sourceDesc = $group->getDescription();
            $clonedDesc = $sourceDesc !== null ? $sourceDesc . ' (' . $target->getName() . ')' : null;

            if ($clonedDesc !== null && isset($existingByDescription[$clonedDesc])) {
                $map[$group->getId()] = $existingByDescription[$clonedDesc];
                unset($existingByDescription[$clonedDesc]); // un groupe cible ne sert qu'une fois
                continue;
            }

            if ($clonedDesc === null) {
                // Description vide : réutiliser un groupe cible sans description au même posOrder
                foreach ($this->em->getRepository(SubjectGroup::class)->findBy(['school' => $school, 'period' => $target, 'posOrder' => $group->getPosOrder()]) as $candidate) {
                    if ($candidate->getDescription() === null && !isset($reusedNullDesc[spl_object_id($candidate)])) {
                        $map[$group->getId()] = $candidate;
                        $reusedNullDesc[spl_object_id($candidate)] = true;
                        break;
                    }
                }
                if (isset($map[$group->getId()])) { continue; }
            }

            $new = new SubjectGroup();
            $new->setSchool($school)->setPeriod($target)->setPosOrder($group->getPosOrder());
            if ($sourceDesc !== null) {
                $new->setDescription($clonedDesc);
            }
            $this->em->persist($new);
            $map[$group->getId()] = $new;
            $entities[]           = $new;
        }
        return [$map, $entities];
    }

    /** @return array{0: array<int,SchoolClassPeriod>, 1: SchoolClassPeriod[]} */
    private function cloneClasses(School $school, SchoolPeriod $source, SchoolPeriod $target, array $classMapping): array
    {
        $map      = [];
        $entities = [];

        // Classes déjà présentes dans la période cible, indexées par occurrence :
        // on les réutilise au lieu de les dupliquer.
        $targetByOccurence = [];
        foreach ($this->em->getRepository(SchoolClassPeriod::class)->findBy(['school' => $school, 'period' => $target]) as $existing) {
            $occId = $existing->getClassOccurence()?->getId();
            if ($occId !== null && !isset($targetByOccurence[$occId])) { $targetByOccurence[$occId] = $existing; }
        }

        foreach ($this->em->getRepository(SchoolClassPeriod::class)->findBy(['school' => $school, 'period' => $source]) as $scp) {
            $existing = null;

            // 1. Mapping explicite choisi par l'utilisateur (classe cible existante).
            //    Un mapping vide ou pointant vers une classe hors période cible est ignoré :
            //    on retombe sur la résolution par occurrence ou la création.
            if (!empty($classMapping[$scp->getId()] ?? null)) {
                $existing = $this->em->getRepository(SchoolClassPeriod::class)->find($classMapping[$scp->getId()]);
                if ($existing && ($existing->getSchool() !== $school || $existing->getPeriod() !== $target)) {
                    $existing = null;
                }
            }

            // 2. Classe cible existante avec la même occurrence
            $occId = $scp->getClassOccurence()?->getId();
            if (!$existing && $occId !== null) { $existing = $targetByOccurence[$occId] ?? null; }

            if ($existing) {
                $map[$scp->getId()] = $existing;
                continue;
            }

            // 3. Sinon, création
            $new = new SchoolClassPeriod();
            $new->setSchool($school)->setPeriod($target)
                ->setClassOccurence($scp->getClassOccurence())
                ->setClassMaster($scp->getClassMaster())
                ->setEvaluationAppreciationTemplate($scp->getEvaluationAppreciationTemplate())
                ->setReportCardTemplate($scp->getReportCardTemplate());
            $this->em->persist($new);
            if ($occId !== null) { $targetByOccurence[$occId] = $new; }
            $map[$scp->getId()] = $new;
            $entities[]         = $new;
        }
        return [$map, $entities];
    }

    /** @return array{0: int, 1: SchoolClassSubject[]} */
    private function cloneSubjects(array $classMap, array $groupMap): array
    {
        $entities = [];
        $seen     = [];
        foreach ($classMap as $oldSCPId => $newSCP) {
            $oldSCP = $this->em->getRepository(SchoolClassPeriod::class)->find($oldSCPId);
            if (!$oldSCP) { continue; }

            // Matières déjà présentes sur la classe cible : ne pas les dupliquer
            $existingSubjects = [];
            foreach ($newSCP->getSchoolClassSubjects() as $existing) {
                $existingSubjects[$existing->getStudySubject()?->getId()] = true;
            }

            foreach ($oldSCP->getSchoolClassSubjects() as $scs) {
                $subjectId = $scs->getStudySubject()?->getId();
                if ($subjectId !== null && isset($existingSubjects[$subjectId])) { continue; }
                if (isset($seen[spl_object_id($newSCP)][$subjectId])) { continue; }

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
                $seen[spl_object_id($newSCP)][$subjectId] = true;
            }
        }
        return [count($entities), $entities];
    }

    /** @return array{0: int, 1: ClassSubjectModule[]} */
    private function cloneModules(School $school, SchoolPeriod $source, SchoolPeriod $target, array $classMap): array
    {
        $entities = [];
        $seen     = [];
        foreach ($classMap as $oldSCPId => $newSCP) {
            $oldSCP = $this->em->getRepository(SchoolClassPeriod::class)->find($oldSCPId);
            if (!$oldSCP) { continue; }

            // Modules déjà présents sur la classe cible : ne pas les dupliquer
            $existingModules = [];
            foreach ($newSCP->getClassSubjectModules() as $existing) {
                $existingModules[$existing->getSubject()?->getId() . ':' . $existing->getModule()?->getId()] = true;
            }

            foreach ($oldSCP->getClassSubjectModules() as $csm) {
                $key = $csm->getSubject()?->getId() . ':' . $csm->getModule()?->getId();
                if (isset($existingModules[$key])) { continue; }
                if (isset($seen[spl_object_id($newSCP)][$key])) { continue; }

                $new = new ClassSubjectModule();
                $new->setSchool($school)->setPeriod($target)->setClass($newSCP)
                    ->setSubject($csm->getSubject())->setModule($csm->getModule())
                    ->setModuleNotation($csm->getModuleNotation());
                $this->em->persist($new);
                $entities[] = $new;
                $seen[spl_object_id($newSCP)][$key] = true;
            }
        }
        return [count($entities), $entities];
    }

    /** @return array{0: int, 1: SchoolClassPaymentModal[]} */
    private function clonePaymentModals(School $school, SchoolPeriod $source, SchoolPeriod $target, array $classMap): array
    {
        $entities = [];
        $seen     = [];
        foreach ($classMap as $oldSCPId => $newSCP) {
            $oldSCP = $this->em->getRepository(SchoolClassPeriod::class)->find($oldSCPId);
            if (!$oldSCP) { continue; }

            // Modalités déjà présentes sur la classe cible : ne pas les dupliquer
            $existingModals = [];
            foreach ($newSCP->getPaymentModals() as $existing) {
                $existingModals[$existing->getModalType() . ':' . $existing->getLabel()] = true;
            }

            foreach ($oldSCP->getPaymentModals() as $modal) {
                $key = $modal->getModalType() . ':' . $modal->getLabel();
                if (isset($existingModals[$key])) { continue; }
                if (isset($seen[spl_object_id($newSCP)][$key])) { continue; }

                $new = new SchoolClassPaymentModal();
                $new->setSchool($school)->setSchoolPeriod($target)->setSchoolClassPeriod($newSCP)
                    ->setLabel($modal->getLabel())->setAmount($modal->getAmount())
                    ->setDueDate($modal->getDueDate())->setModalType($modal->getModalType())
                    ->setModalPriority($modal->getModalPriority());
                $this->em->persist($new);
                $entities[] = $new;
                $seen[spl_object_id($newSCP)][$key] = true;
            }
        }
        return [count($entities), $entities];
    }

    /**
     * Chaîne de promotion de l'école pour une occurrence (null si jamais configurée
     * pour cette école). L'occurrence est globale : chaque école a sa propre config.
     */
    private function getSchoolConfig(School $school, ?ClassOccurence $occurence): ?ClassOccurenceSchoolConfig
    {
        if ($occurence === null) { return null; }
        return $this->em->getRepository(ClassOccurenceSchoolConfig::class)
            ->findOneBy(['school' => $school, 'classOccurence' => $occurence]);
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

    /** @return array<int, SchoolClassPeriod[]> classes de la période cible groupées par occurrence (une requête). */
    private function getTargetSCPsByOccurence(School $school, SchoolPeriod $targetPeriod): array
    {
        $map = [];
        foreach ($this->em->getRepository(SchoolClassPeriod::class)->findBy(['school' => $school, 'period' => $targetPeriod]) as $scp) {
            $occId = $scp->getClassOccurence()?->getId();
            if ($occId !== null) { $map[$occId][] = $scp; }
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
