<?php

namespace App\Controller;

use App\Entity\User;
use App\DTO\ProgressQueryDTO;
use App\Service\PdfGenerator;
use App\DTO\BulletinRequestDTO;
use Symfony\Component\Uid\Uuid;
use App\Service\BulletinDataService;
use App\Service\BulletinRenderService;
use App\Service\BulletinContextService;
use App\Service\BulletinProgressService;
use App\Service\BulletinGenerationService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Message\GenerateAllBulletinsPdfMessage;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

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
                $this->getConnectedUser()->getId(),
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

    public function getConnectedUser(): User
    {
        return $this->getUser();
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
