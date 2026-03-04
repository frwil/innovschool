<?php

namespace App\Service;

use App\Contract\GenderEnum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Html;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Entity\StudentClass;
use App\Entity\SchoolClassPeriod;
use App\Entity\EvaluationAppreciationTemplate;
use App\Repository\StudentClassRepository;
use App\Repository\SchoolClassPeriodRepository;
use App\Repository\EvaluationRepository;
use App\Repository\SubjectGroupRepository;
use App\Repository\SchoolClassSubjectRepository;
use App\Repository\SchoolPeriodRepository;
use App\Repository\ClassSubjectModuleRepository;
use App\Repository\EvaluationAppreciationTemplateRepository;
use App\Service\AppreciationService;
use App\Repository\EvaluationAppreciationBaremeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Repository\SchoolEvaluationTimeRepository;
use App\Repository\SchoolEvaluationFrameRepository;
use App\Repository\StudySubjectRepository;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use App\Entity\School;
use App\Entity\SchoolPeriod;
use App\Entity\StudentClassAttendance;
use App\Entity\SubjectModule;
use App\Entity\SubjectsModules;
use App\Entity\SchoolClassSubject;
use App\Repository\StudentClassAttendanceRepository;
use Psr\Log\LoggerInterface;
use App\Entity\ReportCardTemplate;
use Proxies\__CG__\App\Entity\StudentClass as EntityStudentClass;

class BulletinGenerator
{
    private $studentRepo;
    private $classRepo;
    private $evaluationRepo;
    private $subjectGroupRepo;
    private $schoolClassSubjectRepo;
    private $periodRepo;
    private $subjectModuleRepo;
    private $appreciationTemplateRepo;
    private $appreciationService;
    private $appreciationBaremeRepo;
    private $params;
    private $timeRepo;
    private $frameRepo;
    private $studySubjectRepo;
    private School $currentSchool;
    private SchoolPeriod $currentPeriod;
    private EntityManagerInterface $entityManager;
    private StudentClassAttendanceRepository $attendanceRepo;
    private LoggerInterface $logger;

    public function __construct(
        StudentClassRepository $studentRepo,
        SchoolClassPeriodRepository $classRepo,
        EvaluationRepository $evaluationRepo,
        SubjectGroupRepository $subjectGroupRepo,
        SchoolClassSubjectRepository $schoolClassSubjectRepo,
        SchoolPeriodRepository $periodRepo,
        ClassSubjectModuleRepository $subjectModuleRepo,
        EvaluationAppreciationTemplateRepository $appreciationTemplateRepo,
        EvaluationAppreciationBaremeRepository $appreciationBaremeRepo,
        AppreciationService $appreciationService,
        ParameterBagInterface $params,
        SchoolEvaluationTimeRepository $timeRepo,
        SchoolEvaluationFrameRepository $frameRepo,
        StudySubjectRepository $studySubjectRepo,
        EntityManagerInterface $entityManager,
        StudentClassAttendanceRepository $attendanceRepo,
        LoggerInterface $logger
    ) {
        $this->studentRepo = $studentRepo;
        $this->classRepo = $classRepo;
        $this->evaluationRepo = $evaluationRepo;
        $this->subjectGroupRepo = $subjectGroupRepo;
        $this->schoolClassSubjectRepo = $schoolClassSubjectRepo;
        $this->periodRepo = $periodRepo;
        $this->subjectModuleRepo = $subjectModuleRepo;
        $this->appreciationTemplateRepo = $appreciationTemplateRepo;
        $this->appreciationService = $appreciationService;
        $this->appreciationBaremeRepo = $appreciationBaremeRepo;
        $this->params = $params;
        $this->timeRepo = $timeRepo;
        $this->frameRepo = $frameRepo;
        $this->studySubjectRepo = $studySubjectRepo;
        $this->entityManager = $entityManager;
        $this->attendanceRepo = $attendanceRepo;
    }

    public function generateBulletinA(
        $studentId,
        $periodicityId,
        $bulletinType,
        $classId,
        $user,
        $currentSchool,
        $currentPeriod,
        EvaluationRepository $evaluationRepository,
        $reportCardTemplate = 'A',
        $progressFile = null, // Nouveau paramètre
        $currentProgress = 0,  // Nouveau paramètre
        $totalStudents = 0,     // Nouveau paramètre
        $bulLang = 'fr',        // Nouveau paramètre
        $passNote=10,
        $printType = null,
    ) {

        ini_set('pcre.backtrack_limit', '5000000');
        ini_set('pcre.recursion_limit', '5000000');
        ini_set('memory_limit', '4096M');
        // OPTIMISATION : Nettoyage mémoire
        gc_enable();

        // OPTIMISATION : Désactiver le logging SQL
        $this->entityManager->getConnection()->getConfiguration()->setSQLLogger(null);

        // Ajoutez cette partie au début de la méthode pour mettre à jour la progression
        if ($progressFile && file_exists($progressFile)) {
            $this->updateGenerationProgress($progressFile, $currentProgress, $totalStudents, "Génération du bulletin pour l'étudiant");
        }

        $student = $this->studentRepo->findByStudent($studentId);
        if (!$student) {
            throw new NotFoundHttpException('Élève non trouvé.');
        }
        $selStudent = $student;

        if ($bulletinType == 'sub-period') {
            $periodicity = $this->timeRepo->findById($periodicityId);
            $periods = $periodicity;
        } else {
            $periodicity = $this->frameRepo->findById($periodicityId);
            $periods = $this->timeRepo->findBy(['evaluationFrame' => $periodicity]);
            $periodsEval = $this->evaluationRepo->findBy(['time' => $periods]);
            $periodList = [];
            foreach ($periodsEval as $period) {
                $periodId = $period->getTime()->getId();
                if (!in_array($periodId, $periodList)) $periodList[] = $periodId;
            }
            $periods = $this->timeRepo->findBy(['id' => $periodList]);
        }
        //retrouver uniquement les périodes de la classe à partir des évaluations qui sont liés aux modules d'évaluations qui sont eux aussi liés à la période
        $periods = array_filter($periods, function ($period) use ($student) {
            return !empty(array_filter(
                $period->getEvaluations()->toArray(),
                function ($p) use ($student) {
                    return in_array($p->getStudent()->getId(), array_map(fn($s) => $s->getId(), $student));
                }
            ));
        });

        if (!$periodicity) {
            throw new NotFoundHttpException('Périodicité non trouvée.');
        }

        $school = $currentSchool;
        if (!$school) {
            throw new NotFoundHttpException('École non trouvée.');
        }

        $class = $this->classRepo->findOneBy(['id' => $classId, 'period' => $currentPeriod, 'school' => $school]);
        if (!$class) {
            throw new NotFoundHttpException('Classe non trouvée.');
        }

        $period = $currentPeriod;
        if (!$period) {
            throw new NotFoundHttpException('Aucune période active trouvée.');
        }

        // Précharge tous les élèves de la classe/période
        $students = $this->studentRepo->findBy(['schoolClassPeriod' => $class]);
        $studentsIds = array_map(fn($student) => $student->getId(), $students);

        // Précharge toutes les évaluations nécessaires en une seule requête
        $evaluations = $this->evaluationRepo->findBy([
            'time' => $periods,
            'student' => $studentsIds,
        ], ['time' => 'ASC', 'student' => 'ASC', 'classSubjectModule' => 'ASC']);

        //dd($evaluations);



        // Précharge le template d'appréciation
        $template = $class->getEvaluationAppreciationTemplate() ? $this->appreciationTemplateRepo->find($class->getEvaluationAppreciationTemplate()->getId() ?? null) : null;
        if (!$template) {
            $template = $school->getEvaluationAppreciationTemplate() ? $this->appreciationTemplateRepo->find($school->getEvaluationAppreciationTemplate()->getId() ?? null) : null;
        }
        // Indexe les évaluations par [studentId][moduleId][timeId]
        $evalIndex = [];
        $notesMatieres = [];
        foreach ($evaluations as $evaluation) {
            $sid = $evaluation->getStudent()->getStudent()->getId();
            $mid = $evaluation->getClassSubjectModule()->getId();
            $tid = $evaluation->getTime()->getId();
            $tidName = $evaluation->getTime()->getShortName();
            $suid = $evaluation->getClassSubjectModule()->getSubject()->getId();
            $evalIndex[$sid][$suid][$mid][$tid] = $evaluation;
            $evalIndex[$sid][$suid]['TotalNote'] = (isset($evalIndex[$sid][$suid]['TotalNote']) ? $evalIndex[$sid][$suid]['TotalNote'] : 0) + $evaluation->getEvaluationNote();
            $evalIndex[$sid][$suid]['TotalModule'] = (isset($evalIndex[$sid][$suid]['TotalModule']) ? $evalIndex[$sid][$suid]['TotalModule'] : 0) + $evaluation->getClassSubjectModule()->getModuleNotation();
            $evalIndex[$sid][$suid]['MoyenneModule'] = $evalIndex[$sid][$suid]['TotalNote'] / ($evalIndex[$sid][$suid]['TotalModule'] > 0 ? $evalIndex[$sid][$suid]['TotalModule'] : 1) * 20;
            $evalIndex[$sid][$suid]['MoyenneModule'] = round($evalIndex[$sid][$suid]['MoyenneModule'], 2);
            $evalIndex[$sid][$suid][$tid]['TotalNoteMatierePeriode'] = (isset($evalIndex[$sid][$suid][$tid]['TotalNoteMatierePeriode']) ? $evalIndex[$sid][$suid][$tid]['TotalNoteMatierePeriode'] : 0) + $evaluation->getEvaluationNote();
            $evalIndex[$sid][$suid][$tid]['TotalModuleMatierePeriode'] = (isset($evalIndex[$sid][$suid][$tid]['TotalModuleMatierePeriode']) ? $evalIndex[$sid][$suid][$tid]['TotalModuleMatierePeriode'] : 0) + $evaluation->getClassSubjectModule()->getModuleNotation();
            $evalIndex[$sid][$suid][$tid]['MoyenneModuleMatierePeriode'] = $evalIndex[$sid][$suid][$tid]['TotalNoteMatierePeriode'] / ($evalIndex[$sid][$suid][$tid]['TotalModuleMatierePeriode'] > 0 ? $evalIndex[$sid][$suid][$tid]['TotalModuleMatierePeriode'] : 1) * 20;
            $evalIndex[$sid][$suid][$tid]['MoyenneModuleMatierePeriode'] = round($evalIndex[$sid][$suid][$tid]['MoyenneModuleMatierePeriode'], 2);
            $evalIndex[$sid][$tidName]['TotalNote'] = (isset($evalIndex[$sid][$tidName]['TotalNote']) ? $evalIndex[$sid][$tidName]['TotalNote'] : 0) + $evaluation->getEvaluationNote();
            //if($tidName=='UA2' && $sid==17) echo $evaluation->getEvaluationNote(),'-',$evaluation->getClassSubjectModule()->getSubject()->getName(),'-',$evalIndex[$sid][$tidName]['TotalNote'],'/';
            $evalIndex[$sid][$tidName]['TotalModule'] = (isset($evalIndex[$sid][$tidName]['TotalModule']) ? $evalIndex[$sid][$tidName]['TotalModule'] : 0) + $evaluation->getClassSubjectModule()->getModuleNotation();
            $evalIndex[$sid][$tidName]['MoyennePeriode'] = $evalIndex[$sid][$tidName]['TotalNote'] / ($evalIndex[$sid][$tidName]['TotalModule'] > 0 ? $evalIndex[$sid][$tidName]['TotalModule'] : 1) * 20;
            $evalIndex[$sid][$tidName]['MoyennePeriode'] = round($evalIndex[$sid][$tidName]['MoyennePeriode'], 2);
            $evalIndex[$sid]['TotalNote'] = (isset($evalIndex[$sid]['TotalNote']) ? $evalIndex[$sid]['TotalNote'] : 0) + $evaluation->getEvaluationNote();
            $evalIndex[$sid]['TotalModule'] = (isset($evalIndex[$sid]['TotalModule']) ? $evalIndex[$sid]['TotalModule'] : 0) + $evaluation->getClassSubjectModule()->getModuleNotation();
            $evalIndex[$sid]['Moyenne'] = $evalIndex[$sid]['TotalNote'] / ($evalIndex[$sid]['TotalModule'] > 0 ? $evalIndex[$sid]['TotalModule'] : 1) * 20;
            $evalIndex[$sid]['Moyenne'] = round($evalIndex[$sid]['Moyenne'], 2);
            $evalIndex[$sid]['Appreciation'] = $template ? $this->appreciationService->getAppreciationByNote($template, $evalIndex[$sid]['Moyenne']) : '';
        }


        $moyennesPeriodes = [];
        foreach ($students as $eleve) {
            $sid = $eleve->getStudent()->getId();
            // Correction : vérifier l'existence de la clé avant d'accéder
            $moyennes[] = isset($evalIndex[$sid]['Moyenne']) ? $evalIndex[$sid]['Moyenne'] : 0;
            $moyennesPeriodes[] = [];
            foreach ($periods as $periode) {
                $tid = $periode->getId();
                $tidName = $periode->getShortName();
                $evalIndex[$sid]['periods'][] = $periode;
                $moyennesPeriodes[$tid][] = isset($evalIndex[$sid][$tidName]['MoyennePeriode']) ? $evalIndex[$sid][$tidName]['MoyennePeriode'] . '-' . $sid : '0-' . $sid;
            }
        }

        foreach ($students as $eleve) {
            $sid = $eleve->getStudent()->getId();
            $evalIndex[$sid]['Rang'] = $this->getRank(isset($evalIndex[$sid]['Moyenne']) ? $evalIndex[$sid]['Moyenne'] : 0, $moyennes);
            foreach ($periods as $periode) {
                $tid = $periode->getId();
                $tidName = $periode->getShortName();
                $evalIndex[$sid][$tidName]['RangPeriode'] = $this->getRank(isset($evalIndex[$sid][$tidName]['MoyennePeriode']) ? $evalIndex[$sid][$tidName]['MoyennePeriode'] : 0, $moyennesPeriodes[$tid]);
            }
        }


        // Précharge tous les groupes de matières
        $subjectGroups = $this->entityManager->getRepository(SchoolClassSubject::class)->findBy(['schoolClassPeriod' => $class], ['group' => 'ASC']);
        $subjectGroupsIds = array_map(fn($subjectGroup) => $subjectGroup->getGroup() ? $subjectGroup->getGroup()->getId() : null, $subjectGroups);
        $subjectGroups = $this->subjectGroupRepo->findBy(['id' => $subjectGroupsIds], ['description' => 'ASC']);
        $subjects = $this->studySubjectRepo->findBy([], ['name' => 'ASC']);

        // Précharge tous les modules pour tous les subjects
        $subjectIds = array_map(fn($subject) => $subject->getId(), $subjects);
        $modules = $this->subjectModuleRepo->findBy([
            'subject' => $subjectIds,
            'period' => $period,
            'school' => $school,
            'class' => $class
        ]);
        // Indexe les modules par subjectId
        $modulesBySubject = [];
        foreach ($modules as $module) {
            $modulesBySubject[$module->getSubject()->getId()][] = $module;
        }

        // Au début de la méthode generateBulletinA, après avoir chargé les évaluations
        $completionRates = [];
        foreach ($students as $student) {
            $studentId = $student->getStudent()->getId();
            $completionRates[$studentId] = $this->calculateCompletionRate($evalIndex, $studentId, $periods, $modulesBySubject);
        }

        // Puis utiliser $completionRates dans vos filtres
        $validStudentsForStats = array_filter($completionRates, fn($rate) => $rate >= 100);
        $validStudentIds = array_keys($validStudentsForStats);


        // Précharge toutes les matières de la classe/groupe/période/école
        $subjectsClass = $this->schoolClassSubjectRepo->findBy(['group' => $subjectGroupsIds, 'schoolClassPeriod' => $class], ['group' => 'ASC']);
        if (!$subjectsClass) {
            throw new NotFoundHttpException('Aucun sujet trouvé pour cette classe.');
        }
        $pp = [];
        foreach ($periods as $period) {
            $p = $period->getEvaluations()->toArray();
            foreach ($p as $eval) {
                if ($eval->getStudent()->getSchoolClassPeriod()->getId() != $class->getId()) {
                    $pp[] = $period->getId();
                }
            }
        }
        /* $periods = array_filter($periods, function ($p) use ($pp) {
            return !in_array($p->getId(), $pp);
        }); */
        if ($reportCardTemplate == 'A') {
            // Ouvre le fichier Excel existant
            $filePath = $this->params->get('kernel.project_dir') . '/public/uploads/bulletins/bulletin_type_a.xlsx';
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);
            $spreadsheet = $reader->load($filePath);
            $sheet = $spreadsheet->getSheetByName('Stats');
            $sheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

            $sheet = $spreadsheet->getSheet(0);
            $sheet->setCellValue('M4', $student[0]->getStudent()->getRegistrationNumber());


            $sheet->setCellValue('A1', $school->getName() . chr(10) . $school->getAddress() . chr(10) . $school->getContactPhone() . chr(10) . $school->getContactEmail());
            $sheet->setCellValue('H1', $school->getName() . chr(10) . $school->getAddress() . chr(10) . $school->getContactPhone() . chr(10) . $school->getContactEmail());

            $sheet->setCellValue('A3', strtoupper(str_replace('è', 'e', str_replace('é', 'e', $periodicity[0]->getName()))) . ' - Année scolaire : ' . $currentPeriod->getName());
            $sheet->setCellValue('C4', strtoupper($student[0]->getStudent()->getFullName()));
            $sheet->setCellValue('M4', $student[0]->getStudent()->getRegistrationNumber());
            if ($student[0]->getStudent()->getDateOfBirth()) {
                $sheet->setCellValue('C5', date_format($student[0]->getStudent()->getDateOfBirth(), 'd/m/Y'));
            }
            $sheet->setCellValue('F5', $student[0]->getStudent()->getPlaceOfBirth());
            if ($student[0]->getStudent()->getTutor()) {
                $sheet->setCellValue('M6', $student[0]->getStudent()->getTutor()->getFullName());
                $sheet->setCellValue('M7', $student[0]->getStudent()->getTutor()->getPhone());
            }
            $sheet->setCellValue('C6', $student[0]->getStudent()->getGender() == 'M' ? 'Masculin' : 'Féminin');
            $sheet->setCellValue('C7', $class->getClassOccurence()->getName());
            $k = 0;
            $l = 0;
            $sid = $student[0]->getStudent()->getId();

            $totalperiode = 0;
            $totalModule = 0;
            foreach ($periods as $periode) {
                $tid = $periode->getId();
                $sheet->getColumnDimension(chr(76 + $k))->setVisible(true);
                $sheet->getColumnDimension(chr(76 + $k + 1))->setVisible(true);
                $sheet->setCellValue(chr(76 + $k) . '9', $periode->getShortName());
                $sheet->setCellValue(chr(71 + $l) . '194', $periode->getShortName());
                $k += 3;
                $l++;
            }
            foreach (range(194, 209) as $row) {
                foreach (range('G', 'M') as $col) {
                    $sheet->setCellValue($col . $row, $sheet->getCell($col . $row)->getCalculatedValue());
                }
            }
            $sheet->setCellValue('G209', date('d/m/Y H:i:s'));
            $lastCol = $sheet->getCell(chr(76 + $k - 2) . '9')->getColumn();

            foreach (range(chr(ord($lastCol) + 1), 'T') as $col) {
                $sheet->removeColumn($col);
            }
            $sheet->setCellValue('L209', $user->getFullName());


            //dd($evalIndex[21]);

            $i = 11;
            $j = 0;
            foreach ($periods as $periode) {
                $tid = $periode->getId();
                $totalNotes[$tid] = 0;
                $totalModules[$tid] = 0;
                $totalGroupe[$tid] = 0;
                $totalModuleGroupe[$tid] = 0;
                $totalMatiere[$tid] = 0;
                $totalModuleMatiere[$tid] = 0;
            }
            $csg = 0;
            foreach ($subjectGroups as $sg) {
                $subjectsFiltered = array_filter($subjectsClass, function ($s) use ($sg) {
                    return $s->getGroup() && $s->getGroup()->getId() == $sg->getId();
                });
                $k = 0;
                foreach ($periods as $periode) {
                    $tid = $periode->getId();
                    $totalMatiere[$tid] = 0;
                    $totalModuleMatiere[$tid] = 0;
                }
                foreach ($subjectsFiltered as $sf) {
                    $sSel = array_filter($subjects, function ($s) use ($sf) {
                        return $s->getId() == $sf->getStudySubject()->getId();
                    });
                    foreach ($sSel as $sName) {
                        $sId = $sName->getId();
                        $sName = $sName->getName();
                    }
                    $sheet->setCellValue('F' . $i + $j + $k + 1, $sName);
                    $modulesFiltered = array_filter($modules, function ($m) use ($sId) {
                        return $m->getSubject()->getId() == $sId;
                    });
                    //dd($modulesFiltered,$modules,$subjectsFiltered);
                    $l = 0;
                    $totalMatierePeriode = [];
                    $totalModuleMatierePeriode = [];
                    $totalMatierePeriodes = 0;
                    $totalModuleMatierePeriodes = 0;
                    foreach ($modulesFiltered as $mf) {
                        $sheet->setCellValue('I' . $i + $j + $k + $l, $mf->getModule()->getModuleName() . ': ' . $mf->getModuleNotation() . ' pts');
                        $m = 0;
                        foreach ($periods as $periode) {
                            $tid = $periode->getId();
                            // Utilise l'indexation pour récupérer l'évaluation
                            $eval = $evalIndex[$sid][$sId][$mf->getId()][$tid] ?? null;
                            if ($eval) {
                                $sheet->setCellValue(chr(75 + $m) . ($i + $j + $k + $l), $mf->getModuleNotation());
                                $sheet->setCellValue(chr(76 + $m) . ($i + $j + $k + $l), $eval->getEvaluationNote() ?? '');
                                $totalMatierePeriodes += $eval->getEvaluationNote();
                                $totalModuleMatierePeriodes += $mf->getModuleNotation();
                                $totalMatierePeriode[$tid] = (isset($totalMatierePeriode[$tid]) ? $totalMatierePeriode[$tid] : 0) + $eval->getEvaluationNote();
                                $totalModuleMatierePeriode[$tid] = (isset($totalModuleMatierePeriode[$tid]) ? $totalModuleMatierePeriode[$tid] : 0) + $mf->getModuleNotation();
                            }
                            if ($l == 3) {
                                $sheet->setCellValue(chr(75 + $m) . ($i + $j + $k + 4), $totalModuleMatierePeriode[$tid] ?? 0);
                                $sheet->setCellValue(chr(76 + $m) . ($i + $j + $k + 4), $totalMatierePeriode[$tid] ?? 0);
                                if ($template) {
                                    //dd($totalMatierePeriode, $totalModuleMatierePeriode, $tid);
                                    if (isset($totalModuleMatierePeriode[$tid]) && isset($totalMatierePeriode[$tid])) {
                                        $sheet->setCellValue(chr(77 + $m) . ($i + $j + $k + 4), $this->appreciationService->getAppreciationByNote($template, ($totalMatierePeriode[$tid] / ($totalModuleMatierePeriode[$tid] > 0 ? $totalModuleMatierePeriode[$tid] : 1) * 20) ?? 0));
                                        $sheet->setCellValue('L' . ($i + $j + $k + 5), $this->appreciationService->getAppreciationByNote($template, ($totalMatierePeriodes / ($totalModuleMatierePeriodes > 0 ? $totalModuleMatierePeriodes : 1) * 20) ?? 0));
                                    }
                                }
                            }
                            $m += 3;
                        }
                        $sheet->getRowDimension($i + $j + $k + $l)->setVisible(true);

                        $l++;
                    }
                    foreach ($periods as $periode) {
                        $tid = $periode->getId();

                        $totalMatiere[$tid] += $totalMatierePeriode[$tid] ?? 0;
                        $totalModuleMatiere[$tid] += $totalModuleMatierePeriode[$tid] ?? 0;
                    }
                    $sheet->getRowDimension($i + $j + $k + 4)->setVisible(true);
                    $sheet->setCellValue('F' . $i + $j + $k + 4, 'Total');
                    $sheet->getRowDimension($i + $j + $k + 5)->setVisible(true);
                    $sheet->setCellValue('F' . $i + $j + $k + 5, 'COTE');
                    $k += 6;
                }
                $totalGroupes = 0;
                $totalModuleGroupes = 0;
                foreach ($periods as $periode) {
                    $tid = $periode->getId();
                    $totalGroupe[$tid] += $totalMatiere[$tid] ?? 0;
                    $totalModuleGroupe[$tid] += $totalModuleMatiere[$tid] ?? 0;
                    $totalGroupes += $totalGroupe[$tid] ?? 0;
                    $totalModuleGroupes += $totalModuleGroupe[$tid] ?? 0;
                }
                $sheet->setCellValue('A' . ($i + (int)(($j + $k) / 2) - 1), $sg->getDescription());
                $sheet->mergeCells('A' . ($i + (int)(($j + $k) / 2) - 1) . ':E' . ($i + (int)(($j + $k) / 2) - 1), false);
                $styleBorders = [
                    'borders' => [
                        'top' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ],
                        'bottom' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ],
                        'left' => [
                            'borderStyle' => Border::BORDER_THIN,

                        ],
                        'right' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ],
                    ],
                ];
                $lignesEntete[] = $i;
                $sheet->getStyle('A' . $i   . ':E' . $i - 2 + (int)(($j + $k)))->applyFromArray($styleBorders);
                $sheet->insertNewRowBefore($i + $j + $k, 3);
                if ($csg < count($subjectGroups) - 1)
                    foreach (range('I', $lastCol) as $col) {
                        $sheet->setCellValue($col . ($i + $j + $k + 1), $sheet->getCell($col . '9')->getValue());
                        $sheet->setCellValue($col . ($i + $j + $k + 2), $sheet->getCell($col . '10')->getValue());
                    }
                $csg++;
                $i += $j + $k + 3;
                //$j += 6;
            }
            $p = 0;
            foreach ($periods as $periode) {
                $tid = $periode->getId();
                $sheet->setCellValue(chr(71 + $p) . (195 + count($subjectGroups) * 3), ($totalGroupe[$tid] ?? 0) . '/' . ($totalModuleGroupe[$tid] ?? 0));
                $sheet->setCellValue('J' . (195 + count($subjectGroups) * 3), ($totalGroupes) . '/' . ($totalModuleGroupes ?? 0));
                $sheet->setCellValue(chr(71 + $p) . (196 + count($subjectGroups) * 3), round(($totalGroupe[$tid] / ($totalModuleGroupe[$tid] > 0 ? $totalModuleGroupe[$tid] : 1) * 20), 2) ?? 0);
                $sheet->setCellValue('J' . (196 + count($subjectGroups) * 3), round(($totalGroupes / ($totalModuleGroupes > 0 ? $totalModuleGroupes : 1) * 20), 2) ?? 0);
                $sheet->setCellValue(chr(71 + $p) . (197 + count($subjectGroups) * 3), $this->appreciationService->getAppreciationByNote($template, $sheet->getCell(chr(71 + $p) . (196 + count($subjectGroups) * 3))->getValue()));
                $sheet->setCellValue('J' . (197 + count($subjectGroups) * 3), $this->appreciationService->getAppreciationByNote($template, $sheet->getCell('J' . (196 + count($subjectGroups) * 3))->getValue()));
                if (isset($moyennesPeriodes[$tid])) $sheet->setCellValue(chr(71 + $p) . (198 + count($subjectGroups) * 3), $this->getRank($sheet->getCell(chr(71 + $p) . (196 + count($subjectGroups) * 3))->getValue(), $moyennesPeriodes[$tid]) . 'e');
                if (isset($moyennes)) $sheet->setCellValue('J' . (198 + count($subjectGroups) * 3), $this->getRank($sheet->getCell('J' . (196 + count($subjectGroups) * 3))->getValue(), $moyennes) . 'e');

                $p++;
            }
            $lastLigne = $i;
            for ($i = 191; $i < 220; $i++) {
                if ($sheet->getCell('A' . $i)->getValue() != '') {
                    $sheet->mergeCells('A' . $i . ':C' . $i, true);
                    $sheet->mergeCells('F' . $i . ':J' . $i, true);
                    $c = $i + 1;
                    if ($template) {
                        $appreciationBaremes = $this->appreciationBaremeRepo->findBy(['evaluationAppreciationTemplate' => $template]);
                        $maxnote = 0;
                        foreach ($appreciationBaremes as $appreciationBareme) {
                            $sheet->setCellValue('A' . $c, $appreciationBareme->getEvaluationAppreciationFullValue());
                            $sheet->setCellValue('B' . $c, $appreciationBareme->getEvaluationAppreciationValue());
                            $sheet->setCellValue('C' . $c, $maxnote . '-' . $appreciationBareme->getEvaluationAppreciationMaxNote());
                            $maxnote = $appreciationBareme->getEvaluationAppreciationMaxNote();
                            $c++;
                        }
                    }
                    break;
                }
            }
            for ($i = 191; $i < 220; $i++) {
                if ($sheet->getCell('L' . $i)->getValue() != '') {
                    $c = $i + 1;
                    foreach ($periods as $periode) {
                        $sheet->setCellValue('L' . $c, $periode->getShortName() . '=' . $periode->getName());
                        $sheet->mergeCells('L' . $c . ':N' . $c, true);
                        $c++;
                    }
                    break;
                }
            }

            // // Génère le HTML si besoin

            $htmlFileName = $student[0]->getStudent()->getRegistrationNumber() . '.html';
            $htmlFilePath = $this->params->get('kernel.project_dir') . '/public/uploads/bulletins/' . $htmlFileName;
            $htmlWriter = new Html($spreadsheet);
            $htmlWriter->setSheetIndex(0);
            $htmlWriter->setPreCalculateFormulas(false);
            $htmlWriter->save($htmlFilePath);

            // // Optionnel : post-traitement HTML (logo, impression, etc.)
            // $html = file_get_contents($htmlFilePath);
            // $html = str_replace('<title>Untitled Spreadsheet</title>', '<title>Bulletin ' . $student[0]->getStudent()->getRegistrationNumber() . '</title>', $html);
            // file_put_contents($htmlFilePath, $html);
            return [$htmlFilePath, $lastLigne, isset($lignesEntete) ? $lignesEntete : null, ord($lastCol), $sheet->getHighestRow()];
        } elseif ($reportCardTemplate == 'B') {
            // Code pour le modèle B
            // ...
            $subjects = [];

            $notesMatieres = [];
            foreach ($class->getSchoolClassSubjects() as $subject) {
                $subjectId = $subject->getStudySubject()->getId();
                foreach ($class->getStudentClasses() as $studentClass) {
                    $studentIdForCalculation = $studentClass->getStudent()->getId();
                    $total = 0;
                    $count = 0;
                    foreach ($periods as $periode) {
                        $tid = $periode->getId();
                        $modules = $subject->getStudySubject()->getClassSubjectModules()->toArray();
                        $modules = array_filter($modules, function ($m) use ($class) {
                            return $m->getModule()->getModuleName() == 'Ecrit' && $m->getClass()->getId()==$class->getId();
                        });
                        $modules = array_values($modules);
                        $mid = isset($modules[0]) ? $modules[0]->getId() : null;
                        $eval = $evalIndex[$studentIdForCalculation][$subjectId][$mid][$tid] ?? null;
                        if ($eval) {
                            $total += $eval->getEvaluationNote();
                            $count++;
                        }
                    }
                    $notesMatieres[$subjectId][$studentIdForCalculation] = $count > 0 ? $total / $count : null;
                }
            }

            $studentIdForCalculation = $student->getStudent()->getId(); // l'ID de l'élève concerné
            $nbMatieres10 = 0;
            $nbMat = 0;



            $moyennesEleves = [];
            foreach ($class->getStudentClasses() as $studentClass) {
                $studentIdForCalculation = $studentClass->getStudent()->getId();
                $totalPondere = 0;
                $totalCoefficients = 0;
                foreach ($class->getSchoolClassSubjects() as $subject) {
                    $coef = $subject->getCoefficient();
                    $subjectId = $subject->getStudySubject()->getId();
                    $sommeNotes = 0;
                    $nbNotes = 0;
                    foreach ($periods as $periode) {
                        $tid = $periode->getId();
                        $modules = $subject->getStudySubject()->getClassSubjectModules()->toArray();
                        $modules = array_filter($modules, function ($m) use($class){
                            return $m->getModule()->getModuleName() == 'Ecrit' && $m->getClass()->getId()==$class->getId();
                        });
                        $modules = array_values($modules);
                        $mid = isset($modules[0]) ? $modules[0]->getId() : null;
                        $eval = $evalIndex[$studentIdForCalculation][$subjectId][$mid][$tid] ?? null;
                        if ($eval && $eval->getEvaluationNote() !== null) {
                            $sommeNotes += $eval->getEvaluationNote();
                            $nbNotes++;
                        }
                    }
                    if ($nbNotes > 0) {
                        $moyenneMatiere = $sommeNotes / $nbNotes;
                        $totalPondere += $moyenneMatiere * $coef;
                        $totalCoefficients += $coef;
                    }
                }
                $moyennesEleves[$studentIdForCalculation] = $totalCoefficients > 0 ? round($totalPondere / $totalCoefficients, 2) : null;
            }

            $disciplines = $this->attendanceRepo->findBy(['studentClass' => $student]);
            $disciplines = array_map(function ($d) {
                return [
                    'heuresAbsence' => $d->getHeuresAbsence(),
                    'absencesJustifiee' => $d->getAbsencesJustifiee(),
                    'retard' => $d->getRetard(),
                    'retardInjustifie' => $d->getRetardInjustifie(),
                    'retenue' => $d->getRetenue(),
                    'avertissementDiscipline' => $d->getAvertissementDiscipline(),
                    'blame' => $d->getBlame(),
                    'jourExclusion' => $d->getJourExclusion(),
                    'exclusionDefinitive' => $d->isExclusionDefinitive()
                ];
            }, $disciplines);

            $discipline = [
                'heuresAbsence' => 0,
                'absencesJustifiee' => 0,
                'retard' => 0,
                'retardInjustifie' => 0,
                'retenue' => 0,
                'avertissementDiscipline' => 0,
                'blame' => 0,
                'jourExclusion' => 0,
                'exclusionDefinitive' => 0
            ];
            foreach ($disciplines as $d) {
                foreach ($d as $key => $value) {
                    if (!isset($discipline[$key])) $discipline[$key] = 0;
                    $discipline[$key] += $value;
                }
            }

            $student=$selStudent[0];
           
            $reportCard = $this->entityManager->getRepository(ReportCardTemplate::class)->findOneBy(['name' => $reportCardTemplate]);
            
            $html = '<div class="ranking-div" rank-value="'.$this->getRank($moyennesEleves[$student->getStudent()->getId()], $moyennesEleves, true, array_keys($validStudentsForStats)).'"><div class="row bulletin-header">
            <div class="col-5 text-center" style="font-size: 0.9em;"><p >' . $reportCard->getHeaderLeft() . '</p><p >' . $reportCard->getNationalMottoLeft() . '</p>' . $reportCard->getAdditionalHeaderLeft() . '<p style="font-weight:bold">' . $currentSchool->getName() . '</p><p >' . $reportCard->getSchoolValuesLeft() . '</p><p >PO BOX :' . $currentSchool->getAddress() . '</p><p >Tel. :' . $currentSchool->getContactPhone() . '</p></div><div class="col-2 text-center"><img class="school-logo" src="' . $this->params->get('kernel.project_dir') .  '/public' . ($class->getSchool()->getLogo() ? '/uploads/logos/' . $class->getSchool()->getLogo() : '/img/logo_test.png') . '" width="120px" height="120px"></div><div class="col-5 text-center" style="font-size: 0.9em;"><p >' . $reportCard->getHeaderRight() . '</p><p >' . $reportCard->getNationalMottoRight() . '</p>' . $reportCard->getAdditionalHeaderRight() . '<p style="font-weight:bold">' . $currentSchool->getName() . '</p><p >' . $reportCard->getSchoolValuesRight() . '</p><p >BP :' . $currentSchool->getAddress() . '</p><p >Tél. :' . $currentSchool->getContactPhone() . '</p></div>
            <div class="col-12 text-center" style="font-size:14pt">' . $reportCard->getHeaderTitle() . '</div>
            <div class="col-12 text-center" style="font-size:9pt">' . $currentPeriod->getName() . ' - ' . strtoupper($periodicity[0]->getName()) . '</div>
            <div class="row">
                <div class="col-md-9">
                    <div class="row" style="font-size:10pt">
                        <div class="col-md-5">Nom : <strong>' . strtoupper($student->getStudent()->getFullName()) . '</strong></div><div class="col-md-4">Classe : <strong>' . $class->getClassOccurence()->getName() . '</strong></div><div class="col-md-3">Redoublant : <strong></strong></div>
                        <div class="col-md-5">Né(e) le : <strong>' . $student->getStudent()->getDateOfBirth()->format('d M Y') . '</strong> à : <strong>' . $student->getStudent()->getPlaceOfBirth() . '</strong></div><div class="col-md-4">Effectif : <strong>' . count($class->getStudentClasses()->toArray()) . '</strong></div><div class="col-md-3">Nb. de matières : <strong>' . count($class->getSchoolClassSubjects()->toArray()) . '</strong></div>
                        <div class="col-md-5">Sexe: <strong>' . ($student->getStudent()->getGender()->value == 'male' ? 'Masculun' : 'Féminin') . '</strong> / Mattricule : <strong>' . $student->getStudent()->getRegistrationNumber() . '</strong></div><div class="col-md-4">Enseignant Principal : <strong>' . ($class->getClassMaster() ? $class->getClassMaster()->getFullName() : '') . '</strong></div><div class="col-md-3"></div>
                        <div class="col-md-5">Nom du tuteur/parent : <strong>' . ($student->getStudent()->getTutor() ? $student->getStudent()->getTutor()->getFullName() : '') . '</strong></div><div class="col-md-4">Contact tuteur/parent : <strong>' . ($student->getStudent()->getTutor() ? $student->getStudent()->getTutor()->getPhone() : '') . '</strong></div><div class="col-md-3"></div>
                    </div>
                </div>
                <div class="col-md-3 text-center">
                    <img class="student-photo" src="' . ($student->getStudent()->getPhoto() ? '/uploads/'.$student->getStudent()->getPhoto() : '/img/default_student.png').'" width="100px" height="100px">
                </div>
            </div>';
            $studentSelId = $student->getStudent()->getId();
            $completionRateStudent = $completionRates[$studentSelId] ?? 0;
            if ($completionRateStudent < 100) {
                // Ajouter un message dans le HTML
                $html .= '<div class="alert alert-warning" style="font-size:0.8em">
                            <i class="fas fa-exclamation-triangle"></i> 
                            Taux de complétion des notes : ' . round($completionRateStudent, 1) . '%. 
                            Les statistiques de classe ne sont calculées qu\'avec les élèves ayant 100% de notes.
                        </div>';
            }
            $baremes = $class->getReportCardTemplate() ? $class->getReportCardTemplate()->getEvaluationAppreciationTemplate()->getBaremes()->toArray() : [];
            $total_moys = 0;
            foreach ($subjectGroups as $sg) {
                $html .= '<div class="row">
                    <div class="col-md-12" style="font-size:16pt;font-weight:bold">' . $sg->getDescription() . '</div>
                </div>';
                $html .= '<table class="table table-bordered table-striped">
                    <thead>
                        <tr class="bg-secondary">
                            <th style="width:300px">Matière</th>';
                foreach ($periods as $periode) {
                    $html .= '<th>' . $periode->getShortName() . '</th>';
                }
                $html .= '<th>Moy.</th><th>Coef.</th><th>Note x Coef.</th><th>Rang</th><th>Cotation</th><th>Grade</th><th>Min</th><th>Moy. Gen.</th><th>Max</th><th>% Réuss.</th><th>Signature enseignant</th></tr>
                    </thead>
                    <tbody>';

                $notes_coef = [];
                $sum_coef = [];
                $total_moy = 0;
                $scs = array_filter($sg->getSchoolClassSubjects()->toArray(), function ($scs) use ($class) {
                    return $scs->getSchoolClassPeriod()->getId() == $class->getId();
                });
                //dd($class->getId(),$scs);
                foreach ($scs as $subject) {
                    $html .= '<tr><td style="font-weight:bold"><span style="display:block;margin:0;padding:0;line-height:0.7em">' . $subject->getStudySubject()->getName() .'</span>'. ($subject->getTeacher() ? '<span class="teacher-name-B" style="font-size:0.7em;font-weight:lighter !important;font-style:italic">' . $subject->getTeacher()->getFullName() . '</span>' : '') . '</td>';
                    $total = 0;
                    foreach ($periods as $periode) {
                        $tid = $periode->getId();
                        $module = $subject->getStudySubject()->getClassSubjectModules()->toArray();
                        //if($subject->getStudySubject()->getId()==252) dd($module);
                        $module = array_filter($module, function ($m) use ($class) {
                            return $m->getModule()->getModuleName() == 'Ecrit' && $m->getClass()->getId()==$class->getId();
                        });
                        //dd($module);

                        $module = array_values($module);
                        $mid = isset($module[0]) ? $module[0]->getId() : null;
                        //if($subject->getStudySubject()->getName()=='MATHEMATIQUES') dd($student->getStudent()->getId(),$subject->getStudySubject()->getId(),$mid,$tid, $evalIndex,$subject,$module);
                        //if($subject->getStudySubject()->getName()=='MATHEMATIQUES') dd($evalIndex[$student->getStudent()->getId()][$subject->getStudySubject()->getId()][$mid][$tid],$module);
                        // Utilise l'indexation pour récupérer l'évaluation
                        $eval = $evalIndex[$student->getStudent()->getId()][$subject->getStudySubject()->getId()][$mid][$tid] ?? null;
                        //if($tid==2 && $subject->getStudySubject()->getName()=='MATHEMATIQUES') dd($eval);
                        //if($mid==836) dd($eval);
                        if ($eval) {
                            $html .= '<td class="text-end">' . $this->setNoteStyle(number_format($eval->getEvaluationNote(), 2),$passNote, true) . '</td>';
                            $total += $eval->getEvaluationNote();
                            if (!isset($notes_coef[$tid])) $notes_coef[$tid] = 0;
                            if (!isset($sum_coef[$tid])) $sum_coef[$tid] = 0;
                            $notes_coef[$tid] += $eval->getEvaluationNote() * $subject->getCoefficient();
                            $sum_coef[$tid] += $subject->getCoefficient();
                        } else {
                            $html .= '<td></td>';
                        }
                    }
                    $moy = count($periods) > 0 ? $total / count($periods) : 0;
                    $nbMatieres10 += $moy >= $passNote ? 1 : 0;
                    $nbMat++;
                    $total_moy += $moy * $subject->getCoefficient();
                    $nb_reussite = isset($notesMatieres[$subject->getStudySubject()->getId()]) ? count(array_filter($notesMatieres[$subject->getStudySubject()->getId()], function ($note) use ($passNote) {
                        return $note >= $passNote;
                    })) : 0;
                    $taux_reussite = isset($notesMatieres[$subject->getStudySubject()->getId()]) ? $nb_reussite / count($notesMatieres[$subject->getStudySubject()->getId()]) * 100 : 0;
                    $notesMatieresCleaned = isset($notesMatieres[$subject->getStudySubject()->getId()]) ? array_filter($notesMatieres[$subject->getStudySubject()->getId()], function ($note) {
                        return $note !== null && $note !== '';
                    }) : [];
                    $moyenne_generale = isset($notesMatieres[$subject->getStudySubject()->getId()]) ? array_sum($notesMatieres[$subject->getStudySubject()->getId()]) / count($notesMatieres[$subject->getStudySubject()->getId()]) : 0;
                    $html .= '<td class="text-end fw-100" style="font-weight:bold">' . $this->setNoteStyle(number_format($moy, 2),$passNote, true) . '</td><td class="text-end">' . number_format($subject->getCoefficient(), 2) . '</td><td  class="text-end">' . number_format($moy * $subject->getCoefficient(), 2) . '</td><td class="text-end">' . (isset($notesMatieres[$subject->getStudySubject()->getId()]) ? $this->getRank($moy, $notesMatieres[$subject->getStudySubject()->getId()]) : '') . '</td><td class="text-center" style="font-size:0.8em">' . ($this->getBareme($moy, $baremes) ? explode('(', $this->getBareme($moy, $baremes)->getEvaluationAppreciationFullValue())[0] : '') . '</td><td class="text-center">' . ($this->getBareme($moy, $baremes) ? $this->getBareme($moy, $baremes)->getEvaluationAppreciationValue() : '') . '</td><td class="text-end">' . (count($notesMatieresCleaned) > 0 ? round(min($notesMatieresCleaned), 2) : '') . '</td><td class="text-end">' . number_format($moyenne_generale, 2) . '</td><td class="text-end">' . (count($notesMatieresCleaned) > 0 ? round(max($notesMatieresCleaned), 2) : '') . '</td><td class="text-end">' . number_format($taux_reussite, 2) . '%</td><td></td></tr>'; // Appréciation à ajouter
                }
                $html .= '<tr class="bg-light" style="font-weight:bold;font-style:italic"><td>Récap</td>';
                foreach ($periods as $periode) {
                    $tid = $periode->getId();
                    $total = isset($notes_coef[$tid]) ? $notes_coef[$tid] : 0;
                    $moy = isset($notes_coef[$tid]) ? $total / ((isset($sum_coef[$tid]) && $sum_coef[$tid] > 0) ? $sum_coef[$tid] : 0) : 0;
                    $html .= '<td class="text-end">' . number_format($moy, 2) . '</td>';
                }
                $moy_groupe = isset($total_moy) ? $total_moy / ((isset($sum_coef[$tid]) && $sum_coef[$tid] > 0) ? $sum_coef[$tid] : 1) : 0;
                $html .= '<td class="text-end">' . number_format($moy_groupe, 2) . '</td><td class="text-end">' . number_format(isset($sum_coef[$tid]) ? $sum_coef[$tid] : 0, 2) . '</td><td class="text-end">' . number_format($total_moy, 2) . '</td><td colspan="8" class="text-end"></td></tr>';
                $html .= '</tbody></table>';
                $total_moys += $total_moy;
            }



            // Dans la section du template B, avant d'utiliser $modulesBySubject, recalculer-le :
            $modulesBySubjectForB = [];
            foreach ($class->getSchoolClassSubjects() as $subject) {
                $subjectId = $subject->getStudySubject()->getId();
                $modules = $subject->getStudySubject()->getClassSubjectModules()->toArray();
                $modules = array_filter($modules, function ($m) use ($class, $subject) {
                    return $m->getClass()->getId() === $class->getId();
                });
                $modulesBySubjectForB[$subjectId] = $modules;
            }

            // Puis utiliser $modulesBySubjectForB au lieu de $modulesBySubject dans cette section
            $validStudentsForStats = array_filter($moyennesEleves, function ($studentId) use ($evalIndex, $periods, $modulesBySubjectForB, $class) {
                $completionRate = $this->calculateCompletionRate($evalIndex, $studentId, $periods, $modulesBySubjectForB, $class);
                return $completionRate >= 100;
            }, ARRAY_FILTER_USE_KEY);

            $moyennesElevesForStats = array_intersect_key($moyennesEleves, $validStudentsForStats);


            $html .= '<div class="row">
            <div class="col-md-6 total-block">
            <div class="row" style="margin-left:10px!important">
            <div class="col-md-3" style="vertical-align:middle;padding-top:3rem;border:1px solid #000;">Elève</div>
            <div class="col-md-9">
                <div class="row table-content">
                    <div class="col-md-9 text-start">Total</div>
                    <div class="col-md-3 text-end">' . number_format($total_moys, 2) . '</div>
                    <div class="col-md-9 text-start">Moyenne</div>
                    <div class="col-md-3 text-end">' . $moyennesEleves[$student->getStudent()->getId()] . '</div>
                    <div class="col-md-9 text-start">Rang</div>
                    <div class="col-md-3 text-end">' . $this->getRank($moyennesEleves[$student->getStudent()->getId()], $moyennesEleves, true, array_keys($validStudentsForStats)) .'e</div>
                    <div class="col-md-9 text-start">Nombre de matières validées</div>
                    <div class="col-md-3 text-end">' . $nbMatieres10 . '/' . $nbMat . '</div>
                </div>
            </div>
            <div class="col-md-3" style="vertical-align:middle;padding-top:3rem;border:1px solid #000">Classe</div>
            <div class="col-md-9">
                <div class="row table-content">
                    <div class="col-md-12 text-start text-danger" style="font-style:italic">* La moyenne générale est calculée sur 20</div>
                    <div class="col-md-9 text-start">Moyenne la plus faible</div>
                    <div class="col-md-3 text-end">' . (count($moyennesElevesForStats) > 0 && $moyennesElevesForStats ? round(min(count(array_filter($moyennesElevesForStats, function ($moy) {
                return $moy != null;
            })) > 0 ? array_filter($moyennesElevesForStats, function ($moy) {
                return $moy != null;
            }) : [0]), 2) : 0) . '</div>
                    <div class="col-md-9 text-start">Moyenne la plus forte</div>
                    <div class="col-md-3 text-end">' . (count($moyennesElevesForStats) > 0 && $moyennesElevesForStats ? round(max($moyennesElevesForStats), 2) : 0) . '</div>
                    <div class="col-md-9 text-start">Taux de réussite</div>
                    <div class="col-md-3 text-end">' . number_format(count(array_filter($moyennesElevesForStats, function ($moy) {
                return $moy >= 11;
            })) / (count($moyennesElevesForStats) > 0 ? count($moyennesElevesForStats) : 1) * 100, 2) . '%</div>
                    <div class="col-md-9 text-start">Ecart-type</div>
                    <div class="col-md-3 text-end">' . (count($moyennesElevesForStats) > 0 ? number_format($this->getEcartType($moyennesElevesForStats), 2) : 0) . '</div>
                    <div class="col-md-9 text-start">Moyenne Générale</div>
                    <div class="col-md-3 text-end">' . (count($moyennesElevesForStats) > 0 ? number_format($this->getMoyenneGenerale($moyennesElevesForStats), 2) : 0) . '</div>
                </div>
            </div>
            </div>
            </div>
            <div class="col-md-3">
                <table class="table table-bordered" id="table-discipline">
                    <thead>
                        <tr class="bg-secondary">
                            <th>Discipline</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Absences (en Heures)</td>
                            <td class="text-end">' . $discipline['heuresAbsence'] . '</td>
                        </tr>
                        <tr>
                            <td>Absences justifiées (en Heures)</td>
                            <td class="text-end">' . $discipline['absencesJustifiee'] . '</td>
                        </tr>
                        <tr>
                            <td>Retards (en Heures)</td>
                            <td class="text-end">' . $discipline['retard'] . '</td>
                        </tr>
                        <tr>
                            <td>Retards justifiés (en Heures)</td>
                            <td class="text-end">' . $discipline['retardInjustifie'] . '</td>
                        </tr>
                        <tr>
                            <td>Heures de retenue</td>
                            <td class="text-end">' . $discipline['retenue'] . '</td>
                        </tr>
                        <tr>
                            <td>Avertissement</td>
                            <td class="text-end">' . $discipline['avertissementDiscipline'] . '</td>
                        </tr>
                        <tr>
                            <td>Blâme</td>
                            <td class="text-end">' . $discipline['blame'] . '</td>
                        </tr>
                        <tr>
                            <td>Exclusion temporaire (en jours)</td>
                            <td class="text-end">' . $discipline['jourExclusion'] . '</td>
                        </tr>
                        <tr>
                            <td>Exclusion définitive</td>
                            <td class="text-end">' . ($discipline['jourExclusion'] == 0 ? 'Non' : 'Oui') . '</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="col-md-3">
                <table class="table table-bordered">
                    <thead>
                        <tr class="bg-secondary">
                            <th>Travail</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="text-start">Félicitations</td>
                            <td class="text-center">'.($moyennesEleves[$student->getStudent()->getId()]>=14 ? "Oui" : "").'</td>
                        </tr>
                        <tr>
                            <td class="text-start">Tableau d\'honneur</td>
                            <td class="text-center">'.($moyennesEleves[$student->getStudent()->getId()]>=12 ? "Oui" : "").'</td>
                        </tr>
                        <tr>
                            <td class="text-start">Encouragements</td>
                            <td class="text-center">'.($moyennesEleves[$student->getStudent()->getId()]>=10 ? "Oui" : "").'</td>
                        </tr>
                        <tr>
                            <td class="text-start">Avertissement</td>
                            <td class="text-center">'.($moyennesEleves[$student->getStudent()->getId()]<10 && $moyennesEleves[$student->getStudent()->getId()]>=8 ? "Oui" : "").'</td>
                        </tr>
                        <tr>
                            <td class="text-start">Blame</td>
                            <td class="text-center">'.($moyennesEleves[$student->getStudent()->getId()]<8 ? "Oui" : "").'</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            </div>';
            $html .= '<div class="col-12 text-end">Yaoundé, ' . ($bulLang === 'fr' ? 'le' : 'on') . ' ' . date('d M Y') . '</div>';
            $html .= '<div class="col-3"><div class="table-responsive"><table class="table table-bordered" width="100%"><thead><tr><th style="font-size:0.68em">' . ($bulLang === 'fr' ? 'Appréciation du travail de l\'élève (forces et points à améliorer)' : 'Appreciation of the student\'s work (strengths and areas for improvement)') . '</th></tr></thead><tbody><tr><td style="height:100px"></td></tr></tbody></table></div></div>';
            $html .= '<div class="col-3"><div class="table-responsive"><table class="table table-bordered" width="100%"><thead><tr><th>' . ($bulLang === 'fr' ? 'Visa du parent' : 'Parent\'s approval') . '</th></tr></thead><tbody><tr><td style="height:100px"></td></tr></tbody></table></div></div>';
            $html .= '<div class="col-3"><div class="table-responsive"><table class="table table-bordered" width="100%"><thead><tr><th>' . ($bulLang === 'fr' ? 'Enseignant(e) principal(e)' : 'Main teacher') . '</th></tr></thead><tbody><tr><td style="height:100px"></td></tr></tbody></table></div></div>';
            $html .= '<div class="col-3"><div class="table-responsive"><table class="table table-bordered" width="100%"><thead><tr><th>' . ($bulLang === 'fr' ? 'Le Directeur' : 'The Director') . '</th></tr></thead><tbody><tr><td style="height:100px"></td></tr></tbody></table></div></div>';
            

            $html .= '</div></div>';
            //dd($html);
            return [$html];     // Retourne un tableau vide ou les données nécessaires pour le modèle B
        } elseif ($reportCardTemplate == 'C') {

            $subjects = [];

            $notesMatieres = [];
            foreach ($class->getSchoolClassSubjects() as $subject) {
                $subjectId = $subject->getStudySubject()->getId();
                foreach ($class->getStudentClasses() as $studentClass) {
                    $studentId = $studentClass->getStudent()->getId();
                    $total = 0;
                    $count = 0;
                    foreach ($periods as $periode) {
                        $tid = $periode->getId();
                        $modules = $subject->getStudySubject()->getClassSubjectModules()->toArray();
                        $modules = array_filter($modules, function ($m) {
                            return $m->getModule()->getModuleName() == 'Ecrit';
                        });
                        $modules = array_values($modules);
                        $mid = isset($modules[0]) ? $modules[0]->getId() : null;
                        $eval = $evalIndex[$studentId][$subjectId][$mid][$tid] ?? null;
                        if ($eval) {
                            $total += $eval->getEvaluationNote();
                            $count++;
                        }
                    }
                    $notesMatieres[$subjectId][$studentId] = $count > 0 ? $total / $count : null;
                }
            }
            $student = $selStudent;
            $studentSelId = $student[0]->getStudent()->getId(); // l'ID de l'élève concerné
            $nbMatieres10 = 0;
            $nbMat = 0;

            $moyennesEleves = [];
            foreach ($class->getStudentClasses() as $studentClass) {
                $studentId = $studentClass->getStudent()->getId();
                $totalPondere = 0;
                $totalCoefficients = 0;
                foreach ($class->getSchoolClassSubjects() as $subject) {
                    $coef = $subject->getCoefficient();
                    $subjectId = $subject->getStudySubject()->getId();
                    $sommeNotes = 0;
                    $nbNotes = 0;
                    foreach ($periods as $periode) {
                        $tid = $periode->getId();
                        $modules = $subject->getStudySubject()->getClassSubjectModules()->toArray();
                        $modules = array_filter($modules, function ($m) {
                            return $m->getModule()->getModuleName() == 'Ecrit';
                        });
                        $modules = array_values($modules);
                        $mid = isset($modules[0]) ? $modules[0]->getId() : null;
                        $eval = $evalIndex[$studentId][$subjectId][$mid][$tid] ?? null;
                        if ($eval && $eval->getEvaluationNote() !== null) {
                            $sommeNotes += $eval->getEvaluationNote();
                            $nbNotes++;
                        }
                    }
                    if ($nbNotes > 0) {
                        $moyenneMatiere = $sommeNotes / $nbNotes;
                        $totalPondere += $moyenneMatiere * $coef;
                        $totalCoefficients += $coef;
                    }
                }
                $moyennesEleves[$studentId] = $totalCoefficients > 0 ? round($totalPondere / $totalCoefficients, 2) : null;
            }
            $baremes = $class->getReportCardTemplate() ? $class->getReportCardTemplate()->getEvaluationAppreciationTemplate()->getBaremes()->toArray() : [];

            $student = $student[0];
            $evaluationsCount = count($periods);
            $evaluationsTitles = [];
            foreach ($periods as $period) {
                $evaluationsTitles[] = $period->getShortName();
            }
            $evaluationColumns = 4; // Note, Barème, Moyenne, Appréciation

            // Structure de données (exemple)
            $subjectsGroup = [
                'Groupe 1' => [
                    'Matière A' => ['Module 1.1', 'Module 1.2'],
                    'Matière B' => ['Module 2.1'],
                    'Matière C' => ['Module 3.1', 'Module 3.2', 'Module 3.3', 'Module 3.4'],
                ],
                'Groupe 2' => [
                    'Matière D' => ['Module 4.1', 'Module 4.2', 'Module 4.3'],
                    'Matière E' => ['Module 5.1'],
                ],
                'Groupe 3' => [
                    'Matière F' => ['Module 6.1', 'Module 6.2'],
                    'Matière G' => ['Module 7.1'],
                    'Matière H' => ['Module 8.1', 'Module 8.2', 'Module 8.3'],
                ],
            ];
            $html = '<div class="row">
                <div class="col-4"></div>
                <div class="col-4"></div>
                <div class="col-4"></div>
                <div class="col-12 text-center mb-3" style="font-size:24pt"><span style="line-height:1px;display:block">BULLETIN DE NOTES</span><span style="line-height:1px;font-size:0.8em"><i>REPORT CARD</i></span></div>
                <div class="12 text-center mb-3"  style="font-size:14pt"><span>Année scolaire/<i>Period : </i></span><strong>' . $currentPeriod->getName() . ' - ' . strtoupper($periodicity[0]->getName()) . '</strong></div>
                <div class="col-2 pb-3 pt-3"><span style="line-height:1px;display:block">Nom et prénoms : </span><span style="line-height:1px;font-size:0.8em"><i>Name and surname : </i></span></div><div class="col-5"><strong>' . strtoupper($student->getStudent()->getFullName()) . '</strong></div><div class="col-2 pb-3 pt-3"><span style="line-height:1px;display:block">Matricule : </span><span style="line-height:1px;font-size:0.8em"><i>Reg. Number : </i></span></div><div class="col-3"><strong>' . $student->getStudent()->getRegistrationNumber() . '</strong></div>
                <div class="col-2 pb-3 pt-3"><span style="line-height:1px;display:block">Né(e) le : </span><span style="line-height:1px;font-size:0.8em"><i>Born on : </i></span></div><div class="col-5"><strong>' . $student->getStudent()->getDateOfBirth()->format('d M Y') . '</strong> à/at <strong>' . $student->getStudent()->getPlaceOfBirth() . '</strong></div><div class="col-2 pb-3 pt-3"><span style="line-height:1px;display:block">Statut : </span><span style="line-height:1px;font-size:0.8em"><i>Status : </i></span></div><div class="col-3"><strong>' . ($student->getStudent()->isRepeated() ? 'Redoublant' : 'Non redoublant') . '</strong></div>
                <div class="col-2 pb-3 pt-3"><span style="line-height:1px;display:block">Sexe : </span><span style="line-height:1px;font-size:0.8em"><i>Gender : </i></span></div><div class="col-5"><strong>' . strtoupper($student->getStudent()->getGender()->value) . '</strong></div><div class="col-2 pb-3 pt-3"><span style="line-height:1px;display:block">Parent : </span><span style="line-height:1px;font-size:0.8em"><i>Tutor : </i></span></div><div class="col-3"><strong>' . ($student->getStudent()->getTutor() ? $student->getStudent()->getTutor()->getFullName() : '') . '</strong></div>
                <div class="col-2 pb-3 pt-3"><span style="line-height:1px;display:block">Classe : </span><span style="line-height:1px;font-size:0.8em"><i>Class : </i></span></div><div class="col-5"><strong>' . $class->getClassOccurence()->getName() . '</strong></div><div class="col-2 pb-3 pt-3"><span style="line-height:1px;display:block">Contact parent : </span><span style="line-height:1px;font-size:0.8em"><i>Tutor phone : </i></span></div><div class="col-3"><strong>' . ($student->getStudent()->getTutor() ? $student->getStudent()->getTutor()->getPhone() : '') . '</strong></div>
                <div class="col-12 mb-4"></div>
                <div class="col-12" id="block-bul-1">';
            $html .= '<table class="table table-bordered" style="font-size:0.9em">';

            // --- Table Header ---
            $html .= '<thead>';
            $html .= '<tr class="bg-secondary">';
            $html .= '<th rowspan="2" style="vertical-align:middle;width:30%">Groupe de matières</th>';
            $html .= '<th rowspan="2" style="vertical-align:middle">Matières</th>';
            $html .= '<th rowspan="2" style="vertical-align:middle">Modules</th>';
            $html .= '<th></th>';
            for ($i = 0; $i < $evaluationsCount; $i++) {
                $html .= '<th colspan="' . $evaluationColumns - 1 . '" style="text-align:center">' . $evaluationsTitles[$i] . '</th>';
            }
            $html .= '</tr>';
            $html .= '<tr class="bg-secondary">';
            //$html .= '<th></th>';
            //$html .= '<th></th>';
            //$html .= '<th></th>';
            $html .= '<th></th>';
            for ($i = 0; $i < $evaluationsCount; $i++) {
                $html .= '<th>Note</th>';
                $html .= '<th style="display: none;">Bar.</th>';
                $html .= '<th>Moy.</th>';
                $html .= '<th>Cote</th>';
            }
            $html .= '</tr>';
            $html .= '</thead>';

            // --- Table Body ---
            $html .= '<tbody>';
            foreach ($subjectGroups as $group) {
                $totalGS = [];
                $totalGN = [];
                $groupName = $group->getDescription();
                $schoolClassSubjects = array_filter($group->getSchoolClassSubjects()->toArray(), function ($scs) use ($class) {
                    return $scs->getSchoolClassPeriod()->getId() == $class->getId();
                });
                $groupRowspan = 0;
                foreach ($schoolClassSubjects as $schoolClassSubject) {
                    $modules = $schoolClassSubject->getStudySubject()->getClassSubjectModules()->toArray();
                    $modules = array_filter($modules, function ($m) use ($class) {
                        return $m->getClass()->getId() === $class->getId();
                    });
                    //dump($modules);
                    if (count($modules) > 0) $groupRowspan += count($modules) + 1; // +1 for the total row
                }
                // Add 1 for the total group row at the end
                //$groupRowspan += 1;

                $firstGroupRow = true;
                foreach ($schoolClassSubjects as $schoolClassSubject) {
                    $sid = $schoolClassSubject->getStudySubject()->getId();
                    $subjectName = $schoolClassSubject->getStudySubject()->getName();
                    $subjectTeacher = $schoolClassSubject->getTeacher() ? $schoolClassSubject->getTeacher()->getFullName() : '';
                    $skills = $schoolClassSubject->getAwaitedSkills();
                    $modules = $schoolClassSubject->getStudySubject()->getClassSubjectModules()->toArray();
                    $modules = array_filter($modules, function ($m) use ($class) {
                        return $m->getClass()->getId() === $class->getId();
                    });
                    $subjectRowspan = count($modules);
                    $firstSubjectRow = true;
                    $totalS[$sid] = [];
                    $totalN[$sid] = [];
                    if (count($modules) > 0) {
                        foreach ($modules as $module) {
                            $moduleName = $module->getModule()->getModuleName();
                            $mid = $module->getId();

                            $html .= '<tr>';
                            if ($firstGroupRow) {
                                $html .= '<td rowspan="' . $groupRowspan . '">' . $groupName . '</td>';
                                $firstGroupRow = false;
                            }
                            if ($firstSubjectRow) {
                                $html .= '<td rowspan="' . $subjectRowspan . '"><p>' . $subjectName . '</p><p style="font-size:0.7em">' . $skills . '</p><p style="font-size:0.7em"><u><i>' . $subjectTeacher . '</i></u></p></td>';
                                $firstSubjectRow = false;
                            }
                            $html .= '<td>' . $moduleName . '</td>';
                            $html .= '<td></td>';
                            if (!isset($totalS[$sid])) $totalS[$sid] = [];
                            if (!isset($totalN[$sid])) $totalN[$sid] = [];
                            for ($i = 0; $i < $evaluationsCount; $i++) {
                                $tid = $periods[$i]->getId();
                                //dump($sid,$mid,$tid,$modules,$studentSelId,$evalIndex[1752][$sid]);
                                if (!isset($totalGS[$tid])) $totalGS[$tid] = 0;
                                if (!isset($totalGN[$tid])) $totalGN[$tid] = 0;
                                $note = $evalIndex[$studentSelId][$sid][$mid][$tid]->getEvaluationNote();
                                if (!isset($totalS[$sid][$tid])) $totalS[$sid][$tid] = 0;
                                $totalS[$sid][$tid] += $note;
                                if (!isset($totalN[$sid][$tid])) $totalN[$sid][$tid] = 0;
                                $totalN[$sid][$tid] += $module->getModuleNotation() && $module->getModuleNotation() > 0 ? $module->getModuleNotation() : 0;
                                $moy = $note / ($module->getModuleNotation() && $module->getModuleNotation() > 0 ? $module->getModuleNotation() : 0) * 20;
                                $html .= '<td style="text-align:right">' . $this->setNoteStyle(number_format($note, 2),$passNote, true) . '</td>';
                                $html .= '<td style="text-align:right;display:none">' . $module->getModuleNotation() . '</td>';
                                $html .= '<td style="text-align:right">' . $this->setNoteStyle(number_format($moy, 2),$passNote) . '</td>';
                                $html .= '<td style="text-align:center">' . ($this->getBareme($moy, $baremes) ? $this->getBareme($moy, $baremes)->getEvaluationAppreciationValue() : '') . '</td>';
                            }
                            $html .= '</tr>';
                        }


                        // Ligne de total par matière
                        $html .= '<tr class="bg-light" style="font-weight:bold">';
                        $html .= '<td colspan="3">TOTAL ' . $subjectName . '</td>';
                        //$html .= '<td></td>';
                        for ($i = 0; $i < $evaluationsCount; $i++) {
                            $tid = $periods[$i]->getId();
                            $totalGS[$tid] += $totalS[$sid][$tid];
                            $totalGN[$tid] += $totalN[$sid][$tid];
                            $moy = ($totalN[$sid][$tid] > 0 ? $totalS[$sid][$tid] / $totalN[$sid][$tid] : 0) * 20;
                            $html .= '<td style="text-align:right">' . number_format($totalS[$sid][$tid], 2) . '</td>';
                            $html .= '<td style="display: none;">-</td>';
                            $html .= '<td style="text-align:right">' . number_format($moy, 2) . '</td>';
                            $html .= '<td style="text-align:center">' . ($this->getBareme($moy, $baremes) ? $this->getBareme($moy, $baremes)->getEvaluationAppreciationValue() : '') . '</td>';
                        }
                        $html .= '</tr>';
                    }
                }

                // Ligne de total par groupe
                $html .= '<tr class="bg-light" style="font-weight:bold">';
                $html .= '<td colspan="4">TOTAL ' . $groupName . '</td>';
                //$html .= '<td></td>';
                for ($i = 0; $i < $evaluationsCount; $i++) {
                    $tid = $periods[$i]->getId();
                    $moy = ($totalGN[$tid] > 0 ? $totalGS[$tid] / $totalGN[$tid] : 0) * 20;
                    $html .= '<td style="text-align:right">' . number_format($totalGS[$tid], 2) . '</td>';
                    $html .= '<td style="display: none;">-</td>';
                    $html .= '<td style="text-align:right">' . number_format($moy, 2) . '</td>';
                    $html .= '<td style="text-align:center">' . ($this->getBareme($moy, $baremes) ? $this->getBareme($moy, $baremes)->getEvaluationAppreciationValue() : '') . '</td>';
                }
                $html .= '</tr>';
            }

            $html .= '</tbody>';
            $html .= '</table>';

            $html .= '</div>
            <div class="col-3"></div><div class="col-3"></div><div class="col-3"></div><div class="col-3"></div>
            </div>';
            return [$html];
        } elseif ($reportCardTemplate == 'D') {
            
            $student = $selStudent;
            
            $headerColspan = 9 + count($periods);

            $studentSel = $student[0]->getStudent();
            $studentSelId = $student[0]->getStudent()->getId();

            // Tableaux pour stocker les données globales
            $totalGroupe = [];
            $totalNotation = [];
            $totalGroupeSubject = [];
            $sumTotalGroupe = [];

            // Nouveaux tableaux pour les calculs
            $moduleRanks = [];
            $moduleMin = [];
            $moduleMax = [];
            $moduleSuccessRates = [];
            $globalPeriodTotals = [];
            $globalAverages = [];
            $globalMin = null;
            $globalMax = null;
            $globalSuccessRate = null;

            // Initialisation des tableaux
            foreach ($subjectGroups as $subjectGroup) {
                $totalNotation[$subjectGroup->getId()] = 0;
                $totalGroupeSubject[$subjectGroup->getId()] = [];
                $totalGroupe[$subjectGroup->getId()] = [];
                $sumTotalGroupe[$subjectGroup->getId()] = 0;

                foreach ($periods as $period) {
                    $totalGroupe[$subjectGroup->getId()][$period->getId()] = 0;
                }
            }

            $baremes = $class->getReportCardTemplate() ? $class->getReportCardTemplate()->getEvaluationAppreciationTemplate()->getBaremes()->toArray() : [];

            // Calcul des moyennes par module pour TOUS les étudiants
            $allStudentsModuleData = [];
            foreach ($students as $student) {
                $studentId = $student->getStudent()->getId();
                $allStudentsModuleData[$studentId] = [];

                foreach ($subjectGroups as $subjectGroup) {
                    $allStudentsModuleData[$studentId][$subjectGroup->getId()] = [];

                    foreach ($subjectGroup->getSchoolClassSubjects()->toArray() as $subject) {
                        if ($subject->getSchoolClassPeriod()->getId() == $class->getId()) {
                            $modules = $subject->getStudySubject()->getClassSubjectModules()->toArray();
                            $modules = array_filter($modules, function ($m) use ($class, $subject) {
                                return $m->getClass()->getId() === $class->getId() && $subject->getStudySubject()->getId() === $m->getSubject()->getId();
                            });

                            foreach ($modules as $module) {
                                $moduleTotal = 0;
                                $periodCount = 0;

                                foreach ($periods as $period) {
                                    // Vérification de l'existence des notes avec la bonne structure
                                    if (
                                        isset($evalIndex[$studentId]) &&
                                        isset($evalIndex[$studentId][$subject->getStudySubject()->getId()]) &&
                                        isset($evalIndex[$studentId][$subject->getStudySubject()->getId()][$module->getId()]) &&
                                        isset($evalIndex[$studentId][$subject->getStudySubject()->getId()][$module->getId()][$period->getId()])
                                    ) {

                                        $note = $evalIndex[$studentId][$subject->getStudySubject()->getId()][$module->getId()][$period->getId()]->getEvaluationNote();
                                        $moduleTotal += $note;
                                        $periodCount++;
                                    }
                                }

                                $average = $periodCount > 0 ? round($moduleTotal / $periodCount, 2) : 0;
                                $allStudentsModuleData[$studentId][$subjectGroup->getId()][$module->getId()] = $average;
                            }
                        }
                    }
                }
            }




            // Calcul des rangs, min, max et taux de réussite pour chaque module
            foreach ($subjectGroups as $subjectGroup) {
                foreach ($subjectGroup->getSchoolClassSubjects()->toArray() as $subject) {
                    if ($subject->getSchoolClassPeriod()->getId() == $class->getId()) {
                        $modules = $subject->getStudySubject()->getClassSubjectModules()->toArray();
                        $modules = array_filter($modules, function ($m) use ($class, $subject) {
                            return $m->getClass()->getId() === $class->getId() && $subject->getStudySubject()->getId() === $m->getSubject()->getId();
                        });

                        foreach ($modules as $module) {
                            $moduleAverages = [];

                            // Collecter toutes les moyennes pour ce module
                            foreach ($allStudentsModuleData as $studentId => $studentData) {
                                if (isset($studentData[$subjectGroup->getId()][$module->getId()])) {
                                    $moduleAverages[$studentId] = $studentData[$subjectGroup->getId()][$module->getId()];
                                }
                            }

                            if (!empty($moduleAverages)) {
                                // Trier par moyenne décroissante
                                arsort($moduleAverages);

                                $ranks = [];
                                $currentRank = 1;
                                $previousAverage = null;

                                foreach ($moduleAverages as $sid => $avg) {
                                    // Si c'est le premier élément ou si la note est différente de la précédente
                                    if (empty($ranks) || $avg !== $previousAverage) {
                                        $currentRank = count($ranks) + 1;
                                    }

                                    // Attribuer le rang (même rang si note identique)
                                    $ranks[$sid] = $currentRank;
                                    $previousAverage = $avg;
                                }

                                // Récupérer le rang de l'étudiant sélectionné
                                $moduleRanks[$subjectGroup->getId()][$module->getId()] = $ranks[$studentSelId] ?? 'N/A';

                                // Calcul min/max (préserve cette partie)
                                $moduleMin[$subjectGroup->getId()][$module->getId()] = min($moduleAverages);
                                $moduleMax[$subjectGroup->getId()][$module->getId()] = max($moduleAverages);

                                // Taux de réussite (préserve cette partie)
                                $successCount = 0;
                                $totalStudents = count($moduleAverages);
                                $threshold = $module->getModuleNotation() * 0.5;

                                foreach ($moduleAverages as $avg) {
                                    if ($avg >= $threshold) {
                                        $successCount++;
                                    }
                                }

                                $moduleSuccessRates[$subjectGroup->getId()][$module->getId()] = $totalStudents > 0 ?
                                    round(($successCount / $totalStudents) * 100, 2) : 0;
                            } else {
                                // Aucune donnée pour calculer le rang
                                $moduleRanks[$subjectGroup->getId()][$module->getId()] = 'N/A';
                                $moduleMin[$subjectGroup->getId()][$module->getId()] = 'N/A';
                                $moduleMax[$subjectGroup->getId()][$module->getId()] = 'N/A';
                                $moduleSuccessRates[$subjectGroup->getId()][$module->getId()] = 'N/A';
                            }
                        }
                    }
                }
            }

            // Calcul des données globales pour TOUS les étudiants
            $globalPeriodTotals = [];
            $globalAverages = [];

            foreach ($students as $student) {
                $studentId = $student->getStudent()->getId();
                $globalPeriodTotals[$studentId] = [];
                $globalAverages[$studentId] = 0;

                $totalNotationGlobal = 0;
                $totalNotesGlobal = 0;

                foreach ($periods as $period) {
                    $globalPeriodTotals[$studentId][$period->getId()] = 0;
                }

                foreach ($subjectGroups as $subjectGroup) {
                    foreach ($subjectGroup->getSchoolClassSubjects()->toArray() as $subject) {
                        if ($subject->getSchoolClassPeriod()->getId() == $class->getId()) {
                            $modules = $subject->getStudySubject()->getClassSubjectModules()->toArray();
                            $modules = array_filter($modules, function ($m) use ($class, $subject) {
                                return $m->getClass()->getId() === $class->getId() && $subject->getStudySubject()->getId() === $m->getSubject()->getId();
                            });

                            foreach ($modules as $module) {
                                $totalNotationGlobal += $module->getModuleNotation();

                                foreach ($periods as $period) {
                                    if (isset($evalIndex[$studentId][$subject->getStudySubject()->getId()][$module->getId()][$period->getId()])) {
                                        $note = $evalIndex[$studentId][$subject->getStudySubject()->getId()][$module->getId()][$period->getId()]->getEvaluationNote();
                                        $globalPeriodTotals[$studentId][$period->getId()] += $note;
                                        $totalNotesGlobal += $note;
                                    }
                                }
                            }
                        }
                    }
                }

                if ($totalNotationGlobal > 0 && count($periods) > 0) {
                    $globalAverages[$studentId] = round((($totalNotesGlobal / count($periods)) / $totalNotationGlobal) * 20, 2);
                } else {
                    $globalAverages[$studentId] = 0; // ou null selon votre logique métier
                }
            }

            // Calcul du rang global
            $globalRanks = [];
            if (!empty($globalAverages)) {
                arsort($globalAverages);
                $rank = 1;
                $previousAverage = null;
                $count = 0;

                foreach ($globalAverages as $sid => $avg) {
                    if ($previousAverage !== null && $avg < $previousAverage) {
                        $rank = $count + 1;
                    }

                    $globalRanks[$sid] = $rank;
                    $previousAverage = $avg;
                    $count++;
                }

                // Calcul min/max globaux
                $globalMin = min($globalAverages);
                $globalMax = max($globalAverages);

                // Taux de réussite global
                $successCount = 0;
                $totalStudents = count($globalAverages);

                foreach ($globalAverages as $avg) {
                    if ($avg >= $passNote) {
                        $successCount++;
                    }
                }

                $globalSuccessRate = $totalStudents > 0 ? round(($successCount / $totalStudents) * 100, 2) : 0;
            } else {
                // Aucune donnée pour calculer les rangs globaux
                $globalMin = 'N/A';
                $globalMax = 'N/A';
                $globalSuccessRate = 'N/A';
            }

            // Filtrer les élèves avec 100% de complétion
            $validStudentsForStats = [];
            foreach ($students as $student) {
                $studentId = $student->getStudent()->getId();

                // Calculer le taux de complétion pour cet élève
                $completionRate = 0;
                $totalPossible = 0;
                $actualEvaluations = 0;

                foreach ($subjectGroups as $subjectGroup) {
                    foreach ($subjectGroup->getSchoolClassSubjects()->toArray() as $subject) {
                        if ($subject->getSchoolClassPeriod()->getId() == $class->getId()) {
                            $modules = $subject->getStudySubject()->getClassSubjectModules()->toArray();
                            $modules = array_filter($modules, function ($m) use ($class, $subject) {
                                return $m->getClass()->getId() === $class->getId() && $subject->getStudySubject()->getId() === $m->getSubject()->getId();
                            });

                            foreach ($modules as $module) {
                                foreach ($periods as $period) {
                                    $totalPossible++;
                                    if (isset($evalIndex[$studentId][$subject->getStudySubject()->getId()][$module->getId()][$period->getId()])) {
                                        $actualEvaluations++;
                                    }
                                }
                            }
                        }
                    }
                }

                $completionRate = $totalPossible > 0 ? ($actualEvaluations / $totalPossible) * 100 : 0;

                if ($completionRate >= 100) {
                    $validStudentsForStats[] = $studentId;
                }
            }

            // Filtrer les moyennes générales pour ne garder que les élèves valides
            $filteredGlobalAverages = array_filter($globalAverages, function ($studentId) use ($validStudentsForStats) {
                return in_array($studentId, $validStudentsForStats);
            }, ARRAY_FILTER_USE_KEY);

            // Utiliser les données filtrées pour les statistiques
            if (!empty($filteredGlobalAverages)) {
                arsort($filteredGlobalAverages);
                $rank = 1;
                $previousAverage = null;
                $count = 0;

                foreach ($filteredGlobalAverages as $sid => $avg) {
                    if ($previousAverage !== null && $avg < $previousAverage) {
                        $rank = $count + 1;
                    }

                    $globalRanks[$sid] = $rank;
                    $previousAverage = $avg;
                    $count++;
                }

                // Calcul min/max globaux avec données filtrées
                $globalMin = min($filteredGlobalAverages);
                $globalMax = max($filteredGlobalAverages);

                // Taux de réussite global avec données filtrées
                $successCount = 0;
                $totalStudentsFiltered = count($filteredGlobalAverages);

                foreach ($filteredGlobalAverages as $avg) {
                    if ($avg >= $passNote) {
                        $successCount++;
                    }
                }

                $globalSuccessRate = $totalStudentsFiltered > 0 ? round(($successCount / $totalStudentsFiltered) * 100, 2) : 0;
            } else {
                $globalMin = 'N/A';
                $globalMax = 'N/A';
                $globalSuccessRate = 'N/A';
            }

            // Pour le rang de l'élève, vérifier s'il est dans la liste valide
            $studentRank = 'N/A';
            if (in_array($studentSelId, $validStudentsForStats) && isset($globalRanks[$studentSelId])) {
                $studentRank = $globalRanks[$studentSelId];
            }

            foreach ($students as $student) {
                $studentId = $student->getStudent()->getId();
                if (!isset($globalRanks[$studentId])) {
                    $globalRanks[$studentId] = 'N/A';
                }
            }

            $reportCard = $this->entityManager->getRepository(ReportCardTemplate::class)->findOneBy(['name' => $reportCardTemplate]);
            $student = $selStudent;
            
            $html = '<div class="ranking-div" rank-value="'.(isset($globalRanks[$studentSelId]) ? $globalRanks[$studentSelId] : 1000).'">';
            $html .= '<div class="row bulletin-header">
            <div class="col-5 text-center" style="font-size: 0.9em;"><p >' . $reportCard->getHeaderLeft() . '</p><p >' . $reportCard->getNationalMottoLeft() . '</p>' . $reportCard->getAdditionalHeaderLeft() . '<p style="font-weight:bold">' . $currentSchool->getName() . '</p><p >' . $reportCard->getSchoolValuesLeft() . '</p><p >PO BOX :' . $currentSchool->getAddress() . '</p><p >Tel. :' . $currentSchool->getContactPhone() . '</p></div><div class="col-2 text-center"><img class="school-logo" src="' . $this->params->get('kernel.project_dir') .  '/public' . ($class->getSchool()->getLogo() ? '/uploads/logos/' . $class->getSchool()->getLogo() : '/img/logo_test.png') . '" width="120px" height="120px"></div><div class="col-5 text-center" style="font-size: 0.9em;"><p >' . $reportCard->getHeaderRight() . '</p><p >' . $reportCard->getNationalMottoRight() . '</p>' . $reportCard->getAdditionalHeaderRight() . '<p style="font-weight:bold">' . $currentSchool->getName() . '</p><p >' . $reportCard->getSchoolValuesRight() . '</p><p >BP :' . $currentSchool->getAddress() . '</p><p >Tél. :' . $currentSchool->getContactPhone() . '</p></div>
            </div>';

            $html .= '<div class="row">
            <div class="col-12 text-center" style="font-size:14pt">' . $reportCard->getHeaderTitle() . '</div>
            </div>';

            $html .= '<div class="row">
            <div class="col-12 text-center" style="font-size:9pt">' . $currentPeriod->getName() . ' - ' . strtoupper($periodicity[0]->getName()) . '</div>
            </div>';

            $html .= '<div class="row">
            <div class="col-6"><span style="font-weight:bold">' . ($bulLang === 'fr' ? 'Nom et prénoms : ' : 'Full Name: ') . '</span>' . $student[0]->getStudent()->getFullName() . '</div><div class="col-4"><span style="font-weight:bold">' . ($bulLang === 'fr' ? 'Classe : ' : 'Class: ') . '</span>' . $student[0]->getSchoolClassPeriod()->getClassOccurence()->getName() . '</div><div class="col-2">' . ($bulLang === 'fr' ? 'Effectif : ' : 'Total: ') . count($students) . '</div>
            </div>';

            $html .= '<div class="row">
            <div class="col-6"><span style="font-weight:bold">' . ($bulLang === 'fr' ? 'Né(e) le : ' : 'Born on: ') . '</span>' . date_format($student[0]->getStudent()->getDateOfBirth(), 'd/m/Y') . ' ' . ($bulLang === 'fr' ? 'à' : 'in') . ' ' . $student[0]->getStudent()->getPlaceOfBirth() . '</div><div class="col-4"><span style="font-weight:bold">' . ($bulLang === 'fr' ? 'Matricule : ' : 'Reg. Number: ') . '</span>' . $student[0]->getStudent()->getRegistrationNumber() . '</div><div class="col-2"></div>
            </div>';

            $html .= '<div class="row" mb-3>
            <div class="col-6"><span style="font-weight:bold">' . ($bulLang === 'fr' ? 'Nom du tuteur : ' : 'Tutor Name: ') . '</span>' . ($student[0]->getStudent()->getTutor() !== null ? $student[0]->getStudent()->getTutor()->getFullName() . '/' . $student[0]->getStudent()->getTutor()->getPhone() : '') . '</div><div class="col-4"><span style="font-weight:bold">' . ($bulLang === 'fr' ? 'Enseignant principal : ' : 'Class Master: ') . '</span>' . ($student[0]->getSchoolClassPeriod()->getClassMaster() !== null ? $student[0]->getSchoolClassPeriod()->getClassMaster()->getFullName() : '') . '</div><div class="col-2"><img class="student-photo" src="' . $this->params->get('kernel.project_dir') .  '/public' . ($student[0]->getStudent()->getPhoto() ? '/uploads/' . $student[0]->getStudent()->getPhoto() : '/img/default_student.png') . '" width="60px" height="60px" style="position:relative"></div>
            </div>';

            // Génération du HTML
            if ($class->getSchoolClassSubjects()->count() > 0) {
                foreach ($subjectGroups as $subjectGroup) :
                    $html .= '<table class="table table-bordered" style="font-size:0.9em"><thead><tr><th colspan="' . $headerColspan . '"><h5 style="font-weight:bold;margin-top:0;margin-bottom:0;">' . $subjectGroup->getDescription() . '</h5></th></tr><tr class="coloured-header"><th>' . ($bulLang === 'fr' ? 'Sujet' : 'Subject') . '</th>';
                    foreach ($periods as $period) $html .= '<th>' . $period->getShortName() . '</th>';
                    $html .= '<th>' . ($bulLang === 'fr' ? 'Barème' : 'Grading sc.') . '</th><th>' . ($bulLang === 'fr' ? 'Moy.' : 'Avg.') . '</th><th>' . ($bulLang === 'fr' ? 'Rang' : 'Rank') . '</th><th>' . ($bulLang === 'fr' ? 'Cotation' : 'Rating') . '</th><th>' . ($bulLang === 'fr' ? 'Min.' : 'Min.') . '</th><th>' . ($bulLang === 'fr' ? 'Moy. gén.' : 'Gen. Avg.') . '</th><th>' . ($bulLang === 'fr' ? 'Max.' : 'Max.') . '</th><th>' . ($bulLang === 'fr' ? 'T.R%' : 'S.R%') . '</th></tr></thead><tbody>';

                    foreach ($subjectGroup->getSchoolClassSubjects()->toArray() as $subject) {
                        if ($subject->getSchoolClassPeriod()->getId() == $class->getId()) {
                            $modules = $subject->getStudySubject()->getClassSubjectModules()->toArray();
                            $modules = array_filter($modules, function ($m) use ($class, $subject) {
                                return $m->getClass()->getId() === $class->getId() && $subject->getStudySubject()->getId() === $m->getSubject()->getId();
                            });

                            //Tri des modules par nom
                            usort($modules, function ($a, $b) {
                                return strcmp($a->getModule()->getModuleName(), $b->getModule()->getModuleName());
                            });

                            foreach ($modules as $module) {
                                $html .= '<tr><td style="width:40%">' . $subject->getStudySubject()->getName() . ' - ' . $module->getModule()->getModuleName() . '<p style="" class="teacher-name">' . ($subject->getTeacher() ? $subject->getTeacher()->getFullName() : '') . '</p></td>';

                                if (!isset($totalGroupeSubject[$subjectGroup->getId()][$module->getId()])) {
                                    $totalGroupeSubject[$subjectGroup->getId()][$module->getId()] = 0;
                                }

                                foreach ($periods as $period) {
                                    if (isset($evalIndex[$studentSelId][$subject->getStudySubject()->getId()][$module->getId()][$period->getId()])) {
                                        $note = $evalIndex[$studentSelId][$subject->getStudySubject()->getId()][$module->getId()][$period->getId()]->getEvaluationNote();
                                        $html .= '<td class="cell-number" style="border: 1px solid #000000 !important;">' . ($note == 0 ? '' : round($note, 1)) . '</td>';
                                        $totalGroupeSubject[$subjectGroup->getId()][$module->getId()] += $note;
                                        $totalGroupe[$subjectGroup->getId()][$period->getId()] += $note;
                                    } else {
                                        $html .= '<td></td>';
                                    }
                                }

                                $totalNotation[$subjectGroup->getId()] += $module->getModuleNotation();
                                if (count($periods) > 0)
                                    $moduleAverage = round($totalGroupeSubject[$subjectGroup->getId()][$module->getId()] / count($periods), 2);
                                else
                                    $moduleAverage = 0;

                                // Récupération des données calculées
                                $rank = isset($moduleRanks[$subjectGroup->getId()][$module->getId()]) ?
                                    $moduleRanks[$subjectGroup->getId()][$module->getId()] : 'N/A';

                                $min = isset($moduleMin[$subjectGroup->getId()][$module->getId()]) ?
                                    $moduleMin[$subjectGroup->getId()][$module->getId()] : 'N/A';

                                $max = isset($moduleMax[$subjectGroup->getId()][$module->getId()]) ?
                                    $moduleMax[$subjectGroup->getId()][$module->getId()] : 'N/A';

                                $successRate = isset($moduleSuccessRates[$subjectGroup->getId()][$module->getId()]) ?
                                    $moduleSuccessRates[$subjectGroup->getId()][$module->getId()] : 'N/A';

                                // Calcul de la moyenne générale (moyenne de toutes les moyennes des étudiants pour ce module)
                                $generalAverage = 'N/A';
                                if (isset($allStudentsModuleData) && !empty($allStudentsModuleData)) {
                                    $allAverages = [];
                                    foreach ($allStudentsModuleData as $studentData) {
                                        if (isset($studentData[$subjectGroup->getId()][$module->getId()])) {
                                            $allAverages[] = $studentData[$subjectGroup->getId()][$module->getId()];
                                        }
                                    }
                                    if (!empty($allAverages)) {
                                        $generalAverage = round(array_sum($allAverages) / count($allAverages), 2);
                                    }
                                }

                                $html .= '<td class="cell-number">' . $module->getModuleNotation() . '</td>';
                                $html .= '<td class="cell-number" style="font-weight:bold;background-color:#f0f0f0 !important">' . $moduleAverage . '</td>';
                                $html .= '<td class="cell-number">' . ($moduleAverage > 0 ? $rank . ($bulLang === 'fr' ? 'e' : 'th') . '' : '') . '</td>';
                                //dd($this->getBareme(($moduleAverage / $module->getModuleNotation() * 20), $baremes));
                                $html .= '<td style="text-align:center">' . ($this->getBareme(($moduleAverage / $module->getModuleNotation() * 20), $baremes) ? $this->getBareme(($moduleAverage / $module->getModuleNotation() * 20), $baremes)->getEvaluationAppreciationValue() : '') . '</td>';
                                $html .= '<td class="cell-number">' . $min . '</td>';
                                $html .= '<td class="cell-number">' . $generalAverage . '</td>'; // Moyenne générale de la classe
                                $html .= '<td class="cell-number">' . $max . '</td>';
                                $html .= '<td class="cell-number">' . $successRate . '%</td>';
                                $html .= '</tr>';
                            }
                        }
                    }

                    $html .= '<tr class="bg-light table-recap" style="font-weight:bold;font-style:italic"><td>Récapitulatif</td>';
                    if (!isset($sumTotalGroupe[$subjectGroup->getId()])) $sumTotalGroupe[$subjectGroup->getId()] = 0;

                    foreach ($periods as $period) {
                        $html .= '<td class="cell-number">' . $totalGroupe[$subjectGroup->getId()][$period->getId()] . '</td>';
                        $sumTotalGroupe[$subjectGroup->getId()] += $totalGroupe[$subjectGroup->getId()][$period->getId()];
                    }

                    $html .= '<td class="cell-number">' . $totalNotation[$subjectGroup->getId()] . '</td><td class="cell-number" style="font-weight:bold;background-color:#f0f0f0">' . (count($periods) > 0 ? round($sumTotalGroupe[$subjectGroup->getId()] / count($periods), 2) : 0) . '</td><td class="cell-number"></td><td class="cell-number"></td><td class="cell-number"></td><td class="cell-number"></td><td class="cell-number"></td><td class="cell-number"></td></tr>';
                    $html .= '</tbody></table>';
                endforeach;
            }

            // Section pour les totaux globaux
            $html .= '<div class="row"><div class="col-6">';
            $html .= '<table class="table table-bordered" style="font-size:0.9em; margin-top: 20px;">';
            $html .= '<thead><tr class="bg-light"><th colspan="3"><h4 style="font-weight:bold;margin-top:0;margin-bottom:0;">' . ($bulLang === 'fr' ? 'BILAN GLOBAL' : 'GLOBAL SUMMARY') . '</h4></th></tr></thead>';
            $html .= '<tbody>';

            // Calcul des variables nécessaires
            $totalNotesEleve = 0;
            foreach ($periods as $period) {
                $periodTotal = isset($globalPeriodTotals[$studentSelId][$period->getId()]) ? $globalPeriodTotals[$studentSelId][$period->getId()] : 0;
                $totalNotesEleve += $periodTotal;
            }

            // CALCULS CONSERVÉS (ne pas retirer)
            // Calcul de la moyenne générale globale (moyenne des moyennes générales de tous les étudiants)
            $allGlobalAverages = array_values($globalAverages);
            $generalGlobalAverage = !empty($allGlobalAverages) ?
                round(array_sum($allGlobalAverages) / count($allGlobalAverages), 2) : 'N/A';
            $globalAverage = isset($globalAverages[$studentSelId]) ? $globalAverages[$studentSelId] : 'N/A';
            $globalRank = isset($globalRanks[$studentSelId]) ? $globalRanks[$studentSelId] : 'N/A';

            // Calcul du nombre de matières validées
            $nbMatieresValidees = 0;
            $nbMatieresTotales = 0;
            foreach ($subjectGroups as $subjectGroup) {
                foreach ($subjectGroup->getSchoolClassSubjects()->toArray() as $subject) {
                    if ($subject->getSchoolClassPeriod()->getId() == $class->getId()) {
                        $nbMatieresTotales++;
                        $subjectId = $subject->getStudySubject()->getId();

                        // Calcul de la notation totale de la matière
                        $notationTotaleMatiere = 0;
                        $modules = $subject->getStudySubject()->getClassSubjectModules()->toArray();
                        $modules = array_filter($modules, function ($m) use ($class, $subject) {
                            return $m->getClass()->getId() === $class->getId() && $subject->getStudySubject()->getId() === $m->getSubject()->getId();
                        });

                        foreach ($modules as $module) {
                            $notationTotaleMatiere += $module->getModuleNotation();
                        }

                        // Calcul de la moyenne de la matière
                        $totalMatiere = 0;
                        $countPeriods = 0;
                        foreach ($periods as $period) {
                            foreach ($modules as $module) {
                                if (isset($evalIndex[$studentSelId][$subjectId][$module->getId()][$period->getId()])) {
                                    $note = $evalIndex[$studentSelId][$subjectId][$module->getId()][$period->getId()]->getEvaluationNote();
                                    $totalMatiere += $note;
                                    $countPeriods++;
                                }
                            }
                        }

                        $moyenneMatiere = count($periods) > 0 ? ($totalMatiere / count($periods)) : 0;


                        // Condition modifiée : moyenne >= moitié de la notation totale
                        if ($moyenneMatiere >= $notationTotaleMatiere / 2) {
                            $nbMatieresValidees++;
                        }
                    }
                }
            }

            // Calcul de l'écart-type
            $ecartType = $this->getEcartType($filteredGlobalAverages);

            // Première partie : Élève
            $html .= '<tr>';
            $html .= '<td rowspan="4" style="vertical-align:middle; text-align:center; writing-mode: vertical-lr; transform: rotate(180deg); width: 80px;">';
            $html .= '<strong>' . ($bulLang === 'fr' ? 'ÉLÈVE' : 'STUDENT') . '</strong>';
            $html .= '</td>';
            $html .= '<td style="text-align:left"><strong>Total</strong></td>';
            $html .= '<td class="cell-number">' . (count($periods) > 0 ? round($totalNotesEleve / count($periods), 2) : 0) . '</td>';
            $html .= '</tr>';

            $html .= '<tr>';
            $html .= '<td style="text-align:left"><strong>' . ($bulLang === 'fr' ? 'Moyenne' : 'Average') . '</strong></td>';
            $html .= '<td class="cell-number avg" style="font-size:9pt; font-weight:bold">' . $globalAverage . '</td>';
            $html .= '</tr>';

            $html .= '<tr>';
            $html .= '<td style="text-align:left"><strong>' . ($bulLang === 'fr' ? 'Rang' : 'Rank') . '</strong></td>';
            $html .= '<td class="cell-number rang" style="font-size:8pt">' . ($globalAverage > 0 ? $globalRank . ($bulLang === 'fr' ? 'e' : ($globalRank === 1 ? 'st' : ($globalRank === 2 ? 'nd' : ($globalRank === 3 ? 'rd' : 'th')))) : '') . '</td>';
            $html .= '</tr>';

            $html .= '<tr>';
            $html .= '<td style="text-align:left"><strong>' . ($bulLang === 'fr' ? 'Nbre de matières validées' : 'Number of subjects passed') . '</strong></td>';
            $html .= '<td class="cell-number">' . $nbMatieresValidees . '/' . $nbMatieresTotales . '</td>';
            $html .= '</tr>';

            // Deuxième partie : Classe
            $html .= '<tr>';
            $html .= '<td rowspan="6" style="vertical-align:middle; text-align:center; writing-mode: vertical-lr; transform: rotate(180deg); width: 80px;">';
            $html .= '<strong>' . ($bulLang === 'fr' ? 'CLASSE' : 'CLASS') . '</strong>';
            $html .= '</td>';
            $html .= '<td class="muted-text" style="text-align:left;font-style: italic;background-color: #080808;color: #ffffff">' . ($bulLang === 'fr' ? 'La moyenne générale est calculée sur 20' : 'The overall average is calculated out of 20') . '</td>';
            $html .= '<td class="cell-number muted-text" style="text-align:left;font-style: italic;background-color: #080808;color: #ffffff"></td>';
            $html .= '</tr>';

            $html .= '<tr>';
            $html .= '<td style="text-align:left"><strong>' . ($bulLang === 'fr' ? 'Moyenne la plus faible' : 'Lowest average') . '</strong></td>';
            $html .= '<td class="cell-number">' . ($globalMin !== null ? $globalMin : '-') . '</td>';
            $html .= '</tr>';

            $html .= '<tr>';
            $html .= '<td style="text-align:left"><strong>' . ($bulLang === 'fr' ? 'Moyenne la plus forte' : 'Highest average') . '</strong></td>';
            $html .= '<td class="cell-number">' . ($globalMax !== null ? $globalMax : '-') . '</td>';
            $html .= '</tr>';

            $html .= '<tr>';
            $html .= '<td style="text-align:left"><strong>' . ($bulLang === 'fr' ? 'Taux de réussite' : 'Success rate') . '</strong></td>';
            $html .= '<td class="cell-number">' . ($globalSuccessRate !== null ? $globalSuccessRate . '%' : 'N/A') . '</td>';
            $html .= '</tr>';

            $html .= '<tr>';
            $html .= '<td style="text-align:left"><strong>' . ($bulLang === 'fr' ? 'Écart-type' : 'Standard deviation') . '</strong></td>';
            $html .= '<td class="cell-number">' . ($ecartType !== null ? round($ecartType, 2) : 'N/A') . '</td>';
            $html .= '</tr>';

            $html .= '<tr>';
            $html .= '<td style="text-align:left"><strong>' . ($bulLang === 'fr' ? 'Moyenne générale' : 'Overall average') . '</strong></td>';
            $html .= '<td class="cell-number">' . $generalGlobalAverage . '</td>';
            $html .= '</tr>';

            $html .= '</tbody></table>';
            $html .= '</div>';
            $html .= '<div class="col-3">';
            // 9 lignes 2colonnes pour la discipline
            $html .= '<div class="table-responsive"><table class="table table-bordered" style="font-size:0.9em; margin-top: 20px;">';
            $html .= '<thead><tr class="bg-light"><th colspan="2"><h4 style="font-weight:bold;margin-top:0;margin-bottom:0">DISCIPLINE</h4></th></tr></thead>';
            $html .= '<tbody>';
            $html .= '<tr><td style="width:70%;">' . ($bulLang === 'fr' ? 'Absences (h)' : 'Absences (h)') . '</td><td></td></tr>';
            $html .= '<tr><td style="width:70%;">' . ($bulLang === 'fr' ? 'Absences justifiées (h)' : 'Justified absences (h)') . '</td><td></td></tr>';
            $html .= '<tr><td style="width:70%;">' . ($bulLang === 'fr' ? 'Retard ' : 'Late') . '</td><td></td></tr>';
            $html .= '<tr><td style="width:70%;">' . ($bulLang === 'fr' ? 'Retards injustifiés (h) ' : 'Unjustified lateness (h)') . '</td><td></td></tr>';
            $html .= '<tr><td style="width:70%;">' . ($bulLang === 'fr' ? 'Retenues (h)' : 'Detentions (h)') . '</td><td></td></tr>';
            $html .= '<tr><td style="width:70%;">' . ($bulLang === 'fr' ? 'Avertissement' : 'Warning') . '</td><td></td></tr>';
            $html .= '<tr><td style="width:70%;">' . ($bulLang === 'fr' ? 'Blâme' : 'Reprimand') . '</td><td></td></tr>';
            $html .= '<tr><td style="width:70%;">' . ($bulLang === 'fr' ? 'Exclusion (jours)' : 'Suspension (days)') . '</td><td></td></tr>';
            $html .= '<tr><td style="width:70%;">' . ($bulLang === 'fr' ? 'Exclusion définitive' : 'Expulsion') . '</td><td></td></tr>';
            $html .= '</tbody></table>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '<div class="col-3">';
            $html .= '<table><thead><tr><th style="padding: 3px;">'.($bulLang === 'fr' ? 'APPRECIATION' : 'COTATION') . '</th></tr></thead><tbody><tr><td style="font-weight:bold;text-align:center">' . ($this->getBareme($globalAverage, $baremes) ? $this->getBareme($globalAverage, $baremes)->getEvaluationAppreciationFullValue() : '') . '</td></tr></tbody></table>';
            
            //5 lignes 2 colonnes pour appréciation travail : "TRAVAIL"
            $html .= '<div class="table-responsive"><table class="table table-bordered" style="font-size:0.9em; margin-top: 20px;">';
            $html .= '<thead><tr class="bg-light"><th colspan="2"><h4 style="font-weight:bold;margin-top:0;margin-bottom:0">' . ($bulLang === 'fr' ? 'TRAVAIL' : 'WORK') . '</h4></th></tr></thead>';
            $html .= '<tbody>';
            $html .= '<tr><td style="width:70%;">' . ($bulLang === 'fr' ? 'Félicitations' : 'Congratulations') . '</td><td>' . ($globalAverage >= 15 ? ($bulLang === 'fr' ? "Oui" : "Yes") : "") . '</td></tr>';
            $html .= '<tr><td style="width:70%;">' . ($bulLang === 'fr' ? 'Tableau d\'honneur' : 'Honor roll') . '</td><td>' . ($globalAverage >= 12 ? ($bulLang === 'fr' ? "Oui" : "Yes") : "") . '</td></tr>';
            $html .= '<tr><td style="width:70%;">' . ($bulLang === 'fr' ? 'Encouragements' : 'Encouragements') . '</td><td>' . ($globalAverage >= 11 ? ($bulLang === 'fr' ? "Oui" : "Yes") : "") . '</td></tr>';
            $html .= '<tr><td style="width:70%;">' . ($bulLang === 'fr' ? 'Avertissements' : 'Warnings') . '</td><td>' . ($globalAverage <= 8 ? ($bulLang === 'fr' ? "Oui" : "Yes") : "") . '</td></tr>';
            $html .= '<tr><td style="width:70%;">' . ($bulLang === 'fr' ? 'Blâme' : 'Reprimand') . '</td><td>' . ($globalAverage <= 7 ? ($bulLang === 'fr' ? "Oui" : "Yes") : "") . '</td></tr>';
            $html .= '</tbody></table>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '<div class="col-12 text-end">Yaoundé, ' . ($bulLang === 'fr' ? 'le' : 'on') . ' ' . date('d M Y') . '</div>';
            $html .= '<div class="col-3"><div class="table-responsive"><table class="table table-bordered" width="100%"><thead><tr><th style="font-size:0.68em">' . ($bulLang === 'fr' ? 'Appréciation du travail de l\'élève (forces et points à améliorer)' : 'Appreciation of the student\'s work (strengths and areas for improvement)') . '</th></tr></thead><tbody><tr><td style="height:100px"></td></tr></tbody></table></div></div>';
            $html .= '<div class="col-3"><div class="table-responsive"><table class="table table-bordered" width="100%"><thead><tr><th>' . ($bulLang === 'fr' ? 'Visa du parent' : 'Parent\'s approval') . '</th></tr></thead><tbody><tr><td style="height:100px"></td></tr></tbody></table></div></div>';
            $html .= '<div class="col-3"><div class="table-responsive"><table class="table table-bordered" width="100%"><thead><tr><th>' . ($bulLang === 'fr' ? 'Enseignant(e) principal(e)' : 'Main teacher') . '</th></tr></thead><tbody><tr><td style="height:100px"></td></tr></tbody></table></div></div>';
            $html .= '<div class="col-3"><div class="table-responsive"><table class="table table-bordered" width="100%"><thead><tr><th>' . ($bulLang === 'fr' ? 'Le Directeur' : 'The Director') . '</th></tr></thead><tbody><tr><td style="height:100px"></td></tr></tbody></table></div></div>';
            $html .= '</div>';
            //fermeture du ranking div
            $html.='</div>';

            return [$html];
        }

        if ($progressFile && $totalStudents > 0) {
            $this->updateGenerationProgress($progressFile, $currentProgress, $totalStudents, "Bulletin généré avec succès");
        }
    }

    /**
     * Retourne le rang d'une note dans un tableau, ex aequo inclus (rang 1 pour la meilleure note).
     *
     * @param float|int $note   La note à classer
     * @param array     $notes  Tableau de notes (float ou int)
     * @param bool      $desc   true = classement décroissant (meilleure note = rang 1), false = croissant
     * @return int|null         Le rang (1 = meilleur), ou null si la note n'est pas trouvée
     */
    function getRank($note, array $notes, $desc = true, array $validStudentIds = null): ?int
    {
        // Si c'est un tableau associatif [studentId => note], extraire juste les notes
        $isAssociative = false;
        foreach ($notes as $key => $value) {
            if (!is_numeric($key)) {
                $isAssociative = true;
                break;
            }
        }

        if ($isAssociative) {
            // C'est un tableau associatif, filtrer d'abord par étudiants valides si nécessaire
            $filteredNotes = [];
            foreach ($notes as $studentId => $studentNote) {
                if ($validStudentIds === null || in_array($studentId, $validStudentIds)) {
                    if ($studentNote !== null && $studentNote !== '') {
                        $filteredNotes[] = (float)$studentNote;
                    }
                }
            }
            $notes = $filteredNotes;
        } else {
            // C'est un tableau simple, juste filtrer les valeurs nulles
            $notes = array_filter($notes, fn($n) => $n !== null && $n !== '');
            $notes = array_map('floatval', $notes);
        }

        if (empty($notes)) {
            return null;
        }

        $desc ? rsort($notes) : sort($notes);

        // Indexe les rangs
        $ranks = [];
        $currentRank = 1;
        foreach ($notes as $idx => $n) {
            if (!isset($ranks['' . $n])) {
                $ranks['' . $n] = $currentRank;
            }
            $currentRank++;
        }
        //dd($ranks);

        return $ranks['' . $note] ?? null;
    }


    function setNoteStyle($note,$passNote, $onlyBad = false)
    {
        $style = '<span class="text';
        if ((float)$note < $passNote) {
            $style .= '-danger';
        } else {
            if (!$onlyBad) $style .= '-success';
        }
        $style .= '">' . $note . '</span>';
        return $style;
    }

    function getBareme($note, array $baremes)
    {
        //trier par $bareme->getEvaluationAppreciationMaxNote() croissant
        usort(
            $baremes,
            fn($a, $b) =>
            $a->getEvaluationAppreciationMaxNote() <=> $b->getEvaluationAppreciationMaxNote()
        );
        foreach ($baremes as $bareme) {
            if ($note <= $bareme->getEvaluationAppreciationMaxNote()) {
                return $bareme;
                break;
            }
        }
    }

    function getEcartType(array $moyennes)
    {
        $n = count($moyennes);
        if ($n === 0) return null;
        $moyenne = array_sum($moyennes) / $n;
        $somme = 0;
        foreach ($moyennes as $note) {
            $somme += pow($note - $moyenne, 2);
        }
        return sqrt($somme / $n); // ou / ($n-1) pour l'écart-type empirique
    }

    function getMoyenneGenerale(array $moyennes)
    {
        $total = 0;
        $count = 0;
        foreach ($moyennes as $moyenne) {
            if ($moyenne !== null && $moyenne !== '') {
                $total += $moyenne;
                $count++;
            }
        }
        return $count > 0 ? round($total / $count, 2) : null;
    }

    public function updateGenerationProgress(string $progressFile, int $currentProgress, int $totalStudents, string $message): void
    {
        try {
            // Utiliser error_log temporairement
            error_log("BulletinGenerator - Mise à jour progression: {$currentProgress}/{$totalStudents} - {$message}");

            if (!file_exists($progressFile)) {
                error_log("BulletinGenerator - Fichier de progression non trouvé: {$progressFile}");
                return;
            }

            // Lire les données existantes
            $content = file_get_contents($progressFile);
            $progressData = json_decode($content, true);

            if (!$progressData) {
                error_log("BulletinGenerator - Données de progression invalides dans: {$progressFile}");
                return;
            }

            $percentage = $totalStudents > 0 ?
                min(100, round(($currentProgress / $totalStudents) * 100, 2)) : 0;

            // Mettre à jour seulement les champs nécessaires
            $progressData['progress'] = $percentage;
            $progressData['message'] = $message . " " . $currentProgress . "/" . $totalStudents;
            $progressData['updatedAt'] = (new \DateTime())->format('Y-m-d H:i:s');

            // Écrire les données mises à jour
            file_put_contents($progressFile, json_encode($progressData, JSON_PRETTY_PRINT));

            // Forcer l'écriture sur le disque
            if (function_exists('fflush')) {
                if ($handle = fopen($progressFile, 'r+')) {
                    fflush($handle);
                    fclose($handle);
                }
            }

            error_log("BulletinGenerator - Progression mise à jour: {$percentage}%");
        } catch (\Exception $e) {
            error_log("BulletinGenerator - Erreur mise à jour progression: " . $e->getMessage());
        }
    }

    private function calculateCompletionRate(array $evalIndex, int $studentId, array $periods, array $modulesBySubject, $class = null): float
    {
        $totalPossibleEvaluations = 0;
        $actualEvaluations = 0;

        foreach ($modulesBySubject as $subjectId => $subjectModules) {
            if (!is_array($subjectModules)) {
                continue;
            }

            foreach ($subjectModules as $module) {
                // Vérifier que le module appartient bien à la classe concernée
                if ($class && method_exists($module, 'getClass') && $module->getClass()->getId() !== $class->getId()) {
                    continue;
                }

                foreach ($periods as $period) {
                    $totalPossibleEvaluations++;

                    // Vérifier l'existence de l'évaluation avec des vérifications en cascade
                    if (
                        isset($evalIndex[$studentId]) &&
                        isset($evalIndex[$studentId][$subjectId]) &&
                        isset($evalIndex[$studentId][$subjectId][$module->getId()]) &&
                        isset($evalIndex[$studentId][$subjectId][$module->getId()][$period->getId()])
                    ) {

                        $eval = $evalIndex[$studentId][$subjectId][$module->getId()][$period->getId()];
                        if ($eval && $eval->getEvaluationNote() !== null) {
                            $actualEvaluations++;
                        }
                    }
                }
            }
        }

        return $totalPossibleEvaluations > 0 ? ($actualEvaluations / $totalPossibleEvaluations) * 100 : 0;
    }
}
