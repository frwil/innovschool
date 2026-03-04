<?php

namespace App\Controller;

use App\Entity\SchoolClassPeriod;
use App\Entity\SchoolClassAttendance;
use App\Entity\SchoolEvaluation;
use App\Entity\SchoolPeriod;
use App\Entity\StudentAttendance;
use App\Form\SchoolAttendanceType;
use App\Form\SchoolClassSearchType;
use App\Form\StudentAttendanceType;
use App\Repository\StudentAttendanceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Entity\School;
use App\Service\OperationLogger;
use Doctrine\Persistence\ManagerRegistry;

#[Route('/student/attendance')]
final class StudentAttendanceController extends AbstractController
{
    private School $currentSchool;
    private SchoolPeriod $currentPeriod;
    private EntityManagerInterface $entityManager;
    private SessionInterface $session;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route(name: 'app_student_attendance_index', methods: ['GET', 'POST'])]
    public function index(
        StudentAttendanceRepository $studentAttendanceRepository,
        Request $request,
        EntityManagerInterface $entityManager,
        SessionInterface $session
    ): Response {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));

        /** @var \App\Entity\User */
        $currentUser = $this->getUser();
        $school = $this->currentSchool;

        $form = $this->createForm(SchoolClassSearchType::class, null, [
            'action' => $this->generateUrl('app_student_attendance_index'),
            'hide_classe' => true,
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $evaluation = $data['evaluation'];
            $section = $data['schoolSection'];

            $period = $this->currentPeriod;
            $classes = $entityManager->getRepository(SchoolClassPeriod::class)->findBy([
                'period' => $period,
                'school' => $school,
            ]);

            return $this->render('student_attendance/index.html.twig', [
                // 'student_attendances' => $studentAttendanceRepository->findAll(),
                'form' => $form->createView(),
                'classes' => $classes,
                'evaluation' => $evaluation,
                'section' => $section,
                'period' => $period,
            ]);
        }


        return $this->render('student_attendance/index.html.twig', [
            // 'student_attendances' => $studentAttendanceRepository->findAll(),
            'form' => $form->createView(),
        ]);
    }

    #[Route('/insert-attendance/{scid}/{evaluationId}', name: 'app_student_attendance_insert_abscences', methods: ['GET', 'POST'])]
    public function insertAbscences(
        int $scid,
        int $evaluationId,
        Request $request,
        EntityManagerInterface $entityManager,
        OperationLogger $operationLogger,
        ManagerRegistry $doctrine
    ): Response {
        /** @var \App\Entity\User */
        $currentUser = $this->getUser();
        $school = $this->currentSchool;

        /** @var \App\Entity\SchoolClassPeriod */
        $schoolClassPeriod = $entityManager->getRepository(SchoolClassPeriod::class)->find($scid);
        $evaluation = $entityManager->getRepository(SchoolEvaluation::class)->find($evaluationId);
        $students = $schoolClassPeriod->getStudents();

        /**
         * Vérifier si un enregistrement SchoolClassAttendance existe déjà
         * @var SchoolClassAttendance
         */
        $schoolClassAttendance = $entityManager->getRepository(SchoolClassAttendance::class)
            ->findOneBy(['evaluation' => $evaluation, 'schoolClassPeriod' => $schoolClassPeriod]);

        $attendanceJson = $schoolClassAttendance ? $schoolClassAttendance->getAttendancJson() : [];

        $studentAttendances = [];
        foreach ($students as $student) {
            $studentId = $student->getId();
            $abscence = isset($attendanceJson[$studentId]) ? $attendanceJson[$studentId] : 0;
            $studentAttendance = (new StudentAttendance())
                ->setStudent($student)
                ->setAbscence($abscence);
            $studentAttendances[] = $studentAttendance;
        }


        $form = $this->createForm(SchoolAttendanceType::class, null, [
            'studentAttendances' => $studentAttendances,
            'action' => $this->generateUrl('app_student_attendance_insert_abscences', [
                'scid' => $scid,
                'evaluationId' => $evaluationId,
            ]),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \App\Entity\StudentAttendance */
            $studentAttendances = $form->get('studentAttendances')->getData();
            $attendanceJson = [];
            foreach ($studentAttendances as $item) {
                $attendanceJson[$item->getStudent()->getId()] = $item->getAbscence();
            }
            if ($schoolClassAttendance) {
                $schoolClassAttendance->setAttendancJson($attendanceJson);
            } else {
                $schoolClassAttendance = (new SchoolClassAttendance())
                    ->setSchoolClass($schoolClassPeriod)
                    ->setEvaluation($evaluation)
                    ->setAttendancJson($attendanceJson);
                $entityManager->persist($schoolClassAttendance);
            }

            try {
                $entityManager->flush();
                // Log the operation
                $operationLogger->log(
                    'ENREGISTREMENT DES NOTES',
                    'SUCCESS',
                    'StudentAttendance',
                    null,
                    null,
                    ['class' => $schoolClassPeriod->getId(), 'evaluation' => $evaluation->getId(), 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()]
                );
                // Flash message
                $this->addFlash('success', 'Notes enregistrées');
            } catch (\Exception $e) {
                if (!$this->entityManager->isOpen()) {
                    $this->entityManager = $doctrine->resetManager();
                }
                // Log the error
                $operationLogger->log(
                    'ÉCHEC D\'ENREGISTREMENT DES NOTES',
                    'ERROR',
                    'StudentAttendance',
                    null,
                    $e->getMessage(),
                    ['class' => $schoolClassPeriod->getId(), 'evaluation' => $evaluation->getName(), 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()]
                );
                $this->addFlash('error', 'Une erreur est survenue lors de l\'enregistrement des notes : ' . $e->getMessage());
            }
        }


        return $this->render('student_attendance/insert.attendance.html.twig', [
            'form' => $form->createView(),
            'school_class' => $schoolClassPeriod,
            'evaluation' => $evaluation,
        ]);
    }

    #[Route('/new', name: 'app_student_attendance_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $studentAttendance = new StudentAttendance();
        $form = $this->createForm(StudentAttendanceType::class, $studentAttendance);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($studentAttendance);
            try {
                $entityManager->flush();


                return $this->redirectToRoute('app_student_attendance_index', [], Response::HTTP_SEE_OTHER);
            } catch (\Exception $e) {
            }
        }

        return $this->render('student_attendance/new.html.twig', [
            'student_attendance' => $studentAttendance,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_student_attendance_show', methods: ['GET'])]
    public function show(StudentAttendance $studentAttendance): Response
    {
        return $this->render('student_attendance/show.html.twig', [
            'student_attendance' => $studentAttendance,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_student_attendance_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, StudentAttendance $studentAttendance, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(StudentAttendanceType::class, $studentAttendance);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_student_attendance_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('student_attendance/edit.html.twig', [
            'student_attendance' => $studentAttendance,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_student_attendance_delete', methods: ['POST'])]
    public function delete(Request $request, StudentAttendance $studentAttendance, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $studentAttendance->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($studentAttendance);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_student_attendance_index', [], Response::HTTP_SEE_OTHER);
    }
}
