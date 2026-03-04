<?php

namespace App\Controller;

use App\Repository\SchoolClassSubjectRepository;
use App\Entity\Timetable;
use App\Entity\TimetableDay;
use App\Entity\TimetableSlot;
use App\Entity\User;
use App\Repository\TimetableRepository;
use App\Repository\UserRepository;
use App\Repository\SchoolClassPeriodRepository;
use App\Repository\StudySubjectRepository;
use App\Repository\SchoolPeriodRepository;
use App\Repository\TimetableSlotRepository;
use App\Entity\SchoolPeriod;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Entity\School;
use App\Service\OperationLogger;
use Doctrine\Persistence\ManagerRegistry;

#[Route('/timetable', defaults: ['_module' => 'timetable'])]
class TimeTableController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private SchoolPeriod $currentPeriod;
    private School $currentSchool;
    private SessionInterface $session;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/', name: 'app_timetable_index')]
    public function index(SessionInterface $session, TimetableRepository $timetableRepository, EntityManagerInterface $entityManager): Response
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $period = $this->currentPeriod;
        $timetables = $timetableRepository->findBy([
            'school' => $this->currentSchool,
            'period' => $period,
        ], ['teacher' => 'ASC']);
        $teachers = $entityManager->getRepository(User::class)->findByRoleAndSchool(
            'ROLE_TEACHER',
            $this->currentSchool
        );
        // Récupération des enseignants pour le formulaire de création/modification
        if (count($timetables) === 0) {
            $existingTeachers = [];
        } else {
            $excludedTeachersId = [];
            foreach ($timetables as $timetable) {
                if ($timetable->getTeacher()) {
                    $excludedTeachersId[] = $timetable->getTeacher()->getId();
                }
            }
            $existingTeachers = array_filter($teachers, function ($teacher) use ($excludedTeachersId) {
                return in_array($teacher->getId(), $excludedTeachersId);
            });;
            // Exclude teachers already assigned to a timetable
            $teachers = array_filter($teachers, function ($teacher) use ($excludedTeachersId) {
                return !in_array($teacher->getId(), $excludedTeachersId);
            });
        }

        return $this->render('timetable/index.html.twig', [
            'timetables' => $timetables,
            'existingteachers' => $existingTeachers,
            'teachers' => $teachers
        ]);
    }

    #[Route('/{id}/show', name: 'app_timetable_show')]
    public function show(SessionInterface $session, User $teacher, SchoolClassSubjectRepository $schoolClassRepo, EntityManagerInterface $entityManager): Response
    {
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $timetables = $teacher->getTimetables();
        $schoolClassPeriods = $schoolClassRepo->findBy(['teacher' => $teacher]);
        $classes = [];
        foreach ($schoolClassPeriods as $schoolClassPeriod) {
            if ($schoolClassPeriod->getSchoolClassPeriod()->getSchool() !== $this->currentSchool) {
                continue; // Skip if no school class is set
            }
            $classes[] = $schoolClassPeriod->getSchoolClassPeriod()->getClassOccurence();
        }
        return $this->render('timetable/show.html.twig', [
            'teacher' => $teacher,
            'timetables' => $timetables,
            'classes' => $classes,
        ]);
    }

    #[Route('/new', name: 'app_timetable_new', methods: ['POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepo,
        SchoolClassPeriodRepository $classRepo,
        StudySubjectRepository $subjectRepo,
        SchoolPeriodRepository $periodRepo,
        \Symfony\Component\HttpFoundation\RequestStack $requestStack,
        SessionInterface $session,
        OperationLogger $operationLogger,
        ManagerRegistry $doctrine
    ): Response {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));

        // Ici tu peux gérer la création d'un emploi du temps (formulaire, etc.)
        // Exemple minimal :
        if ($request->isMethod('POST')) {
            $timetable = new Timetable();
            $teacherId = $request->request->get('teacher');
            $teacher = $userRepo->find($teacherId);
            $period = $this->currentPeriod; // Récupération de la période actuelle
            $timetable->setLabel('TT_' . str_replace(' ', '_', $teacher->getRegistrationNumber()) . '_' . str_replace('/', '_', $period->getName()));
            $timetable->setTeacher($teacher);
            $timetable->setSchool($this->currentSchool);
            $timetable->setPeriod($period);
            $em->persist($timetable);
            try {
                $em->flush();
                $this->addFlash('success', 'Emploi du temps créé.');
                // log de l'action
                $operationLogger->log(
                    'CREATION D\'UN EMPLOI DU TEMPS POUR L\'ENSEIGNANT ' . $teacher->getFullName() . ' POUR LA PÉRIODE ' . $period->getName(),
                    'SUCCESS',
                    'timetable',
                    $timetable->getId(),
                    null,
                    [
                        'school' => $this->currentSchool->getName(),
                        'period' => $this->currentPeriod->getName(),
                        'teacher' => $teacher->getFullName()
                    ]
                );
                return $this->redirectToRoute('app_timetable_index');
            } catch (\Exception $e) {
                if (!$this->entityManager->isOpen()) {
                    $this->entityManager = $doctrine->resetManager();
                }
                //log de l'opération
                $operationLogger->log(
                    'CREATION D\'UN EMPLOI DU TEMPS POUR L\'ENSEIGNANT ' . $teacher->getFullName() . ' POUR LA PÉRIODE ' . $period->getName(),
                    'ERROR',
                    'timetable',
                    $timetable->getId(),
                    $e->getMessage(),
                    [
                        'school' => $this->currentSchool->getName(),
                        'period' => $this->currentPeriod->getName(),
                        'teacher' => $teacher->getFullName()
                    ]
                );
                $this->addFlash('error', 'Echec de la création de l\'emploi du temps');
            }
        }

        return $this->redirectToRoute('app_timetable_index');
    }

    #[Route('/{id}/edit', name: 'app_timetable_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Timetable $timetable,
        EntityManagerInterface $em,
        UserRepository $userRepo,
        SchoolClassPeriodRepository $classRepo,
        StudySubjectRepository $subjectRepo,
        SchoolPeriodRepository $periodRepo,
        OperationLogger $operationLogger,
        ManagerRegistry $doctrine
    ): Response {
        if ($request->isMethod('POST')) {
            // Hydrate $timetable avec les données du formulaire (à adapter selon ton formulaire)
            // Exemple :
            $timetable->setLabel($request->request->get('label'));
            // ... autres champs à mettre à jour ...
            try {
                $em->flush();
                $this->addFlash('success', 'Emploi du temps modifié.');
                // log de l'action
                $operationLogger->log(
                    'MODIFICATION D\'UN EMPLOI DU TEMPS POUR L\'ENSEIGNANT ' . $timetable->getTeacher()->getFullName() . ' POUR LA PÉRIODE ' . $timetable->getPeriod()->getName(),
                    'SUCCESS',
                    'timetable',
                    $timetable->getId(),
                    null,
                    [
                        'school' => $this->currentSchool->getName(),
                        'period' => $this->currentPeriod->getName(),
                        'teacher' => $timetable->getTeacher()->getFullName()
                    ]
                );
                return $this->redirectToRoute('app_timetable_index');
            } catch (\Exception $e) {
                if (!$this->entityManager->isOpen()) {
                    $this->entityManager = $doctrine->resetManager();
                }
                //log de l'opération
                $operationLogger->log(
                    'MODIFICATION D\'UN EMPLOI DU TEMPS POUR L\'ENSEIGNANT ' . $timetable->getTeacher()->getFullName() . ' POUR LA PÉRIODE ' . $timetable->getPeriod()->getName(),
                    'ERROR',
                    'timetable',
                    $timetable->getId(),
                    $e->getMessage(),
                    [
                        'school' => $this->currentSchool->getName(),
                        'period' => $this->currentPeriod->getName(),
                        'teacher' => $timetable->getTeacher()->getFullName()
                    ]
                );
                $this->addFlash('error', 'Echec de la modification de l\'emploi du temps');
            }
        }

        return $this->render('timetable/edit.html.twig', [
            'timetable' => $timetable,
            'teachers' => $userRepo->findByRole('ROLE_TEACHER'),
            'classes' => $classRepo->findAll(),
            'subjects' => $subjectRepo->findAll(),
            'periods' => $periodRepo->findAll(),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_timetable_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Timetable $timetable,
        EntityManagerInterface $em,
        OperationLogger $operationLogger
    ): Response {
        if ($this->isCsrfTokenValid('delete_timetable_' . $timetable->getId(), $request->request->get('_token'))) {
            $em->remove($timetable);
            try {
                $em->flush();
                //log de l'opération
                $operationLogger->log(
                    'SUPPRESSION D\'UN EMPLOI DU TEMPS ' . $timetable->getLabel(),
                    'SUCCESS',
                    'timetable',
                    $timetable->getId(),
                    null,
                    [
                        'school' => $this->currentSchool->getName(),
                        'period' => $this->currentPeriod->getName()
                    ]
                );
                $this->addFlash('success', 'Emploi du temps supprimé.');
            } catch (\Exception $e) {
            }
        }
        return $this->redirectToRoute('app_timetable_index');
    }

    #[Route('/grid', name: 'app_timetable_grid', methods: ['POST'])]
    public function grid(
        Request $request,
        EntityManagerInterface $em,
        SchoolClassPeriodRepository $classRepo,
        SchoolClassSubjectRepository $subjectRepo
    ): Response {
        $timetableId = $request->request->get('timetableId');
        $classId = $request->request->get('classId');

        $timetable = $em->getRepository(\App\Entity\Timetable::class)->find($timetableId);
        $schoolClassPeriod = $classRepo->findOneBy(['classOccurence' => $classId]);
        // Prépare les jours de la semaine (lundi à samedi)
        $days = [
            1 => 'Lundi',
            2 => 'Mardi',
            3 => 'Mercredi',
            4 => 'Jeudi',
            5 => 'Vendredi',
            6 => 'Samedi'
        ];

        // Récupère tous les créneaux pour ce timetable et cette classe
        $slotsByDayAndTime = [];
        foreach ($timetable->getDays() as $day) {
            if (!isset($days[$day->getDayOfWeek()])) continue;
            foreach ($day->getSlots() as $slot) {
                if ($slot->getSchoolClassPeriod() && $slot->getSchoolClassPeriod()->getId() == $schoolClassPeriod->getId()) {
                    $slotsByDayAndTime[$slot->getStartTime()->format('H:i') . ' - ' . $slot->getEndTime()->format('H:i')][$day->getDayOfWeek()] = $slot;
                }
            }
        }

        $subjectsClass = $subjectRepo->findBy(['schoolClassPeriod' => $schoolClassPeriod, 'teacher' => $timetable->getTeacher()]);
        $subjects = [];
        foreach ($subjectsClass as $subject) {
            $subjects[] = $subject->getStudySubject();
        }

        // Liste des horaires uniques (ordonnés)
        $times = array_keys($slotsByDayAndTime);
        sort($times);

        return $this->render('timetable/_grid.html.twig', [
            'days' => $days,
            'times' => $times,
            'slotsByDayAndTime' => $slotsByDayAndTime,
            'subjects' => $subjects,
            'timetable' => $timetable,
            'class' => $schoolClassPeriod,
        ]);
    }

    #[Route('/slot/add', name: 'app_timetable_slot_add', methods: ['POST'])]
    public function addSlot(
        Request $request,
        EntityManagerInterface $em,
        SchoolClassPeriodRepository $classRepo,
        StudySubjectRepository $subjectRepo,
        OperationLogger $operationLogger,
        SessionInterface $session,
        \Doctrine\Persistence\ManagerRegistry $doctrine
    ): Response {
        $this->session = $session;
        $this->entityManager = $em;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $timetableId = $request->request->get('timetableId');
        $classId = $request->request->get('classId');
        $dayOfWeek = $request->request->get('dayOfWeek');
        $startTime = $request->request->get('startTime');
        $duration = $request->request->get('endTime');
        $endTime = date('H:i', strtotime($startTime) + strtotime($duration)); // Calculer l'heure de fin
        $subjectId = $request->request->get('subject');
        $notes = $request->request->get('notes');

        $timetable = $em->getRepository(\App\Entity\Timetable::class)->find($timetableId);
        $schoolClassPeriod = $classRepo->find($classId);
        $subject = $subjectRepo->find($subjectId);

        // Trouver ou créer le TimetableDay correspondant
        $timetableDay = null;
        foreach ($timetable->getDays() as $day) {
            if ($day->getDayOfWeek() == $dayOfWeek) {
                $timetableDay = $day;
                break;
            }
        }
        if (!$timetableDay) {
            $timetableDay = new \App\Entity\TimetableDay();
            $timetableDay->setTimetable($timetable);
            $timetableDay->setDayOfWeek($dayOfWeek);
            $em->persist($timetableDay);
        }

        // Créer le slot
        $slot = new \App\Entity\TimetableSlot();
        $slot->setTimetableDay($timetableDay);
        $slot->setStartTime(\DateTime::createFromFormat('H:i', $startTime));
        $slot->setEndTime(\DateTime::createFromFormat('H:i', $endTime));
        $slot->setSubject($subject);
        $slot->setSchoolClassPeriod($schoolClassPeriod);
        $slot->setNotes($notes);

        try {
            $em->persist($slot);
            $em->flush();
            // Log de l'opération
            $operationLogger->log(
                'AJOUT D\'UN CRÉNEAU DANS L\'EMPLOI DU TEMPS ' . $timetable->getLabel(),
                'SUCCESS',
                'timetable',
                $timetable->getId(),
                null,
                ['slot' => $slot->getId(), 'class' => $schoolClassPeriod->getId(), 'subject' => $subject->getId(), 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()],
            );
            return $this->json([
                'success' => true,
                'message' => 'Créneau ajouté avec succès.',
            ]);
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            // If the EntityManager is closed, clear it BEFORE logging so the logger can persist
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            $operationLogger->log(
                'ÉCHEC D\'AJOUT D\'UN CRÉNEAU DANS L\'EMPLOI DU TEMPS ' . $timetable->getLabel(),
                'ERROR',
                'timetable',
                $timetable->getId(),
                $e->getMessage(),
                ['slot' => $slot->getId(), 'class' => $schoolClassPeriod->getId(), 'subject' => $subject->getId(), 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()],
            );
            return $this->json([
                'success' => false,
                'message' => "Erreur : ce créneau existe déjà.",
                'error' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            // If the EntityManager is closed, clear it BEFORE logging so the logger can persist
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            $operationLogger->log(
                'ÉCHEC D\'AJOUT D\'UN CRÉNEAU DANS L\'EMPLOI DU TEMPS ' . $timetable->getLabel(),
                'ERROR',
                'timetable',
                $timetable->getId(),
                $e->getMessage(),
                ['slot' => $slot->getId(), 'class' => $schoolClassPeriod->getId(), 'subject' => $subject->getId(), 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()],
            );
            return $this->json([
                'success' => false,
                'message' => "Erreur lors de l'enregistrement.",
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/slot/{id}/delete', name: 'app_timetable_slot_delete', methods: ['POST'])]
    public function deleteSlot(
        Request $request,
        EntityManagerInterface $em,
        int $id,
        OperationLogger $operationLogger,
        ManagerRegistry $doctrine
    ): Response {
        $token = $request->request->get('_token');
        $slot = $em->getRepository(\App\Entity\TimetableSlot::class)->find($id);

        if (!$slot) {
            return $this->json([
                'success' => false,
                'message' => "Créneau introuvable."
            ], 404);
        }

        if (!$this->isCsrfTokenValid('delete_timetable_slot_' . $id, $token)) {
            return $this->json([
                'success' => false,
                'message' => "Token CSRF invalide."
            ], 400);
        }

        try {
            $em->remove($slot);
            $em->flush();
            // Log de l'opération
            $operationLogger->log(
                'SUPPRESSION D\'UN CRÉNEAU DANS L\'EMPLOI DU TEMPS ' . $slot->getTimetable()->getLabel(),
                'SUCCESS',
                'timetable',
                $slot->getId(),
                null,
                ['slot' => $slot->getId(), 'class' => $slot->getSchoolClassPeriod()->getId(), 'subject' => $slot->getSubject()->getId(), 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()],
            );
            return $this->json([
                'success' => true,
                'message' => "Créneau supprimé avec succès."
            ]);
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // Log de l'erreur
            $operationLogger->log(
                'ÉCHEC DE LA SUPPRESSION D\'UN CRÉNEAU DANS L\'EMPLOI DU TEMPS ' . $slot->getTimetable()->getLabel(),
                'ERROR',
                'timetable',
                $slot->getId(),
                $e->getMessage(),
                ['slot' => $slot->getId(), 'class' => $slot->getSchoolClassPeriod()->getId(), 'subject' => $slot->getSubject()->getId(), 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()],
            );
            return $this->json([
                'success' => false,
                'message' => "Erreur lors de la suppression.",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/timetable/teacher', name: 'app_timetable_per_teacher')]
    public function perTeacher(SessionInterface $session, EntityManagerInterface $entityManager, UserRepository $userRepo): Response
    {
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $id = $this->entityManager->getRepository(\App\Entity\SchoolClassPeriod::class)->findBy(['school' => $this->currentSchool, 'period' => $this->currentPeriod]);

        // On suppose que SchoolClassSubject relie SchoolClassPeriod à Teacher
        $subjects = $this->entityManager->getRepository(\App\Entity\SchoolClassSubject::class)->findBy(['schoolClassPeriod' => $id]);
        $teachers = [];
        foreach ($subjects as $subject) {
            $teacher = $subject->getTeacher();
            if ($teacher) {
                // si le professeur n'est pas déjà dans la liste, on l'ajoute
                if (!isset($teachers[$teacher->getId()])) {
                    // On ajoute le professeur à la liste
                    $teachers[$teacher->getId()] = [
                        'id' => $teacher->getId(),
                        'fullName' => $teacher->getFullName(),
                    ];
                }
            }
        }

        return $this->render('timetable/per_teacher.html.twig', [
            'teachers' => $teachers
        ]);
    }

    #[Route('/get-teachers-by-class/{id}', name: 'app_get_teachers_by_class', methods: ['GET'])]
    public function getTeachersByClass(int $id, EntityManagerInterface $em): \Symfony\Component\HttpFoundation\JsonResponse
    {
        // On suppose que SchoolClassSubject relie SchoolClassPeriod à Teacher
        $subjects = $em->getRepository(\App\Entity\SchoolClassSubject::class)->findBy(['schoolClassPeriod' => $id]);
        $teachers = [];
        foreach ($subjects as $subject) {
            $teacher = $subject->getTeacher();
            if ($teacher) {
                $teachers[$teacher->getId()] = [
                    'teacher_id' => $teacher->getId(),
                    'teacher' => $teacher->getFullName(),
                ];
            }
        }

        // Retourne une liste unique
        return $this->json(array_values($teachers));
    }

    // ...existing code...
    #[Route('/show-per-teacher/{id}', name: 'app_timetable_show_per_teacher', methods: ['GET'])]
    public function showPerTeacher(int $id, SessionInterface $session, EntityManagerInterface $em): Response
    {
        $this->session = $session;
        $this->entityManager = $em;
        $this->currentSchool = $this->entityManager->getRepository(\App\Entity\School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(\App\Entity\SchoolPeriod::class)->find($this->session->get('period_id'));
        $teacher = $this->entityManager->getRepository(\App\Entity\User::class)->find($id);
        if (!$teacher) {
            return new Response('<div class="alert alert-danger">Enseignant introuvable.</div>');
        }
        // Récupérer tous les slots de l'enseignant pour l'école et la période en cours
        $slots = $this->entityManager->getRepository(\App\Entity\TimetableSlot::class)->createQueryBuilder('s')
            ->join('s.timetableDay', 'd')
            ->join('d.timetable', 't')
            ->where('t.teacher = :teacher')
            ->andWhere('t.school = :school')
            ->andWhere('t.period = :period')
            ->setParameter('teacher', $teacher)
            ->setParameter('school', $this->currentSchool)
            ->setParameter('period', $this->currentPeriod)
            ->getQuery()->getResult();

        // Regrouper les slots par horaire et jour
        $slotsByTimeAndDay = [];
        $allTimes = [];
        foreach ($slots as $slot) {
            $startTime = $slot->getStartTime() ? $slot->getStartTime()->format('H:i') : '';
            $endTime = $slot->getEndTime() ? $slot->getEndTime()->format('H:i') : '';
            $timeKey = $startTime . ' - ' . $endTime;
            $day = $slot->getTimetableDay() ? $slot->getTimetableDay()->getDayOfWeek() : null;
            $slotArr = [
                'id' => $slot->getId(),
                'startTime' => $startTime,
                'endTime' => $endTime,
                'dayOfWeek' => $day,
                'subject' => $slot->getSubject() ? $slot->getSubject()->getName() : null,
                'className' => $slot->getSchoolClassPeriod() && $slot->getSchoolClassPeriod()->getClassOccurence() ? $slot->getSchoolClassPeriod()->getClassOccurence()->getName() : null,
                'notes' => $slot->getNotes(),
            ];
            if (!isset($slotsByTimeAndDay[$timeKey])) {
                $slotsByTimeAndDay[$timeKey] = [];
            }
            if (!isset($slotsByTimeAndDay[$timeKey][$day])) {
                $slotsByTimeAndDay[$timeKey][$day] = [];
            }
            $slotsByTimeAndDay[$timeKey][$day][] = $slotArr;
            if (!in_array($timeKey, $allTimes, true)) {
                $allTimes[] = $timeKey;
            }
        }
        sort($allTimes);
        return $this->render('timetable/_teacher_timetable.html.twig', [
            'teacher' => $teacher,
            'slotsByTimeAndDay' => $slotsByTimeAndDay,
            'allTimes' => $allTimes,
        ]);
    }

    public function getConnectedUser(): User
    {
        return $this->getUser();
    }

    #[Route('/per-class', name: 'app_timetable_per_class', methods: ['GET'])]
    public function perClass(EntityManagerInterface $entityManager): Response
    {
        $this->entityManager = $entityManager;
        $studyLevels = $this->entityManager->getRepository(\App\Entity\StudyLevel::class)->findAll();
        $user = $this->getConnectedUser();
        if (!in_array('ROLE_SUPER_ADMIN', $user->getRoles())) {
            $config = $user->getBaseConfigurations()->toArray();
            if (count($config) > 0) {
                $studyLevels = count($config[0]->getSectionList()) > 0 ? $this->entityManager->getRepository(\App\Entity\StudyLevel::class)->findBy(['id' => $config[0]->getSectionList()]) : $studyLevels;
            } else {
                $studyLevels = [];
            }
        }
        return $this->render('timetable/per_class.html.twig', [
            'sections' => $studyLevels
        ]);
    }

    #[Route('/class-grid/{id}', name: 'app_timetable_class_grid', methods: ['GET'])]
    public function classGrid(int $id, EntityManagerInterface $em): Response
    {
        // Récupérer la classe (SchoolClassPeriod)
        $classPeriod = $em->getRepository(\App\Entity\SchoolClassPeriod::class)->find($id);
        if (!$classPeriod) {
            return new Response('<div class="alert alert-danger">Classe introuvable.</div>', 404);
        }

        // Récupérer tous les slots pour cette classe, tous enseignants confondus
        $slots = $em->getRepository(\App\Entity\TimetableSlot::class)->createQueryBuilder('s')
            ->join('s.timetableDay', 'd')
            ->join('d.timetable', 't')
            ->where('s.schoolClassPeriod = :classPeriod')
            ->setParameter('classPeriod', $classPeriod)
            ->getQuery()->getResult();

        // Regrouper les slots par horaire et jour
        $slotsByTimeAndDay = [];
        $allTimes = [];
        foreach ($slots as $slot) {
            $startTime = $slot->getStartTime() ? $slot->getStartTime()->format('H:i') : '';
            $endTime = $slot->getEndTime() ? $slot->getEndTime()->format('H:i') : '';
            $timeKey = $startTime . ' - ' . $endTime;
            $day = $slot->getTimetableDay() ? $slot->getTimetableDay()->getDayOfWeek() : null;
            $slotArr = [
                'id' => $slot->getId(),
                'startTime' => $startTime,
                'endTime' => $endTime,
                'dayOfWeek' => $day,
                'subject' => $slot->getSubject() ? $slot->getSubject()->getName() : null,
                'teacher' => $slot->getTimetableDay() && $slot->getTimetableDay()->getTimetable() && $slot->getTimetableDay()->getTimetable()->getTeacher() ? $slot->getTimetableDay()->getTimetable()->getTeacher()->getFullName() : null,
                'notes' => $slot->getNotes(),
            ];
            if (!isset($slotsByTimeAndDay[$timeKey])) {
                $slotsByTimeAndDay[$timeKey] = [];
            }
            if (!isset($slotsByTimeAndDay[$timeKey][$day])) {
                $slotsByTimeAndDay[$timeKey][$day] = [];
            }
            $slotsByTimeAndDay[$timeKey][$day][] = $slotArr;
            if (!in_array($timeKey, $allTimes, true)) {
                $allTimes[] = $timeKey;
            }
        }
        sort($allTimes);

        return $this->render('timetable/_class_timetable.html.twig', [
            'classPeriod' => $classPeriod,
            'slotsByTimeAndDay' => $slotsByTimeAndDay,
            'allTimes' => $allTimes,
        ]);
    }
}
