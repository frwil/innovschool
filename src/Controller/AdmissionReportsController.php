<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\School;
use App\Entity\StudyLevel;
use App\Entity\SchoolPeriod;
use App\Entity\ClassOccurence;
use App\Service\OperationLogger;
use App\Entity\SchoolClassPeriod;
use App\Entity\SchoolClassPaymentModal;
use App\Service\AdmissionReportService;
use App\Repository\StudyLevelRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\SchoolPeriodRepository;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\SchoolClassPeriodRepository;
use Symfony\Component\Routing\Annotation\Route;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Repository\UserBaseConfigurationsRepository;
use App\Repository\SchoolClassPaymentModalRepository;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/admission', defaults: ['_module' => 'admission'])]
class AdmissionReportsController extends AbstractController
{
    private AdmissionReportService $admissionReportService;
    private UserBaseConfigurationsRepository $userBaseRepository;
    private SchoolClassPeriodRepository $schoolClassRepository;
    private StudyLevelRepository $sectionRepository;
    private SessionInterface $session;
    private School $currentSchool;
    private SchoolPeriod $currentPeriod;
    private EntityManagerInterface $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        AdmissionReportService $admissionReportService,
        UserBaseConfigurationsRepository $userBaseRepository,
        SchoolClassPeriodRepository $schoolClassRepository,
        StudyLevelRepository $sectionRepository
    )
    {
        $this->admissionReportService = $admissionReportService;
        $this->userBaseRepository = $userBaseRepository;
        $this->schoolClassRepository = $schoolClassRepository;
        $this->entityManager = $entityManager;
        $this->sectionRepository = $sectionRepository;
    }
    #[Route('/reports', name: 'app_admission_reports')]
    public function index(SessionInterface $session, EntityManagerInterface $entityManager): Response
    {
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $user = $this->getUser();
        $config = $this->userBaseRepository->findOneBy(['user' => $user, 'school' => $this->currentSchool]);
        if ($config) {
            $sections = count($config->getSectionList()) > 0 ? $this->entityManager->getRepository(StudyLevel::class)->findBy(['id' => $config->getSectionList()]) : $this->sectionRepository->findAll();
        } else {
            if (in_array('ROLE_ADMIN', $this->getUser()->getRoles())) {
                $sections = $this->sectionRepository->findAll();
            } else {
                $sections = [];
            }
        }
        return $this->render('admission/report_results.html.twig', [
            'sections' => $sections,
        ]);
    }

    #[Route('/reports/generate', name: 'app_generate_admission_report', methods: ['POST', 'GET'])]
    public function generateReport(Request $request): JsonResponse
    {


        //récupérer les modalités de paiement en fonction du type de méthode GET ou POST
        if ($request->isMethod('GET')) {
            $modalities = $request->query->get('modalities');
            $modalities = explode(',', $modalities);
            $sectionId = "1";
            $classId = $request->query->get('classId');
            $paymentStatus = $request->query->get('paymentStatus');
            $exportFormat = $request->query->get('exportFormat', 'html');
        } else {
            $modalities = $request->request->all('modalities');
            $sectionId = $request->request->get('section', 0);
            $classId = $request->request->get('class');
            $paymentStatus = $request->request->get('paymentStatus');
            $exportFormat = $request->request->get('exportFormat', 'html');
        }

        if (!is_array($modalities) || empty($modalities)) {
            return new JsonResponse(['error' => 'Le champ "modalités" est obligatoire et doit être un tableau.'], Response::HTTP_BAD_REQUEST);
        }


        //dd($sectionId, $classId, $modalities, $paymentStatus, $exportFormat);

        // Vérifier que tous les champs sont renseignés
        if (!$sectionId || !$classId || empty($modalities) || !$paymentStatus || !$exportFormat) {
            return new JsonResponse(['error' => 'Tous les champs sont obligatoires.'], Response::HTTP_BAD_REQUEST);
        }

        // Récupérer les données en fonction des critères
        $reportData = $this->admissionReportService->getReportData($classId, $modalities, $paymentStatus);

        if ($exportFormat === 'pdf') {
            // Retourner une URL vers la route qui génère le PDF
            return new JsonResponse($reportData);
        }

        /* if ($exportFormat === 'excel') {
            // Générer un fichier Excel
            $excelFile = $this->admissionReportService->generateExcel($reportData);
            return $this->file($excelFile, 'rapport_admission.xlsx');
        } */

        // Rendre les données en HTML
        return new JsonResponse($reportData);
    }

    #[Route('/reports/pdf', name: 'app_generate_pdf_report', methods: ['GET'])]
    public function generatePdfReport(Request $request, AdmissionReportService $admissionReportService): Response
    {
        $classId = $request->query->get('classId');
        $modalities = explode(',', $request->query->get('modalities'));
        $paymentStatus = $request->query->get('paymentStatus');

        // Récupérer les données du rapport
        $reportData = $admissionReportService->getReportData($classId, $modalities);

        // Filtrer les données en fonction du statut de paiement et des modalités sélectionnées
        $filteredData = array_filter($reportData, function ($student) use ($paymentStatus, $modalities) {
            $totalPaid = 0;
            $totalRemaining = 0;

            if (!isset($student['modalities']) || !is_array($student['modalities'])) {
                return false; // Ignorer les étudiants sans modalités valides
            }

            foreach ($student['modalities'] as $modality) {
                // Vérifier si la modalité est dans la liste des modalités sélectionnées
                if (!in_array($modality['id'], $modalities)) {
                    continue; // Ignorer les modalités non sélectionnées
                }
                $totalPaid += $modality['totalPaid'] ?? 0;
                $totalRemaining += $modality['remainingAmount'] ?? 0;
            }
            //dd($paymentStatus);
            // Appliquer le filtre en fonction du statut de paiement
            if ($paymentStatus === 'partial') {
                return $totalPaid > 0 && $totalRemaining > 0; // Partiellement payé
            } elseif ($paymentStatus === 'full') {
                return $totalRemaining === 0; // Totalement payé
            } elseif ($paymentStatus === 'unpaid') {
                return $totalPaid === 0; // Totalement impayé
            }

            return true; // Inclure tous les étudiants si aucun statut n'est spécifié
        });

        // Initialiser les totaux pour chaque modalité
        $modalityTotals = [];
        //dd($filteredData);

        // Calculer les totaux après le filtrage
        foreach ($filteredData as $student) {
            for ($j = 0; $j < count($student['modalities']); $j++) {
                //dd($student['modalities'][$j]);
                if (in_array($student['modalities'][$j]['id'], $modalities)) {
                    //dd($i);               
                    $m = $student['modalities'][$j];
                    if (!isset($modalityTotals[$m['id']])) {
                        $modalityTotals[$m['id']] = ['modalLabel' => $m['modalLabel'], 'totalPaid' => 0, 'totalRemaining' => 0];
                    }

                    $modalityTotals[$m['id']]['totalPaid'] += $m['totalPaid'] ?? 0;
                    $modalityTotals[$m['id']]['totalRemaining'] += $m['remainingAmount'] ?? 0;
                }
            }
        }
        //dd($modalityTotals);

        // Rendre la vue PDF avec les données filtrées et les totaux
        $html = $this->renderView('admission/report_pdf.html.twig', [
            'reportData' => $filteredData,
            'modalityTotals' => $modalityTotals, // Passer les totaux au frontend
        ]);

        // Utiliser Dompdf pour générer le PDF
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        // Activer les numéros de page
        $dompdf->render();

        // Ajouter les numéros de page
        $canvas = $dompdf->getCanvas();
        $fontMetrics = $dompdf->getFontMetrics(); // Obtenir l'objet FontMetrics
        $font = $fontMetrics->getFont('Helvetica', 'normal'); // Charger la police

        $canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) use ($font) {
            $text = "Page $pageNumber / $pageCount";
            $fontSize = 10;
            $width = $canvas->get_width();
            $height = $canvas->get_height();
            $textWidth = $fontMetrics->getTextWidth($text, $font, $fontSize); // Utiliser FontMetrics pour obtenir la largeur du texte
            $canvas->text($width - $textWidth - 20, $height - 20, $text, $font, $fontSize);
        });

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="rapport_admission.pdf"',
        ]);
    }

    #[Route('/reports/excel', name: 'app_generate_excel_report', methods: ['GET'])]
    public function generateExcelReport(Request $request, AdmissionReportService $admissionReportService, SchoolClassPaymentModalRepository $repository): Response
    {
        $classId = $request->query->get('classId');
        $modalities = explode(',', $request->query->get('modalities'));
        $paymentStatus = $request->query->get('paymentStatus');

        // Récupérer les données du rapport
        $reportData = $admissionReportService->getReportData($classId, $modalities);

        // Filtrer les données en fonction du statut de paiement
        $filteredData = array_filter($reportData, function ($student) use ($paymentStatus, $modalities) {
            $totalPaid = 0;
            $totalRemaining = 0;

            if (!isset($student['modalities']) || !is_array($student['modalities'])) {
                return false; // Ignorer les étudiants sans modalités valides
            }

            foreach ($student['modalities'] as $modality) {
                if (!in_array($modality['id'], $modalities)) {
                    continue; // Ignorer les modalités non sélectionnées
                }

                $totalPaid += $modality['totalPaid'] ?? 0;
                $totalRemaining += $modality['remainingAmount'] ?? 0;
            }

            if ($paymentStatus === 'partial') {
                return $totalPaid > 0 && $totalRemaining > 0; // Partiellement payé
            } elseif ($paymentStatus === 'full') {
                return $totalRemaining === 0; // Totalement payé
            } elseif ($paymentStatus === 'unpaid') {
                return $totalPaid === 0; // Totalement impayé
            }

            return true; // Inclure tous les étudiants si aucun statut n'est spécifié
        });

        // Créer un fichier Excel avec PhpSpreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Ajouter les en-têtes
        $sheet->setCellValue('A1', 'Nom');
        $sheet->setCellValue('B1', 'Matricule');
        $columnIndex = 3;
        foreach ($modalities as $modality) {
            $modalityEntity = $repository->find($modality);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex) . '1', ($modalityEntity ? $modalityEntity->getLabel() : 'Unknown') . ' (Payé)');
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex + 1) . '1', ($modalityEntity ? $modalityEntity->getLabel() : 'Unknown') . ' (Reste)');
            $columnIndex += 2;
        }
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex) . '1', 'Total Payé');
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex + 1) . '1', 'Total Reste');

        // Ajouter les données des étudiants
        $rowIndex = 2;
        foreach ($filteredData as $student) {
            $sheet->setCellValue('A' . $rowIndex, $student['name']);
            $sheet->setCellValue('B' . $rowIndex, $student['matricule']);

            $totalPaid = 0;
            $totalRemaining = 0;
            $columnIndex = 3;

            //dd($student);

            for ($i = 0; $i < count($modalities); $i++) {
                $paid = $student['modalities'][$i]['totalPaid'] ?? 0;
                $remaining = $student['modalities'][$i]['remainingAmount'] ?? 0;

                $cellCoordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex) . $rowIndex;
                $sheet->setCellValue($cellCoordinate, $paid);
                $cellCoordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex + 1) . $rowIndex;
                $sheet->setCellValue($cellCoordinate, $remaining);

                $totalPaid += $paid;
                $totalRemaining += $remaining;
                $columnIndex += 2;
            }

            $cellCoordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex) . $rowIndex;
            $sheet->setCellValue($cellCoordinate, $totalPaid);
            $cellCoordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex + 1) . $rowIndex;
            $sheet->setCellValue($cellCoordinate, $totalRemaining);

            $rowIndex++;
        }

        // Ajouter les totaux globaux
        $sheet->setCellValue('A' . $rowIndex, 'Total');
        $sheet->mergeCells('A' . $rowIndex . ':B' . $rowIndex);
        $columnIndex = 3;
        $totalTPaid = 0;
        $totalTRemaining = 0;

        for ($i = 0; $i < count($modalities); $i++) {
            $modality = $modalities[$i];
            $totalPaid = array_sum(array_column(array_column(array_column($filteredData, 'modalities'), $i), 'totalPaid'));
            $totalRemaining = array_sum(array_column(array_column(array_column($filteredData, 'modalities'), $i), 'remainingAmount'));

            $cellCoordinatePaid = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex) . $rowIndex;
            $sheet->setCellValue($cellCoordinatePaid, $totalPaid);
            $sheet->getStyle($cellCoordinatePaid)->getNumberFormat()->setFormatCode('_-* # ##0_-;-* # ##0_-;_-* "-"_-;_-@_');

            $cellCoordinateRemaining = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex + 1) . $rowIndex;
            $sheet->setCellValue($cellCoordinateRemaining, $totalRemaining);
            $sheet->getStyle($cellCoordinateRemaining)->getNumberFormat()->setFormatCode('_-* # ##0_-;-* # ##0_-;_-* "-"_-;_-@_');

            $totalTPaid += $totalPaid;
            $totalTRemaining += $totalRemaining;
            $columnIndex += 2;
        }

        $cellCoordinateTotalPaid = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex) . $rowIndex;
        $sheet->setCellValue($cellCoordinateTotalPaid, $totalTPaid);
        $sheet->getStyle($cellCoordinateTotalPaid)->getNumberFormat()->setFormatCode('_-* # ##0_-;-* # ##0_-;_-* "-"_-;_-@_');

        $cellCoordinateTotalRemaining = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex + 1) . $rowIndex;
        $sheet->setCellValue($cellCoordinateTotalRemaining, $totalTRemaining);
        $sheet->getStyle($cellCoordinateTotalRemaining)->getNumberFormat()->setFormatCode('_-* # ##0_-;-* # ##0_-;_-* "-"_-;_-@_');

        // Appliquer des bordures au tableau
        $highestColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex + 1);
        $highestRow = $rowIndex;

        $sheet->getStyle('A1:' . $highestColumn . $highestRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => '000000'], // Couleur noire
                ],
            ],
        ]);

        // Ajuster automatiquement la largeur des colonnes
        foreach (range('A', $highestColumn) as $columnLetter) {
            $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
        }

        // Générer le fichier Excel
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'excel');
        $writer->save($tempFile);

        return $this->file($tempFile, 'rapport_admission.xlsx', ResponseHeaderBag::DISPOSITION_INLINE);
    }

    #[Route('/sections', name: 'app_get_sections', methods: ['GET'])]
    public function getSections(SessionInterface $session, StudyLevelRepository $sectionRepository, SchoolPeriodRepository $schoolPeriodRepository): JsonResponse
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $school = $this->currentSchool;
        // Vérifier si l'utilisateur a une école associée
        if (!$school) {
            return new JsonResponse(['error' => 'Aucune école associée à l\'utilisateur.'], Response::HTTP_BAD_REQUEST);
        }
        $schoolPeriod = $this->currentPeriod;
        // Vérifier si la période scolaire actuelle existe
        if (!$schoolPeriod) {
            return new JsonResponse(['error' => 'Aucune période scolaire actuelle trouvée.'], Response::HTTP_BAD_REQUEST);
        }

        // Récupérer la configuration de base de l'utilisateur
        $configuration = $this->userBaseRepository->findOneBy(['user' => $this->getUser(), 'school' => $school]);

        $sections = [];

        if ($configuration) {
            // Récupérer les sections et classes associées à l'utilisateur
            $sections = count($configuration->getSectionList()) > 0 ? $this->entityManager->getRepository(StudyLevel::class)->findBy(['id' => $configuration->getSectionList()]) : $this->sectionRepository->findAll();
            // Récupérer toutes les sections
            $sections = $sectionRepository->findBy(['id' => $sections], ['name' => 'ASC']);
        } else {
            if (in_array('ROLE_ADMIN', $this->getUser()->getRoles())) {
                // Si l'utilisateur est admin, récupérer toutes les sections
                $sections = $sectionRepository->findAll();
            } else {
                $sections = [];
            }
        }

        $data = [];
        foreach ($sections as $section) {
            $data[] = [
                'id' => $section->getId(),
                'name' => $section->getName(),
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/modalities/by-class', name: 'app_modalities_by_class', methods: ['GET'])]
    public function getModalitiesByClass(SessionInterface $session, Request $request, SchoolClassPaymentModalRepository $repository, SchoolPeriodRepository $schoolPeriodRepository): JsonResponse
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $classId = $request->query->get('classId');
        $school = $this->currentSchool;
        // Vérifier si l'utilisateur a une école associée
        if (!$school) {
            return new JsonResponse(['error' => 'Aucune école associée à l\'utilisateur.'], Response::HTTP_BAD_REQUEST);
        }
        $schoolPeriod = $this->currentPeriod;
        $modalities = $repository->findBy(['schoolClassPeriod' => $classId, 'schoolPeriod' => $schoolPeriod, 'school' => $school], ['modalPriority' => 'ASC']);

        $data = [];
        foreach ($modalities as $modality) {
            $data[] = [
                'id' => $modality->getId(),
                'label' => $modality->getLabel(),
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/transferts', name: 'app_admission_transferts', methods: ['GET', 'POST'])]
    public function admissionTransferts(SessionInterface $session, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $school = $this->currentSchool;

        $studyLevels = $entityManager->getRepository(StudyLevel::class)->findAll();
        if (!in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles())) {
            $config = $this->userBaseRepository->findOneBy(['user' => $this->getUser(), 'school' => $school]);
            if ($config) {
                $studyLevels = count($studyLevels) > 0 ? array_filter($studyLevels, function ($level) use ($config) {
                    return in_array($level->getId(), $config->getSectionList());
                }) : $studyLevels;
            } else {
                $studyLevels = [];
            }
        }
        return $this->render('admission/transferts.html.twig', [
            'studyLevels' => $studyLevels,
        ]);
    }

    #[Route('/students/by-class', name: 'app_students_by_class', methods: ['GET'])]
    public function getStudentsByClass(SessionInterface $session, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $classOccurenceId = $request->query->get('classOccurenceId');
        if (!$classOccurenceId) {
            return new JsonResponse(['error' => 'Classe non spécifiée.'], 400);
        }

        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        // On récupère la période scolaire active
        $school = $this->currentSchool;
        $period = $this->currentPeriod;

        // On récupère le SchoolClassPeriod correspondant à la classe et à la période
        $schoolClassPeriod = $entityManager->getRepository(\App\Entity\SchoolClassPeriod::class)
            ->findOneBy([
                'classOccurence' => $classOccurenceId,
                'school' => $school,
                'period' => $period
            ]);

        if (!$schoolClassPeriod) {
            return new JsonResponse([]);
        }

        // On récupère les StudentClass liés à ce SchoolClassPeriod
        $studentClasses = $entityManager->getRepository(\App\Entity\StudentClass::class)
            ->findBy(['schoolClassPeriod' => $schoolClassPeriod]);

        $data = [];
        foreach ($studentClasses as $studentClass) {
            $student = $studentClass->getStudent();
            $data[] = [
                'id' => $student->getId(),
                'fullName' => $student->getFullName(),
                'regNumber' => $student->getRegistrationNumber(),
                'birthDate' => $student->getDateOfBirth() ? $student->getDateOfBirth()->format('d/m/Y') : '',
                'studentClassId' => $studentClass->getId(),
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/classes/destinations', name: 'app_classes_destinations', methods: ['GET'])]
    public function getDestinationClasses(SessionInterface $session, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $excludeId = $request->query->get('excludeId');
        $qb = $entityManager->createQueryBuilder();
        $qb->select('c')
            ->from(\App\Entity\ClassOccurence::class, 'c');
        if ($excludeId) {
            $period = $this->currentPeriod;
            $qb->where('c.id != :excludeId')
                ->andWhere('c.id IN (
                   SELECT IDENTITY(scp.classOccurence)
                   FROM App\Entity\SchoolClassPeriod scp
                   WHERE scp.school = :school AND scp.period = :period
               )')
                ->setParameter('school', $this->currentSchool)
                ->setParameter('period', $period)
                ->setParameter('excludeId', $excludeId);
        }
        $classes = $qb->getQuery()->getResult();


        $data = [];
        foreach ($classes as $class) {
            $data[] = [
                'id' => $class->getId(),
                'name' => $class->getName(),
            ];
        }
        return new JsonResponse($data);
    }

    #[Route('/students/transfer-mass', name: 'app_transfer_students_mass', methods: ['POST'])]
    public function transferStudentsMass(SessionInterface $session, Request $request, EntityManagerInterface $entityManager, OperationLogger $operationLogger): JsonResponse
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $transfers = $request->request->all('transfers');
        if (!is_array($transfers) || empty($transfers)) {
            return new JsonResponse(['error' => 'Aucun transfert à effectuer.'], 400);
        }

        $success = 0;
        $errors = [];

        foreach ($transfers as $transfer) {
            $studentClassId = $transfer['studentClassId'] ?? null;
            $destinationClassId = $transfer['destinationClassId'] ?? null;

            if (!$studentClassId || !$destinationClassId) {
                $errors[] = $transfer;
                continue;
            }

            $studentClass = $entityManager->getRepository(\App\Entity\StudentClass::class)->find($studentClassId);
            $period = $this->currentPeriod;
            $destinationSchoolClassPeriod = $entityManager->getRepository(\App\Entity\SchoolClassPeriod::class)->findOneBy(['classOccurence' => $destinationClassId, 'period' => $period, 'school' => $this->currentSchool]);

            if ($studentClass && $destinationSchoolClassPeriod) {
                $studentClass->setSchoolClassPeriod($destinationSchoolClassPeriod);
                $entityManager->persist($studentClass);
                $success++;
            } else {
                $errors[] = $transfer;
            }
        }

        try {
            $entityManager->flush();
            // Log the successful transfers
            $operationLogger->log(
                'Transfert des étudiants', 
                'success', 
                'schoolClassPeriod, StudentClass',
                null,
                null,
                [
                    $this->currentSchool, 
                    $this->currentPeriod
                ]
            );

            $this->addFlash('success', "$success transfert(s) effectué(s) avec succès.");
            // Return a success response
            return new JsonResponse(['message' => 'Transfert(s) effectué(s) avec succès.']);
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // Log the error
            $operationLogger->log(
                'Transfert des étudiants', 
                'error', 
                'schoolClassPeriod, StudentClass',
                null,
                null,
                [
                    $this->currentSchool, 
                    $this->currentPeriod
                ],
                $e->getMessage()
            );
            return new JsonResponse(['error' => 'Une erreur est survenue lors du transfert.'], 500);
        }

        return new JsonResponse([
            'success' => $success,
            'errors' => $errors,
            'message' => $success . ' transfert(s) effectué(s).'
        ]);
    }

    public function getConnectedUser(): User
    {
        return $this->getUser();
    }

    #[Route('/inscription', name: 'app_student_inscription')]
    public function studentInscription(Request $request, EntityManagerInterface $entityManager): Response
    {

        $this->entityManager= $entityManager;
        // Récupérer les niveaux d'étude disponibles
        $studyLevels = $entityManager->getRepository(StudyLevel::class)->findAll();
        if(!in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles())) {
            $user = $this->getConnectedUser();
            $config = $user->getBaseConfigurations()->toArray();
            if (count($config) > 0) {
                $studyLevels = count($config[0]->getSectionList()) > 0 ? $this->entityManager->getRepository(StudyLevel::class)->findBy(['id' => $config[0]->getSectionList()]) : $studyLevels;
            } else {
                $studyLevels = [];
            }
        }

        return $this->render('admission/inscription.html.twig', [
            'sections' => $studyLevels
        ]);
    }

    #[Route('/students/list', name: 'app_students_list', methods: ['GET'])]
    public function studentsList(SessionInterface $session, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $students = $entityManager->getRepository(\App\Entity\User::class)->findAll();
        $students=array_filter($students,function($student){
            return in_array('ROLE_STUDENT',$student->getRoles());
        });
        $schoolClassPeriod = $entityManager->getRepository(\App\Entity\SchoolClassPeriod::class)->findBy(['school' => $this->currentSchool, 'period' => $this->currentPeriod]);
        
        $data = [];
        foreach ($students as $student) {
            $isRegistered = false;
            foreach ($schoolClassPeriod as $scp) {
                //$studentClass = $entityManager->getRepository(\App\Entity\StudentClass::class)->findOneBy(['student' => $student, 'schoolClassPeriod' => $scp]);
                $studentClass = $student->getStudentClasses()->filter(function($sc) use ($scp) {
                    return $sc->getSchoolClassPeriod() === $scp;
                })->first();
                if ($studentClass) {
                    $isRegistered = true;
                    break;
                }
            }
            $data[] = [
                'id' => $student->getId(),
                'fullName' => $student->getFullName(),
                'birthDate' => $student->getDateOfBirth() ? $student->getDateOfBirth()->format('d/m/Y') : '',
                'registrationNumber' => $student->getRegistrationNumber(),
                'isRegistered' => $isRegistered,
            ];
        }

        return new JsonResponse(['data' => $data]);
    }

    #[Route('/registered-students-list', name: 'print_students_list', methods: ['GET'])]
    public function printStudentsList(Request $request, EntityManagerInterface $entityManager): Response
    {
        // Récupérer les niveaux d'étude disponibles
        $studyLevels = $entityManager->getRepository(\App\Entity\StudyLevel::class)->findAll();
        return $this->render('admission/print_students_list.html.twig', [
            'studyLevels' => $studyLevels
        ]);
    }

    #[Route('/print-students-list', name: 'app_print_students_list', methods: ['GET'])]
    public function getStudentsList(Request $request, EntityManagerInterface $entityManager,SessionInterface $session): Response
    {
        $levelId = $request->query->get('studyLevel');
        $classId = $request->query->get('classSelect');
        $format = $request->query->get('format', 'html');
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        if (!$levelId || !$classId) {
            return new Response('<div class="alert alert-warning">Veuillez sélectionner un niveau et une classe.</div>');
        }
        // Récupérer la classe et les élèves inscrits
        $class = $entityManager->getRepository(\App\Entity\SchoolClassPeriod::class)->find($classId);
        $studentClasses = $entityManager->getRepository(\App\Entity\StudentClass::class)->findBy(['schoolClassPeriod' => $class]);
        $students = array_map(function($sc) { return $sc->getStudent(); }, $studentClasses);
        
        // Rendu HTML partiel pour AJAX
        return $this->render('admission/_students_list.html.twig', [
            'students' => $students,
            'class' => $class,
            'school' => $this->currentSchool,
            'period' => $this->currentPeriod,
        ]);
    }

    #[Route('/print-students-list/pdf', name: 'app_print_students_list_pdf', methods: ['GET'])]
    public function getStudentsListPdf(Request $request, EntityManagerInterface $entityManager): Response
    {
        $levelId = $request->query->get('studyLevel');
        $classId = $request->query->get('classSelect');
        if (!$levelId || !$classId) {
            return new Response('Paramètres manquants.', 400);
        }
        $class = $entityManager->getRepository(\App\Entity\SchoolClassPeriod::class)->find($classId);
        $studentClasses = $entityManager->getRepository(\App\Entity\StudentClass::class)->findBy(['schoolClassPeriod' => $class]);
        $students = array_map(function($sc) { return $sc->getStudent(); }, $studentClasses);
        $html = $this->renderView('admission/_students_list_pdf.html.twig', [
            'students' => $students,
            'class' => $class
        ]);
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="liste_eleves.pdf"',
        ]);
    }
    #[Route('/admission/finance-report', name: 'app_admission_finance_report')]
    public function financeReport(SessionInterface $session, Request $request, EntityManagerInterface $em): Response
    {
        $this->session = $session;
        $this->entityManager = $em;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $schoolClassPeriod = $this->entityManager->getRepository(\App\Entity\SchoolClassPeriod::class)->findBy(['school' => $this->currentSchool, 'period' => $this->currentPeriod]);
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');
        $payments = [];
        if ($startDate && $endDate) {
            $qb = $em->getRepository(\App\Entity\SchoolClassAdmissionPayment::class)->createQueryBuilder('p')
                ->join('p.student', 'student')
                ->join('p.schoolClassPeriod', 'class')
                ->join('class.classOccurence', 'classOccurence')
                ->join('p.paymentModal', 'modality')
                ->where('p.paymentDate BETWEEN :start AND :end')
                ->andWhere('p.schoolClassPeriod IN (:classes)')
                ->setParameter('start', $startDate.' 00:00:00')
                ->setParameter('end', $endDate.' 23:59:59')
                ->setParameter('classes', $schoolClassPeriod)
                ->orderBy('p.paymentDate', 'ASC')
                ->addOrderBy('classOccurence.name', 'ASC')
                ->addOrderBy('student.fullName', 'ASC')
                ->addOrderBy('modality.label', 'ASC');
            $payments = $qb->getQuery()->getResult();
        }
        return $this->render('admission/finance_report.html.twig', [
            'payments' => $payments,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
    }

    #[Route('/finance-report/export-excel', name: 'app_admission_finance_report_export_excel', methods: ['GET'])]
    public function financeReportExportExcel(SessionInterface $session, Request $request, EntityManagerInterface $em, SchoolClassPaymentModalRepository $repository): Response
    {
        $this->session = $session;
        $this->entityManager = $em;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $schoolClassPeriod = $this->entityManager->getRepository(\App\Entity\SchoolClassPeriod::class)->findBy(['school' => $this->currentSchool, 'period' => $this->currentPeriod]);
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');
        $payments = [];
        if ($startDate && $endDate) {
            $qb = $em->getRepository(\App\Entity\SchoolClassAdmissionPayment::class)->createQueryBuilder('p')
                ->join('p.student', 'student')
                ->join('p.schoolClassPeriod', 'class')
                ->join('class.classOccurence', 'classOccurence')
                ->join('p.paymentModal', 'modality')
                ->where('p.paymentDate BETWEEN :start AND :end')
                ->andWhere('p.schoolClassPeriod IN (:classes)')
                ->setParameter('start', $startDate.' 00:00:00')
                ->setParameter('end', $endDate.' 23:59:59')
                ->setParameter('classes', $schoolClassPeriod)
                ->orderBy('p.paymentDate', 'ASC')
                ->addOrderBy('classOccurence.name', 'ASC')
                ->addOrderBy('student.fullName', 'ASC')
                ->addOrderBy('modality.label', 'ASC');
            $payments = $qb->getQuery()->getResult();
        }

        // Grouper les paiements par date
        $grouped = [];
        $globalTotal = 0;
        foreach ($payments as $payment) {
            $date = $payment->getPaymentDate() ? $payment->getPaymentDate()->format('d/m/Y') : '';
            if (!isset($grouped[$date])) {
                $grouped[$date] = [];
            }
            $grouped[$date][] = $payment;
            $globalTotal += $payment->getPaymentAmount();
        }

        // Générer le fichier Excel avec mention d'export et pagination
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Mention exporté le ... par ...
        $user = $this->getConnectedUser();
        if ($user) {
            if (method_exists($user, '__toString')) {
                $userName = (string)$user;
            } elseif (property_exists($user, 'fullName') && $user->getFullName()) {
                $userName = $user->getFullName();
            } elseif (property_exists($user, 'username') && $user->getUsername()) {
                $userName = $user->getUsername();
            } else {
                $userName = 'Utilisateur';
            }
        } else {
            $userName = 'Utilisateur inconnu';
        }
        $exportedAt = (new \DateTime())->format('d/m/Y H:i');
        $mention = 'Exporté le : ' . $exportedAt . ' par : ' . $userName;
        $sheet->setCellValue('A1', $mention);
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->getFont()->setItalic(true);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);

        // En-têtes
        $headers = ['Date', 'Classe', 'Élève', 'Modalité', 'Montant'];
        $sheet->fromArray($headers, null, 'A2');
        // Style en-tête
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F81BD']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ];
        $sheet->getStyle('A2:E2')->applyFromArray($headerStyle);

        $row = 3;
        foreach ($grouped as $date => $datePayments) {
            $dateTotal = 0;
            foreach ($datePayments as $payment) {
                $sheet->setCellValue('A'.$row, $date);
                $sheet->setCellValue('B'.$row, $payment->getSchoolClass()->getClassOccurence()->getName());
                $sheet->setCellValue('C'.$row, $payment->getStudent()->getFullName());
                $sheet->setCellValue('D'.$row, $payment->getPaymentModal()->getLabel());
                $sheet->setCellValue('E'.$row, $payment->getPaymentAmount());
                $dateTotal += $payment->getPaymentAmount();
                $row++;
            }
            // Ligne de sous-total
            $sheet->setCellValue('A'.$row, 'Sous-total '.$date);
            $sheet->mergeCells('A'.$row.':D'.$row);
            $sheet->setCellValue('E'.$row, $dateTotal);
            $sheet->getStyle('A'.$row.':E'.$row)->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'D9E1F2']
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ]
            ]);
            $row++;
        }
        // Ligne de total global
        $sheet->setCellValue('A'.$row, 'Total général');
        $sheet->mergeCells('A'.$row.':D'.$row);
        $sheet->setCellValue('E'.$row, $globalTotal);
        $sheet->getStyle('A'.$row.':E'.$row)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);

        // Bordures sur tout le tableau
        $sheet->getStyle('A2:E'.$row)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);

        // Ajuster la largeur des colonnes
        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Pagination en pied de page
        $sheet->getHeaderFooter()->setOddFooter('&RPage &P / &N');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'finance_report_excel');
        $writer->save($tempFile);
        return $this->file($tempFile, 'rapport_financier_admissions.xlsx', ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }
}
