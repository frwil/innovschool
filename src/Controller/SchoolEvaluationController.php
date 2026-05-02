<<<<<<< HEAD
<?php

namespace App\Controller;

use App\Entity\School;
use App\Entity\SchoolClassPeriod;
use App\Entity\SchoolClassGrade;
use App\Entity\SchoolClassSubject;
use App\Entity\SchoolEvaluation;
use App\Entity\SchoolEvaluationFrame;
use App\Entity\SchoolPeriod;
use App\Entity\SectionCategorySubject;
use App\Entity\StudentClass;
use App\Entity\StudentNote;
use App\Entity\SubjectGrade;
use App\Entity\User;
use App\Form\ReportCardSearchType;
use App\Form\SchoolClassSearchType;
use App\Form\SchoolEvaluationType;
use App\Form\SchoolNotesType;
use App\Form\SchoolNoteType;
use App\Form\SchoolPrimaryNoteType;
use App\Form\SearchSubjectType;
use App\Form\UploadGradeCSVType;
use App\Repository\SchoolEvaluationRepository;
use App\Service\EvaluationNoteDTO;
use App\Service\GradeCSVGenerator;
use App\Service\PrimaryFrameReportCard;
use App\Service\ReportCardService;
use App\Service\SecondaryFrameReportCard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

#[Route('/school-evaluation')]
final class SchoolEvaluationController extends AbstractController
{
    private School $currentSchool;
    private SchoolPeriod $currentPeriod;
    private EntityManagerInterface $entityManager;
    private SessionInterface $session;
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route(name: 'app_school_evaluation_index', methods: ['GET'])]
    public function index(SchoolEvaluationRepository $schoolEvaluationRepository, Request $request): Response
    {
        $schoolEvaluation = new SchoolEvaluation();
        $form = $this->createForm(SchoolEvaluationType::class, $schoolEvaluation, [
            'action' => $this->generateUrl('app_school_evaluation_new'),
        ]);

        return $this->render('school_evaluation/index.html.twig', [
            'school_evaluations' => $schoolEvaluationRepository->findAll(),
            'form' => $form->createView(),
        ]);
    }

    #[Route('/insert-notes-init', name: 'app_school_evaluation_insert_notes_init', methods: ['GET', 'POST'])]
    public function insertNotesInit(SessionInterface $session,EntityManagerInterface $entityManager, Request $request): Response
    {
        
        $this->session=$session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        /** @var \App\Entity\User */
        $currentUser = $this->getUser();
        $school = $this->currentSchool;
        $form = $this->createForm(SchoolClassSearchType::class, null, [
            'action' => $this->generateUrl('app_school_evaluation_insert_notes_init'),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $evaluation = $data['evaluation'];
            $schoolClassPeriod = $data['schoolClassPeriod'];
            $period = $this->currentPeriod;
            $subjects = $entityManager->getRepository(SchoolClassSubject::class)->findBy([
                'schoolClassPeriod' => $schoolClassPeriod,
                'period' => $period,
            ]);


            return $this->render('school_evaluation/insert.notes.html.twig', [
                'form' => $form->createView(),
                'evaluation' => $evaluation,
                'subjects' => $subjects,
                'school_class' => $schoolClassPeriod,
            ]);
        }

        return $this->render('school_evaluation/insert.notes.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/insert-notes/{sid}/{eid}/{sclid}', name: 'app_school_evaluation_insert_notes', methods: ['GET', 'POST'])]
    public function insertNotes(
        int $sid,
        int $eid,
        int $sclid,
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        /** @var \App\Entity\User */
        $currentUser = $this->getUser();
        $school = $this->currentSchool;

        /** @var \App\Entity\SchoolClassPeriod */
        $schoolClassPeriod = $entityManager->getRepository(SchoolClassPeriod::class)->find($sclid);
        $evaluation = $entityManager->getRepository(SchoolEvaluation::class)->find($eid);
        $subject = $entityManager->getRepository(SchoolClassSubject::class)->find($sid);

        if ($subject->getSchoolClass()->getSectionCategory()->getSection()->getSlug() == $slugger->slug("Primaire")) {
            return $this->redirectToRoute("app_school_evaluation_insert_notes_primary", [
                'sid' => $sid,
                'eid' => $eid,
                'sclid' => $sclid,
            ]);
        }

        $students = $schoolClassPeriod->getStudents();

        /**
         * Vérifier si un enregistrement SchoolClassGrade existe déjà
         */
        $gradeRepository = $entityManager->getRepository(SchoolClassGrade::class);
        $existingGrade = $gradeRepository->findOneBy([
            'evaluation' => $evaluation,
            'subject' => $subject,
            'schoolClassPeriod' => $schoolClassPeriod,
        ]);

        // Si un enregistrement existe, récupérer les notes existantes, sinon, créer un tableau vide
        $notesJson = $existingGrade ? $existingGrade->getNotesJson() : [];

        // Préparation des notes pour le formulaire
        $studentNotes = [];
        foreach ($students as $student) {
            $studentId = $student->getId();
            $noteValue = $notesJson[$studentId] ?? 0; // Récupère la note existante ou 0 par défaut

            $note = (new StudentNote())
                ->setStudent($student)
                ->setNote($noteValue);

            $studentNotes[] = $note;
        }

        // Création du formulaire
        $form = $this->createForm(SchoolNoteType::class, null, [
            'studentNotes' => $studentNotes,
            'action' => $this->generateUrl('app_school_evaluation_insert_notes', [
                'sid' => $sid,
                'eid' => $eid,
                'sclid' => $sclid,
            ]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var StudentNote[] $submittedNotes */
            $submittedNotes = $form->get('studentNotes')->getData();

            // Mise à jour des notes dans le tableau JSON
            $notesJson = [];
            foreach ($submittedNotes as $note) {
                $notesJson[$note->getStudent()->getId()] = $note->getNote();
            }

            if ($existingGrade) {
                // Mise à jour de l'enregistrement existant
                $existingGrade->setNotesJson($notesJson);
            } else {
                // Création d'un nouvel enregistrement
                $grade = (new SchoolClassGrade())
                    ->setEvaluation($evaluation)
                    ->setSubject($subject)
                    ->setSchoolClass($schoolClassPeriod)
                    ->setNotesJson($notesJson);

                $entityManager->persist($grade);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Notes enregistrées');
        }

        return $this->render('school_evaluation/insert.notes.form.html.twig', [
            'form' => $form->createView(),
            'evaluation' => $evaluation,
            'subject' => $subject,
            'school_class' => $schoolClassPeriod,
        ]);
    }

    #[Route('/insert-notes-primary/{sid}/{eid}/{sclid}', name: 'app_school_evaluation_insert_notes_primary', methods: ['GET', 'POST'])]
    public function insertNotesPrimary(int $sid, int $eid, int $sclid, Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var \App\Entity\User */
        $currentUser = $this->getUser();
        $school = $this->currentSchool;

        /** @var \App\Entity\SchoolClassPeriod */
        $schoolClassPeriod = $entityManager->getRepository(SchoolClassPeriod::class)->find($sclid);
        $evaluation = $entityManager->getRepository(SchoolEvaluation::class)->find($eid);
        /** @var \App\Entity\SchoolClassSubject */
        $subject = $entityManager->getRepository(SchoolClassSubject::class)->find($sid);
        $students = $schoolClassPeriod->getStudents();

        /**
         * Vérifier si un enregistrement SchoolClassGrade existe déjà
         */
        $gradeRepository = $entityManager->getRepository(SchoolClassGrade::class);
        $existingGrade = $gradeRepository->findOneBy([
            'evaluation' => $evaluation,
            'subject' => $subject,
            'schoolClassPeriod' => $schoolClassPeriod,
        ]);

        // Si un enregistrement existe, récupérer les notes existantes, sinon, créer un tableau vide
        $notesJson = $existingGrade ? $existingGrade->getNotesJson() : [];

        // Préparation des notes pour le formulaire
        $studentNotes = [];
        foreach ($students as $student) {
            $studentId = $student->getId();
            $kh = 0;
            $or = 0;
            $w = 0;
            $p = 0;
            if (isset($notesJson[$studentId])) {
                $kh = $notesJson[$studentId]['pointKnowHomw'];
                $or = $notesJson[$studentId]['pointOral'];
                $w = $notesJson[$studentId]['pointWritten'];
                $p = $notesJson[$studentId]['pointPractical'];
            }

            $note = [
                'student' => $student,
                'pointKnowHomw' => $kh,
                'pointOral' => $or,
                'pointWritten' => $w,
                'pointPractical' => $p,
            ];
            $studentNotes[] = $note;
        }

        // Création du formulaire
        $form = $this->createForm(SchoolPrimaryNoteType::class, null, [
            'studentNotes' => $studentNotes,
            'action' => $this->generateUrl('app_school_evaluation_insert_notes_primary', [
                'sid' => $sid,
                'eid' => $eid,
                'sclid' => $sclid,
            ]),
            'subject' => $subject,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var StudentNote[] $submittedNotes */
            $submittedNotes = $form->get('studentNotes')->getData();

            // Mise à jour des notes dans le tableau JSON
            $notesJson = [];
            foreach ($submittedNotes as $note) {
                $notesJson[$note['student']->getId()] = [
                    'pointKnowHomw' => $note['pointKnowHomw'],
                    'pointOral' => $note['pointOral'],
                    'pointWritten' => $note['pointWritten'],
                    'pointPractical' => $note['pointPractical'],
                    'note' => $note['pointKnowHomw'] + $note['pointOral'] + $note['pointWritten'] + $note['pointPractical'],
                ];
            }

            if ($existingGrade) {
                // Mise à jour de l'enregistrement existant
                $existingGrade->setNotesJson($notesJson);
            } else {
                // Création d'un nouvel enregistrement
                $grade = (new SchoolClassGrade())
                    ->setEvaluation($evaluation)
                    ->setSubject($subject)
                    ->setSchoolClass($schoolClassPeriod)
                    ->setNotesJson($notesJson);

                $entityManager->persist($grade);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Notes enregistrées');
        }

        return $this->render('school_evaluation/insert.notes.primary.form.html.twig', [
            'form' => $form->createView(),
            'evaluation' => $evaluation,
            'subject' => $subject,
            'school_class' => $schoolClassPeriod,
        ]);
    }

    #[Route('/new', name: 'app_school_evaluation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $schoolEvaluation = new SchoolEvaluation();
        $form = $this->createForm(SchoolEvaluationType::class, $schoolEvaluation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($schoolEvaluation);
            $entityManager->flush();

            return $this->redirectToRoute('app_school_evaluation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('school_evaluation/new.html.twig', [
            'school_evaluation' => $schoolEvaluation,
            'form' => $form,
        ]);
    }

    #[Route('/report-card', name: 'app_school_evaluation_report_card', methods: ['GET', 'POST'])]
    public function reportCard(SessionInterface $session,Request $request, EntityManagerInterface $entityManager): Response
    {
        
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        /** @var \App\Entity\User */
        $currentUser = $this->getUser();
        $school = $this->currentSchool;

        $form = $this->createForm(SchoolClassSearchType::class, null, [
            'action' => $this->generateUrl('app_school_evaluation_report_card'),

        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            /** @var SubmitButton $submitButton */
            $submitButton = $form->get('submit');
            if (!$submitButton->isClicked()) {

                return $this->render('school_evaluation/report.card.html.twig', [
                    'form' => $form->createView(),
                ]);
            }


            $data = $form->getData();
            $evaluation = $data['evaluation'];
            $section = $data['schoolSection'];
            $schoolClassPeriod = $data['schoolClassPeriod'];
            $period = $this->currentPeriod;


            return $this->render('school_evaluation/report.card.html.twig', [
                'students' => $schoolClassPeriod->getStudents(),
                'form' => $form->createView(),
                'evaluation' => $evaluation,
                'section' => $section,
                'period' => $period,
                'schoolClassPeriod' => $schoolClassPeriod,
            ]);
        }

        return $this->render('school_evaluation/report.card.html.twig', [

            'form' => $form->createView(),
        ]);
    }

    /**
     * bulletin trimestriel
     */
    #[Route('/report-card/frame', name: 'app_school_evaluation_report_card_frame', methods: ['GET', 'POST'])]
    public function reportCardFrame(SessionInterface $session,Request $request, EntityManagerInterface $entityManager): Response
    {
        
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        /** @var \App\Entity\User */
        $currentUser = $this->getUser();
        $school = $this->currentSchool;

        $form = $this->createForm(ReportCardSearchType::class, null, [
            'action' => $this->generateUrl('app_school_evaluation_report_card_frame'),

        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            /** @var SubmitButton $submitButton */
            $submitButton = $form->get('submit');
            if (!$submitButton->isClicked()) {

                return $this->render('school_evaluation/report.card.frame.html.twig', [
                    'form' => $form->createView(),
                ]);
            }


            $data = $form->getData();
            $evaluation = $data['evaluation'];
            $section = $data['schoolSection'];
            $schoolClassPeriod = $data['schoolClassPeriod'];
            $period = $this->currentPeriod;


            return $this->render('school_evaluation/report.card.frame.html.twig', [
                'students' => $schoolClassPeriod->getStudents(),
                'form' => $form->createView(),
                'evaluation' => $evaluation,
                'section' => $section,
                'period' => $period,
                'schoolClassPeriod' => $schoolClassPeriod,
            ]);
        }

        return $this->render('school_evaluation/report.card.frame.html.twig', [

            'form' => $form->createView(),
        ]);
    }

    #[Route('/download-report-card/{studentId}/{evaluationId}/{classId}', name: 'app_school_evaluation_download_report_card', methods: ['GET'])]
    public function downloadReportCard(
        int $studentId,
        int $evaluationId,
        int $classId,
        EntityManagerInterface $entityManager,
        ReportCardService $reportCardService,
    ): Response {

        $student = $entityManager->getRepository(User::class)->find($studentId);
        $evaluation = $entityManager->getRepository(SchoolEvaluation::class)->find($evaluationId);
        $schoolClassPeriod = $entityManager->getRepository(SchoolClassPeriod::class)->find($classId);

        $reportCardService->init($student, $evaluation, $schoolClassPeriod);

        return $this->render('documents/report.card.html.twig', [
            'model' => $reportCardService,
        ]);
    }

    #[Route('/download-report-card-primary-frame/{studentId}/{evaluationId}/{classId}', name: 'app_school_evaluation_download_report_card_frame', methods: ['GET'])]
    public function downloadReportCardPrimaryFrame(
        int $studentId,
        int $evaluationId,
        int $classId,
        EntityManagerInterface $entityManager,
        PrimaryFrameReportCard $reportCardService,
        SluggerInterface $slugger,
    ): Response {

        $student = $entityManager->getRepository(User::class)->find($studentId);
        $evaluation = $entityManager->getRepository(SchoolEvaluationFrame::class)->find($evaluationId);
        $schoolClassPeriod = $entityManager->getRepository(SchoolClassPeriod::class)->find($classId);

        if ($schoolClassPeriod->getSectionCategory()->getSection()->getSlug() == $slugger->slug("Secondaire général")) {
            return $this->redirectToRoute("app_school_evaluation_download_report_card_secondary_frame", [
                'studentId' => $studentId,
                'evaluationId' => $evaluationId,
                'classId' => $classId,
            ]);
        }

        $reportCardService->init($student, $evaluation, $schoolClassPeriod);


        return $this->render('documents/report.card.primary.frame.html.twig', [
            'model' => $reportCardService,
        ]);
    }

    #[Route('/download-report-card-frame/{studentId}/{evaluationId}/{classId}', name: 'app_school_evaluation_download_report_card_secondary_frame', methods: ['GET'])]
    public function downloadReportCardFrame(
        int $studentId,
        int $evaluationId,
        int $classId,
        EntityManagerInterface $entityManager,
        SecondaryFrameReportCard $reportCardService,
    ): Response {

        $student = $entityManager->getRepository(User::class)->find($studentId);
        $evaluation = $entityManager->getRepository(SchoolEvaluationFrame::class)->find($evaluationId);
        $schoolClassPeriod = $entityManager->getRepository(SchoolClassPeriod::class)->find($classId);

        $reportCardService->init($student, $evaluation, $schoolClassPeriod);

        return $this->render('documents/report.card.secondary.frame.html.twig', [
            'model' => $reportCardService,
        ]);
    }


    #[Route('/{id}', name: 'app_school_evaluation_show', methods: ['GET'])]
    public function show(SchoolEvaluation $schoolEvaluation, EntityManagerInterface $entityManager): Response
    {
        /** @var \App\Entity\User */
        $user = $this->getUser();
        /** @var \App\Entity\SchoolClassPeriod[] */
        $classes = $entityManager->getRepository(SchoolClassPeriod::class)->findBy(['school' => $this->currentSchool]);

        $subjects = [];
        foreach ($classes as $key => $class) {
            $subjects[] = new EvaluationNoteDTO($class->getSectionCategory()->getSectionCategorySubjects()->toArray(), $class);
        }

        return $this->render('school_evaluation/show.html.twig', [
            'school_evaluation' => $schoolEvaluation,
            'subjects' => $subjects,
        ]);
    }

    #[Route('/{evalid}/{subid}/{scoid}/download-csv', name: 'app_school_evaluation_download_grades_csv', methods: ['GET'])]
    public function downloadCSV(
        int $evalid,
        int $subid,
        int $scoid,
        EntityManagerInterface $entityManager,
        GradeCSVGenerator $gradeCSVGenerator,
        SessionInterface $session
    ): Response {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        
        /** @var \App\Entity\User */
        $user = $this->getUser();
        /** @var \App\Entity\SchoolClassPeriod */
        $sclass = $entityManager->getRepository(SchoolClassPeriod::class)->find($scoid);
        /** @var \App\Entity\SchoolEvaluation */
        $evaluation = $entityManager->getRepository(SchoolEvaluation::class)->find($evalid);
        /** @var \App\Entity\SectionCategorySubject */
        $subject = $entityManager->getRepository(SectionCategorySubject::class)->find($subid);
        /** @var \App\Entity\SchoolPeriod */
        $period = $this->currentPeriod;
        /** @var SubjectGrade */
        $subjectGrade = $entityManager->getRepository(SubjectGrade::class)->findOneBy([
            'evaluation' => $evaluation,
            'subject' => $subject,
            'sClass' => $sclass,
            'period' => $period,
        ]);

        $studentClass = $entityManager->getRepository(StudentClass::class)->findBy([
            'schoolClassPeriod' => $sclass,
            'period' => $period,
        ]);
        $students = [];
        foreach ($studentClass as $key => $student) {
            $students[] = $student->getStudent();
        }

        if (null === $subjectGrade) {
            $subjectGrade = (new SubjectGrade())
                ->setEvaluation($evaluation)
                ->setSubject($subject)
                ->setSClass($sclass)
                ->setPeriod($period);
            $entityManager->persist($subjectGrade);
            $entityManager->flush();
        }

        $csvContent = $gradeCSVGenerator->generateCSV($subjectGrade->getId(), $students);

        $response = new Response($csvContent);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="grades_' . $subject->getName() . '_' . $evaluation->__toString() . '.csv"');

        return $response;
    }

    #[Route('/upload-grade/upload', name: 'app_school_evaluation_upload_grade', methods: ['GET', 'POST'])]
    public function uploadGrades(Request $request, GradeCSVGenerator $gradeCSVGenerator, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(UploadGradeCSVType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('csv_file')->getData();

            if ($file) {
                $data = file_get_contents($file);
                $rows = array_map('str_getcsv', explode("\n", $data));
                $header = array_shift($rows);
                $studentNotes = [];

                foreach ($rows as $row) {
                    if (count($row) >= 4) {
                        $ref = $row[0];
                        $uid = $row[1];
                        $name = $row[2];
                        $note = $row[3];

                        $subjectGrade = $entityManager->getRepository(SubjectGrade::class)
                            ->find($ref);
                        $student = $entityManager(User::class)->find($uid);

                        if (null === $subjectGrade || null === $student) {
                            $this->addFlash(
                                'error',
                                'Fichier invalide'
                            );
                            return $this->redirectToRoute('app_school_evaluation_upload_grade');
                        }


                        if ($student) {
                            // Persister la note ou d'autres informations nécessaires
                            $student->setGrade($note);
                            $entityManager->persist($student);
                        }
                    }
                }

                $entityManager->flush();
            }

            return $this->redirectToRoute('app_school_evaluation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('school_evaluation/upload_grades.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_school_evaluation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, SchoolEvaluation $schoolEvaluation, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SchoolEvaluationType::class, $schoolEvaluation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_school_evaluation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('school_evaluation/edit.html.twig', [
            'school_evaluation' => $schoolEvaluation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_school_evaluation_delete', methods: ['POST'])]
    public function delete(Request $request, SchoolEvaluation $schoolEvaluation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $schoolEvaluation->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($schoolEvaluation);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_school_evaluation_index', [], Response::HTTP_SEE_OTHER);
    }
}
=======
<?php

namespace App\Controller;

use App\Entity\School;
use App\Entity\SchoolClassPeriod;
use App\Entity\SchoolClassGrade;
use App\Entity\SchoolClassSubject;
use App\Entity\SchoolEvaluation;
use App\Entity\SchoolEvaluationFrame;
use App\Entity\SchoolPeriod;
use App\Entity\SectionCategorySubject;
use App\Entity\StudentClass;
use App\Entity\StudentNote;
use App\Entity\SubjectGrade;
use App\Entity\User;
use App\Form\ReportCardSearchType;
use App\Form\SchoolClassSearchType;
use App\Form\SchoolEvaluationType;
use App\Form\SchoolNotesType;
use App\Form\SchoolNoteType;
use App\Form\SchoolPrimaryNoteType;
use App\Form\SearchSubjectType;
use App\Form\UploadGradeCSVType;
use App\Repository\SchoolEvaluationRepository;
use App\Service\EvaluationNoteDTO;
use App\Service\GradeCSVGenerator;
use App\Service\PrimaryFrameReportCard;
use App\Service\ReportCardService;
use App\Service\SecondaryFrameReportCard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

#[Route('/school-evaluation')]
final class SchoolEvaluationController extends AbstractController
{
    private School $currentSchool;
    private SchoolPeriod $currentPeriod;
    private EntityManagerInterface $entityManager;
    private SessionInterface $session;
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route(name: 'app_school_evaluation_index', methods: ['GET'])]
    public function index(SchoolEvaluationRepository $schoolEvaluationRepository, Request $request): Response
    {
        $schoolEvaluation = new SchoolEvaluation();
        $form = $this->createForm(SchoolEvaluationType::class, $schoolEvaluation, [
            'action' => $this->generateUrl('app_school_evaluation_new'),
        ]);

        return $this->render('school_evaluation/index.html.twig', [
            'school_evaluations' => $schoolEvaluationRepository->findAll(),
            'form' => $form->createView(),
        ]);
    }

    #[Route('/insert-notes-init', name: 'app_school_evaluation_insert_notes_init', methods: ['GET', 'POST'])]
    public function insertNotesInit(SessionInterface $session,EntityManagerInterface $entityManager, Request $request): Response
    {
        
        $this->session=$session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        /** @var \App\Entity\User */
        $currentUser = $this->getUser();
        $school = $this->currentSchool;
        $form = $this->createForm(SchoolClassSearchType::class, null, [
            'action' => $this->generateUrl('app_school_evaluation_insert_notes_init'),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $evaluation = $data['evaluation'];
            $schoolClassPeriod = $data['schoolClassPeriod'];
            $period = $this->currentPeriod;
            $subjects = $entityManager->getRepository(SchoolClassSubject::class)->findBy([
                'schoolClassPeriod' => $schoolClassPeriod,
                'period' => $period,
            ]);


            return $this->render('school_evaluation/insert.notes.html.twig', [
                'form' => $form->createView(),
                'evaluation' => $evaluation,
                'subjects' => $subjects,
                'school_class' => $schoolClassPeriod,
            ]);
        }

        return $this->render('school_evaluation/insert.notes.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/insert-notes/{sid}/{eid}/{sclid}', name: 'app_school_evaluation_insert_notes', methods: ['GET', 'POST'])]
    public function insertNotes(
        int $sid,
        int $eid,
        int $sclid,
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        /** @var \App\Entity\User */
        $currentUser = $this->getUser();
        $school = $this->currentSchool;

        /** @var \App\Entity\SchoolClassPeriod */
        $schoolClassPeriod = $entityManager->getRepository(SchoolClassPeriod::class)->find($sclid);
        $evaluation = $entityManager->getRepository(SchoolEvaluation::class)->find($eid);
        $subject = $entityManager->getRepository(SchoolClassSubject::class)->find($sid);

        if ($subject->getSchoolClass()->getSectionCategory()->getSection()->getSlug() == $slugger->slug("Primaire")) {
            return $this->redirectToRoute("app_school_evaluation_insert_notes_primary", [
                'sid' => $sid,
                'eid' => $eid,
                'sclid' => $sclid,
            ]);
        }

        $students = $schoolClassPeriod->getStudents();

        /**
         * Vérifier si un enregistrement SchoolClassGrade existe déjà
         */
        $gradeRepository = $entityManager->getRepository(SchoolClassGrade::class);
        $existingGrade = $gradeRepository->findOneBy([
            'evaluation' => $evaluation,
            'subject' => $subject,
            'schoolClassPeriod' => $schoolClassPeriod,
        ]);

        // Si un enregistrement existe, récupérer les notes existantes, sinon, créer un tableau vide
        $notesJson = $existingGrade ? $existingGrade->getNotesJson() : [];

        // Préparation des notes pour le formulaire
        $studentNotes = [];
        foreach ($students as $student) {
            $studentId = $student->getId();
            $noteValue = $notesJson[$studentId] ?? 0; // Récupère la note existante ou 0 par défaut

            $note = (new StudentNote())
                ->setStudent($student)
                ->setNote($noteValue);

            $studentNotes[] = $note;
        }

        // Création du formulaire
        $form = $this->createForm(SchoolNoteType::class, null, [
            'studentNotes' => $studentNotes,
            'action' => $this->generateUrl('app_school_evaluation_insert_notes', [
                'sid' => $sid,
                'eid' => $eid,
                'sclid' => $sclid,
            ]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var StudentNote[] $submittedNotes */
            $submittedNotes = $form->get('studentNotes')->getData();

            // Mise à jour des notes dans le tableau JSON
            $notesJson = [];
            foreach ($submittedNotes as $note) {
                $notesJson[$note->getStudent()->getId()] = $note->getNote();
            }

            if ($existingGrade) {
                // Mise à jour de l'enregistrement existant
                $existingGrade->setNotesJson($notesJson);
            } else {
                // Création d'un nouvel enregistrement
                $grade = (new SchoolClassGrade())
                    ->setEvaluation($evaluation)
                    ->setSubject($subject)
                    ->setSchoolClass($schoolClassPeriod)
                    ->setNotesJson($notesJson);

                $entityManager->persist($grade);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Notes enregistrées');
        }

        return $this->render('school_evaluation/insert.notes.form.html.twig', [
            'form' => $form->createView(),
            'evaluation' => $evaluation,
            'subject' => $subject,
            'school_class' => $schoolClassPeriod,
        ]);
    }

    #[Route('/insert-notes-primary/{sid}/{eid}/{sclid}', name: 'app_school_evaluation_insert_notes_primary', methods: ['GET', 'POST'])]
    public function insertNotesPrimary(int $sid, int $eid, int $sclid, Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var \App\Entity\User */
        $currentUser = $this->getUser();
        $school = $this->currentSchool;

        /** @var \App\Entity\SchoolClassPeriod */
        $schoolClassPeriod = $entityManager->getRepository(SchoolClassPeriod::class)->find($sclid);
        $evaluation = $entityManager->getRepository(SchoolEvaluation::class)->find($eid);
        /** @var \App\Entity\SchoolClassSubject */
        $subject = $entityManager->getRepository(SchoolClassSubject::class)->find($sid);
        $students = $schoolClassPeriod->getStudents();

        /**
         * Vérifier si un enregistrement SchoolClassGrade existe déjà
         */
        $gradeRepository = $entityManager->getRepository(SchoolClassGrade::class);
        $existingGrade = $gradeRepository->findOneBy([
            'evaluation' => $evaluation,
            'subject' => $subject,
            'schoolClassPeriod' => $schoolClassPeriod,
        ]);

        // Si un enregistrement existe, récupérer les notes existantes, sinon, créer un tableau vide
        $notesJson = $existingGrade ? $existingGrade->getNotesJson() : [];

        // Préparation des notes pour le formulaire
        $studentNotes = [];
        foreach ($students as $student) {
            $studentId = $student->getId();
            $kh = 0;
            $or = 0;
            $w = 0;
            $p = 0;
            if (isset($notesJson[$studentId])) {
                $kh = $notesJson[$studentId]['pointKnowHomw'];
                $or = $notesJson[$studentId]['pointOral'];
                $w = $notesJson[$studentId]['pointWritten'];
                $p = $notesJson[$studentId]['pointPractical'];
            }

            $note = [
                'student' => $student,
                'pointKnowHomw' => $kh,
                'pointOral' => $or,
                'pointWritten' => $w,
                'pointPractical' => $p,
            ];
            $studentNotes[] = $note;
        }

        // Création du formulaire
        $form = $this->createForm(SchoolPrimaryNoteType::class, null, [
            'studentNotes' => $studentNotes,
            'action' => $this->generateUrl('app_school_evaluation_insert_notes_primary', [
                'sid' => $sid,
                'eid' => $eid,
                'sclid' => $sclid,
            ]),
            'subject' => $subject,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var StudentNote[] $submittedNotes */
            $submittedNotes = $form->get('studentNotes')->getData();

            // Mise à jour des notes dans le tableau JSON
            $notesJson = [];
            foreach ($submittedNotes as $note) {
                $notesJson[$note['student']->getId()] = [
                    'pointKnowHomw' => $note['pointKnowHomw'],
                    'pointOral' => $note['pointOral'],
                    'pointWritten' => $note['pointWritten'],
                    'pointPractical' => $note['pointPractical'],
                    'note' => $note['pointKnowHomw'] + $note['pointOral'] + $note['pointWritten'] + $note['pointPractical'],
                ];
            }

            if ($existingGrade) {
                // Mise à jour de l'enregistrement existant
                $existingGrade->setNotesJson($notesJson);
            } else {
                // Création d'un nouvel enregistrement
                $grade = (new SchoolClassGrade())
                    ->setEvaluation($evaluation)
                    ->setSubject($subject)
                    ->setSchoolClass($schoolClassPeriod)
                    ->setNotesJson($notesJson);

                $entityManager->persist($grade);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Notes enregistrées');
        }

        return $this->render('school_evaluation/insert.notes.primary.form.html.twig', [
            'form' => $form->createView(),
            'evaluation' => $evaluation,
            'subject' => $subject,
            'school_class' => $schoolClassPeriod,
        ]);
    }

    #[Route('/new', name: 'app_school_evaluation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $schoolEvaluation = new SchoolEvaluation();
        $form = $this->createForm(SchoolEvaluationType::class, $schoolEvaluation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($schoolEvaluation);
            $entityManager->flush();

            return $this->redirectToRoute('app_school_evaluation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('school_evaluation/new.html.twig', [
            'school_evaluation' => $schoolEvaluation,
            'form' => $form,
        ]);
    }

    #[Route('/report-card', name: 'app_school_evaluation_report_card', methods: ['GET', 'POST'])]
    public function reportCard(SessionInterface $session,Request $request, EntityManagerInterface $entityManager): Response
    {
        
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        /** @var \App\Entity\User */
        $currentUser = $this->getUser();
        $school = $this->currentSchool;

        $form = $this->createForm(SchoolClassSearchType::class, null, [
            'action' => $this->generateUrl('app_school_evaluation_report_card'),

        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            /** @var SubmitButton $submitButton */
            $submitButton = $form->get('submit');
            if (!$submitButton->isClicked()) {

                return $this->render('school_evaluation/report.card.html.twig', [
                    'form' => $form->createView(),
                ]);
            }


            $data = $form->getData();
            $evaluation = $data['evaluation'];
            $section = $data['schoolSection'];
            $schoolClassPeriod = $data['schoolClassPeriod'];
            $period = $this->currentPeriod;


            return $this->render('school_evaluation/report.card.html.twig', [
                'students' => $schoolClassPeriod->getStudents(),
                'form' => $form->createView(),
                'evaluation' => $evaluation,
                'section' => $section,
                'period' => $period,
                'schoolClassPeriod' => $schoolClassPeriod,
            ]);
        }

        return $this->render('school_evaluation/report.card.html.twig', [

            'form' => $form->createView(),
        ]);
    }

    /**
     * bulletin trimestriel
     */
    #[Route('/report-card/frame', name: 'app_school_evaluation_report_card_frame', methods: ['GET', 'POST'])]
    public function reportCardFrame(SessionInterface $session,Request $request, EntityManagerInterface $entityManager): Response
    {
        
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        /** @var \App\Entity\User */
        $currentUser = $this->getUser();
        $school = $this->currentSchool;

        $form = $this->createForm(ReportCardSearchType::class, null, [
            'action' => $this->generateUrl('app_school_evaluation_report_card_frame'),

        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            /** @var SubmitButton $submitButton */
            $submitButton = $form->get('submit');
            if (!$submitButton->isClicked()) {

                return $this->render('school_evaluation/report.card.frame.html.twig', [
                    'form' => $form->createView(),
                ]);
            }


            $data = $form->getData();
            $evaluation = $data['evaluation'];
            $section = $data['schoolSection'];
            $schoolClassPeriod = $data['schoolClassPeriod'];
            $period = $this->currentPeriod;


            return $this->render('school_evaluation/report.card.frame.html.twig', [
                'students' => $schoolClassPeriod->getStudents(),
                'form' => $form->createView(),
                'evaluation' => $evaluation,
                'section' => $section,
                'period' => $period,
                'schoolClassPeriod' => $schoolClassPeriod,
            ]);
        }

        return $this->render('school_evaluation/report.card.frame.html.twig', [

            'form' => $form->createView(),
        ]);
    }

    #[Route('/download-report-card/{studentId}/{evaluationId}/{classId}', name: 'app_school_evaluation_download_report_card', methods: ['GET'])]
    public function downloadReportCard(
        int $studentId,
        int $evaluationId,
        int $classId,
        EntityManagerInterface $entityManager,
        ReportCardService $reportCardService,
    ): Response {

        $student = $entityManager->getRepository(User::class)->find($studentId);
        $evaluation = $entityManager->getRepository(SchoolEvaluation::class)->find($evaluationId);
        $schoolClassPeriod = $entityManager->getRepository(SchoolClassPeriod::class)->find($classId);

        $reportCardService->init($student, $evaluation, $schoolClassPeriod);

        return $this->render('documents/report.card.html.twig', [
            'model' => $reportCardService,
        ]);
    }

    #[Route('/download-report-card-primary-frame/{studentId}/{evaluationId}/{classId}', name: 'app_school_evaluation_download_report_card_frame', methods: ['GET'])]
    public function downloadReportCardPrimaryFrame(
        int $studentId,
        int $evaluationId,
        int $classId,
        EntityManagerInterface $entityManager,
        PrimaryFrameReportCard $reportCardService,
        SluggerInterface $slugger,
    ): Response {

        $student = $entityManager->getRepository(User::class)->find($studentId);
        $evaluation = $entityManager->getRepository(SchoolEvaluationFrame::class)->find($evaluationId);
        $schoolClassPeriod = $entityManager->getRepository(SchoolClassPeriod::class)->find($classId);

        if ($schoolClassPeriod->getSectionCategory()->getSection()->getSlug() == $slugger->slug("Secondaire général")) {
            return $this->redirectToRoute("app_school_evaluation_download_report_card_secondary_frame", [
                'studentId' => $studentId,
                'evaluationId' => $evaluationId,
                'classId' => $classId,
            ]);
        }

        $reportCardService->init($student, $evaluation, $schoolClassPeriod);


        return $this->render('documents/report.card.primary.frame.html.twig', [
            'model' => $reportCardService,
        ]);
    }

    #[Route('/download-report-card-frame/{studentId}/{evaluationId}/{classId}', name: 'app_school_evaluation_download_report_card_secondary_frame', methods: ['GET'])]
    public function downloadReportCardFrame(
        int $studentId,
        int $evaluationId,
        int $classId,
        EntityManagerInterface $entityManager,
        SecondaryFrameReportCard $reportCardService,
    ): Response {

        $student = $entityManager->getRepository(User::class)->find($studentId);
        $evaluation = $entityManager->getRepository(SchoolEvaluationFrame::class)->find($evaluationId);
        $schoolClassPeriod = $entityManager->getRepository(SchoolClassPeriod::class)->find($classId);

        $reportCardService->init($student, $evaluation, $schoolClassPeriod);

        return $this->render('documents/report.card.secondary.frame.html.twig', [
            'model' => $reportCardService,
        ]);
    }


    #[Route('/{id}', name: 'app_school_evaluation_show', methods: ['GET'])]
    public function show(SchoolEvaluation $schoolEvaluation, EntityManagerInterface $entityManager): Response
    {
        /** @var \App\Entity\User */
        $user = $this->getUser();
        /** @var \App\Entity\SchoolClassPeriod[] */
        $classes = $entityManager->getRepository(SchoolClassPeriod::class)->findBy(['school' => $this->currentSchool]);

        $subjects = [];
        foreach ($classes as $key => $class) {
            $subjects[] = new EvaluationNoteDTO($class->getSectionCategory()->getSectionCategorySubjects()->toArray(), $class);
        }

        return $this->render('school_evaluation/show.html.twig', [
            'school_evaluation' => $schoolEvaluation,
            'subjects' => $subjects,
        ]);
    }

    #[Route('/{evalid}/{subid}/{scoid}/download-csv', name: 'app_school_evaluation_download_grades_csv', methods: ['GET'])]
    public function downloadCSV(
        int $evalid,
        int $subid,
        int $scoid,
        EntityManagerInterface $entityManager,
        GradeCSVGenerator $gradeCSVGenerator,
        SessionInterface $session
    ): Response {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        
        /** @var \App\Entity\User */
        $user = $this->getUser();
        /** @var \App\Entity\SchoolClassPeriod */
        $sclass = $entityManager->getRepository(SchoolClassPeriod::class)->find($scoid);
        /** @var \App\Entity\SchoolEvaluation */
        $evaluation = $entityManager->getRepository(SchoolEvaluation::class)->find($evalid);
        /** @var \App\Entity\SectionCategorySubject */
        $subject = $entityManager->getRepository(SectionCategorySubject::class)->find($subid);
        /** @var \App\Entity\SchoolPeriod */
        $period = $this->currentPeriod;
        /** @var SubjectGrade */
        $subjectGrade = $entityManager->getRepository(SubjectGrade::class)->findOneBy([
            'evaluation' => $evaluation,
            'subject' => $subject,
            'sClass' => $sclass,
            'period' => $period,
        ]);

        $studentClass = $entityManager->getRepository(StudentClass::class)->findBy([
            'schoolClassPeriod' => $sclass,
            'period' => $period,
        ]);
        $students = [];
        foreach ($studentClass as $key => $student) {
            $students[] = $student->getStudent();
        }

        if (null === $subjectGrade) {
            $subjectGrade = (new SubjectGrade())
                ->setEvaluation($evaluation)
                ->setSubject($subject)
                ->setSClass($sclass)
                ->setPeriod($period);
            $entityManager->persist($subjectGrade);
            $entityManager->flush();
        }

        $csvContent = $gradeCSVGenerator->generateCSV($subjectGrade->getId(), $students);

        $response = new Response($csvContent);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="grades_' . $subject->getName() . '_' . $evaluation->__toString() . '.csv"');

        return $response;
    }

    #[Route('/upload-grade/upload', name: 'app_school_evaluation_upload_grade', methods: ['GET', 'POST'])]
    public function uploadGrades(Request $request, GradeCSVGenerator $gradeCSVGenerator, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(UploadGradeCSVType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('csv_file')->getData();

            if ($file) {
                $data = file_get_contents($file);
                $rows = array_map('str_getcsv', explode("\n", $data));
                $header = array_shift($rows);
                $studentNotes = [];

                foreach ($rows as $row) {
                    if (count($row) >= 4) {
                        $ref = $row[0];
                        $uid = $row[1];
                        $name = $row[2];
                        $note = $row[3];

                        $subjectGrade = $entityManager->getRepository(SubjectGrade::class)
                            ->find($ref);
                        $student = $entityManager(User::class)->find($uid);

                        if (null === $subjectGrade || null === $student) {
                            $this->addFlash(
                                'error',
                                'Fichier invalide'
                            );
                            return $this->redirectToRoute('app_school_evaluation_upload_grade');
                        }


                        if ($student) {
                            // Persister la note ou d'autres informations nécessaires
                            $student->setGrade($note);
                            $entityManager->persist($student);
                        }
                    }
                }

                $entityManager->flush();
            }

            return $this->redirectToRoute('app_school_evaluation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('school_evaluation/upload_grades.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_school_evaluation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, SchoolEvaluation $schoolEvaluation, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SchoolEvaluationType::class, $schoolEvaluation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_school_evaluation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('school_evaluation/edit.html.twig', [
            'school_evaluation' => $schoolEvaluation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_school_evaluation_delete', methods: ['POST'])]
    public function delete(Request $request, SchoolEvaluation $schoolEvaluation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $schoolEvaluation->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($schoolEvaluation);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_school_evaluation_index', [], Response::HTTP_SEE_OTHER);
    }
}
>>>>>>> claude/naughty-rubin-200ad9
