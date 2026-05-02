<<<<<<< HEAD
<?php

namespace App\Controller;

use App\DTO\BulletinRequestDTO;
use App\DTO\ProgressQueryDTO;
use App\Service\BulletinContextService;
use App\Service\BulletinDataService;
use App\Service\BulletinGenerationService;
use App\Service\BulletinProgressService;
use App\Service\BulletinRenderService;
use App\Service\PdfGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use App\Message\GenerateAllBulletinsPdfMessage;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class BulletinController extends AbstractController
{
    public function __construct(
        private BulletinContextService $contextService,
        private BulletinDataService $dataService,
        private BulletinGenerationService $generationService,
        private BulletinRenderService $renderService,
        private BulletinProgressService $progressService,
        private PdfGenerator $pdfGenerator,
        private MessageBusInterface $bus
    ) {
        $this->contextService->initializeFromSession();
    }

    #[Route('/bulletins', name: 'app_bulletins')]
    public function index(): Response
    {
        // Logique simplifiée - à adapter selon vos besoins
        $sections = $this->dataService->getSections($this->getUser());
        $templates = $this->dataService->getTemplates();

        return $this->render('evaluation/bulletin.index.html.twig', [
            'sections' => $sections,
            'templates' => $templates,
        ]);
    }

    #[Route('/get-school-evaluation-frames', name: 'app_get_school_evaluation_frames', methods: ['GET'])]
    public function getSchoolEvaluationFrames(): JsonResponse
    {
        try {
            $frames = $this->dataService->getEvaluationFrames();
            return new JsonResponse($frames);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/get-school-evaluation-times', name: 'app_get_school_evaluation_times', methods: ['GET'])]
    public function getSchoolEvaluationTimes(Request $request): JsonResponse
    {
        try {
            $classId = (int)$request->query->get('classId');
            $times = $this->dataService->getEvaluationTimes($classId, $this->contextService);
            return new JsonResponse($times);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/get-students-by-class', name: 'app_get_students_by_class', methods: ['GET'])]
    public function getStudentsByClass(Request $request): JsonResponse
    {
        try {
            $classId = (int)$request->query->get('classId');
            $students = $this->dataService->getStudentsByClass($classId);
            return new JsonResponse($students);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/bulletin/individual', name: 'app_bulletin_frame', methods: ['GET'])]
    public function showBulletin(Request $request): Response
    {
        try {
            $dto = BulletinRequestDTO::fromRequest($request);

            // Validation des paramètres requis
            if (!$dto->classId || !$dto->periodicityId || !$dto->bulletinType || !$dto->templateId) {
                throw new \InvalidArgumentException('Paramètres manquants pour afficher le bulletin');
            }

            // Si printType=full, nous n'avons pas besoin de studentId
            if ($dto->printType !== 'full' && !$dto->studentId) {
                throw new \InvalidArgumentException('ID étudiant manquant pour afficher le bulletin individuel');
            }

            $renderData = $this->renderService->renderIndividualBulletin($dto, $this->contextService);

            return $this->render($renderData['template'], $renderData['data']);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_bulletins');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la génération du bulletin: ' . $e->getMessage());
            return $this->redirectToRoute('app_bulletins');
        }
    }

    #[Route('/bulletin/pdf/individual', name: 'app_bulletin_pdf_individual', methods: ['GET'])]
    public function generateIndividualPdf(Request $request): Response
    {
        try {
            $dto = BulletinRequestDTO::fromRequest($request);
            $result = $this->generationService->generateIndividualBulletin($dto, $this->contextService);

            // Si printType=full, nous générons tous les bulletins
            if ($dto->printType === 'full') {
                $class = $this->dataService->getClass($dto->classId);
                $filename = 'bulletins_complets_' . $class->getClassOccurence()->getName() . '_' . date('Y-m-d_H-i-s') . '.pdf';
            } else {
                // Génération individuelle normale
                $student = $this->generationService->getStudentForFilename($dto->studentId);
                $class = $this->dataService->getClass($dto->classId);

                $filename = 'bulletin_' .
                    ($student[0]->getStudent()->getRegistrationNumber() ?? 'unknown') . '_' .
                    $class->getClassOccurence()->getName() . '.pdf';
            }

            return $this->pdfGenerator->generatePdf($result['html'], $filename);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/bulletin/pdf/all-chunked-async', name: 'app_bulletin_pdf_all_chunked_async', methods: ['POST'])]
    public function generateAllPdfChunkedAsync(Request $request): JsonResponse
    {
        try {
            ini_set('max_execution_time', 1200); // Augmente le temps d'exécution pour les gros fichiers
            ini_set('memory_limit', '4096M');
            $dto = BulletinRequestDTO::fromRequest($request);
            $dto->validateForMassGeneration();

            $class = $this->dataService->getClass($dto->classId);
            $taskId = Uuid::v4()->toRfc4122();
            $filename = 'bulletins_' . $class->getClassOccurence()->getName() . '_' . $taskId . '.pdf';

            $this->progressService->initializePdfTask($taskId, $filename);
            $this->progressService->updateProgress($taskId, 0, 100, 'Initialisation de la génération PDF...');


            $this->bus->dispatch(new GenerateAllBulletinsPdfMessage(
                $taskId,
                $dto->classId,
                $dto->periodicityId,
                $dto->bulletinType,
                $dto->templateId,
                $this->getUser()->getId(),
                $this->contextService->getCurrentSchool()->getId(),
                $this->contextService->getCurrentPeriod()->getId(),
                $dto->bulLang ?? 'fr',
                $dto->passNote ?? 10,
            ));

            return new JsonResponse([
                'taskId' => $taskId,
                'message' => 'Génération PDF démarrée'
            ]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Erreur interne du serveur'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/get-evaluation-progress', name: 'app_get_evaluation_progress', methods: ['GET'])]
    public function getEvaluationProgress(Request $request): JsonResponse
    {
        try {
            $dto = ProgressQueryDTO::fromRequest($request);
            $progress = $this->dataService->calculateEvaluationProgress($dto, $this->contextService);
            return new JsonResponse($progress);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/get-student-evaluation-progress', name: 'app_get_student_evaluation_progress', methods: ['GET'])]
    public function getStudentEvaluationProgress(Request $request): JsonResponse
    {
        try {
            $dto = ProgressQueryDTO::fromRequest($request);
            $progress = $this->dataService->calculateEvaluationProgress($dto, $this->contextService);
            return new JsonResponse($progress);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/get-students-progress', name: 'app_get_students_progress', methods: ['GET'])]
    public function getStudentsProgress(Request $request): JsonResponse
    {
        try {
            $dto = ProgressQueryDTO::fromRequest($request);
            $progress = $this->dataService->getStudentsProgress($dto, $this->contextService);
            return new JsonResponse($progress);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/bulletins/progress/{taskId}', name: 'app_bulletin_progress', methods: ['GET'])]
    public function getBulletinProgress(string $taskId): JsonResponse
    {
        try {
            $progress = $this->progressService->getProgress($taskId);
            return new JsonResponse(['status' => 'success', 'progress' => $progress]);
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'not_found'], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/bulletin/pdf/status/{taskId}', name: 'app_bulletin_pdf_status', methods: ['GET'])]
    public function getPdfGenerationStatus(string $taskId): JsonResponse
    {
        try {
            $status = $this->progressService->getPdfTaskStatus($taskId);
            return new JsonResponse($status);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['status' => 'not_found'], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/bulletin/pdf/download', name: 'app_bulletin_pdf_download', methods: ['GET'])]
    public function downloadGeneratedPdf(Request $request): Response
    {
        try {

            $fileUrl = $request->query->get('fileUrl');
            $filePath = $this->getParameter('kernel.project_dir') . '/public' . $fileUrl;

            if (!file_exists($filePath)) {
                throw new \RuntimeException('Fichier PDF non trouvé: ' . $filePath);
            }

            $filename = basename($filePath);
            $response = new BinaryFileResponse($filePath);
            $response->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $filename
            );

            return $response;
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_bulletins');
        }
    }

    // Méthodes dépréciées mais conservées pour compatibilité
    #[Route('/bulletins/generate-all-async', name: 'app_generate_all_bulletins_async', methods: ['POST'])]
    public function generateAllBulletinsAsync(Request $request): JsonResponse
    {
        // Rediriger vers la nouvelle méthode
        return $this->generateAllPdfChunkedAsync($request);
    }

    #[Route('/bulletin/pdf/all-chunked', name: 'app_bulletin_pdf_all_chunked', methods: ['GET'])]
    public function generateAllPdfChunked(Request $request): Response
    {
        try {
            ini_set('max_execution_time', 1200); // Augmente le temps d'exécution pour les gros fichiers
            ini_set('memory_limit', '4096M');
            $dto = BulletinRequestDTO::fromRequest($request);
            $dto->validateForMassGeneration();

            // Récupérer le seuil depuis les paramètres de la requête (par défaut 100%)
            $completionThreshold = (int)($request->query->get('threshold') ?? 100);
            $completionThreshold = min(100, max(0, $completionThreshold)); // S'assurer que c'est entre 0 et 100

            // Créer un DTO pour vérifier la progression
            $progressDto = new ProgressQueryDTO(
                $dto->classId,
                $dto->periodicityId,
                $dto->bulletinType
            );

            // Récupérer la progression de tous les étudiants
            $studentsProgress = $this->dataService->getStudentsProgress($progressDto, $this->contextService);

            // Filtrer les étudiants selon le seuil
            $eligibleStudents = array_filter(
                $studentsProgress['students_progress'],
                function ($progress) use ($completionThreshold) {
                    return $progress['percentage'] >= $completionThreshold;
                }
            );

            if (empty($eligibleStudents)) {
                $this->addFlash('warning', sprintf(
                    'Aucun étudiant n\'a un bulletin avec au moins %d%% de remplissage',
                    $completionThreshold
                ));
                return $this->redirectToRoute('app_bulletins');
            }

            $class = $this->dataService->getClass($dto->classId);
            $bulletinsHtml = [];

            $this->addFlash('info', sprintf(
                'Génération de %d bulletin(s) sur %d étudiant(s) (seuil: %d%%)',
                count($eligibleStudents),
                $studentsProgress['total_students'],
                $completionThreshold
            ));

            // Générer les bulletins des étudiants éligibles
            foreach ($eligibleStudents as $studentId => $progress) {
                $studentDto = new BulletinRequestDTO(
                    $dto->classId,
                    $dto->periodicityId,
                    $dto->bulletinType,
                    $dto->templateId,
                    $studentId,
                    $dto->bulLang ?? 'fr',
                    $dto->passNote ?? 10,
                );

                $result = $this->generationService->generateIndividualBulletin($studentDto, $this->contextService);

                $completionColor = $progress['percentage'] == 100 ? '#28a745' : '#ffc107';

                $studentHeader = '<div class="student-header" style="background: #f8f9fa; padding: 10px; border-left: 4px solid ' . $completionColor . '; margin-bottom: 20px;">
                <h3>Bulletin de ' . $progress['student_name'] . ' - ' . $progress['registration_number'] . '</h3>
                <p>Taux de remplissage: <strong>' . $progress['percentage'] . '%</strong> (' . $progress['evaluated'] . '/' . $progress['total'] . ' évaluations)</p>
            </div>';

                $bulletinsHtml[] = $studentHeader . $result['html'];
            }

            $thresholdSuffix = $completionThreshold == 100 ? 'complets' : 'seuil_' . $completionThreshold;
            $filename = 'bulletins_' . $thresholdSuffix . '_' . $class->getClassOccurence()->getName() . '_' . date('Y-m-d_H-i-s') . '.pdf';

            // Préparer le HTML complet
            $fullHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            .student-bulletin { page-break-after: always; margin: 20px 0; }
            .student-bulletin:last-child { page-break-after: auto; }
            @media print { 
                .student-bulletin { page-break-after: always; margin: 0; } 
                .student-header { display: none; }
            }
        </style></head><body>';

            // En-tête global du document
            $fullHtml .= '<div style="text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px;" class="student-bulletin">
            <h1>Bulletins - Classe ' . $class->getClassOccurence()->getName() . '</h1>
            <p>Généré le ' . date('d/m/Y à H:i') . ' - Seuil de remplissage: ' . $completionThreshold . '%</p>
            <p>' . count($eligibleStudents) . ' bulletin(s) sur ' . $studentsProgress['total_students'] . ' étudiant(s)</p>
        </div>';

            foreach ($bulletinsHtml as $bulletinHtml) {
                $fullHtml .= '<div class="student-bulletin">' . $bulletinHtml . '</div>';
            }

            $fullHtml .= '</body></html>';

            return $this->pdfGenerator->generatePdf($fullHtml, $filename);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_bulletins');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la génération des bulletins: ' . $e->getMessage());
            return $this->redirectToRoute('app_bulletins');
        }
    }
}
=======
<?php

namespace App\Controller;

use App\Entity\ReportCardTemplate;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\OperationLogger;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\SchoolEvaluationFrameRepository;
use App\Repository\SchoolEvaluationTimeRepository;
use App\Repository\ClassSubjectModuleRepository;
use App\Repository\SchoolPeriodRepository;
use App\Repository\EvaluationRepository;
use App\Repository\StudentClassRepository;
use App\Repository\UserRepository;
use App\Repository\SubjectGroupRepository;
use App\Repository\SchoolClassSubjectRepository;
use App\Repository\EvaluationAppreciationTemplateRepository;
use App\Service\AppreciationService;
use App\Repository\EvaluationAppreciationBaremeRepository;
use App\Service\BulletinGenerator;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Message\GenerateAllBulletinsMessage;
use App\Repository\StudyLevelRepository;
use App\Repository\SchoolClassPeriodRepository;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Entity\School;
use App\Entity\SchoolPeriod;
use Doctrine\ORM\EntityManager;

class BulletinController extends AbstractController
{

    private StudyLevelRepository $sectionRepo;
    private EntityManagerInterface $entityManager;
    private OperationLogger $operationLog;
    private SchoolEvaluationFrameRepository $frameRepo;
    private SchoolEvaluationTimeRepository $timeRepo;
    private ClassSubjectModuleRepository $subjectModuleRepo;
    private SchoolPeriodRepository $periodRepo;
    private SchoolClassPeriodRepository $classRepo;
    private EvaluationRepository $evaluationRepo;
    private StudentClassRepository $studentRepo;
    private UserRepository $userRepo;
    private SubjectGroupRepository $subjectGroupRepo;
    private SchoolClassSubjectRepository $schoolClassSubjectRepo;
    private EvaluationAppreciationTemplateRepository $appreciationTemplateRepo;
    private AppreciationService $appreciationService;
    private EvaluationAppreciationBaremeRepository $appreciationBaremeRepo;
    private $bulletinGenerator;
    private MessageBusInterface $bus;
    private SessionInterface $session;
    private School $currentSchool;
    private SchoolPeriod $currentPeriod;


    public function __construct(
        EntityManagerInterface $entityManager,
        OperationLogger $operationLog,
        StudyLevelRepository $sectionRepo,
        SchoolEvaluationFrameRepository $frameRepo,
        SchoolEvaluationTimeRepository $timeRepo,
        ClassSubjectModuleRepository $subjectModuleRepo,
        SchoolPeriodRepository $periodRepo,
        SchoolClassPeriodRepository $classRepo,
        EvaluationRepository $evaluationRepo,
        StudentClassRepository $studentRepo,
        UserRepository $userRepo,
        SubjectGroupRepository $subjectGroupRepo,
        SchoolClassSubjectRepository $schoolClassSubjectRepo,
        EvaluationAppreciationTemplateRepository $appreciationTemplate,
        AppreciationService $appreciationService,
        EvaluationAppreciationBaremeRepository $appreciationBaremeRepo,
        BulletinGenerator $bulletinGenerator,
        MessageBusInterface $bus
    ) {
        $this->entityManager = $entityManager;
        $this->operationLog = $operationLog;
        $this->sectionRepo = $sectionRepo;
        $this->frameRepo = $frameRepo;
        $this->timeRepo = $timeRepo;
        $this->subjectModuleRepo = $subjectModuleRepo;
        $this->periodRepo = $periodRepo;
        $this->classRepo = $classRepo;
        $this->evaluationRepo = $evaluationRepo;
        $this->studentRepo = $studentRepo;
        $this->userRepo = $userRepo;
        $this->subjectGroupRepo = $subjectGroupRepo;
        $this->schoolClassSubjectRepo = $schoolClassSubjectRepo;
        $this->appreciationService = $appreciationService;
        $this->appreciationTemplateRepo = $appreciationTemplate;
        $this->appreciationBaremeRepo = $appreciationBaremeRepo;
        $this->bulletinGenerator = $bulletinGenerator;
        $this->bus = $bus;
       }


    /**
     * @Route("/bulletins", name="app_bulletins")
     */
    #[Route('/bulletins', name: 'app_bulletins')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        // Exemple de données pour les sections (à remplacer par des données réelles depuis la base de données)
        if (in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles())) {
            $sections = $this->sectionRepo->findAll();
        } else {
            // Utilise getUserIdentifier() pour retrouver l'utilisateur courant
            $user = $entityManager->getRepository(\App\Entity\User::class)->findOneBy(['username' => $this->getUser()->getUserIdentifier()]);
            $config = $user ? $user->getBaseConfigurations()->toArray() : [];
            if (count($config) > 0) {
                $sections = $this->sectionRepo->findBy(['id' => $config[0]->getSectionList()]);
            } else {
                $sections = [];
            }
        }
        // $sections = $this->sectionRepo->findAll();
        $templates = $entityManager->getRepository(ReportCardTemplate::class)->findAll();

        return $this->render('evaluation/bulletin.index.html.twig', [
            'sections' => $sections,
            'templates' => $templates,
        ]);
    }

    /**
     * @Route("/get-school-evaluation-frames", name="app_get_school_evaluation_frames", methods={"GET"})
     */
    #[Route('/get-school-evaluation-frames', name: 'app_get_school_evaluation_frames', methods: ['GET'])]
    public function getSchoolEvaluationFrames(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {

        // Récupérer les périodes associées à la classe
        $frames = $this->frameRepo->findAll();

        // Transformer les données en tableau JSON
        $data = array_map(function ($frame) {
            return [
                'id' => $frame->getId(),
                'name' => $frame->getName(),
            ];
        }, $frames);

        return new JsonResponse($data);
    }

    /**
     * @Route("/get-school-evaluation-times", name="app_get_school_evaluation_times", methods={"GET"})
     */
    #[Route('/get-school-evaluation-times', name: 'app_get_school_evaluation_times', methods: ['GET'])]
    public function getSchoolEvaluationTimes(SessionInterface $session,Request $request): JsonResponse
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        
        $classId = $request->query->get('classId');

        if (!$classId) {
            return new JsonResponse(['error' => 'Class ID is required'], Response::HTTP_BAD_REQUEST);
        }

        $class = $this->classRepo->find($classId);
        if (!$class) {
            return new JsonResponse(['error' => 'Class not found'], Response::HTTP_NOT_FOUND);
        }

        $period = $this->currentPeriod;
        if (!$period) {
            return new JsonResponse(['error' => 'No active period found'], Response::HTTP_NOT_FOUND);
        }

        $school = $this->currentSchool;
        if (!$school) {
            return new JsonResponse(['error' => 'School not found'], Response::HTTP_NOT_FOUND);
        }

        $modules = $this->subjectModuleRepo->findBy(['class' => $class, 'period' => $period, 'school' => $school]);
        if (!$modules) {
            return new JsonResponse(['error' => 'No modules found for this class'], Response::HTTP_NOT_FOUND);
        }

        $evaluations = $this->evaluationRepo->findBy(['classSubjectModule' => $modules]);
        if (!$evaluations) {
            return new JsonResponse(['error' => 'No evaluations found for this class'], Response::HTTP_NOT_FOUND);
        }

        // Mettre toutes les sous-périodes (getTime()) dans un tableau
        $times = [];
        foreach ($evaluations as $evaluation) {
            $time = $evaluation->getTime();
            if ($time) {
                if (! in_array($time->getId(), $times)) $times[] = $time->getId();
            }
        }

        $times = $this->timeRepo->findBy(['id' => $times]);
        if (!$times) {
            return new JsonResponse(['error' => 'No evaluation times found for this class'], Response::HTTP_NOT_FOUND);
        }
        // Transformer les données en tableau JSON
        $data = array_map(function ($time) {
            return [
                'id' => $time->getId(),
                'name' => $time->getName(),
            ];
        }, $times);


        return new JsonResponse($data);
    }

    /**
     * @Route("/get-students-by-class", name="app_get_students_by_class", methods={"GET"})
     */
    #[Route('/get-students-by-class', name: 'app_get_students_by_class', methods: ['GET'])]
    public function getStudentsByClass(SessionInterface $session,Request $request): JsonResponse
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        
        $classId = $request->query->get('classId');

        if (!$classId) {
            return new JsonResponse(['error' => 'Class ID is required'], Response::HTTP_BAD_REQUEST);
        }

        $class = $this->classRepo->find($classId);
        if (!$class) {
            return new JsonResponse(['error' => 'Class not found'], Response::HTTP_NOT_FOUND);
        }

        $period = $this->currentPeriod;
        if (!$period) {
            return new JsonResponse(['error' => 'No active period found'], Response::HTTP_NOT_FOUND);
        }

        $students = $this->studentRepo->findBy(['schoolClassPeriod' => $class]);
        if (!$students) {
            return new JsonResponse(['error' => 'No students found for this class'], Response::HTTP_NOT_FOUND);
        }

        // Transformer les données en tableau JSON
        $data = array_map(function ($student) {
            return [
                'id' => $student->getStudent()->getId(),
                'name' => $student->getStudent()->getFullName(),
                'registrationNumber' => $student->getStudent()->getRegistrationNumber(),
            ];
        }, $students);

        // Trier les étudiants par ordre alphabétique
        usort($data, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return new JsonResponse($data);
    }

    /**
     * @Route("/bulletin/{studentId}", name="app_bulletin_frame", methods={"GET"})
     */
    #[Route('/bulletin', name: 'app_bulletin_frame', methods: ['GET'])]
    public function showBulletin(SessionInterface $session,Request $request): Response
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        
        set_time_limit(600);
        $studentId = $request->query->get('studentId');

        $periodicityId = $request->query->get('periodicityId');
        $bulletinType = $request->query->get('bulletinType');
        $classId = $request->query->get('classId');
        $templateId = $request->query->get('templateId');
        $template=$this->entityManager->getRepository(ReportCardTemplate::class)->find($templateId);
        $html=$this->bulletinGenerator->generateBulletinA(
            $studentId,
            $periodicityId,
            $bulletinType,
            $classId,
            $this->getUser(),
            $this->currentSchool,
            $this->currentPeriod,
            $template->getName()
        );
        
        
        if($template->getName()=='A') {
            $htmlContent = file_get_contents($html[0]);
        
        //$classSubjectModules = $this->subjectModuleRepo->findBy(['class' => $class, 'period' => $period, 'school' => $school]);




        //$htmlContent = $htmlWriter->generateSheetData();
        // Ajouter manuellement l'image dans le HTML
        //$logoPath = '/img/logo_test.png'; // Chemin relatif à partir de "public/"
        //$logoHtml = '<img id="schoolLogo" src="' . $logoPath . '" alt="Logo de l\'école" style="height: 80px; display: block; margin: 0 auto;">';
        // Insérer l'image au début du contenu HTML
        // $htmlContent = $logoHtml . $htmlContent;

        $school = $this->currentSchool;
        if (!$school) {
            throw $this->createNotFoundException('School not found');
        }
        $class = $this->classRepo->find($classId);
        if (!$class) {
            throw $this->createNotFoundException('Class not found');
        }
        $student = $this->studentRepo->findByStudent($studentId);
        if (!$student) {
            throw $this->createNotFoundException('Student not found');
        }
        if ($bulletinType == 'sub-period') {
            $periodicity = $this->timeRepo->findById($periodicityId);
            $periods = $periodicity;
        } else {
            $periodicity = $this->frameRepo->findById($periodicityId);
            $periods = $this->timeRepo->findBy(['evaluationFrame' => $periodicity]);
            $periods = $this->evaluationRepo->findBy(['time' => $periods]);
            $periodList = [];
            foreach ($periods as $period) {
                if (! in_array($period->getTime()->getId(), $periodList)) $periodList[] = $period->getTime()->getId();
            }
            $periods = $this->timeRepo->findBy(['id' => $periodList]);
        }
        if (!$periodicity) {
            throw $this->createNotFoundException('Periodicity not found');
        }
        if (!$periods) {
            throw $this->createNotFoundException('Periods not found');
        }

        $period = $this->currentPeriod;
        if (!$period) {
            throw $this->createNotFoundException('Aucune période active trouvée.');
        }
        $students = $this->studentRepo->findBy(['schoolClassPeriod' => $class]);
        $studentsIds = array_map(function ($student) {
            return $student->getId();
        }, $students);

        $evaluations = $this->evaluationRepo->findBy([
            'time' => $periods,
            'student' => $studentsIds,
        ]);
        $subjectGroups = $this->subjectGroupRepo->findBy(['period' => $period, 'school' => $school]);
        $subjectGroupsIds = array_map(function ($subjectGroup) {
            return $subjectGroup->getId();
        }, $subjectGroups);

        $subjects = $this->schoolClassSubjectRepo->findBy(['group' => $subjectGroupsIds,'schoolClassPeriod' => $class], ['group' => 'ASC']);
        if (!$subjects) {
            throw $this->createNotFoundException('Subjects not found');
        }


        $period = $this->currentPeriod;

        // Retourner une réponse pour télécharger le fichier PDF

        return $this->render('evaluation/bulletin.frame.html.twig', [
            'student' => $student[0]->getStudent(),
            'periodicity' => $periodicity,
            'evaluations' => $evaluations,
            'school' => $school,
            'schoolPeriod' => $period->getName(),
            'periodName' => $periodicity[0]->getName(),
            'class' => $class,
            'subjectGroups' => $subjectGroups,
            'periods' => $periods,
            'subjects' => $subjects,
            'htmlContent' => $htmlContent,
            'nbLignes' => $html[1],
            'lignesEntete' => $html[2],
            'lastcolumn' => $html[3],
            'lastRow' => $html[4],
            'studentPhoto' =>'/uploads/' . $student[0]->getStudent()->getPhoto(),
            'schoolLogo' => '/img/'.($school->getLogo()!== null ? $school->getLogo() : 'logo_test.png'),
        ]);
    }elseif($template->getName()=='B'){
            $htmlContent=$html[0];
            return $this->render('evaluation/bulletin.frameB.html.twig', [
                'htmlContent'=>$htmlContent
            ]);
        }else{
            return $this->render('evaluation/bulletin.frame.html.twig', []);
        }
    }
    /**
     * @Route("/bulletins/generate-all", name="app_generate_all_bulletins", methods={"GET"})
     */
    #[Route('/bulletins/generate-all', name: 'app_generate_all_bulletins', methods: ['GET'])]
    public function generateAllBulletins(SessionInterface $session,Request $request): Response
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        
        set_time_limit(600); // Augmente le temps d'exécution si besoin

        $classId = $request->query->get('classId');
        $periodicityId = $request->query->get('periodicityId');
        $bulletinType = $request->query->get('bulletinType');

        $class = $this->classRepo->find($classId);
        $period = $this->currentPeriod;
        $students = $this->studentRepo->findBy(['schoolClassPeriod' => $class, 'period' => $period]);

        foreach ($students as $student) {
            // Appelle la logique de génération pour chaque élève
            $studentId = $student->getStudent()->getId();

            $this->bulletinGenerator->generateBulletinA(
                $studentId,
                $periodicityId,
                $bulletinType,
                $classId,
                $this->getUser(),
                $this->currentSchool,
                $this->currentPeriod
            );
            // Optionnel : tu peux aussi extraire la logique de génération dans un service pour éviter le rendu HTML à chaque fois
        }

        $this->addFlash('success', 'Tous les bulletins ont été générés.');
        return $this->redirectToRoute('app_bulletins');
    }

    /**
     * @Route("/bulletins/generate-all-async", name="app_generate_all_bulletins_async", methods={"POST"})
     */
    #[Route('/bulletins/generate-all-async', name: 'app_generate_all_bulletins_async', methods: ['POST'])]
    public function generateAllBulletinsAsync(Request $request,MessageBusInterface $bus): JsonResponse
    {
        set_time_limit(6000);
        $taskId = Uuid::v4()->toRfc4122();
        $classId = $request->request->get('classId');
        $periodicityId = $request->request->get('periodicityId');
        $bulletinType = $request->request->get('bulletinType');

        // Stocke l'état initial dans un fichier (ou Redis, ou BDD)
        $fs = new Filesystem();
        $progressFile = $this->getParameter('kernel.project_dir') . "/var/bulletin_progress_$taskId.json";
        $fs->dumpFile($progressFile, json_encode([
            'status' => 'pending',
            'messages' => [],
            'percent' => 0,
        ]));

        
        // Lance la génération en tâche de fond (exemple simple avec exec, pour du vrai asynchrone utiliser Messenger)
        //$phpPath = PHP_BINARY;
        
        //dd($proc);
        // $cmd = "start /B $phpPath bin/console app:generate-all-bulletins-async $classId $periodicityId $bulletinType $taskId > /dev/null 2>&1 &";
        // pclose(popen($cmd, "r"));

        // Envoie le message à Messenger
        $bus->dispatch(new GenerateAllBulletinsMessage($classId, $periodicityId, $bulletinType, $taskId));


        return new JsonResponse(['taskId' => $taskId]);
    }

    /**
     * @Route("/bulletins/generation-status/{taskId}", name="app_bulletin_generation_status", methods={"GET"})
     */
    #[Route('/bulletins/generation-status/{taskId}', name: 'app_bulletin_generation_status', methods: ['GET'])]
    public function getBulletinGenerationStatus(string $taskId): JsonResponse
    {
        $progressFile = $this->getParameter('kernel.project_dir') . "/var/bulletin_progress_$taskId.json";
        if (!file_exists($progressFile)) {
            return new JsonResponse(['status' => 'not_found'], 404);
        }
        $data = json_decode(file_get_contents($progressFile), true);
        return new JsonResponse($data);
    }
    #[Route('/bulletins/save-bulletin-file', name: 'app_save_bulletin_file', methods: ['POST'])]
    public function saveBulletinFile(Request $request): JsonResponse
    {
        $file = $request->request->get('fileContent');
        $fileName = $request->request->get('fileName');
        if (!$file || !$fileName) {
            return new JsonResponse(['error' => 'File content or file name is missing'], Response::HTTP_BAD_REQUEST);
        }
        
        // Déplace le fichier vers le répertoire de destination
        $destination = $this->getParameter('kernel.project_dir') . '/public/uploads/bulletins/';
        try{
            file_put_contents($destination . $fileName,$file);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Failed to save file: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['message' => 'File uploaded successfully', 'fileName' => $fileName]);
    }
}
>>>>>>> claude/naughty-rubin-200ad9
