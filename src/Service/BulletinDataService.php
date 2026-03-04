<?php

namespace App\Service;

use App\DTO\ProgressQueryDTO;
use App\Entity\SchoolClassPeriod;
use App\Entity\User;
use App\Repository\ClasseRepository;
use App\Repository\SchoolClassPeriodRepository;
use App\Repository\StudentClassRepository;
use App\Repository\SchoolEvaluationFrameRepository;
use App\Repository\SchoolEvaluationTimeRepository;
use App\Repository\ClassSubjectModuleRepository;
use App\Repository\EvaluationRepository;
use App\Repository\ReportCardTemplateRepository;
use App\Repository\SubjectGroupRepository;
use App\Repository\SchoolClassSubjectRepository;
use App\Repository\StudyLevelRepository;
use App\Repository\UserRepository;

class BulletinDataService
{
    public function __construct(
        private SchoolClassPeriodRepository $classRepo,
        private StudentClassRepository $studentRepo,
        private SchoolEvaluationFrameRepository $frameRepo,
        private SchoolEvaluationTimeRepository $timeRepo,
        private ClassSubjectModuleRepository $subjectModuleRepo,
        private EvaluationRepository $evaluationRepo,
        private SubjectGroupRepository $subjectGroupRepo,
        private SchoolClassSubjectRepository $schoolClassSubjectRepo,
        private ClasseRepository $classeRepo,
        private ReportCardTemplateRepository $templateRepo,
        private StudyLevelRepository $studyLevelRepo,
        private UserRepository $userRepo
    ) {}

    public function getSections(User $user): array
    {
        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles())) {
            $sections = $this->studyLevelRepo->findAll();
        } else {
            $user = $this->userRepo->findOneBy(['username' => $user->getUserIdentifier()]);
            $config = $user ? $user->getBaseConfigurations()->toArray() : [];
            if (count($config) > 0) {
                $sections = $this->studyLevelRepo->findBy(['id' => count($config[0]->getSectionList()) > 0 ? $this->studyLevelRepo->findBy(['id' => $config[0]->getSectionList()]) : array_map(fn($sl) => $sl->getId(), $this->studyLevelRepo->findAll())]);
            } else {
                $sections = [];
            }
        }
        return array_map(function ($section) {
            return [
                'id' => $section->getId(),
                'name' => $section->getName(),
            ];
        }, $sections);
    }

    public function getTemplates(): array
    {
        $templates = $this->templateRepo->findAll();
        return array_map(function ($template) {
            return [
                'id' => $template->getId(),
                'name' => $template->getName(),
                'description' => $template->getDescription(),
            ];
        }, $templates);
    }
    
    public function getClass(int $classId): SchoolClassPeriod
    {
        $class = $this->classRepo->find($classId);
        if (!$class) {
            throw new \InvalidArgumentException('Classe non trouvée');
        }
        return $class;
    }

    public function getStudentsByClass(int $classId): array
    {
        $class = $this->getClass($classId);
        $students = $this->studentRepo->findBy(['schoolClassPeriod' => $class]);
        
        if (empty($students)) {
            throw new \InvalidArgumentException('Aucun étudiant trouvé pour cette classe');
        }

        // Transformer et trier les étudiants
        $data = array_map(function ($student) {
            return [
                'id' => $student->getStudent()->getId(),
                'name' => $student->getStudent()->getFullName(),
                'registrationNumber' => $student->getStudent()->getRegistrationNumber(),
            ];
        }, $students);

        usort($data, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return $data;
    }

    public function getEvaluationFrames(): array
    {
        $frames = $this->frameRepo->findAll();
        return array_map(function ($frame) {
            return [
                'id' => $frame->getId(),
                'name' => $frame->getName(),
            ];
        }, $frames);
    }

    public function getEvaluationTimes(int $classId, BulletinContextService $context): array
    {
        $class = $this->getClass($classId);
        $school = $context->getCurrentSchool();
        $period = $context->getCurrentPeriod();

        $modules = $this->subjectModuleRepo->findBy([
            'class' => $class, 
            'period' => $period, 
            'school' => $school
        ]);

        if (!$modules) {
            throw new \InvalidArgumentException('Aucun module trouvé pour cette classe');
        }

        $evaluations = $this->evaluationRepo->findBy(['classSubjectModule' => $modules]);
        $timeIds = [];

        foreach ($evaluations as $evaluation) {
            $time = $evaluation->getTime();
            if ($time && !in_array($time->getId(), $timeIds)) {
                $timeIds[] = $time->getId();
            }
        }

        $times = $this->timeRepo->findBy(['id' => $timeIds]);
        return array_map(function ($time) {
            return [
                'id' => $time->getId(),
                'name' => $time->getName(),
            ];
        }, $times);
    }

    public function calculateEvaluationProgress(ProgressQueryDTO $dto, BulletinContextService $context): array
    {
        $dto->validateForProgress();
        
        $class = $this->getClass($dto->classId);
        $school = $context->getCurrentSchool();
        $period = $context->getCurrentPeriod();

        // Récupérer les matières de la classe
        $subjects = $this->schoolClassSubjectRepo->findBy(['schoolClassPeriod' => $class]);
        
        if (!$subjects) {
            return [
                'total_subjects' => 0,
                'evaluated_subjects' => 0,
                'percentage_subjects' => 0,
                'total_modules' => 0,
                'evaluated_modules' => 0,
                'percentage_modules' => 0,
                'subject_details' => []
            ];
        }

        // Si un étudiant est spécifié, on ne calcule que pour lui
        if ($dto->studentId) {
            $student = $this->studentRepo->findByStudent($dto->studentId);
            if (empty($student)) {
                return [
                    'total_subjects' => 0,
                    'evaluated_subjects' => 0,
                    'percentage_subjects' => 0,
                    'total_modules' => 0,
                    'evaluated_modules' => 0,
                    'percentage_modules' => 0,
                    'subject_details' => []
                ];
            }
            $students = [$student[0]];
        } else {
            // Sinon, tous les étudiants de la classe
            $students = $this->studentRepo->findBy(['schoolClassPeriod' => $class]);
        }

        $totalSubjects = count($subjects);
        $completedSubjects = 0;
        $totalModules = 0;
        $completedModules = 0;
        $subjectDetails = [];

        foreach ($subjects as $subject) {
            $subjectId = $subject->getStudySubject()->getId();
            $subjectName = $subject->getStudySubject()->getName();
            
            // Récupérer les modules de cette matière
            $modules = $this->subjectModuleRepo->findBy([
                'class' => $class,
                'period' => $period,
                'school' => $school,
                'subject' => $subjectId
            ]);

            if (empty($modules)) {
                continue;
            }

            $subjectModulesCount = count($modules);
            $totalModules += $subjectModulesCount;
            
            $subjectCompleted = false;
            $moduleDetails = [];
            $subjectCompletedModules = 0;

            foreach ($modules as $module) {
                $moduleId = $module->getId();
                $moduleName = $module->getModule()->getModuleName();
                $moduleStatus = 'not_evaluated';
                $moduleNote = null;

                if ($dto->bulletinType == 'sub-period') {
                    $time = $this->timeRepo->find($dto->periodicityId);
                    $evaluation = $this->evaluationRepo->findOneBy([
                        'student' => $students[0],
                        'classSubjectModule' => $module,
                        'time' => $time
                    ]);

                    if ($evaluation) {
                        $moduleNote = $evaluation->getEvaluationNote();
                        if ($moduleNote !== null) {
                            if ($moduleNote > 0) {
                                $moduleStatus = 'completed_positive';
                                $subjectCompletedModules++;
                                $completedModules++;
                            } else {
                                $moduleStatus = 'completed_zero';
                                $subjectCompletedModules++;
                            }
                        }
                    }
                } else {
                    $frame = $this->frameRepo->find($dto->periodicityId);
                    $times = $this->timeRepo->findBy(['evaluationFrame' => $frame]);
                    
                    foreach ($students as $student) {
                        $evaluations = $this->evaluationRepo->findBy([
                            'student' => $student,
                            'classSubjectModule' => $module,
                            'time' => $times
                        ]);
                        
                        foreach ($evaluations as $evaluation) {
                            $moduleNote = $evaluation->getEvaluationNote();
                            if ($moduleNote !== null) {
                                if ($moduleNote > 0) {
                                    $moduleStatus = 'completed_positive';
                                    $subjectCompletedModules++;
                                    $completedModules++;
                                    break 2; // Sort des deux boucles
                                } else {
                                    $moduleStatus = 'completed_zero';
                                    $subjectCompletedModules++;
                                }
                            }
                        }
                    }
                }

                $moduleDetails[] = [
                    'module_id' => $moduleId,
                    'module_name' => $moduleName,
                    'status' => $moduleStatus,
                    'note' => $moduleNote
                ];
            }

            // Une matière est complétée si au moins un module a une note > 0
            if ($subjectCompletedModules > 0) {
                // Vérifier s'il y a au moins un module avec note > 0
                $hasPositiveNote = false;
                foreach ($moduleDetails as $module) {
                    if ($module['status'] === 'completed_positive') {
                        $hasPositiveNote = true;
                        break;
                    }
                }
                
                if ($hasPositiveNote) {
                    $subjectCompleted = true;
                    $completedSubjects++;
                }
            }

            // Calculer le taux de complétion des modules pour cette matière
            $subjectModulePercentage = $subjectModulesCount > 0 ? 
                round(($subjectCompletedModules / $subjectModulesCount) * 100, 2) : 0;

            $subjectDetails[$subjectId] = [
                'subject_id' => $subjectId,
                'subject_name' => $subjectName,
                'status' => $subjectCompleted ? 'completed' : 'incomplete',
                'completed' => $subjectCompleted,
                'modules_count' => $subjectModulesCount,
                'completed_modules' => $subjectCompletedModules,
                'module_percentage' => $subjectModulePercentage,
                'modules' => $moduleDetails
            ];
        }

        $percentageSubjects = $totalSubjects > 0 ? round(($completedSubjects / $totalSubjects) * 100, 2) : 0;
        $percentageModules = $totalModules > 0 ? round(($completedModules / $totalModules) * 100, 2) : 0;

        return [
            'total_subjects' => $totalSubjects,
            'evaluated_subjects' => $completedSubjects,
            'percentage_subjects' => $percentageSubjects,
            'total_modules' => $totalModules,
            'evaluated_modules' => $completedModules,
            'percentage_modules' => $percentageModules,
            'subject_details' => $subjectDetails
        ];
    }

    public function getStudentsProgress(ProgressQueryDTO $dto, BulletinContextService $context): array
    {
        $dto->validateForProgress();
        
        $class = $this->getClass($dto->classId);
        $school = $context->getCurrentSchool();
        $period = $context->getCurrentPeriod();
        $students = $this->studentRepo->findBy(['schoolClassPeriod' => $class]);

        // Récupérer les matières de la classe
        $subjects = $this->schoolClassSubjectRepo->findBy(['schoolClassPeriod' => $class]);
        $totalSubjects = count($subjects);

        $studentsProgress = [];

        foreach ($students as $student) {
            $studentId = $student->getStudent()->getId();
            $completedSubjects = 0;
            $totalModules = 0;
            $completedModules = 0;
            $subjectDetails = [];
            $nonCompletedModules = [];

            foreach ($subjects as $subject) {
                $subjectId = $subject->getStudySubject()->getId();
                $subjectName = $subject->getStudySubject()->getName();
                
                // Récupérer les modules de cette matière
                $modules = $this->subjectModuleRepo->findBy([
                    'class' => $class,
                    'period' => $period,
                    'school' => $school,
                    'subject' => $subjectId
                ]);

                
                if (empty($modules)) {
                    continue;
                }

                $subjectModulesCount = count($modules);
                $totalModules += $subjectModulesCount;
                
                $subjectCompleted = false;
                $moduleDetails = [];
                $subjectCompletedModules = 0;

                foreach ($modules as $module) {
                    $moduleId = $module->getId();
                    $moduleName = $module->getModule()->getModuleName();
                    $moduleStatus = 'not_evaluated';
                    $moduleNote = null;

                    if ($dto->bulletinType == 'sub-period') {
                        $time = $this->timeRepo->find($dto->periodicityId);
                        $evaluation = $this->evaluationRepo->findOneBy([
                            'student' => $student,
                            'classSubjectModule' => $module,
                            'time' => $time
                        ]);

                        if ($evaluation) {
                            $moduleNote = $evaluation->getEvaluationNote();
                            if ($moduleNote !== null) {
                                if ($moduleNote > 0) {
                                    $moduleStatus = 'completed_positive';
                                    $subjectCompletedModules++;
                                    $completedModules++;
                                } else {
                                    $moduleStatus = 'completed_zero';
                                    $subjectCompletedModules++;
                                }
                            }
                        }
                    } else {
                        $frame = $this->frameRepo->find($dto->periodicityId);
                        $times = $this->timeRepo->findBy(['evaluationFrame' => $frame]);
                        $evaluations = $this->evaluationRepo->findBy([
                            'student' => $student,
                            'classSubjectModule' => $module,
                            'time' => $times
                        ]);

                        $hasPositiveNote = false;
                        $hasZeroNote = false;

                        foreach ($evaluations as $evaluation) {
                            $note = $evaluation->getEvaluationNote();
                            if ($note !== null) {
                                if ($note > 0) {
                                    $hasPositiveNote = true;
                                    $moduleStatus = 'completed_positive';
                                    $subjectCompletedModules++;
                                    $completedModules++;
                                    break;
                                } elseif ($note == 0) {
                                    $hasZeroNote = true;
                                    $moduleStatus = 'completed_zero';
                                    $subjectCompletedModules++;
                                }
                            }
                        }

                        if (!$hasPositiveNote && !$hasZeroNote && count($evaluations) > 0) {
                            $moduleStatus = 'evaluated_null';
                        }
                    }

                    $moduleDetails[] = [
                        'module_id' => $moduleId,
                        'module_name' => $moduleName,
                        'status' => $moduleStatus,
                        'note' => $moduleNote
                    ];

                    // Collecter les modules non complétés positivement pour l'info-bulle
                    if ($moduleStatus !== 'completed_positive') {
                        $nonCompletedModules[] = [
                            'subject_name' => $subjectName,
                            'module_name' => $moduleName,
                            'status' => $moduleStatus
                        ];
                    }
                }

                // Une matière est complétée si au moins un module a une note > 0
                $subjectCompleted = false;
                foreach ($moduleDetails as $module) {
                    if ($module['status'] === 'completed_positive') {
                        $subjectCompleted = true;
                        $completedSubjects++;
                        break;
                    }
                }

                // Calculer le taux de complétion des modules pour cette matière
                $subjectModulePercentage = $subjectModulesCount > 0 ? 
                    round(($subjectCompletedModules / $subjectModulesCount) * 100, 2) : 0;

                $subjectDetails[$subjectId] = [
                    'subject_name' => $subjectName,
                    'completed' => $subjectCompleted,
                    'modules_count' => $subjectModulesCount,
                    'completed_modules' => $subjectCompletedModules,
                    'module_percentage' => $subjectModulePercentage,
                    'modules' => $moduleDetails
                ];
            }

            $percentageSubjects = $totalSubjects > 0 ? round(($completedSubjects / $totalSubjects) * 100, 2) : 0;
            $percentageModules = $totalModules > 0 ? round(($completedModules / $totalModules) * 100, 2) : 0;

            $studentsProgress[$studentId] = [
                'total_subjects' => $totalSubjects,
                'evaluated_subjects' => $completedSubjects,
                'percentage_subjects' => $percentageSubjects,
                'total_modules' => $totalModules,
                'evaluated_modules' => $completedModules,
                'percentage_modules' => $percentageModules,
                'student_name' => $student->getStudent()->getFullName(),
                'registration_number' => $student->getStudent()->getRegistrationNumber(),
                'subject_details' => $subjectDetails,
                'non_completed_modules' => $nonCompletedModules,
                'non_completed_count' => count($nonCompletedModules)
            ];
        }

        return [
            'students_progress' => $studentsProgress,
            'total_students' => count($students),
            'total_subjects' => $totalSubjects
        ];
    }
}