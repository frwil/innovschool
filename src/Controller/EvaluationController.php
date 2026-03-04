<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\School;
use App\Entity\Evaluation;
use App\Entity\StudyLevel;
use App\Entity\SchoolPeriod;
use App\Entity\StudentClass;
use App\Entity\StudySubject;
use App\Entity\SubjectGroup;
use App\Entity\TimeTableSlot;
use App\Entity\SubjectsModules;
use Doctrine\ORM\EntityManager;
use App\Service\OperationLogger;
use App\Entity\SchoolClassPeriod;
use App\Entity\ClassSubjectModule;
use App\Entity\ReportCardTemplate;
use App\Entity\SchoolClassSubject;
use App\Repository\UserRepository;
use App\Entity\SchoolEvaluationFrame;
use App\Repository\EvaluationRepository;
use App\Repository\StudyLevelRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use App\Repository\SchoolPeriodRepository;
use App\Repository\StudentClassRepository;
use App\Repository\StudySubjectRepository;
use App\Repository\TimetableSlotRepository;
use App\Entity\EvaluationAppreciationBareme;
use App\Entity\StudentClassTimetablePresence;
use App\Repository\SubjectsModulesRepository;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\EvaluationAppreciationTemplate;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\SchoolClassPeriodRepository;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\ClassSubjectModuleRepository;
use App\Repository\SchoolClassSubjectRepository;
use App\Repository\SchoolEvaluationTimeRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Repository\SchoolEvaluationFrameRepository;
use Symfony\Component\HttpFoundation\Session\Session;
use App\Repository\SchoolEvaluationTimeTypeRepository;
use Symfony\Component\String\Slugger\SluggerInterface;
use App\Repository\StudentClassTimetablePresenceRepository;
use App\Entity\SchoolClassSubjectEvaluationTimeNotApplicable;
use App\Entity\SchoolEvaluationTime;
use Exception;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class EvaluationController extends AbstractController
{
    private OperationLogger $operationLog;
    private EntityManagerInterface $entityManager;
    private School $currentSchool;
    private SchoolPeriod $currentPeriod;
    private SessionInterface $session;

    public function __construct(EntityManagerInterface $entityManager, OperationLogger $operationLog)
    {
        $this->entityManager = $entityManager;
        $this->operationLog = $operationLog;
    }
    #[Route('/presences', name: 'app_presence_index')]
    public function presenceIndex(Request $request, StudentClassRepository $studentClassRepo, EntityManagerInterface $entityManager): Response
    {
        $this->entityManager = $entityManager;
        $sections = $this->entityManager->getRepository(StudyLevel::class)->findAll();
        return $this->render('evaluation/presence.index.html.twig', [
            'sections' => $sections,
        ]);
    }

    #[Route('/ajax/timetable-slots-by-class', name: 'app_timetable_slots_by_class')]
    public function timetableSlotsByClass(Request $request, TimetableSlotRepository $slotRepo): JsonResponse
    {
        $classId = $request->query->get('classId');
        $dayOfWeek = $request->query->get('dayOfWeek');
        $criteria = ['schoolClassPeriod' => $classId];
        if ($dayOfWeek) {
            $criteria['timetableDay'] = null; // placeholder
        }
        // Si dayOfWeek est fourni, on doit faire une requête personnalisée
        if ($dayOfWeek) {
            $slots = $slotRepo->createQueryBuilder('s')
                ->join('s.timetableDay', 'd')
                ->where('s.schoolClassPeriod = :classId')
                ->andWhere('d.dayOfWeek = :dayOfWeek')
                ->setParameter('classId', $classId)
                ->setParameter('dayOfWeek', $dayOfWeek)
                ->getQuery()->getResult();
        } else {
            $slots = $slotRepo->findBy(['schoolClassPeriod' => $classId]);
        }
        $result = [];
        foreach ($slots as $slot) {
            $result[] = [
                'id' => $slot->getId(),
                'label' => $slot->getStartTime()->format('H:i') . ' - ' . $slot->getEndTime()->format('H:i') . ' - ' . $slot->getSubject()->getName(),
            ];
        }
        return new JsonResponse($result);
    }

    #[Route('/ajax/presence-students-by-slot', name: 'app_presence_students_by_slot')]
    public function presenceStudentsBySlot(Request $request, StudentClassRepository $studentClassRepo, StudentClassTimetablePresenceRepository $presenceRepo): JsonResponse
    {
        $classId = $request->query->get('classId');
        $slotId = $request->query->get('slotId');
        $datePresence = $request->query->get('datePresence');
        $evaluationTimeId = $request->query->get('evaluationTimeId');
        $studentClasses = $studentClassRepo->findBy(['schoolClassPeriod' => $classId]);
        $result = [];
        $attendanceRepo = $this->entityManager->getRepository(\App\Entity\StudentClassAttendance::class);
        $evalTime = $evaluationTimeId ? $this->entityManager->getRepository(\App\Entity\SchoolEvaluationTime::class)->find($evaluationTimeId) : null;
        foreach ($studentClasses as $studentClass) {
            $criteria = [
                'studentClass' => $studentClass,
                'timeTableSlot' => $slotId
            ];
            if ($datePresence) {
                $criteria['datePresence'] = new \DateTime($datePresence);
            }
            if ($evaluationTimeId) {
                $criteria['schoolEvaluationTime'] = $evaluationTimeId;
            }
            $presence = $presenceRepo->findOneBy($criteria);

            // Création StudentClassTimetablePresence si non existant
            if (!$presence) {
                $presence = new \App\Entity\StudentClassTimetablePresence();
                $presence->setStudentClass($studentClass);
                $presence->setTimetableSlot($slotId ? $this->entityManager->getRepository(\App\Entity\TimetableSlot::class)->find($slotId) : null);
                if ($datePresence) {
                    $presence->setDatePresence(new \DateTime($datePresence));
                }
                if ($evaluationTimeId) {
                    $presence->setSchoolEvaluationTime($evalTime);
                }
                $presence->setStatus('absent');
                $this->entityManager->persist($presence);
            }

            // Création StudentClassAttendance si non existant
            $attendance = $attendanceRepo->findOneBy([
                'studentClass' => $studentClass,
                'time' => $evalTime
            ]);
            if (!$attendance) {
                $attendance = new \App\Entity\StudentClassAttendance();
                $attendance->setStudentClass($studentClass);
                $attendance->setTime($evalTime);
                $attendance->setHeuresAbsence(1);
                $attendance->setAbsencesJustifiee(0);
                $attendance->setRetard(0);
                $attendance->setRetardInjustifie(0);
                $attendance->setRetenue(0);
                $attendance->setAvertissementDiscipline(0);
                $attendance->setBlame(0);
                $attendance->setJourExclusion(0);
                $attendance->setExclusionDefinitive(false);
                $this->entityManager->persist($attendance);
            }

            $result[] = [
                'studentName' => $studentClass->getStudent()->getFullName(),
                'studentClassId' => $studentClass->getId(),
                'presenceId' => $presence ? $presence->getId() : null,
                'status' => $presence ? $presence->getStatus() : 'absent',
                'locked' => $presence ? $presence->isLocked() : false,
            ];
        }
        $this->entityManager->flush();
        return new JsonResponse($result);
    }

    #[Route('/ajax/presence-lock', name: 'app_presence_lock', methods: ['POST'])]
    public function presenceLock(Request $request, StudentClassTimetablePresenceRepository $presenceRepo, StudentClassRepository $studentClassRepo, TimetableSlotRepository $slotRepo): JsonResponse
    {
        $classId = $request->request->get('classId');
        $slotId = $request->request->get('slotId');
        $datePresence = $request->request->get('datePresence');
        $evaluationTimeId = $request->request->get('evaluationTimeId');
        $locked = filter_var($request->request->get('locked'), FILTER_VALIDATE_BOOLEAN);
        $studentClasses = $studentClassRepo->findBy(['schoolClassPeriod' => $classId]);
        foreach ($studentClasses as $studentClass) {
            $criteria = [
                'studentClass' => $studentClass,
                'timeTableSlot' => $slotId
            ];
            if ($datePresence) {
                $criteria['datePresence'] = new \DateTime($datePresence);
            }
            if ($evaluationTimeId) {
                $criteria['schoolEvaluationTime'] = $evaluationTimeId;
            }
            $presence = $presenceRepo->findOneBy($criteria);
            if ($presence) {
                $presence->setLocked($locked);
                $this->entityManager->persist($presence);
            }
        }
        $this->entityManager->flush();
        return new JsonResponse(['success' => true]);
    }

    #[Route('/ajax/presence-update-status', name: 'app_presence_update_status', methods: ['POST'])]
    public function presenceUpdateStatus(Request $request, EntityManagerInterface $em, StudentClassTimetablePresenceRepository $presenceRepo, StudentClassRepository $studentClassRepo, TimetableSlotRepository $slotRepo): JsonResponse
    {
        $presenceId = $request->request->get('presenceId');
        $studentClassId = $request->request->get('studentClassId');
        $slotId = $request->request->get('slotId');
        $status = $request->request->get('status');
        $datePresence = $request->request->get('datePresence');
        $evaluationTimeId = $request->request->get('evaluationTimeId');
        if ($presenceId) {
            $presence = $presenceRepo->find($presenceId);
        } else {
            $criteria = [
                'studentClass' => $studentClassRepo->find($studentClassId),
                'timeTableSlot' => $slotRepo->find($slotId)
            ];
            if ($datePresence) {
                $criteria['datePresence'] = new \DateTime($datePresence);
            }
            if ($evaluationTimeId) {
                $criteria['schoolEvaluationTime'] = $evaluationTimeId;
            }
            $presence = $presenceRepo->findOneBy($criteria);
            if (!$presence) {
                $presence = new StudentClassTimetablePresence();
                $presence->setStudentClass($studentClassRepo->find($studentClassId));
                $presence->setTimetableSlot($slotRepo->find($slotId));
                if ($datePresence) {
                    $presence->setDatePresence(new \DateTime($datePresence));
                }
                if ($evaluationTimeId) {
                    $presence->setSchoolEvaluationTime($this->entityManager->getRepository(\App\Entity\SchoolEvaluationTime::class)->find($evaluationTimeId));
                }
            }
        }
        $presence->setStatus($status);
        if ($datePresence) {
            $presence->setDatePresence(new \DateTime($datePresence));
        }
        if ($evaluationTimeId) {
            $presence->setSchoolEvaluationTime($this->entityManager->getRepository(\App\Entity\SchoolEvaluationTime::class)->find($evaluationTimeId));
        }
        $em->persist($presence);

        // --- Gestion StudentClassAttendance ---
        $attendanceRepo = $this->entityManager->getRepository(\App\Entity\StudentClassAttendance::class);
        $studentClass = $studentClassRepo->find($studentClassId);
        $evalTime = $evaluationTimeId ? $this->entityManager->getRepository(\App\Entity\SchoolEvaluationTime::class)->find($evaluationTimeId) : null;
        $attendance = $attendanceRepo->findOneBy([
            'studentClass' => $studentClass,
            'time' => $evalTime
        ]);
        if ($attendance) {
            // Incrémenter ou décrémenter heuresAbsence selon le statut
            if ($status === 'absent') {
                $attendance->setHeuresAbsence($attendance->getHeuresAbsence() + 1);
            } elseif ($status === 'present' && $attendance->getHeuresAbsence() > 0) {
                $attendance->setHeuresAbsence($attendance->getHeuresAbsence() - 1);
            }
        } else {
            // Créer l'entité avec heuresAbsence=1 si absent, 0 si présent
            $attendance = new \App\Entity\StudentClassAttendance();
            $attendance->setStudentClass($studentClass);
            $attendance->setTime($evalTime);
            $attendance->setHeuresAbsence($status === 'absent' ? 1 : 0);
            $attendance->setAbsencesJustifiee(0);
            $attendance->setRetard(0);
            $attendance->setRetardInjustifie(0);
            $attendance->setRetenue(0);
            $attendance->setAvertissementDiscipline(0);
            $attendance->setBlame(0);
            $attendance->setJourExclusion(0);
            $attendance->setExclusionDefinitive(false);
        }
        $em->persist($attendance);
        $em->flush();
        return new JsonResponse(['success' => true]);
    }


    #[Route('/evaluation', name: 'app_evaluation')]
    public function index(
        StudyLevelRepository $sectionRepo,
        SchoolEvaluationFrameRepository $frameRepo,
        SchoolEvaluationTimeTypeRepository $timeTypeRepo,
        SubjectsModulesRepository $moduleRepo,
        Request $request,
        SessionInterface $session,
        EntityManagerInterface $entityManager
    ): Response {

        if (!in_array('ROLE_ADMIN', $this->getUser()->getRoles()) && !in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_homepage');
        }

        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));

        // Recharge l'utilisateur depuis la base pour garantir l'accès aux méthodes d'entité
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $this->getUser()->getUserIdentifier()]);
        $config = $user ? $user->getBaseConfigurations()->toArray() : [];
        if (in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles())) {
            $sections = $sectionRepo->findAll();
        } else {
            if (count($config) > 0) {
                $sections = $sectionRepo->findBy(['id' => count($config[0]->getSectionList()) > 0 ? $this->entityManager->getRepository(StudyLevel::class)->findBy(['id' => $config[0]->getSectionList()]) : array_map(fn($sl) => $sl->getId(), $sectionRepo->findAll())]);
            } else {
                $sections = []; // Si aucune configuration n'est trouvée, on initialise un tableau vide
            }
        }

        $evaluationFrames = $frameRepo->findAll();
        $evaluationTimeTypes = $timeTypeRepo->findAll(); // Récupérer les périodicités
        $subjectModules = $moduleRepo->findAll();
        $templates = $this->entityManager->getRepository(EvaluationAppreciationTemplate::class)->findAll();
        $reportCardTemplates = $this->entityManager->getRepository(ReportCardTemplate::class)->findAll();
        $groups = $this->entityManager->getRepository(SubjectGroup::class)->findBy(['school' => $this->currentSchool, 'period' => $this->currentPeriod]);
        $teachers = $this->entityManager->getRepository(User::class)->findBy(['school' => $this->currentSchool]);
        $teachers = array_filter($teachers, function ($teacher) {
            return in_array('ROLE_TEACHER', $teacher->getRoles());
        });

        return $this->render('evaluation/index.html.twig', [
            'sections' => $sections,
            'evaluationFrames' => $evaluationFrames,
            'evaluationTimeTypes' => $evaluationTimeTypes, // Passer les périodicités à la vue
            'subjectModules' => $subjectModules, // Passer les modules à la vue,
            'appreciationTemplates' => $templates, // Passer les templates à la vue
            'reportCardTemplates' => $reportCardTemplates, // Passer les modèles de bulletin à la vue
            'groups' => $groups, // Passer les groupes de matières à la vue
            'teachers' => $teachers, // Passer les enseignants à la vue

        ]);
    }

    #[Route('/get-subjects-by-class', name: 'app_subjects_by_class', methods: ['GET'])]
    public function getSubjectsByClass(SessionInterface $session, Request $request, StudySubjectRepository $subjectRepo, SchoolClassSubjectRepository $subjectClassRepo, SchoolPeriodRepository $periodRepo): JsonResponse
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $classId = $request->query->get('classId');

        $classe = $this->entityManager->getRepository(SchoolClassPeriod::class)->findBy(['id' => $classId, 'school' => $this->currentSchool, 'period' => $this->currentPeriod]);

        if (!$classId) {
            return new JsonResponse(['error' => 'Class ID is required'], 400);
        }

        $period = $this->currentPeriod;
        if (!$period) {
            return new JsonResponse(['error' => 'No active period found'], 400);
        }

        $subjects = $subjectClassRepo->findBy(['schoolClassPeriod' => $classe]);
        $subjectIds = array_map(function ($subject) {
            return $subject->getStudySubject()->getId();
        }, $subjects);
        $subjects = $subjectRepo->findBy(['id' => $subjectIds]);

        $data = [];
        foreach ($subjects as $subject) {
            $data[] = [
                'id' => $subject->getId(),
                'name' => $subject->getName(),
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/add-module', name: 'app_add_module', methods: ['POST'])]
    public function addModule(Request $request, SubjectsModulesRepository $moduleRepo, EntityManagerInterface $entityManager, SessionInterface $session, \Doctrine\Persistence\ManagerRegistry $doctrine, SluggerInterface $slugger): JsonResponse
    {
        if (!in_array('ROLE_ADMIN', $this->getUser()->getRoles()) && !in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles())) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        // Récupérer les données du formulaire
        $name = $request->request->get('name');
        $description = $request->request->get('description');

        if (!$name) {
            return new JsonResponse(['error' => 'Le nom du module est requis'], 400);
        }

        $module = new SubjectsModules();
        $module->setModuleName($name);
        $module->setModuleSlug($slugger->slug($name)->lower());
        // Les dates createdAt/updatedAt sont gérées automatiquement par les callbacks Doctrine

        $entityManager->persist($module);
        try {
            $entityManager->flush();
            $this->operationLog->log(
                'Ajout d\'un module',
                'Succès',
                'Module',
                null,
                null,
                [
                    'moduleName' => $module->getModuleName(),
                    'moduleId' => $module->getId(),
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }

            $this->operationLog->log(
                'Erreur lors de l\'ajout d\'un module',
                'Échec',
                'Module',
                $e->getCode(),
                null,
                null,
                [
                    'moduleName' => $module->getModuleName(),
                    'moduleId' => $module->getId(),
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );
            return new JsonResponse(['error' => 'Une erreur est survenue lors de l\'ajout du module.'], 500);
        }

        return new JsonResponse(['success' => 'Module ajouté avec succès', 'id' => $module->getId(), 'moduleName' => $module->getModuleName()], 200);
    }

    #[Route('/create-module', name: 'app_map_module', methods: ['POST'])]
    public function mapModule(SessionInterface $session, Request $request, SubjectsModulesRepository $subjectsModulesRepo, EntityManagerInterface $entityManager, SchoolPeriodRepository $periodRepo, ClassSubjectModuleRepository $classSubjectModuleRepo, \Doctrine\Persistence\ManagerRegistry $doctrine): JsonResponse
    {
        if (!in_array('ROLE_ADMIN', $this->getUser()->getRoles()) && !in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles())) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $sectionId = $request->request->get('sectionId');
        $classId = $request->request->get('classId');
        $subjectIds = $request->request->all('subjectId'); // Récupère plusieurs matières
        $moduleName = $request->request->get('moduleName');
        $moduleNotation = $request->request->get('moduleNotation');

        // Validation des données
        if (!$sectionId || !$classId || empty($subjectIds) || !$moduleName || !$moduleNotation) {
            return new JsonResponse(['error' => 'Tous les champs sont requis.'], 400);
        }

        $class = $this->entityManager->getRepository(SchoolClassPeriod::class)->findOneBy(['id' => $classId, 'school' => $this->currentSchool, 'period' => $this->currentPeriod]);
        if (!$class) {
            return new JsonResponse(['error' => 'Classe non trouvée.'], 404);
        }

        $moduleSubject = $subjectsModulesRepo->find($moduleName);
        if (!$moduleSubject) {
            return new JsonResponse(['error' => 'Module non trouvé.'], 404);
        }

        $period = $this->currentPeriod;
        if (!$period) {
            return new JsonResponse(['error' => 'Aucune période active trouvée.'], 404);
        }

        $createdModules = [];


        // Boucle sur chaque matière
        foreach ($subjectIds as $subjectId) {
            $subject = $this->entityManager->getRepository(StudySubject::class)->find($subjectId);
            if (!$subject) {
                return new JsonResponse(['error' => "Matière avec l'ID $subjectId non trouvée."], 404);
            }
            $moduleMapping = $classSubjectModuleRepo->findOneBy(['class' => $class, 'subject' => $subject, 'module' => $moduleSubject, 'period' => $period, 'school' => $this->currentSchool]);

            if (!$moduleMapping) {
                // Création du module pour la matière
                $module = new ClassSubjectModule();
                $module->setClass($class);
                $module->setSubject($subject);
                $module->setModule($moduleSubject);
                $module->setSchool($this->currentSchool);
                $module->setModuleNotation((int) $moduleNotation);
                $module->setUpdatedAt(new \DateTime());
                $module->setPeriod($period);

                $entityManager->persist($module);
                $createdModules[] = [
                    'id' => $module->getId(),
                    'subjectName' => $subject->getName(),
                    'moduleName' => $module->getModule()->getModuleName(),
                ];
                try {
                    $entityManager->flush();
                    $this->operationLog->log(
                        'Ajout d\'un module',
                        'Succès',
                        'Module',
                        null,
                        null,
                        [
                            'moduleName' => $module->getModule()->getModuleName(),
                            'moduleId' => $module->getId(),
                            'subject' => $subject->getName(),
                            'class' => $class->getClassOccurence()->getName(),
                            'school' => $this->currentSchool->getName(),
                            'period' => $this->currentPeriod->getName(),
                        ]
                    );
                } catch (\Exception $e) {
                    if (!$this->entityManager->isOpen()) {
                        $this->entityManager = $doctrine->resetManager();
                    }
                    $this->operationLog->log(
                        'Erreur lors de l\'ajout d\'un module',
                        'Échec',
                        'Module',
                        null,
                        $e->getMessage(),
                        [
                            'moduleName' => $module->getModule()->getModuleName(),
                            'moduleId' => $module->getId(),
                            'subject' => $subject->getName(),
                            'class' => $class->getClassOccurence()->getName(),
                            'school' => $this->currentSchool->getName(),
                            'period' => $this->currentPeriod->getName(),
                        ]
                    );
                    return new JsonResponse(['error' => 'Une erreur est survenue lors de l\'ajout du module.'], 500);
                }
            }
            $standardModule = $subjectsModulesRepo->findOneBy(['moduleName' => 'Ecrit']);
            if ($standardModule) {
                $standardModuleMapping = $classSubjectModuleRepo->findOneBy(['class' => $class, 'subject' => $subject, 'module' => $standardModule, 'period' => $period, 'school' => $this->currentSchool]);
                if (!$standardModuleMapping) {
                    $standardModuleMapping = new ClassSubjectModule();
                    $standardModuleMapping->setClass($class);
                    $standardModuleMapping->setSubject($subject);
                    $standardModuleMapping->setModule($standardModule);
                    $standardModuleMapping->setSchool($this->currentSchool);
                    $standardModuleMapping->setModuleNotation((int) $moduleNotation);
                    $standardModuleMapping->setUpdatedAt(new \DateTime());
                    $standardModuleMapping->setPeriod($period);

                    $entityManager->persist($standardModuleMapping);
                    $createdModules[] = [
                        'id' => $standardModuleMapping->getId(),
                        'subjectName' => $subject->getName(),
                        'moduleName' => $standardModule->getModuleName(),
                    ];
                    try {
                        $entityManager->flush();
                        $this->operationLog->log(
                            'Module ajouté avec succès',
                            'Succès',
                            'Module',
                            $standardModule->getId(),
                            null,
                            [
                                'moduleName' => $standardModule->getModuleName(),
                                'moduleId' => $standardModule->getId(),
                                'subject' => $subject->getName(),
                                'class' => $class->getClassOccurence()->getName(),
                                'school' => $this->currentSchool->getName(),
                                'period' => $this->currentPeriod->getName(),
                            ]
                        );
                    } catch (\Exception $e) {
                        if (!$this->entityManager->isOpen()) {
                            $this->entityManager = $doctrine->resetManager();
                        }
                        $this->operationLog->log(
                            'Erreur lors de l\'ajout d\'un module',
                            'Échec',
                            'Module',
                            null,
                            $e->getMessage(),
                            [
                                'moduleName' => $standardModule->getModuleName(),
                                'moduleId' => $standardModule->getId(),
                                'subject' => $subject->getName(),
                                'class' => $class->getClassOccurence()->getName(),
                                'school' => $this->currentSchool->getName(),
                                'period' => $this->currentPeriod->getName(),
                            ]
                        );
                    }
                }
            }
        }





        return new JsonResponse([
            'success' => true,
            'message' => 'Modules créés avec succès.',
            'modules' => $createdModules,
        ]);
    }

    #[Route('/get-modules', name: 'app_get_modules', methods: ['GET'])]
    public function getModules(ClassSubjectModuleRepository $moduleRepo, EvaluationRepository $evaluationRepo, SessionInterface $session): JsonResponse
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $classe = $this->entityManager->getRepository(SchoolClassPeriod::class)->findBy(['school' => $this->currentSchool, 'period' => $this->currentPeriod]);

        $modules = $moduleRepo->findBy(['class' => $classe]);
        $data = [];
        $countModules = [];
        foreach ($modules as $module) {
            $occurenceId = $module->getClass()->getClassOccurence()->getId();
            $subjectId = $module->getSubject()->getId();

            $countModules[$occurenceId][$subjectId] = ($countModules[$occurenceId][$subjectId] ?? 0) + 1;
        }
        foreach ($modules as $module) {
            $evaluationCount = $evaluationRepo->countClassesWithEvaluationsForModule($module instanceof \App\Entity\ClassSubjectModule ? $module : null); // Count evaluations for the module
            $data[] = [
                'id' => $module->getId(),
                'subjectName' => $module->getSubject()->getName(),
                'className' => $module->getClass()->getClassOccurence()->getName(),
                'moduleName' => $module->getModule()->getModuleName(),
                'moduleNotation' => $module->getModuleNotation(),
                'evaluationCount' => $evaluationCount,
                'countModules' => $countModules[$module->getClass()->getClassOccurence()->getId()][$module->getSubject()->getId()]
            ];
        }
        return new JsonResponse($data);
    }

    #[Route('/create-evaluation', name: 'app_create_evaluation', methods: ['POST'])]
    public function createEvaluation(
        Request $request,
        EntityManagerInterface $entityManager,
        SchoolClassPeriodRepository $classRepo,
        StudentClassRepository $studentClassRepo,
        SchoolEvaluationTimeRepository $timeRepo,
        ClassSubjectModuleRepository $classSubjectModuleRepo,
        SchoolPeriodRepository $schoolPeriodRepo,
        OperationLogger $operationLogger, // Injection du service
        SessionInterface $session
    ): JsonResponse {
        if (!in_array('ROLE_ADMIN', $this->getUser()->getRoles()) && !in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles())) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));

        $classIds = $request->request->all('classId');
        $evaluationTimeId = $request->request->get('evaluationTimeId');


        // Validation des données
        if (!$classIds || !$evaluationTimeId) {
            return new JsonResponse(['error' => 'Tous les champs sont requis.'], 400);
        }

        // Récupérer les entités associées
        $classes = $classRepo->findBy(['id' => $classIds, 'school' => $this->currentSchool, 'period' => $this->currentPeriod]);
        if (!$classes) {
            return new JsonResponse(['error' => 'Classe non trouvée.'], 404);
        }

        $school = $this->currentSchool;
        $period = $this->currentPeriod;
        if (!$period) {
            return new JsonResponse(['error' => 'Aucune période active trouvée.'], 404);
        }

        $evaluationTime = $timeRepo->find($evaluationTimeId);
        if (!$evaluationTime) {
            return new JsonResponse(['error' => 'Sous-période d\'évaluation non trouvée.'], 404);
        }
        foreach ($classes as $class) {
            // Récupérer les élèves des classes
            $students = $this->entityManager->getRepository(StudentClass::class)->findBy(['schoolClassPeriod' => $class]);
            if (!$students) {
                return new JsonResponse(['error' => 'Aucun élève trouvé pour cette classe : ' . $class->getClassOccurence()->getName()], 404);
            }

            // Récupérer les modules associés à la classe
            $classSubjectModules = $classSubjectModuleRepo->findBy(['class' => $class, 'period' => $period, 'school' => $school]);

            if (!$classSubjectModules) {
                return new JsonResponse(['error' => 'Aucun module trouvé pour cette classe.'], 404);
            }

            // Créer une évaluation pour chaque élève et chaque module
            foreach ($students as $student) {
                foreach ($classSubjectModules as $module) {
                    $existingEvaluation = $entityManager->getRepository(Evaluation::class)->findOneBy([
                        'student' => $student,
                        'classSubjectModule' => $module,
                        'time' => $evaluationTime,
                    ]);

                    if (!$existingEvaluation) {
                        $evaluation = new Evaluation();
                        $evaluation->setStudent($student);
                        $evaluation->setClassSubjectModule($module instanceof \App\Entity\ClassSubjectModule ? $module : null);
                        $evaluation->setTime($evaluationTime);
                        $evaluation->setEvaluationNote(0);
                        $evaluation->setCreatedAt(new \DateTime());
                        $evaluation->setUpdatedAt(new \DateTime());

                        $entityManager->persist($evaluation);
                    }
                }
            }
        }

        // Sauvegarder toutes les évaluations
        $entityManager->flush();

        // Enregistrer l'opération dans les logs
        $operationLogger->log(
            'Création des évaluations',
            'Succès',
            'Evaluation',
            null,
            null,
            [
                'classId' => $classIds,
                'evaluationTimeId' => $evaluationTimeId,
                'studentCount' => count($students),
                'moduleCount' => count($classSubjectModules),
            ]
        );

        return new JsonResponse([
            'success' => true,
            'message' => 'Évaluations créées avec succès pour tous les élèves et modules.',
        ]);
    }

    #[Route('/evaluation/bordereau', name: 'app_evaluation_bordereau', methods: ['GET'])]
    public function bordereau(
        StudyLevelRepository $sectionRepo,
        SchoolEvaluationFrameRepository $frameRepo,
        SchoolEvaluationTimeTypeRepository $timeTypeRepo,
        EntityManagerInterface $entityManager,

    ): Response {
        // Récupérer les sections (niveaux d'étude)
        $sections = $sectionRepo->findAll();
        $user = $this->getConnectedUser();
        $config = $user ? $user->getBaseConfigurations()->toArray() : [];
        if (count($config) > 0) {
            $sections = $sectionRepo->findBy(['id' => count($config[0]->getSectionList()) > 0 ? $this->entityManager->getRepository(StudyLevel::class)->findBy(['id' => $config[0]->getSectionList()]) : array_map(fn($sl) => $sl->getId(), $sectionRepo->findAll())]);
        } else {
            $sections = $sectionRepo->findAll(); // Si aucune configuration n'est trouvée, on initialise un tableau vide
        }

        // Récupérer les périodes d'évaluation
        $evaluationFrames = $frameRepo->findAll();

        $evaluationTimeTypes = $timeTypeRepo->findAll();


        // Renvoyer les données à la vue
        return $this->render('evaluation/bordereau.index.html.twig', [
            'sections' => $sections,
            'evaluationFrames' => $evaluationFrames,
            'evaluationTimeTypes' => $evaluationTimeTypes,

        ]);
    }

    #[Route('/evaluation/bordereau/individuel', name: 'app_evaluation_bordereau_individuel', methods: ['GET'])]
    public function bordereauIndividuel(
        Request $request,
        StudyLevelRepository $sectionRepo,
        SchoolClassPeriodRepository $classRepo,
        SchoolEvaluationFrameRepository $frameRepo,
        SchoolEvaluationTimeRepository $timeRepo,
        StudentClassRepository $studentClassRepo,
        ClassSubjectModuleRepository $classSubjectModuleRepo,
        EvaluationRepository $evaluationRepo,
        SchoolPeriodRepository $periodRepo,
        SessionInterface $session

    ): Response {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $sectionId = $request->query->get('sectionId');
        $classId = $request->query->get('classId');
        $evaluationFrameId = $request->query->get('evaluationFrameId');
        $evaluationTimeId = $request->query->all('evaluationTimeId');
        $subjectId = $request->query->get('subjectId'); // ID de la matière choisie


        // Récupérer les entités associées
        $section = $sectionRepo->find($sectionId);
        $class = $classRepo->find($classId);
        $evaluationFrame = $frameRepo->find($evaluationFrameId);
        $evaluationTime = $timeRepo->find($evaluationTimeId[0]);
        $subject = $classSubjectModuleRepo->find($subjectId);
        $studentClass = $studentClassRepo->findBy(['schoolClassPeriod' => $class]);
        $studentClassIds = [];
        foreach ($studentClass as $sc) {
            $studentClassIds[] = $sc->getId();
        }
        $evaluations = $evaluationRepo->findBy(['student' => $studentClassIds]);
        $evaluationsTimeIds = [];
        foreach ($evaluations as $evaluation) {
            $evaluationsTimeIds[] = $evaluation->getTime()->getId();
        }

        // Récupérer les élèves de la classe
        $students = $studentClassRepo->findBy(['schoolClassPeriod' => $class]);
        // Trier les élèves par ordre alphabétique
        usort($students, function ($a, $b) {
            return strcmp($a->getStudent()->getFullName(), $b->getStudent()->getFullName());
        });
        $period = $this->currentPeriod;

        // Récupérer les modules associés à la matière choisie
        $classSubjectModules = [];
        if ($subjectId) {
            $modules = $classSubjectModuleRepo->findBy(['class' => $class, 'subject' => $subject, 'period' => $period, 'school' => $this->currentSchool]);
            foreach ($modules as $module) {
                $subjectModule = $module->getSubject();
                if (!isset($classSubjectModules[$subjectModule->getId()])) {
                    $classSubjectModules[$subjectModule->getId()] = [
                        'name' => $subjectModule->getName(),
                        'modules' => [],
                    ];
                }
                $classSubjectModules[$subjectModule->getId()]['modules'][] = $module;
            }
        }

        $evaluationTimes = $timeRepo->findBy(['evaluationFrame' => $evaluationFrame]);
        $evaluationT = [];
        $ids = [];
        foreach ($evaluationTimes as $time) {
            foreach ($time->getEvaluations() as $evals) {
                if (in_array($evals->getTime()->getId(), $evaluationsTimeIds)) {
                    // Vérifier si l'évaluation n'existe pas déjà pour cette période et ce module
                    if (!in_array($time->getId(), $ids)) {
                        $ids[] = $time->getId();
                        $evaluationT[] = $time;
                    }
                }
            }
        }
        $evaluations = [];
        //dd($evaluationT);
        $moduleLen = 0;
        foreach ($students as $student) {
            foreach ($evaluationT as $time) {
                foreach ($classSubjectModules as $subjectId => $subjectData) {
                    if ($moduleLen == 0) $moduleLen = count($subjectData['modules']);
                    foreach ($subjectData['modules'] as $module) {
                        $evaluation = $evaluationRepo->findOneBy([
                            'student' => $student,
                            'time' => $time,
                            'classSubjectModule' => $module,
                        ]);
                        $evaluations[$student->getId()][$time->getId()][$module->getId()] = $evaluation;
                    }
                }
            }
        }


        // Passer les données à la vue
        return $this->render('evaluation/bordereau.individuel.html.twig', [
            'sections' => $section,
            'class' => $class,
            'evaluationFrame' => $evaluationFrame,
            'evaluationTime' => $evaluationT,
            'students' => $students,
            'classSubjectModules' => $classSubjectModules,
            'school' => $this->currentSchool,
            'evaluationTimes' => $evaluationTimes,
            'evaluations' => $evaluations,
            'selectedSubject' => $subject, // ID de la matière choisie
            'moduleLen' => $moduleLen,
        ]);
    }

    #[Route('/evaluation/bordereau/general', name: 'app_evaluation_bordereau_general', methods: ['GET'])]
    public function bordereauGeneral(
        Request $request,
        StudentClassRepository $studentClassRepo,
        SchoolClassPeriodRepository $classRepo,
        ClassSubjectModuleRepository $classSubjectModuleRepo,
        SchoolEvaluationTimeRepository $timeRepo,
        SchoolEvaluationTimeTypeRepository $typeRepo,
        EvaluationRepository $evaluationRepo,
        SchoolEvaluationFrameRepository $frameRepo,
        EntityManagerInterface $entityManager,
        SessionInterface $session
    ): Response {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));

        $classId = $request->query->get('classId');
        $evaluationTimeIdsParam = explode(',', $request->query->get('evaluationTimeId')) ?: [];
        $evaluationTimeIds = [];
        foreach ($evaluationTimeIdsParam as $evaluationTimeId) {
            if ($evaluationTimeId != "") $evaluationTimeIds[] = (int) $evaluationTimeId;
        }
        $evaluationFrameId = $request->query->get('evaluationFrameId');
        $evaluationTimeTypeId = $request->query->get('evaluationTimeTypeId');
        $includeNotes = $request->query->get('includeNotes') === 'true';
        $docType = $request->query->get('docType') ?? 'bordereau_general';

        // Si plusieurs sous-périodes, forcer docType à 'pv'
        if (count($evaluationTimeIds) > 1) {
            $docType = 'pv';
        }

        // Récupérer les entités associées
        $period = $this->currentPeriod;
        $class = $classRepo->findOneBy(['id' => $classId, 'period' => $period, 'school' => $this->currentSchool]);

        // Récupérer la périodicité
        $evaluationTimeType = $typeRepo->find($evaluationTimeTypeId);

        // Récupérer les sous-périodes d'évaluation
        $evaluationTimes = [];
        foreach ($evaluationTimeIds as $timeId) {
            $time = $timeRepo->find($timeId);
            if ($time && $time->getType()->getId() == $evaluationTimeTypeId) {
                $evaluationTimes[] = $time;
            }
        }

        // Récupérer les élèves de la classe
        $students = $studentClassRepo->findBy(['schoolClassPeriod' => $class]);

        // Trier les élèves par ordre alphabétique initialement
        usort($students, function ($a, $b) {
            return strcmp($a->getStudent()->getFullName(), $b->getStudent()->getFullName());
        });

        // Récupérer les modules associés à toutes les matières de la classe
        $classSubjectModules = [];
        $modules = $classSubjectModuleRepo->findBy(['class' => $class, 'period' => $period, 'school' => $this->currentSchool]);
        foreach ($modules as $module) {
            $subject = $module->getSubject();
            if (!isset($classSubjectModules[$subject->getId()])) {
                $classSubjectModules[$subject->getId()] = [
                    'name' => $subject->getName(),
                    'modules' => [],
                    'coef' => 0, // Initialiser le coefficient
                ];
            }
            $classSubjectModules[$subject->getId()]['modules'][] = $module;

            // Récupérer le coefficient de la matière
            if ($classSubjectModules[$subject->getId()]['coef'] == 0) {
                $schoolClassSubject = $this->entityManager->getRepository(SchoolClassSubject::class)->findOneBy([
                    'schoolClassPeriod' => $class,
                    'studySubject' => $subject
                ]);
                if ($schoolClassSubject) {
                    $classSubjectModules[$subject->getId()]['coef'] = $schoolClassSubject->getCoefficient() ?? 1;
                }
            }
        }

        // Données pour chaque sous-période
        $periodData = [];
        $cumulativeData = [
            'studentAveragesByPeriod' => [],
            'periodAverages' => [], // Moyennes par frame (ici toutes du même frame)
            'finalAverages' => [], // Moyennes finales par élève
            'studentSubjectAverages' => [], // Moyennes par matière par élève (pour plusieurs périodes)
            'rankedStudents' => [], // Classements des élèves
            'finalClassAverage' => 0,
        ];

        // Taux de complétion par élève pour chaque période
        $completionRatesByPeriod = [];

        $bType = $class->getReportCardTemplate() ? $class->getReportCardTemplate()->getName() : 'D';
        $coefUsed = $bType == 'D' ? false : true;

        // Récupérer les évaluations pour chaque sous-période
        foreach ($evaluationTimes as $evaluationTime) {
            $evaluations = $evaluationRepo->findBy([
                'classSubjectModule' => $modules,
                'time' => $evaluationTime,
            ]);

            // Calculer le taux de complétion par matière pour cette période
            $completionRatesByPeriod[$evaluationTime->getId()] = $this->calculateCompletionRateBySubject($students, $classSubjectModules, $evaluations);

            // Calculer les données pour cette sous-période (moyenne par période)
            $calculatedData = $this->calculateBordereauData($students, $classSubjectModules, $evaluations, $coefUsed);



            $periodData[$evaluationTime->getId()] = [
                'time' => $evaluationTime,
                'evaluations' => $evaluations,
                'studentNotes' => $calculatedData['studentNotes'],
                'studentAverages' => $calculatedData['studentAverages'],
                'rankedStudents' => $calculatedData['rankedStudents'],
                'subjectStats' => $calculatedData['subjectStats'],
                'classStats' => $calculatedData['classStats'],
            ];

            // Stocker les moyennes par élève pour cette période
            foreach ($calculatedData['studentAverages'] as $studentId => $average) {
                $cumulativeData['studentAveragesByPeriod'][$studentId][$evaluationTime->getId()] = $average;
            }

            // Stocker la moyenne de classe pour cette période
            $cumulativeData['periodAverages'][$evaluationTime->getId()] = $calculatedData['classStats']['average'];
        }

        //dd($calculatedData, $cumulativeData, $periodData);

        // Pour les cumulatifs (multiples périodes), un élève est classé s'il est complet pour TOUTES les périodes
        // Pour une seule période, on utilise simplement la complétion de cette période
        $classifiedStudents = [];
        $unclassifiedStudents = [];

        foreach ($students as $student) {
            $isCompleteForAllPeriods = true;

            // Vérifier la complétion pour chaque période
            foreach ($evaluationTimes as $time) {
                $timeId = $time->getId();
                if (isset($completionRatesByPeriod[$timeId][$student->getId()])) {
                    if (!$completionRatesByPeriod[$timeId][$student->getId()]['isComplete']) {
                        $isCompleteForAllPeriods = false;
                        break;
                    }
                } else {
                    $isCompleteForAllPeriods = false;
                    break;
                }
            }

            if ($isCompleteForAllPeriods) {
                $classifiedStudents[] = $student;
            } else {
                $unclassifiedStudents[] = $student;
            }
        }

        // Tous les élèves (classés + non classés)
        $allStudents = array_merge($classifiedStudents, $unclassifiedStudents);

        // Si on a plusieurs périodes, calculer les moyennes cumulatives
        if (count($evaluationTimes) > 1) {
            // Calculer les moyennes par matière par élève sur toutes les périodes
            foreach ($allStudents as $student) {
                $studentId = $student->getId();
                $studentWeightedSum = 0;
                $studentTotalCoef = 0;

                // Initialiser les moyennes par matière pour cet élève
                $cumulativeData['studentSubjectAverages'][$studentId] = [];

                // Pour chaque matière
                foreach ($classSubjectModules as $subjectId => $subjectData) {
                    $subjectTotalForAllPeriods = 0;
                    $periodsWithGrades = 0;

                    // Récupérer le coefficient de la matière
                    $subjectCoef = $subjectData['coef'] ?? 1;

                    // Calculer la moyenne de la matière sur toutes les périodes
                    foreach ($evaluationTimes as $time) {
                        $timeId = $time->getId();

                        // Calculer la moyenne matière pour cette période spécifique
                        $subjectAverageForPeriod = $this->calculateSubjectAverageForPeriod(
                            $studentId,
                            $subjectId,
                            $periodData[$timeId]['evaluations'] ?? [],
                            $classSubjectModules
                        );

                        if ($subjectAverageForPeriod >= 0) {
                            $subjectTotalForAllPeriods += $subjectAverageForPeriod;
                            $periodsWithGrades++;
                        }
                    }

                    // Moyenne de la matière sur toutes les périodes (seulement si on a des notes)
                    $subjectAverage = $periodsWithGrades > 0 ? ($subjectTotalForAllPeriods / $periodsWithGrades) : 0;

                    // Stocker la moyenne matière pour l'affichage
                    $cumulativeData['studentSubjectAverages'][$studentId][$subjectId] = $subjectAverage;

                    // Calculer la moyenne pondérée (uniquement si la matière a une moyenne > 0)
                    if ($subjectAverage >= 0) {
                        if ($coefUsed) {
                            $studentWeightedSum += ($subjectAverage * $subjectCoef);
                            $studentTotalCoef += $subjectCoef;
                        } else {
                            $studentWeightedSum += $subjectAverage;
                            $studentTotalCoef += 1;
                        }
                    }
                }

                // Calculer la moyenne finale de l'élève (uniquement si on a des matières avec notes)
                $studentAverage = $studentTotalCoef > 0 ? $studentWeightedSum / $studentTotalCoef : 0;
                $cumulativeData['finalAverages'][$studentId] = $studentAverage;
            }

            // Calculer les rangs finaux pour TOUS les élèves ayant une moyenne > 0
            $averagesWithIds = [];
            foreach ($allStudents as $student) {
                $studentId = $student->getId();
                if (isset($cumulativeData['finalAverages'][$studentId]) && $cumulativeData['finalAverages'][$studentId] > 0) {
                    $averagesWithIds[] = ['studentId' => $studentId, 'average' => $cumulativeData['finalAverages'][$studentId]];
                }
            }

            // Trier par moyenne décroissante
            usort($averagesWithIds, function ($a, $b) {
                return $b['average'] <=> $a['average'];
            });

            $currentRank = 1;
            $previousAverage = null;
            $sameRankCount = 0;

            foreach ($averagesWithIds as $index => $data) {
                if ($previousAverage !== null && abs($data['average'] - $previousAverage) > 0.001) {
                    $currentRank += $sameRankCount;
                    $sameRankCount = 1;
                } else {
                    $sameRankCount++;
                }

                $cumulativeData['rankedStudents'][$data['studentId']] = $currentRank;
                $previousAverage = $data['average'];
            }

            // Pour les élèves sans moyenne, pas de rang
            foreach ($allStudents as $student) {
                $studentId = $student->getId();
                if (!isset($cumulativeData['rankedStudents'][$studentId])) {
                    $cumulativeData['rankedStudents'][$studentId] = null;
                }
            }

            // Trier les élèves par moyenne finale (si includeNotes)
            if ($includeNotes) {
                usort($allStudents, function ($a, $b) use ($cumulativeData) {
                    $avgA = $cumulativeData['finalAverages'][$a->getId()] ?? 0;
                    $avgB = $cumulativeData['finalAverages'][$b->getId()] ?? 0;
                    return $avgB <=> $avgA;
                });
            }

            // Calculer la moyenne finale de classe (tous les élèves avec moyenne > 0)
            $finalClassAverages = [];
            foreach ($allStudents as $student) {
                if (isset($cumulativeData['finalAverages'][$student->getId()]) && $cumulativeData['finalAverages'][$student->getId()] > 0) {
                    $finalClassAverages[] = $cumulativeData['finalAverages'][$student->getId()];
                }
            }
            $cumulativeData['finalClassAverage'] = !empty($finalClassAverages) ? array_sum($finalClassAverages) / count($finalClassAverages) : 0;
        } else {
            // Pour une seule période
            $timeId = $evaluationTimes[0]->getId();
            if (isset($periodData[$timeId])) {
                // Initialiser les données pour tous les élèves
                foreach ($allStudents as $student) {
                    $studentId = $student->getId();

                    // Moyenne finale de l'élève
                    $cumulativeData['finalAverages'][$studentId] = $periodData[$timeId]['studentAverages'][$studentId] ?? 0;

                    // Rang de l'élève
                    $cumulativeData['rankedStudents'][$studentId] = $periodData[$timeId]['rankedStudents'][$studentId] ?? null;

                    // Moyennes par matière pour cet élève
                    $cumulativeData['studentSubjectAverages'][$studentId] = [];
                    foreach ($classSubjectModules as $subjectId => $subjectData) {
                        $subjectAverage = $this->calculateSubjectAverageForPeriod(
                            $studentId,
                            $subjectId,
                            $periodData[$timeId]['evaluations'] ?? [],
                            $classSubjectModules
                        );
                        $cumulativeData['studentSubjectAverages'][$studentId][$subjectId] = $subjectAverage;
                    }
                }

                // Moyenne de classe
                $cumulativeData['finalClassAverage'] = $periodData[$timeId]['classStats']['average'];

                // Trier les élèves par moyenne (si includeNotes)
                if ($includeNotes) {
                    usort($allStudents, function ($a, $b) use ($periodData, $timeId) {
                        $avgA = $periodData[$timeId]['studentAverages'][$a->getId()] ?? 0;
                        $avgB = $periodData[$timeId]['studentAverages'][$b->getId()] ?? 0;
                        return $avgB <=> $avgA;
                    });
                }
            }
        }

        // Mettre à jour la liste des étudiants pour utiliser allStudents
        $students = $allStudents;

        // Récupérer le frame d'évaluation
        $evaluationFrame = $frameRepo->find($evaluationFrameId);

        $baremes = $class->getReportCardTemplate() ? $class->getReportCardTemplate()->getEvaluationAppreciationTemplate()->getBaremes()->toArray() : [];

        // Rendre la vue du bordereau général
        return $this->render('evaluation/bordereau.general.html.twig', [
            'class' => $class,
            'evaluationTimes' => $evaluationTimes, // Tableau de périodes
            'evaluationFrame' => $evaluationFrame,
            'evaluationTimeType' => $evaluationTimeType,
            'classifiedStudents' => $classifiedStudents, // Nouveau: élèves classés (100%)
            'unclassifiedStudents' => $unclassifiedStudents, // Nouveau: élèves non classés (<100%)
            'classSubjectModules' => $classSubjectModules,
            'includeNotes' => $includeNotes,
            'school' => $this->currentSchool,
            'periodData' => $periodData,
            'cumulativeData' => $cumulativeData,
            'docType' => $docType,
            'multiplePeriods' => count($evaluationTimes) > 1,
            'baremes' => $baremes,
            'completionRatesByPeriod' => $completionRatesByPeriod, // Nouveau: taux de complétion par période
            'coefUsed' => $coefUsed, // Ajouter cette variable pour la vue
            'students' => $students, // Liste complète des élèves avec leurs données
        ]);
    }

    private function calculateSubjectAverageForPeriod($studentId, $subjectId, $evaluations, $classSubjectModules): float
    {
        $subjectTotal = 0;
        $moduleCount = 0;
        $modulesWithGrades = 0;

        // Vérifier si la matière existe dans les modules
        if (!isset($classSubjectModules[$subjectId])) {
            return 0;
        }

        $subjectData = $classSubjectModules[$subjectId];

        foreach ($subjectData['modules'] as $module) {
            $moduleId = $module->getId();
            $found = false;
            $moduleNote = 0;

            // Chercher l'évaluation pour ce module et cet élève
            foreach ($evaluations as $evaluation) {
                if (
                    $evaluation->getStudent()->getId() == $studentId &&
                    $evaluation->getClassSubjectModule()->getId() == $moduleId
                ) {
                    $moduleNote = $evaluation->getEvaluationNote();
                    $found = true;
                    break;
                }
            }

            // Ajouter la note au total
            $subjectTotal += $moduleNote;
            $moduleCount++;

            // Compter les modules avec des notes > 0
            if ($moduleNote > 0) {
                $modulesWithGrades++;
            }
        }

        // Si aucun module n'a de note > 0, retourner 0
        if ($modulesWithGrades == 0) {
            return 0;
        }

        // Calculer la moyenne (la note est déjà sur 20)
        return $subjectTotal / $moduleCount;
    }

    private function calculateBordereauData($students, $classSubjectModules, $evaluations, bool $coefUsed = false): array
    {
        $studentNotes = [];
        $studentAverages = [];
        $subjectStats = [];
        $classStats = [
            'total' => 0,
            'max' => 0,
            'min' => 20,
            'excellent' => 0,
            'tresBien' => 0,
            'bien' => 0,
            'assezBien' => 0,
            'insuffisant' => 0,
        ];

        // Indexer les évaluations pour un accès rapide
        $evaluationIndex = [];
        foreach ($evaluations as $evaluation) {
            $studentId = $evaluation->getStudent()->getId();
            $moduleId = $evaluation->getClassSubjectModule()->getId();
            $evaluationIndex[$studentId][$moduleId] = $evaluation->getEvaluationNote();
        }

        // Initialiser les stats par matière
        foreach ($classSubjectModules as $subjectId => $subjectData) {
            $subjectStats[$subjectId] = [
                'total' => 0,
                'count' => 0,
                'max' => 0,
                'min' => 20,
                'name' => $subjectData['name'],
            ];
        }

        // Calcul pour chaque élève
        foreach ($students as $student) {
            $studentId = $student->getId();
            $studentNotes[$studentId] = [];

            // Variables pour le calcul de la moyenne de l'élève
            $studentWeightedSum = 0; // Somme des (moyenne matière * coefficient matière)
            $studentTotalCoef = 0;   // Somme des coefficients matière

            // Pour chaque matière
            foreach ($classSubjectModules as $subjectId => $subjectData) {
                $subjectTotal = 0;
                $subjectModuleCount = 0;

                // Pour chaque module de la matière
                foreach ($subjectData['modules'] as $module) {
                    $moduleId = $module->getId();
                    $moduleNote = $evaluationIndex[$studentId][$moduleId] ?? 0;

                    // Stocker la note du module
                    $studentNotes[$studentId][$moduleId] = $moduleNote;

                    // Pour la moyenne de la matière : on somme les notes des modules
                    $subjectTotal += $moduleNote; // Note déjà sur 20
                    if ($coefUsed) {
                        $subjectModuleCount++;
                    } else {
                        $subjectModuleCount += $module->getModuleNotation();
                        $studentTotalCoef += $module->getModuleNotation();
                    }
                }

                //dd($subjectModuleCount);

                if ($coefUsed) {
                    // Calculer la moyenne de la matière (déjà sur 20)
                    $subjectAverage = $subjectModuleCount > 0 ? ($subjectTotal / $subjectModuleCount) : 0;
                } else {
                    $subjectAverage = $subjectModuleCount > 0 ? ($subjectTotal / $subjectModuleCount) : 0;
                }

                // Récupérer le coefficient de la matière depuis SchoolClassSubject
                $subjectCoef = 0;
                if (!empty($subjectData['modules'])) {
                    $firstModule = $subjectData['modules'][0];
                    $schoolClassSubject = $this->entityManager->getRepository(SchoolClassSubject::class)->findBy([
                        'schoolClassPeriod' => $firstModule->getClass(),
                        'studySubject' => $firstModule->getSubject()
                    ]);

                    if (!empty($schoolClassSubject)) {
                        $subjectCoef = $schoolClassSubject[0]->getCoefficient() ?? 1;
                        $subjectData[$subjectId]['coef'] = $subjectCoef;
                    }
                }

                if ($coefUsed) {
                    // Mode coefficient : on utilise la moyenne de la matière * coefficient matière
                    $studentWeightedSum += ($subjectAverage * $subjectCoef);
                    $studentTotalCoef += $subjectCoef;
                } else {
                    // Mode par défaut : on somme simplement les moyennes des matières
                    $studentWeightedSum += $subjectTotal;
                    //foreach($subjectData['modules'] as $module)
                    //$studentTotalCoef /=20; // Chaque matière compte pour 1 dans ce mode
                }

                // Mise à jour stats matière
                if ($subjectModuleCount > 0) {
                    $subjectStats[$subjectId]['total'] += $subjectAverage;
                    $subjectStats[$subjectId]['count']++;
                    $subjectStats[$subjectId]['max'] = max($subjectStats[$subjectId]['max'], $subjectAverage);
                    $subjectStats[$subjectId]['min'] = min($subjectStats[$subjectId]['min'], $subjectAverage);
                }
            }

            //dd($studentTotalCoef,$studentWeightedSum);

            // Calcul de la moyenne de l'élève
            $studentAverage = $studentTotalCoef > 0 ? $studentWeightedSum / $studentTotalCoef * ($coefUsed ? 1 : 20) : 0;
            $studentAverages[$studentId] = $studentAverage;
            //dd($subjectStats,$subjectData,$studentAverages,$student,$studentWeightedSum,$studentTotalCoef);
        }

        // Calcul des rangs
        $averagesWithIds = [];
        foreach ($studentAverages as $studentId => $average) {
            $averagesWithIds[] = ['studentId' => $studentId, 'average' => $average];
        }

        usort($averagesWithIds, function ($a, $b) {
            return $b['average'] <=> $a['average'];
        });

        $rankedStudents = [];
        $currentRank = 1;
        $previousAverage = null;
        $sameRankCount = 0;

        foreach ($averagesWithIds as $index => $data) {
            if ($previousAverage !== null && $data['average'] < $previousAverage) {
                $currentRank += $sameRankCount;
                $sameRankCount = 1;
            } else {
                $sameRankCount++;
            }

            $rankedStudents[$data['studentId']] = $currentRank;
            $previousAverage = $data['average'];
        }

        // Stats générales
        foreach ($studentAverages as $average) {
            $classStats['total'] += $average;
            $classStats['max'] = max($classStats['max'], $average);
            $classStats['min'] = min($classStats['min'], $average);

            if ($average >= 16) {
                $classStats['excellent']++;
            } elseif ($average >= 14) {
                $classStats['tresBien']++;
            } elseif ($average >= 12) {
                $classStats['bien']++;
            } elseif ($average >= 10) {
                $classStats['assezBien']++;
            } else {
                $classStats['insuffisant']++;
            }
        }

        $classStats['average'] = count($studentAverages) > 0 ? $classStats['total'] / count($studentAverages) : 0;

        return [
            'studentNotes' => $studentNotes,
            'studentAverages' => $studentAverages,
            'rankedStudents' => $rankedStudents,
            'subjectStats' => $subjectStats,
            'classStats' => $classStats,
        ];
    }

    #[Route('/evaluations', name: 'app_get_evaluations', methods: ['GET'])]
    public function getEvaluations(SessionInterface $session, EvaluationRepository $evaluationRepo, SchoolPeriodRepository $periodRepo): JsonResponse
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));

        $school = $this->currentSchool;
        $period = $this->currentPeriod;
        // Récupérer toutes les évaluations
        $evaluations = $evaluationRepo->findEvaluationPeriods($school->getId(), $period->getId());



        // Retourner la réponse JSON
        return new JsonResponse($evaluations);
    }

    #[Route('/evaluation/{id}/delete', name: 'app_delete_evaluation', methods: ['DELETE'])]
    public function deleteEvaluation(
        int $id,
        EvaluationRepository $evaluationRepo,
        EntityManagerInterface $entityManager,
        ManagerRegistry $doctrine
    ): JsonResponse {
        if (!in_array('ROLE_ADMIN', $this->getUser()->getRoles()) && !in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles())) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }
        // Récupérer l'évaluation par son ID
        $evaluation = $evaluationRepo->find($id);

        if (!$evaluation) {
            return new JsonResponse(['error' => 'Évaluation introuvable.'], 404);
        }

        // Supprimer l'évaluation
        $entityManager->remove($evaluation);
        try {
            $entityManager->flush();
            $this->operationLog->log(
                'Suppression d\'une évaluation',
                'Succès',
                'Évaluation',
                null,
                null,
                [
                    'evaluationId' => $evaluation->getId(),
                    'student' => $evaluation->getStudent()->getStudent()->getFullName(),
                    'class' => $evaluation->getClassSubjectModule()->getClass()->getId(),
                    'subject' => $evaluation->getClassSubjectModule()->getSubject()->getName(),
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            $this->operationLog->log(
                'Erreur lors de la suppression d\'une évaluation',
                'Échec',
                'Évaluation',
                null,
                $e->getMessage(),
                [
                    'evaluationId' => $evaluation->getId(),
                    'student' => $evaluation->getStudent()->getStudent()->getFullName(),
                    'class' => $evaluation->getClassSubjectModule()->getClass()->getId(),
                    'subject' => $evaluation->getClassSubjectModule()->getSubject()->getName(),
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );
        }

        return new JsonResponse(['message' => 'Évaluation supprimée avec succès.']);
    }

    #[Route('/evaluation/{id}/edit', name: 'app_edit_evaluation', methods: ['GET', 'POST'])]
    public function editEvaluation(
        int $id,
        Request $request,
        EvaluationRepository $evaluationRepo,
        EntityManagerInterface $entityManager,
        ManagerRegistry $doctrine
    ): JsonResponse {
        if (!in_array('ROLE_ADMIN', $this->getUser()->getRoles()) && !in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles())) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }
        // Récupérer l'évaluation par son ID
        $evaluation = $evaluationRepo->find($id);

        if (!$evaluation) {
            return new JsonResponse(['error' => 'Évaluation introuvable.'], 404);
        }

        if ($request->isMethod('GET')) {
            // Retourner les données de l'évaluation pour pré-remplir le formulaire
            return new JsonResponse([
                'id' => $evaluation->getId(),
                'evaluationTimeName' => $evaluation->getTime()->getName(),
                'evaluationFrameName' => $evaluation->getTime()->getEvaluationFrame()->getName(),
                'evaluationNote' => $evaluation->getEvaluationNote(),
            ]);
        }

        if ($request->isMethod('POST')) {
            // Récupérer les données du formulaire
            $note = $request->request->get('evaluationNote');

            // Validation des données
            if ($note === null || !is_numeric($note)) {
                return new JsonResponse(['error' => 'La note est invalide.'], 400);
            }

            // Mettre à jour l'évaluation
            $evaluation->setEvaluationNote((float) $note);
            $evaluation->setUpdatedAt(new \DateTime());

            $entityManager->persist($evaluation);
            try {
                $entityManager->flush();
                $this->operationLog->log(
                    'Modification d\'une évaluation',
                    'Succès',
                    'Évaluation',
                    null,
                    null,
                    [
                        'evaluationId' => $evaluation->getId(),
                        'student' => $evaluation->getStudent()->getStudent()->getFullName(),
                        'class' => $evaluation->getClassSubjectModule()->getClass()->getId(),
                        'subject' => $evaluation->getClassSubjectModule()->getSubject()->getName(),
                        'school' => $this->currentSchool->getName(),
                        'period' => $this->currentPeriod->getName(),
                    ]
                );
            } catch (\Exception $e) {
                if (!$this->entityManager->isOpen()) {
                    $this->entityManager = $doctrine->resetManager();
                }
                $this->operationLog->log(
                    'Erreur lors de la modification d\'une évaluation',
                    'Échec',
                    'Évaluation',
                    null,
                    $e->getMessage(),
                    [
                        'evaluationId' => $evaluation->getId(),
                        'student' => $evaluation->getStudent()->getStudent()->getFullName(),
                        'class' => $evaluation->getClassSubjectModule()->getClass()->getId(),
                        'subject' => $evaluation->getClassSubjectModule()->getSubject()->getName(),
                        'school' => $this->currentSchool->getName(),
                        'period' => $this->currentPeriod->getName(),
                    ]
                );
            }

            return new JsonResponse(['message' => 'Évaluation mise à jour avec succès.']);
        }

        return new JsonResponse(['error' => 'Méthode non autorisée.'], 405);
    }

    #[Route('/module/{id}/delete', name: 'app_delete_module', methods: ['DELETE'])]
    public function deleteModule(
        int $id,
        ClassSubjectModuleRepository $moduleRepo,
        EvaluationRepository $evaluationRepo,
        EntityManagerInterface $entityManager,
        SessionInterface $session,
        ManagerRegistry $doctrine
    ): JsonResponse {
        if (!in_array('ROLE_ADMIN', $this->getUser()->getRoles()) && !in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles())) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));

        $module = $moduleRepo->find($id);

        if (!$module) {
            return new JsonResponse(['error' => 'Module introuvable.'], 404);
        }

        // Démarrer la transaction
        $entityManager->getConnection()->beginTransaction();

        try {
            // 1. Supprimer d'abord toutes les évaluations liées
            $evaluations = $evaluationRepo->findBy(['classSubjectModule' => $module]);
            foreach ($evaluations as $evaluation) {
                $entityManager->remove($evaluation);
            }

            // 2. Flush pour s'assurer que les évaluations sont supprimées
            $entityManager->flush();

            // 3. Maintenant supprimer le module
            $entityManager->remove($module);
            $entityManager->flush();

            // 4. Valider la transaction
            $entityManager->getConnection()->commit();

            $this->operationLog->log(
                'Suppression d\'un module',
                'Succès',
                'Module',
                null,
                null,
                [
                    'moduleId' => $module->getId(),
                    'subject' => $module->getSubject()->getName(),
                    'class' => $module->getClass()->getClassOccurence()->getName(),
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );

            return new JsonResponse(['message' => 'Module supprimé avec succès.']);
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // Rollback en cas d'erreur
            if ($entityManager->getConnection()->isTransactionActive()) {
                $entityManager->getConnection()->rollBack();
            }

            $this->operationLog->log(
                'Erreur lors de la suppression d\'un module',
                'Échec',
                'Module',
                null,
                $e->getMessage(),
                [
                    'moduleId' => $module->getId(),
                    'subject' => $module->getSubject()->getName(),
                    'class' => $module->getClass()->getClassOccurence()->getName(),
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );

            return new JsonResponse(['error' => 'Une erreur est survenue lors de la suppression du module: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/module/{id}/edit', name: 'app_edit_module', methods: ['GET', 'POST'])]
    public function editModule(
        int $id,
        Request $request,
        ClassSubjectModuleRepository $moduleRepo,
        EntityManagerInterface $entityManager,
        SessionInterface $session,
        ManagerRegistry $doctrine
    ): JsonResponse {
        if (!in_array('ROLE_ADMIN', $this->getUser()->getRoles()) && !in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles())) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));

        $module = $moduleRepo->find($id);

        if (!$module) {
            return new JsonResponse(['error' => 'Module introuvable.'], 404);
        }

        if ($request->isMethod('GET')) {
            // Retourner les données du module pour pré-remplir le formulaire
            return new JsonResponse([
                'moduleNotation' => $module->getModuleNotation(),
            ]);
        }

        if ($request->isMethod('POST')) {
            // Mettre à jour le module
            $moduleNotationValue = $request->request->get('moduleNotation');
            $moduleNotation = round((float) $moduleNotationValue, 2);

            $module->setModuleNotation($moduleNotation);
            $module->setUpdatedAt(new \DateTime());

            $entityManager->persist($module);
            try {
                $entityManager->flush();
                $refreshedModule = $moduleRepo->find($id);
                $this->operationLog->log(
                    'Modification d\'un module',
                    'Succès',
                    'Module',
                    $module->getId(),
                    null,
                    [
                        'moduleName' => $module->getModule()->getModuleName(),
                        'moduleId' => $module->getId(),
                        'subject' => $module->getSubject()->getName(),
                        'class' => $module->getClass()->getClassOccurence()->getName(),
                        'school' => $this->currentSchool->getName(),
                        'period' => $this->currentPeriod->getName(),
                    ]
                );
            } catch (\Exception $e) {
                if (!$this->entityManager->isOpen()) {
                    $this->entityManager = $doctrine->resetManager();
                }
                $this->operationLog->log(
                    'Erreur lors de la modification d\'un module',
                    'Échec',
                    'Module',
                    $module->getId(),
                    $e->getMessage(),
                    [
                        'moduleName' => $module->getModule()->getModuleName(),
                        'moduleId' => $module->getId(),
                        'subject' => $module->getSubject()->getName(),
                        'class' => $module->getClass()->getClassOccurence()->getName(),
                        'school' => $this->currentSchool->getName(),
                        'period' => $this->currentPeriod->getName(),
                    ]
                );
            }

            return new JsonResponse(['message' => 'Module modifié avec succès.']);
        }

        return new JsonResponse(['error' => 'Méthode non autorisée.'], 405);
    }

    #[Route('/module/{moduleId}/evaluations', name: 'app_view_evaluations', methods: ['GET'])]
    public function viewEvaluations(int $moduleId, EvaluationRepository $evaluationRepo): Response
    {
        $evaluations = $evaluationRepo->findBy(['classSubjectModule' => $moduleId]);

        return $this->render('evaluation/module_evaluations.html.twig', [
            'evaluations' => $evaluations,
        ]);
    }

    #[Route('/evaluation/times', name: 'app_times_by_frame_and_type', methods: ['GET'])]
    public function getTimesByFrameAndType(
        Request $request,
        SchoolEvaluationTimeRepository $timeRepo
    ): JsonResponse {
        $frameId = $request->query->get('frameId');
        $typeId = $request->query->get('typeId');

        if (!$frameId || !$typeId) {
            return new JsonResponse(['error' => 'Période ou périodicité manquante.'], 400);
        }

        $times = $timeRepo->findBy([
            'evaluationFrame' => $frameId,
            'type' => $typeId,
        ]);

        $data = [];
        foreach ($times as $time) {
            $data[] = [
                'id' => $time->getId(),
                'name' => $time->getName(),
            ];
        }

        return new JsonResponse($data);
    }

    public function getConnectedUser(): User
    {
        return $this->getUser();
    }

    #[Route('/evaluation/saisie-notes', name: 'app_saisie_notes', methods: ['GET'])]
    public function saisieNotes(
        StudyLevelRepository $sectionRepo,
        SchoolClassPeriodRepository $classRepo,
        SchoolEvaluationFrameRepository $frameRepo,
        SchoolEvaluationTimeRepository $timeRepo,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        // Récupérer les sections (niveaux d'étude)
        $sections = $sectionRepo->findAll();
        $this->entityManager = $entityManager;
        $user = $this->getConnectedUser();
        $config = $user ? $user->getBaseConfigurations()->toArray() : [];
        if (count($config) > 0) {
            $sections = $sectionRepo->findBy(['id' => count($config[0]->getSectionList()) > 0 ? $this->entityManager->getRepository(StudyLevel::class)->findBy(['id' => $config[0]->getSectionList()]) : array_map(fn($sl) => $sl->getId(), $sectionRepo->findAll())]);
        } else {
            $sections = $sectionRepo->findAll(); // Si aucune configuration n'est trouvée, on initialise un tableau vide
        }

        return $this->render('evaluation/saisie.notes.index.html.twig', [
            'sections' => $sections,
        ]);
    }

    #[Route('/evaluation/evaluations', name: 'app_evaluations_by_class', methods: ['GET'])]
    public function getEvaluationsByClass(
        Request $request,
        EvaluationRepository $evaluationRepo,
        SchoolPeriodRepository $schoolPeriodRepo,
        SessionInterface $session
    ): JsonResponse {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));

        $classId = $request->query->get('classId');
        $schoolId = $this->currentSchool->getId(); // Récupérer l'école de l'utilisateur connecté
        $periodId = $this->currentPeriod->getId(); // Récupérer la période active

        if (!$classId || !$periodId) {
            return new JsonResponse(['error' => 'Class ID ou Period ID manquant.'], 400);
        }

        // Appeler la méthode findEvaluationPeriodsByClass
        $evaluations = $evaluationRepo->findEvaluationPeriodsByClass($classId, $schoolId, $periodId);

        return new JsonResponse($evaluations);
    }

    #[Route('/evaluation/modules-and-students', name: 'app_modules_and_students', methods: ['GET'])]
    public function getModulesAndStudents(
        Request $request,
        ClassSubjectModuleRepository $moduleRepo,
        StudentClassRepository $studentRepo,
        SchoolPeriodRepository $schoolPeriodRepo,
        SchoolClassPeriodRepository $classRepo,
        StudySubjectRepository $classSubjectRepo,
        SchoolEvaluationFrameRepository $frameRepo,
        SchoolEvaluationTimeRepository $timeRepo,
        SessionInterface $session
    ): JsonResponse {

        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $classId = $request->query->get('classId');
        $subjectId = $request->query->get('subjectId'); // ID de la matière choisie
        $frameId = $request->query->get('frame'); // ID de la période d'évaluation
        $timeId = $request->query->get('time'); // ID de la sous-période d'évaluation

        $frameName = $frameRepo->find($frameId)->getName(); // ID de la période d'évaluation
        $timeName = $timeRepo->find($timeId)->getName(); // ID de la sous-période d'évaluation

        $period = $this->currentPeriod;
        $school = $this->currentSchool;
        $subject = $classSubjectRepo->findOneBy(['id' => $subjectId]);

        // Récupérer les modules pour la classe et la période
        $modules = $moduleRepo->findBy([
            'class' => $classId,
            'period' => $period,
            'school' => $school,
            'subject' => $subject
        ]);

        if (!$modules) {
            return new JsonResponse(['error' => 'Aucun module trouvé pour cette classe.'], 404);
        }

        $class = $classRepo->find($classId);
        if (!$class) {
            return new JsonResponse(['error' => 'Classe introuvable.'], 404);
        }


        // Récupérer les élèves pour la classe (triés par ordre alphabétique)
        $students = $studentRepo->findBy(['schoolClassPeriod' => $class]);
        // Trier les élèves par ordre alphabétique
        usort($students, function ($a, $b) {
            return strcmp($a->getStudent()->getFullName(), $b->getStudent()->getFullName());
        });

        $schoolClassSubject = $this->entityManager->getRepository(SchoolClassSubject::class)->findOneBy(['schoolClassPeriod' => $class, 'studySubject' => $subjectId]);
        $isNotApplicable = $this->entityManager->getRepository(SchoolClassSubjectEvaluationTimeNotApplicable::class)->isNotApplicable($schoolClassSubject->getId(), $timeId);

        return new JsonResponse([
            'modules' => array_map(fn($module) => ['id' => $module->getId(), 'name' => $module->getModule()->getModuleName(), 'moduleNotation' => $module->getModuleNotation()], $modules),
            'students' => array_map(fn($student) => ['id' => $student->getStudent()->getId(), 'name' => $student->getStudent()->getFullName()], $students),
            'classId' => $classId,
            'subjectId' => $subjectId, // ID de la matière choisie
            'frameId' => $frameId, // ID de la période d'évaluation
            'timeId' => $timeId, // ID de la période d'évaluation
            'subject' => $subject->getName(),
            'className' => $class->getClassOccurence()->getName(),
            'frameName' => $frameName,
            'timeName' => $timeName,
            'isNotApplicable' => $isNotApplicable,
            'notApplicableId' => $this->entityManager->getRepository(SchoolClassSubjectEvaluationTimeNotApplicable::class)->findBySchoolClassSubjectAndEvaluationTime($schoolClassSubject->getId(), $timeId)?->getId() ?? null,
            'schoolClassSubjectId' => $schoolClassSubject->getId()
        ]);
    }

    #[Route('/evaluation/toggle-applicable', name: 'app_toggle_not_applicable', methods: ['GET'])]
    public function toggleApplicable(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $id = (int)$request->query->get('applicableId');
        $schoolClassSubjectId = (int)$request->query->get('subjectId');
        $schoolClassSubject = $em->getRepository(SchoolClassSubject::class)->find($schoolClassSubjectId);
        $evaluationTimeId = (int)$request->query->get('timeId');
        $evaluationTime = $em->getRepository(SchoolEvaluationTime::class)->find($evaluationTimeId);
        if ($id == 0) {
            $applicableSchoolClassSubjectEvaluationTime = new SchoolClassSubjectEvaluationTimeNotApplicable();
            $applicableSchoolClassSubjectEvaluationTime->setSchoolClassSubject($schoolClassSubject);
            $applicableSchoolClassSubjectEvaluationTime->setSchoolEvaluationTime($evaluationTime);
            $applicableSchoolClassSubjectEvaluationTime->setNotApplicable(true);
            $em->persist($applicableSchoolClassSubjectEvaluationTime);
        } else {
            $applicableSchoolClassSubjectEvaluationTime = $em->getRepository(SchoolClassSubjectEvaluationTimeNotApplicable::class)->find($id);
            $applicableSchoolClassSubjectEvaluationTime->setNotApplicable(!$applicableSchoolClassSubjectEvaluationTime->isNotApplicable());
        }
        try {
            $em->flush();
        } catch (Exception $e) {
            return new JsonResponse([
                'error' => "Une erreur s'est produite. " . $e->getMessage()
            ], 500);
        }

        return new JsonResponse([
            'success' => 'Mise à jour du statut non applicable réussi'
        ], 200);
    }

    #[Route('/evaluation/notes/update', name: 'app_update_evaluation', methods: ['POST'])]
    public function updateEvaluation(
        Request $request,
        EvaluationRepository $evaluationRepo,
        EntityManagerInterface $entityManager,
        StudentClassRepository $studentRepo,
        SchoolClassPeriodRepository $classRepo,
        SchoolPeriodRepository $periodRepo,
        ClassSubjectModuleRepository $classSubjectModuleRepo,
        UserRepository $userRepo,
        SessionInterface $session,
        ManagerRegistry $doctrine
    ): JsonResponse {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));

        // Retry logic for 502 errors
        $maxRetries = 3;
        $retryDelay = 1000; // milliseconds
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                $studentId = $request->request->get('studentId');
                $moduleId = $request->request->get('moduleId');
                $value = $request->request->get('value');
                $timeId = $request->request->get('timeId');
                $classId = $request->request->get('classId');

                if (!$studentId || !$moduleId || $value === null) {
                    return new JsonResponse(['error' => 'Données manquantes.'], 400);
                }

                $module = $classSubjectModuleRepo->find($moduleId);
                if (!$module || !($module instanceof \App\Entity\ClassSubjectModule)) {
                    return new JsonResponse(['error' => 'Module introuvable.'], 404);
                }

                $class = $classRepo->find($classId);
                $period = $this->currentPeriod;

                $user = $userRepo->find($studentId);

                $student = $studentRepo->findOneBy(['student' => $user, 'schoolClassPeriod' => $class]);
                if (!$student) {
                    return new JsonResponse(['error' => 'Élève introuvable.'], 404);
                }

                // Récupérer l'évaluation correspondante
                $evaluation = $evaluationRepo->findOneBy([
                    'student' => $student,
                    'classSubjectModule' => $module,
                    'time' => $timeId,
                ]);

                // Récupérer l'entité SchoolEvaluationTime à partir de l'ID
                $schoolEvaluationTime = $this->entityManager->getRepository(\App\Entity\SchoolEvaluationTime::class)->find($timeId);
                if (!$schoolEvaluationTime) {
                    return new JsonResponse(['error' => 'Période d\'évaluation introuvable.'], 404);
                }

                if (!$evaluation) {
                    $evaluation = new Evaluation();
                    $evaluation->setStudent($student);
                    $evaluation->setClassSubjectModule($module);
                    $evaluation->setTime($schoolEvaluationTime);
                    $evaluation->setCreatedAt(new \DateTime());
                }

                // Mettre à jour la note
                $evaluation->setEvaluationNote((float) $value);
                $evaluation->setUpdatedAt(new \DateTime());

                // Démarrer une transaction pour s'assurer de la cohérence des données
                $this->entityManager->getConnection()->beginTransaction();

                $entityManager->persist($evaluation);
                $entityManager->flush();
                $this->entityManager->getConnection()->commit();

                // Log de succès
                $this->operationLog->log(
                    'Mise à jour d\'une évaluation',
                    'Succès',
                    'Évaluation',
                    null,
                    null,
                    [
                        'evaluationId' => $evaluation->getId(),
                        'student' => $student->getStudent()->getFullName(),
                        'class' => $module->getClass()->getId(),
                        'subject' => $module->getSubject()->getName(),
                        'school' => $this->currentSchool->getName(),
                        'period' => $this->currentPeriod->getName(),
                    ]
                );

                return new JsonResponse(['success' => 'Note mise à jour avec succès.']);
            } catch (\Exception $e) {
                if (!$this->entityManager->isOpen()) {
                    $this->entityManager = $doctrine->resetManager();
                }
                // Rollback en cas d'erreur
                if ($this->entityManager->getConnection()->isTransactionActive()) {
                    $this->entityManager->getConnection()->rollBack();
                }

                $attempt++;

                if ($attempt >= $maxRetries) {
                    // Log de l'erreur finale
                    $this->operationLog->log(
                        'Erreur lors de la mise à jour d\'une évaluation',
                        'Échec',
                        'Évaluation',
                        null,
                        $e->getMessage(),
                        [
                            'evaluationId' => isset($evaluation) ? $evaluation->getId() : null,
                            'student' => isset($student) ? $student->getStudent()->getFullName() : null,
                            'class' => isset($module) ? $module->getClass()->getId() : null,
                            'subject' => isset($module) ? $module->getSubject()->getName() : null,
                            'school' => $this->currentSchool->getName(),
                            'period' => $this->currentPeriod->getName(),
                        ]
                    );

                    // Vérifier si c'est une erreur 502
                    if (strpos($e->getMessage(), '502') !== false || strpos($e->getMessage(), 'Bad Gateway') !== false) {
                        return new JsonResponse([
                            'error' => 'Erreur temporaire du serveur (502). Veuillez réessayer.',
                            'retry' => true
                        ], 502);
                    }

                    return new JsonResponse(['error' => 'Une erreur est survenue lors de la mise à jour de la note.'], 500);
                }

                // Attente avant de réessayer (backoff exponentiel)
                usleep($retryDelay * 1000 * $attempt);
            }
        }

        return new JsonResponse(['error' => 'Échec après plusieurs tentatives.'], 500);
    }

    #[Route('/evaluation/sheet/notes', name: 'app_get_evaluation_notes', methods: ['GET'])]
    public function getEvaluationNotes(
        Request $request,
        EvaluationRepository $evaluationRepo,
        SchoolClassPeriodRepository $classRepo,
        SchoolEvaluationTimeRepository $timeRepo,
        ClassSubjectModuleRepository $classSubjectModuleRepo,
        SchoolPeriodRepository $periodRepo,
        SessionInterface $session,
        EntityManagerInterface $entityManager
    ): JsonResponse {

        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $classId = $request->query->get('classId');
        $timeId = $request->query->get('timeId');
        $subjectId = $request->query->get('subjectId');
        $school = $this->currentSchool;
        $module = $request->query->get('moduleId');
        //dd($module, $classId, $timeId, $subjectId);

        if (!$classId || !$timeId || !$subjectId) {
            return new JsonResponse(['error' => 'Données manquantes.'], 400);
        }

        $class = $classRepo->find($classId);
        if (!$class) {
            return new JsonResponse(['error' => 'Classe introuvable.'], 404);
        }

        $period = $this->currentPeriod;

        $time = $timeRepo->find($timeId);
        $subject = $classSubjectModuleRepo->findBy(['subject' => $subjectId, 'class' => $class, 'period' => $period]);

        // Récupérer les évaluations pour la classe, la sous-période et la matière
        $evaluations = $evaluationRepo->findBy([
            'time' => $time,
            'classSubjectModule' => $subject,
        ]);

        //vérifier que chaque élève a une évaluation pour chaque module

        $modules = $classSubjectModuleRepo->findBy(['class' => $class, 'period' => $period, 'school' => $school]);

        $students = $entityManager->getRepository(StudentClass::class)->findBy(['schoolClassPeriod' => $class]);
        foreach ($students as $student) {
            $evals = $evaluationRepo->findBy([
                'student' => $student,
                'classSubjectModule' => $modules,
                'time' => $time,
            ]);
            // Récupérer les évaluations pour la classe, la sous-période et la matière
            if (!$evals) {
                // Si l'élève n'a pas d'évaluation pour ce module, on en crée une avec une note de 0
                foreach ($modules as $module) {
                    $evaluation = new Evaluation();
                    $evaluation->setStudent($student);
                    if (!$module || !($module instanceof \App\Entity\ClassSubjectModule)) {
                        continue; // Or handle error as needed
                    }
                    $evaluation->setClassSubjectModule($module);
                    $evaluation->setTime($time);
                    $evaluation->setEvaluationNote(0);
                    $evaluation->setCreatedAt(new \DateTime());
                    $evaluation->setUpdatedAt(new \DateTime());

                    $entityManager->persist($evaluation);
                }
            }
        }

        $data = [];
        foreach ($evaluations as $evaluation) {
            $data[] = [
                'studentId' => $evaluation->getStudent()->getStudent()->getId(),
                'moduleId' => $evaluation->getClassSubjectModule()->getId(),
                'note' => $evaluation->getEvaluationNote(),
                'createdAt' => $evaluation->getCreatedAt()->format('Y-m-d H:i:s'),
                'updatedAt' => $evaluation->getUpdatedAt()->format('Y-m-d H:i:s'),
                'moduleNotation' => $evaluation->getClassSubjectModule()->getModuleNotation(),
            ];
        }

        $response = new JsonResponse($data, 200);
        $response->setEncodingOptions(JSON_UNESCAPED_UNICODE);
        return $response;
    }

    #[Route('/evaluation/appreciation-template/create', name: 'app_create_evaluation_appreciation_template', methods: ['POST'])]
    public function createEvaluationAppreciationTemplate(
        Request $request,
        EntityManagerInterface $entityManager,
        SessionInterface $session,
        ManagerRegistry $doctrine
    ): JsonResponse {
        if (!in_array('ROLE_ADMIN', $this->getUser()->getRoles()) && !in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles())) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }
        $name = $request->request->get('name');
        $isDefault = $request->request->get('default', false);

        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));

        if (!$name) {
            return new JsonResponse(['error' => 'Le nom du template est requis.'], 400);
        }

        // Vérifier l'unicité du nom
        $existing = $entityManager->getRepository(EvaluationAppreciationTemplate::class)
            ->findOneBy(['name' => $name]);
        if ($existing) {
            $response = new JsonResponse(
                ['error' => 'Un template d\'appréciation avec ce nom existe déjà.'],
                409
            );
            $response->setEncodingOptions(JSON_UNESCAPED_UNICODE);
            return $response;
        }

        // Si ce template doit être le défaut, désactiver tous les autres
        if ($isDefault) {
            $allTemplates = $entityManager->getRepository(EvaluationAppreciationTemplate::class)->findAll();
            foreach ($allTemplates as $tpl) {
                if ($tpl->isEnabled()) {
                    $tpl->setEnabled(false);
                    $entityManager->persist($tpl);
                }
            }
        }

        $template = new EvaluationAppreciationTemplate();
        $template->setName($name);
        $template->setEnabled((bool)$isDefault);

        $entityManager->persist($template);
        try {
            $entityManager->flush();
            // log l'opération
            $this->operationLog->log(
                'Création d\'un template d\'appréciation',
                'Succès',
                'EvaluationAppreciationTemplate',
                $template->getId(),
                null,
                ['name' => $template->getName(), 'default' => $template->isEnabled(), 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()]
            );
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // log l'erreur
            $this->operationLog->log(
                'Erreur lors de la création d\'un template d\'appréciation',
                'Échec',
                'EvaluationAppreciationTemplate',
                null,
                $e->getMessage(),
                ['name' => $template->getName(), 'default' => $template->isEnabled(), 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()]
            );
            $response = new JsonResponse(
                ['error' => 'Une erreur est survenue lors de la création du template.'],
                500
            );
        }

        // Si enabled = true, mettre à jour la propriété evaluationAppreciationTemplate de l'école courante
        if ($template->isEnabled()) {
            $school = $this->currentSchool;
            $school->setEvaluationAppreciationTemplate($template);
            $entityManager->persist($school);
            try {
                $entityManager->flush();
                $this->operationLog->log(
                    'Mise à jour du template d\'appréciation par défaut de l\'école',
                    'Succès',
                    'School',
                    $school->getId(),
                    null,
                    ['template' => $template->getName(), 'school' => $school->getName(), 'period' => $this->currentPeriod->getName()]
                );
            } catch (\Exception $e) {
                if (!$this->entityManager->isOpen()) {
                    $this->entityManager = $doctrine->resetManager();
                }
                $this->operationLog->log(
                    'Erreur lors de la mise à jour du template d\'appréciation par défaut de l\'école',
                    'Échec',
                    'School',
                    $school->getId(),
                    $e->getMessage(),
                    ['template' => $template->getName(), 'school' => $school->getName(), 'period' => $this->currentPeriod->getName()]
                );
            }
        }

        // Enregistrement dans le logger
        $this->operationLog->log(
            'Création template appréciation',
            'Succès',
            'EvaluationAppreciationTemplate',
            $template->getId(),
            null,
            ['name' => $template->getName(), 'default' => $template->isEnabled(), 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()]
        );

        return new JsonResponse([
            'success' => true,
            'message' => 'Template d\'appréciation créé avec succès.',
            'name' => $template->getName(),
            'default' => $template->isEnabled(),
        ]);
    }

    #[Route('/evaluation/appreciation-bareme/create', name: 'app_create_evaluation_appreciation_bareme', methods: ['POST'])]
    public function createEvaluationAppreciationBareme(
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        if (!in_array('ROLE_ADMIN', $this->getUser()->getRoles()) && !in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles())) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }
        $templateId = $request->request->get('templateId');
        $maxNote = $request->request->get('maxNote');
        $value = strtoupper($request->request->get('value'));
        $fullValue = strtoupper($request->request->get('fullValue'));

        if (!$templateId || !$maxNote || !$value || !$fullValue) {
            return new JsonResponse(['error' => 'Tous les champs sont requis.'], 400);
        }

        $template = $entityManager->getRepository(EvaluationAppreciationTemplate::class)->find($templateId);
        if (!$template) {
            return new JsonResponse(['error' => 'Template d\'appréciation introuvable.'], 404);
        }

        $bareme = new EvaluationAppreciationBareme();
        $bareme->setEvaluationAppreciationTemplate($template);
        $bareme->setEvaluationAppreciationMaxNote((int)$maxNote);
        $bareme->setEvaluationAppreciationValue($value);
        $bareme->setEvaluationAppreciationFullValue($fullValue);

        $entityManager->persist($bareme);
        try {
            $entityManager->flush();

            // Log l'opération si besoin
            $this->operationLog->log(
                'Création barème appréciation',
                'Succès',
                'EvaluationAppreciationBareme',
                $bareme->getId(),
                null,
                [
                    'templateId' => $templateId,
                    'maxNote' => $maxNote,
                    'value' => $value,
                    'fullValue' => $bareme->getEvaluationAppreciationFullValue(),
                ]
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Barème d\'appréciation ajouté avec succès.',
                'bareme' => [
                    'template' => $template->getName(),
                    'maxNote' => $bareme->getEvaluationAppreciationMaxNote(),
                    'value' => $bareme->getEvaluationAppreciationValue(),
                    'fullValue' => $bareme->getEvaluationAppreciationFullValue(),
                ]
            ]);
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // Log l'erreur
            $this->operationLog->log(
                'Erreur lors de la création d\'un barème d\'appréciation',
                'Échec',
                'EvaluationAppreciationBareme',
                null,
                $e->getMessage(),
                [
                    'templateId' => $templateId,
                    'maxNote' => $maxNote,
                    'value' => $value,
                    'fullValue' => $fullValue,
                ]
            );
            return new JsonResponse(['error' => 'Une erreur est survenue lors de la création du barème.'], 500);
        }
    }

    #[Route('/evaluation/appreciation-baremes/list', name: 'app_list_evaluation_appreciation_baremes', methods: ['GET'])]
    public function listEvaluationAppreciationBaremes(EntityManagerInterface $entityManager): JsonResponse
    {
        $baremes = $entityManager->getRepository(EvaluationAppreciationBareme::class)->findAll();
        $data = [];
        foreach ($baremes as $bareme) {
            $data[] = [
                'template' => $bareme->getEvaluationAppreciationTemplate()->getName(),
                'maxNote' => $bareme->getEvaluationAppreciationMaxNote(),
                'value' => $bareme->getEvaluationAppreciationValue(),
                'isDefault' => $bareme->getEvaluationAppreciationTemplate()->isEnabled(),
                'fullValue' => $bareme->getEvaluationAppreciationFullValue(),
                'id' => $bareme->getId(),
            ];
        }
        $response = new JsonResponse($data, 200);
        $response->setEncodingOptions(JSON_UNESCAPED_UNICODE);
        return $response;
    }

    #[Route('/evaluation/config/{id}/edit', name: 'app_edit_evaluation_config', methods: ['GET', 'POST'])]
    public function editEvaluationConfig(
        int $id,
        Request $request,
        SchoolEvaluationFrameRepository $frameRepo,
        SchoolEvaluationTimeRepository $timeRepo,
        StudyLevelRepository $sectionRepo,
        EntityManagerInterface $entityManager,
        SessionInterface $session
    ): Response {
        if (!in_array('ROLE_ADMIN', $this->getUser()->getRoles()) && !in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles())) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));

        // Exemple : récupération de la configuration à éditer (adapte selon ton entité)
        $config = $timeRepo->find($id);
        $period = $this->currentPeriod;
        $qb = $entityManager->createQueryBuilder();

        if (!$config) {
            throw $this->createNotFoundException('Configuration d\'évaluation introuvable.');
        }

        if ($request->isMethod('POST')) {
            // Traite la soumission du formulaire ici
            $config->setName($request->request->get('evaluationName'));
            // ... autres champs à mettre à jour
            try {
                $entityManager->flush();
                // logger l'opération
                $this->operationLog->log(
                    'Modification de la configuration d\'évaluation',
                    'Succès',
                    'SchoolEvaluationTime',
                    $config->getId(),
                    null,
                    [
                        'name' => $config->getName(),
                        'period' => $period->getName(),
                        'frame' => $config->getEvaluationFrame()->getName(),
                        'type' => $config->getType()->getName(),
                        'school' => $this->currentSchool->getName()
                    ]
                );
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de la modification de la configuration d\'évaluation.');
                return $this->redirectToRoute('app_evaluation');
            }

            $this->addFlash('success', 'Configuration d\'évaluation modifiée avec succès.');
            return $this->redirectToRoute('app_evaluation');
        }
        //dd($config);

        return $this->render('evaluation/edit.html.twig', [
            'config' => $config,
            'frames' => $frameRepo->findAll(),
            'sections' => $sectionRepo->findAll(),
        ]);
    }

    #[Route('/evaluation/config/{id}/delete', name: 'app_delete_evaluation_configuration', methods: ['DELETE'])]
    public function deleteEvaluationConfig(
        int $id,
        EvaluationRepository $evaluationRepo,
        EntityManagerInterface $entityManager,
        SessionInterface $session,
        ManagerRegistry $doctrine
    ): JsonResponse {
        if (!in_array('ROLE_ADMIN', $this->getUser()->getRoles()) && !in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles())) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));

        // Récupérer l'évaluation par son ID
        $evaluations = $evaluationRepo->findBy(['time' => $id]);

        if (!$evaluations) {
            return new JsonResponse(['error' => 'Évaluation introuvable.'], 404);
        }

        // Supprimer l'évaluation
        foreach ($evaluations as $evaluation) {
            $entityManager->remove($evaluation);
        }

        try {
            $entityManager->flush();
            // logger l'opération
            $this->operationLog->log(
                'Suppression de l\'évaluation',
                'Succès',
                'SchoolEvaluationTime',
                null,
                null,
                [
                    'evaluations' => $evaluations,
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // logger l'erreur
            $this->operationLog->log(
                'Suppression de l\'évaluation',
                'Échec',
                'SchoolEvaluationTime',
                null,
                $e->getMessage(),
                [
                    'evaluations' => $evaluations,
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );
            return new JsonResponse(['error' => 'Une erreur est survenue lors de la suppression de l\'évaluation.'], 500);
        }

        return new JsonResponse(['message' => 'Évaluation supprimée avec succès.']);
    }

    #[Route('/evaluation/appreciation-bareme/{id}/delete', name: 'app_delete_evaluation_appreciation_bareme', methods: ['DELETE'])]
    public function deleteEvaluationAppreciationBareme(
        int $id,
        EntityManagerInterface $entityManager,
        ManagerRegistry $doctrine
    ): JsonResponse {
        if (!in_array('ROLE_ADMIN', $this->getUser()->getRoles()) && !in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles())) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }
        $bareme = $entityManager->getRepository(\App\Entity\EvaluationAppreciationBareme::class)->find($id);

        if (!$bareme) {
            return new JsonResponse(['error' => 'Barème d\'appréciation introuvable.'], 404);
        }
        $br = $bareme;

        $entityManager->remove($bareme);

        try {
            $entityManager->flush();
            // logger l'opération
            $this->operationLog->log(
                'Suppression du barème d\'appréciation ' . $br->getEvaluationAppreciationValue(),
                'Succès',
                'EvaluationAppreciationBareme',
                null,
                null,
                [
                    'bareme' => $br->getId(),
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // logger l'erreur
            $this->operationLog->log(
                'Suppression du barème d\'appréciation ' . $br->getEvaluationAppreciationValue(),
                'Échec',
                'EvaluationAppreciationBareme',
                null,
                $e->getMessage(),
                [
                    'bareme' => $br->getId(),
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );
            return new JsonResponse(['error' => 'Une erreur est survenue lors de la suppression du barème d\'appréciation.'], 500);
        }

        return new JsonResponse(['message' => 'Barème d\'appréciation supprimé avec succès.']);
    }

    #[Route('/evaluation/reportcard-class/list', name: 'app_reportcard_class_list', methods: ['GET'])]
    public function reportCardClassList(
        SchoolClassPeriodRepository $classRepo,
        EntityManagerInterface $entityManager,
        SessionInterface $session
    ): JsonResponse {
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $classes = $classRepo->findBy(['school' => $this->currentSchool, 'period' => $this->currentPeriod]);
        $data = [];

        foreach ($classes as $class) {
            $template = $class->getReportCardTemplate();
            $data[] = [
                'id' => $class->getId(),
                'className' => $class->getClassOccurence()->getName(),
                'templateName' => $template ? $template->getName() . ($template->getDescription() != ''  ? ' (' . $template->getDescription() . ')' : '') : '<span class="badge bg-secondary">Aucun</span>',
                'templateId' => $template ? $template->getId() : null,
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/evaluation/reportcard-class/assign', name: 'app_assign_reportcard_template', methods: ['POST'])]
    public function assignReportCardTemplate(
        Request $request,
        SchoolClassPeriodRepository $classRepo,
        EntityManagerInterface $entityManager,
        SessionInterface $session,
        ManagerRegistry $doctrine
    ): JsonResponse {
        if (!in_array('ROLE_ADMIN', $this->getUser()->getRoles()) && !in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles())) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $classId = $request->request->all('classId');
        $templateId = $request->request->get('templateId');

        if (!$classId || !$templateId) {
            return new JsonResponse(['error' => 'Classe et modèle de bulletin requis.'], 400);
        }

        $classes = $classRepo->findBy(['id' => $classId]);;
        if (!$classes) {
            return new JsonResponse(['error' => 'Classe introuvable.'], 404);
        }

        $template = $entityManager->getRepository(\App\Entity\ReportCardTemplate::class)->find($templateId);
        if (!$template) {
            return new JsonResponse(['error' => 'Modèle de bulletin introuvable.'], 404);
        }

        foreach ($classes as $class) {
            $class->setReportCardTemplate($template);
            $entityManager->persist($class);
        }
        try {
            $entityManager->flush();
            // logger l'opération
            $this->operationLog->log(
                'Affectation du modèle de bulletin ' . $template->getName() . ' à la classe ' . $class->getClassOccurence()->getName(),
                'Succès',
                'EvaluationAppreciationBareme',
                null,
                null,
                [
                    'template' => $template->getId(),
                    'class' => $class->getId(),
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // logger l'erreur
            $this->operationLog->log(
                'Affectation du modèle de bulletin ' . $template->getName() . ' à la classe ' . $class->getClassOccurence()->getName(),
                'Échec',
                'EvaluationAppreciationBareme',
                null,
                $e->getMessage(),
                [
                    'template' => $template->getId(),
                    'class' => $class->getId(),
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );
            return new JsonResponse(['error' => 'Une erreur est survenue lors de l\'affectation du modèle de bulletin.'], 500);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Modèle de bulletin assigné à la classe avec succès.',
            'classes' => $classes,
            'templateName' => $template->getName(),
        ]);
    }

    #[Route('/evaluation/reportcard-class/{id}/remove', name: 'app_remove_reportcard_template_from_class', methods: ['DELETE'])]
    public function removeReportCardTemplateFromClass(
        int $id,
        SchoolClassPeriodRepository $classRepo,
        EntityManagerInterface $entityManager,
        SessionInterface $session,
        ManagerRegistry $doctrine
    ): JsonResponse {
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $class = $classRepo->find($id);

        if (!$class) {
            return new JsonResponse(['error' => 'Classe introuvable.'], 404);
        }

        $class->setReportCardTemplate(null);
        $entityManager->persist($class);
        try {
            $entityManager->flush();
            // logger l'opération
            $this->operationLog->log(
                'Suppression du modèle de bulletin de la classe ' . $class->getClassOccurence()->getName(),
                'Succès',
                'EvaluationAppreciationBareme',
                null,
                null,
                [
                    'class' => $class->getId(),
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // logger l'erreur
            $this->operationLog->log(
                'Suppression du modèle de bulletin de la classe ' . $class->getClassOccurence()->getName(),
                'Échec',
                'EvaluationAppreciationBareme',
                null,
                $e->getMessage(),
                [
                    'class' => $class->getId(),
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );
            return new JsonResponse(['error' => 'Une erreur est survenue lors de la suppression du modèle de bulletin.'], 500);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Modèle de bulletin retiré de la classe avec succès.',
            'classId' => $class->getId(),
        ]);
    }

    #[Route('/evaluation/reportcard-template/{id}', name: 'app_get_reportcard_template', methods: ['GET'])]
    public function getReportCardTemplate(
        int $id,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $template = $entityManager->getRepository(\App\Entity\ReportCardTemplate::class)->find($id);

        if (!$template) {
            return new JsonResponse(['error' => 'Modèle de bulletin introuvable.'], 404);
        }

        return new JsonResponse([
            'id' => $template->getId(),
            'name' => $template->getName(),
            'description' => $template->getDescription(),
            'headerLeft' => $template->getHeaderLeft(),
            'headerRight' => $template->getHeaderRight(),
            'nationalMottoLeft' => $template->getNationalMottoLeft(),
            'nationalMottoRight' => $template->getNationalMottoRight(),
            'headerTitle' => $template->getHeaderTitle(),
        ]);
    }

    #[Route('/evaluation/reportcard-template/{id}/edit', name: 'app_edit_reportcard_template', methods: ['POST'])]
    public function editReportCardTemplate(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        SessionInterface $session,
        ManagerRegistry $doctrine
    ): JsonResponse {
        if (!in_array('ROLE_ADMIN', $this->getUser()->getRoles()) && !in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles())) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $template = $entityManager->getRepository(\App\Entity\ReportCardTemplate::class)->find($id);

        if (!$template) {
            return new JsonResponse(['error' => 'Modèle de bulletin introuvable.'], 404);
        }

        $template->setName($request->request->get('name'));
        $template->setDescription($request->request->get('description'));
        $template->setHeaderTitle($request->request->get('headerTitle'));
        $template->setHeaderLeft($request->request->get('headerLeft'));
        $template->setHeaderRight($request->request->get('headerRight'));
        $template->setNationalMottoLeft($request->request->get('nationalMottoLeft'));
        $template->setNationalMottoRight($request->request->get('nationalMottoRight'));

        $entityManager->persist($template);
        try {
            $entityManager->flush();
            // logger l'opération
            $this->operationLog->log(
                'Modification du modèle de bulletin',
                'Succès',
                'EvaluationAppreciationBareme',
                $template->getId(),
                null,
                [
                    'name' => $template->getName(),
                    'description' => $template->getDescription(),
                    'headerTitle' => $template->getHeaderTitle(),
                    'headerLeft' => $template->getHeaderLeft(),
                    'headerRight' => $template->getHeaderRight(),
                    'nationalMottoLeft' => $template->getNationalMottoLeft(),
                    'nationalMottoRight' => $template->getNationalMottoRight(),
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // logger l'erreur
            $this->operationLog->log(
                'Modification du modèle de bulletin',
                'Échec',
                'EvaluationAppreciationBareme',
                null,
                $e->getMessage(),
                [
                    'template' => $template->getId(),
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );
            return new JsonResponse(['error' => 'Une erreur est survenue lors de la modification du modèle de bulletin.'], 500);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Modèle de bulletin modifié avec succès.',
        ]);
    }


    #[Route('/discipline', name: 'app_discipline_index', methods: ['GET'])]
    public function discipline(
        Request $request,
        SessionInterface $session
    ): Response {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));

        $studyLevels = $this->entityManager->getRepository(StudyLevel::class)->findAll();

        return $this->render('evaluation/discipline.index.html.twig', [
            'studyLevels' => array_map(function ($level) {
                return [
                    'id' => $level->getId(),
                    'name' => $level->getName(),
                ];
            }, $studyLevels),
        ]);
    }

    #[Route('/studentclass/by-class', name: 'app_studentclass_by_class', methods: ['GET'])]
    public function getStudentClassByClass(
        Request $request,
        StudentClassRepository $studentClassRepo,
        SchoolClassPeriodRepository $classRepo,
        SessionInterface $session,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));

        $classId = $request->query->get('classId');
        if (!$classId) {
            return new JsonResponse(['error' => 'Class ID manquant.'], 400);
        }

        $class = $classRepo->findBy(['id' => $classId, 'period' => $this->currentPeriod, 'school' => $this->currentSchool]);
        if (!$class) {
            return new JsonResponse(['error' => 'Classe introuvable.'], 404);
        }

        // Récupérer les élèves inscrits dans cette classe pour la période courante
        $students = $studentClassRepo->findBy(['schoolClassPeriod' => $class]);

        $data = [];
        foreach ($students as $studentClass) {
            $student = $studentClass->getStudent();
            $data[] = [
                'id' => $student->getId(),
                'fullName' => $student->getFullName(),
                'matricule' => $student->getRegistrationNumber(),
            ];
        }

        return new JsonResponse(['data' => $data]);
    }

    #[Route('/studentclass/attendance/save', name: 'app_save_studentclassattendance', methods: ['POST'])]
    public function saveStudentClassAttendance(
        Request $request,
        EntityManagerInterface $entityManager,
        StudentClassRepository $studentClassRepo,
        SchoolEvaluationTimeRepository $timeRepo,
        SchoolClassPeriodRepository $classRepo,
        SessionInterface $session,
        ManagerRegistry $doctrine
    ): JsonResponse {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(\App\Entity\School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(\App\Entity\SchoolPeriod::class)->find($this->session->get('period_id'));

        $studentId = $request->request->get('studentId');
        $classId = $request->request->get('classId');

        if (!$studentId || !$classId) {
            return new JsonResponse(['error' => 'Élève ou classe manquant.'], 400);
        }

        // Récupérer l'entité StudentClass
        $classPeriods = $classRepo->findBy(['id' => $classId, 'period' => $this->currentPeriod, 'school' => $this->currentSchool]);
        if (!$classPeriods) {
            return new JsonResponse(['error' => 'Classe introuvable.'], 404);
        }
        $studentClass = $studentClassRepo->findOneBy(['student' => $studentId, 'schoolClassPeriod' => $classPeriods]);
        if (!$studentClass) {
            return new JsonResponse(['error' => 'Inscription élève introuvable.'], 404);
        }

        $keys = [];
        // Pour chaque période envoyée, enregistrer les valeurs
        foreach ($request->request->all() as $values) {
            // On ne traite que les champs attendus
            $keys = array_keys($values);
            if (is_array($keys)) break;
        }

        foreach ($keys as $time) {
            $studentClassAttendance = $entityManager->getRepository(\App\Entity\StudentClassAttendance::class)
                ->findOneBy(['studentClass' => $studentClass, 'time' => $time]);
            if (!$studentClassAttendance) {
                // Si l'absence n'existe pas, on la crée
                $studentClassAttendance = new \App\Entity\StudentClassAttendance();
            }
            foreach ($request->request->all() as $key => $value) {
                if (is_array($value))
                    foreach ($value as $k => $attendanceValue) {

                        if (method_exists($studentClassAttendance, 'set' . ucfirst($key)) && $k == $time) {
                            $setter = 'set' . ucfirst($key);
                            $studentClassAttendance->$setter($attendanceValue);
                        }
                    }
            }
            $studentClassAttendance->setStudentClass($studentClass);
            $studentClassAttendance->setTime($timeRepo->find($time));
            $entityManager->persist($studentClassAttendance);
        }



        try {
            $entityManager->flush();
            // logger l'opération
            $this->operationLog->log(
                'Enregistrement des données de discipline',
                'Succès',
                'StudentClassAttendance',
                null,
                null,
                [
                    'classes' => $classPeriods,
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // logger l'erreur
            $this->operationLog->log(
                'Enregistrement des données de discipline',
                'Échec',
                'StudentClassAttendance',
                null,
                $e->getMessage(),
                [
                    'classes' => $classPeriods,
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );
            return new JsonResponse(['error' => 'Une erreur est survenue lors de l\'enregistrement des données de discipline.'], 500);
        }

        return new JsonResponse(['success' => true, 'message' => 'Données de discipline enregistrées.']);
    }

    #[Route('/studentclass/attendance/get', name: 'app_get_studentclassattendance', methods: ['GET'])]
    public function getStudentClassAttendance(
        Request $request,
        StudentClassRepository $studentClassRepo,
        SchoolEvaluationTimeRepository $timeRepo,
        SchoolClassPeriodRepository $classRepo,
        SessionInterface $session,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(\App\Entity\School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(\App\Entity\SchoolPeriod::class)->find($this->session->get('period_id'));

        $studentId = $request->query->get('studentId');
        $classId = $request->query->get('classId');

        if (!$studentId || !$classId) {
            return new JsonResponse(['error' => 'Élève ou classe manquant.'], 400);
        }

        // Récupérer l'entité StudentClass
        $classPeriods = $classRepo->findBy(['id' => $classId, 'period' => $this->currentPeriod, 'school' => $this->currentSchool]);
        if (!$classPeriods) {
            return new JsonResponse(['error' => 'Classe introuvable.'], 404);
        }
        $studentClass = $studentClassRepo->findOneBy(['student' => $studentId, 'schoolClassPeriod' => $classPeriods]);
        if (!$studentClass) {
            return new JsonResponse(['error' => 'Inscription élève introuvable.'], 404);
        }

        // Récupérer toutes les présences/absences pour cet élève et cette classe
        $attendances = $entityManager->getRepository(\App\Entity\StudentClassAttendance::class)
            ->findBy(['studentClass' => $studentClass]);


        $data = [];
        foreach ($attendances as $attendance) {
            $timeId = $attendance->getTime()->getId();
            $data[$timeId] = [
                'heuresAbsence' => $attendance->getHeuresAbsence(),
                'absencesJustifiee' => $attendance->getAbsencesJustifiee(),
                'retard' => $attendance->getRetard(),
                'retardInjustifie' => $attendance->getRetardInjustifie(),
                'retenue' => $attendance->getRetenue(),
                'avertissementDiscipline' => $attendance->getAvertissementDiscipline(),
                'blame' => $attendance->getBlame(),
                'jourExclusion' => $attendance->getJourExclusion(),
                'exclusionDefinitive' => $attendance->isExclusionDefinitive() ? 1 : 0,
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/subject/cancel-group', name: 'app_cancel_subject_group', methods: ['POST'])]
    public function cancelSubjectGroup(
        Request $request,
        EntityManagerInterface $entityManager,
        SchoolClassSubjectRepository $schoolClassSubjectRepo,
        ManagerRegistry $doctrine
    ): JsonResponse {
        $id = $request->request->get('id');
        $token = $request->request->get('_token');

        if (!$this->isCsrfTokenValid('cancel_subject_group', $token)) {
            return new JsonResponse(['error' => 'Token CSRF invalide.'], 400);
        }

        if (!$id) {
            return new JsonResponse(['error' => 'ID manquant.'], 400);
        }

        $subject = $schoolClassSubjectRepo->find($id);
        if (!$subject) {
            return new JsonResponse(['error' => 'Affectation introuvable.'], 404);
        }
        $currentGroup = $subject->getGroup();
        $subject->setGroup(null);
        $entityManager->persist($subject);
        try {
            $entityManager->flush();
            // logger l'opération
            $this->operationLog->log(
                'Annulation de l\'affectation à un groupe',
                'Succès',
                'SchoolClassSubject',
                $subject->getId(),
                null,
                [
                    'subject' => $subject->getId(),
                    'group' => $currentGroup ? $currentGroup->getId() : null,
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // logger l'erreur
            $this->operationLog->log(
                'Annulation de l\'affectation à un groupe',
                'Échec',
                'SchoolClassSubject',
                $subject->getId(),
                $e->getMessage(),
                [
                    'subject' => $subject->getId(),
                    'group' => $currentGroup ? $currentGroup->getId() : null,
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );
            return new JsonResponse(['error' => 'Une erreur est survenue lors de l\'annulation de l\'affectation à un groupe.'], 500);
        }

        return new JsonResponse(['success' => true]);
    }

    #[Route('/subject/cancel-teacher', name: 'app_cancel_subject_teacher', methods: ['POST'])]
    public function cancelSubjectTeacher(
        Request $request,
        EntityManagerInterface $entityManager,
        SchoolClassSubjectRepository $schoolClassSubjectRepo,
        ManagerRegistry $doctrine
    ): JsonResponse {
        $id = $request->request->get('id');
        $token = $request->request->get('_token');

        if (!$this->isCsrfTokenValid('cancel_subject_teacher', $token)) {
            return new JsonResponse(['error' => 'Token CSRF invalide.'], 400);
        }

        if (!$id) {
            return new JsonResponse(['error' => 'ID manquant.'], 400);
        }

        $subject = $schoolClassSubjectRepo->find($id);
        if (!$subject) {
            return new JsonResponse(['error' => 'Affectation introuvable.'], 404);
        }
        $currentTeacher = $subject->getTeacher();
        $subject->setTeacher(null);
        $entityManager->persist($subject);
        try {
            $entityManager->flush();
            // logger l'opération
            $this->operationLog->log(
                'Annulation de l\'affectation à un enseignant',
                'Succès',
                'SchoolClassSubject',
                $subject->getId(),
                null,
                [
                    'subject' => $subject->getId(),
                    'teacher' => $currentTeacher ? $currentTeacher->getId() : null,
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // logger l'erreur
            $this->operationLog->log(
                'Annulation de l\'affectation à un enseignant',
                'Échec',
                'SchoolClassSubject',
                $subject->getId(),
                $e->getMessage(),
                [
                    'subject' => $subject->getId(),
                    'teacher' => $currentTeacher ? $currentTeacher->getId() : null,
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );
            return new JsonResponse(['error' => 'Une erreur est survenue lors de l\'annulation de l\'affectation à un enseignant.'], 500);
        }

        return new JsonResponse(['success' => true]);
    }

    private function calculateCompletionRateBySubject(array $students, array $classSubjectModules, array $evaluations): array
    {
        $completionRates = [];

        foreach ($students as $student) {
            $totalSubjects = count($classSubjectModules);
            $completedSubjects = 0;
            $subjectDetails = [];

            foreach ($classSubjectModules as $subjectId => $subjectData) {
                $subjectCompleted = false;
                $moduleCount = 0;
                $completedModules = 0;

                // Pour chaque module de cette matière
                foreach ($subjectData['modules'] as $module) {
                    $moduleCount++;

                    // Rechercher l'évaluation pour cet élève et ce module
                    foreach ($evaluations as $evaluation) {
                        if (
                            $evaluation->getStudent()->getId() === $student->getId() &&
                            $evaluation->getClassSubjectModule()->getId() === $module->getId()
                        ) {
                            // Si la note est > 0, on considère que ce module est complété
                            if ($evaluation->getEvaluationNote() > 0) {
                                $completedModules++;
                                $subjectCompleted = true;
                            }
                            break;
                        }
                    }
                }

                // La matière est complétée si au moins une note > 0 existe pour ses modules
                if ($subjectCompleted) {
                    $completedSubjects++;
                }

                $subjectDetails[$subjectId] = [
                    'name' => $subjectData['name'],
                    'completed' => $subjectCompleted,
                    'moduleCount' => $moduleCount,
                    'completedModules' => $completedModules,
                ];
            }

            $rate = $totalSubjects > 0 ? ($completedSubjects / $totalSubjects) * 100 : 0;
            $completionRates[$student->getId()] = [
                'rate' => $rate,
                'completedSubjects' => $completedSubjects,
                'totalSubjects' => $totalSubjects,
                'isComplete' => $rate == 100,
                'subjectDetails' => $subjectDetails,
            ];
        }

        return $completionRates;
    }
}
