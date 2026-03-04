<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Repository\UserBaseConfigurationsRepository;
use App\Repository\SchoolClassPeriodRepository;
use App\Repository\SchoolPeriodRepository;
use App\Repository\StudentClassRepository;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Color\Color;
use Dompdf\Options;
use Dompdf\Dompdf;
use App\Entity\RegistrationCardBaseConfig;
use App\Service\ImageOptimizer;
use App\Service\OperationLogger;
use App\Repository\ClasseRepository;
use App\Repository\SchoolSectionRepository;
use App\Repository\StudyLevelRepository;
use App\Entity\SchoolPeriod;
use App\Entity\School;
use App\Entity\User;
use App\Entity\StudyLevel;
use App\Entity\StudentClass;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Entity\SchoolSection;
use App\Entity\SchoolClassPeriod;
use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ManagerRegistry;

#[Route('/registration-card', defaults: ['_module' => 'registration_card'])]
class RegistrationCardController extends AbstractController
{

    private $schoolSectionRepository;
    private $userRepository;
    private $userBaseConfigurationsRepository;
    private $schoolClassRepository;
    private $schoolPeriodRepository;
    private $studentRepository;
    private $classeRepository;
    private ImageOptimizer $imageOptimizer;
    private OperationLogger $operationLogger;
    private School $currentSchool;
    private SchoolPeriod $currentPeriod;
    private SessionInterface $session;
    private EntityManagerInterface $entityManager;

    public function __construct(
        StudyLevelRepository $schoolSectionRepository,
        UserRepository $userRepository,
        UserBaseConfigurationsRepository $userBaseConfigurationsRepository,
        SchoolClassPeriodRepository $schoolClassRepository,
        SchoolPeriodRepository $schoolPeriodRepository,
        StudentClassRepository $studentRepository,
        ClasseRepository $classeRepository,
        EntityManagerInterface $entityManager,
        ImageOptimizer $imageOptimizer,
        OperationLogger $operationLogger
    ) {
        $this->schoolSectionRepository = $schoolSectionRepository;
        $this->userRepository = $userRepository;
        $this->userBaseConfigurationsRepository = $userBaseConfigurationsRepository;
        $this->schoolClassRepository = $schoolClassRepository;
        $this->schoolPeriodRepository = $schoolPeriodRepository;
        $this->studentRepository = $studentRepository;
        $this->classeRepository = $classeRepository;
        $this->entityManager = $entityManager;
        $this->imageOptimizer = $imageOptimizer;
        $this->operationLogger = $operationLogger;
    }
    /**
     * @Route("/registration-card", name="app_registration_card")
     */
    #[Route('', name: 'app_registration_card', methods: ['GET'])]
    public function index(SessionInterface $session): Response
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $school = $this->currentSchool;
        if (!$school) {
            return $this->redirectToRoute('app_choose_school');
        }
        $config = $this->userBaseConfigurationsRepository->findOneBy(['user' => $user, 'school' => $school]);
        if (!$config) {
            $schoolSections = $this->entityManager->getRepository(StudyLevel::class)->findAll();
        } else {
            $sections = count($config->getSectionList()) > 0 ? $config->getSectionList() : array_map(fn($sl) => $sl->getId(), $this->schoolSectionRepository->findAll());
            $schoolSections = $this->schoolSectionRepository->findBy(['id' => $sections]);
            $schoolSections = array_map(function ($s) {
                return ['id' => $s->getId(), 'name' => $s->getName()];
            }, $schoolSections);
        }


        return $this->render('registration_card/index.html.twig', [
            'sections' => $schoolSections,
        ]);
    }

    #[Route('/classes/{sectionId}', name: 'app_registration_card_classes', methods: ['GET'])]
    public function getClassesBySection(SessionInterface $session, Request $request, SchoolSectionRepository $schoolSectionRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));

        $sectionId = $request->get('sectionId');
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
        }
        $school = $this->currentSchool;
        if (!$school) {
            return new JsonResponse(['error' => 'School not found'], Response::HTTP_UNAUTHORIZED);
        }
        $config = $this->userBaseConfigurationsRepository->findOneBy(['user' => $user, 'school' => $school]);
        if (!$config) {
            $sections = [];
            if (in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles())) {
                $sections = $this->entityManager->getRepository(StudyLevel::class)->findAll();
                $sections = array_map(function ($section) {
                    return $section->getId();
                }, $sections);
            }
        } else {
            $sections = count($config->getSectionList()) > 0 ? $config->getSectionList() : array_map(fn($sl) => $sl->getId(), $this->schoolSectionRepository->findAll());
            $sections = $this->entityManager->getRepository(StudyLevel::class)->findBy(['id' => $sections]);
            $sections = array_map(function ($s) {
                return $s->getId();
            }, $sections);
        }
        if (!in_array($sectionId, $sections)) {
            return new JsonResponse(['error' => 'StudyLevel not found'], Response::HTTP_UNAUTHORIZED);
        }

        $period = $this->currentPeriod;
        $schoolClassPeriod = $this->schoolClassRepository->findBy(['school' => $school, 'period' => $period]);
        $classes = array_filter($schoolClassPeriod, function ($class) use ($sectionId) {
            return $class->getClassOccurence()->getClasse()->getStudyLevel() && $class->getClassOccurence()->getClasse()->getStudyLevel()->getId() == $sectionId;
        });

        if ($config) {
            $authorizedClasses = count($config->getClassList()) > 0 ? $config->getClassList() : array_map(fn($sl) => $sl->getId(), $classes);
            $authorizedClasses = $this->entityManager->getRepository(SchoolClassPeriod::class)->findBy(['id' => $authorizedClasses]);
            $authorizedClasses = array_map(function ($class) {
                return $class->getClassOccurence()->getId();
            }, $authorizedClasses);
        } else {
            $authorizedClasses = [];
            if (in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles())) {
                $authorizedClasses = array_map(function ($class) {
                    return $class->getClassOccurence()->getId();
                }, $classes);
            }
        }

        $classes = array_filter($classes, function ($class) use ($authorizedClasses) {
            return in_array($class->getClassOccurence()->getId(), $authorizedClasses);
        });
        if (empty($classes)) {
            return new JsonResponse(['error' => 'No classes found'], Response::HTTP_NOT_FOUND);
        }

        $data = [];
        foreach ($classes as $class) {
            $data[] = [
                'id' => $class->getClassOccurence()->getId(),
                'name' => $class->getClassOccurence()->getName(),
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/students/{classId}', name: 'app_registration_card_students', methods: ['GET'])]
    public function getStudentsByClass(SessionInterface $session, Request $request, UserRepository $userRepository): JsonResponse
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));

        $classId = $request->get('classId');
        if ($classId != null) {
            $user = $this->getUser();
            if (!$user) {
                return new JsonResponse(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
            }
            $school = $this->currentSchool;
            if (!$school) {
                return new JsonResponse(['error' => 'School not found'], Response::HTTP_UNAUTHORIZED);
            }
            $schoolPeriod = $this->currentPeriod;
            $class = $this->schoolClassRepository->findBy(['classOccurence' => $classId, 'school' => $school, 'period' => $schoolPeriod]);
            if (!$class) {
                return new JsonResponse(['error' => 'Class not found'], Response::HTTP_NOT_FOUND);
            }
            // Récupérer les élèves pour la classe donnée
            $students = $this->studentRepository->findBy(['schoolClassPeriod' => $class]);
            if (empty($students)) {
                return new JsonResponse(['error' => 'No students found'], Response::HTTP_NOT_FOUND);
            }

            // Transformer les données en JSON
            $data = [];
            foreach ($students as $student) {
                $data[] = [
                    'matricule' => $student->getStudent()->getRegistrationNumber(),
                    'name' => $student->getStudent()->getFullName(),
                    'photo' => $student->getStudent()->getPhoto(),
                    'school' => $school,
                ];
            }

            return new JsonResponse($data);
        } else {
            return new JsonResponse([]);
        }
    }

    #[Route('/print/{matricule}', name: 'app_registration_card_print', methods: ['GET'])]
    public function printCard(Request $request, SessionInterface $session, string $matricule, UserRepository $userRepository): Response
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $classe = $request->get('classe');
        // Récupérer l'élève par son matricule
        $student = $userRepository->findOneBy(['registrationNumber' => $matricule]);

        if (!$student) {
            throw $this->createNotFoundException('Élève non trouvé.');
        }

        $school = $this->currentSchool;
        if (!$school) {
            throw $this->createNotFoundException('École non trouvée.');
        }
        $config = $school->getRegistrationBaseConfig();
        if (!$config) {
            $this->addFlash('error', 'Configuration de la carte non trouvée. Veuillez configurer la carte scolaire avant de l\'imprimer.');
            return $this->redirectToRoute('app_registration_card_configuration');
        }

        $gender = $student->getGender();
        $student->genderString = $gender instanceof \App\Contract\GenderEnum ? $gender->value : (string) $gender;

        $schoolClassPeriod = $this->schoolClassRepository->findOneBy(['school' => $this->currentSchool, 'period' => $this->currentPeriod, 'classOccurence' => $classe]);
        $studentInfos = $this->entityManager->getRepository(StudentClass::class)->findOneBy(['student' => $student, 'schoolClassPeriod' => $schoolClassPeriod]);

        // Générer le QR code
        $qrCode = new QrCode(
            "Matricule: {$student->getRegistrationNumber()}, Nom: {$student->getFullName()}, Classe: {$studentInfos->getSchoolClassPeriod()->getClassOccurence()->getName()}",
            new Encoding('UTF-8'),
            ErrorCorrectionLevel::Low,
            70,
            0,
            RoundBlockSizeMode::Margin,
            new Color(0, 0, 0),
            new Color(255, 255, 255)
        );

        $writer = new PngWriter();
        $qrCodeImage = $writer->write($qrCode);

        // Convertir le QR code en URI de données pour l'afficher dans Twig
        $qrCodeDataUri = $qrCodeImage->getDataUri();
        // Create generic label sans arguments nommés pour compatibilité PHP < 8.0
        $label = new Label(
            'Label',
            new \Endroid\QrCode\Label\Font\Font(__DIR__ . '/../../vendor/endroid/qr-code/assets/open_sans.ttf', 16),
            \Endroid\QrCode\Label\LabelAlignment::Center,
            new \Endroid\QrCode\Label\Margin\Margin(0, 10, 10, 10),
            new Color(255, 0, 0)
        );

        $result = $writer->write($qrCode, null, $label);

        // Validate the result
        $writer->validateResult($result, "Matricule: {$student->getRegistrationNumber()}, Nom: {$student->getFullName()}, Classe: {$studentInfos->getSchoolClassPeriod()->getClassOccurence()->getName()}");

        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true); // Autorise le chargement des fichiers locaux
        $options->set('isHtml5ParserEnabled', true); // Active le parser HTML5
        $options->set('isPhpEnabled', true); // Autorise le PHP dans les templates

        $dompdf = new Dompdf($options);

        $config = $school->getRegistrationBaseConfig();

        $imagePath = $this->getParameter('kernel.project_dir') . '/public/uploads/' . $config->getCardBg();;
        $imageData = base64_encode(file_get_contents($imagePath));
        $imageBase64 = 'data:image/png;base64,' . $imageData;

        $imagePath = $this->getParameter('kernel.project_dir') . '/public/uploads/' . $school->getLogo();
        if (!file_exists($imagePath) || !is_file($imagePath)) {
            // Si l'image n'existe pas, utiliser une image par défaut
            $imagePath = $this->getParameter('kernel.project_dir') . '/public/img/logo_test.png'; // Chemin par défaut si l'image n'existe pas
        }
        $imageData = base64_encode(file_get_contents($imagePath));
        $imageLogoBase64 = 'data:image/png;base64,' . $imageData;

        $imagePath = $this->getParameter('kernel.project_dir') . '/public/uploads/' . $config->getHeadOfficerSign();;
        if (!file_exists($imagePath) || !is_file($imagePath)) {
            // Si l'image n'existe pas, utiliser une image par défaut
            $imagePath = $this->getParameter('kernel.project_dir') . '/public/img/logo_test.png'; // Chemin par défaut si l'image n'existe pas
        }
        $imageData = base64_encode(file_get_contents($imagePath));
        $imageHeadOfficerBase64 = 'data:image/png;base64,' . $imageData;

        $imagePath = $this->getParameter('kernel.project_dir') . '/public/uploads/' . $student->getPhoto();
        if (!file_exists($imagePath) || !is_file($imagePath)) {
            // Si l'image n'existe pas, utiliser une image par défaut
            $imagePath = $this->getParameter('kernel.project_dir') . '/public/img/default_student.png'; // Chemin par défaut si l'image n'existe pas
        }
        $imageData = base64_encode(file_get_contents($imagePath));
        $imageStudentPhotoBase64 = 'data:image/png;base64,' . $imageData;


        $tutor = $studentInfos->getStudent()->getTutor() !== null ? $this->userRepository->findOneBy(['id' => $studentInfos->getStudent()->getTutor()->getId()]) : null;
        if ($tutor) {
            $student->tutorName = $tutor->getFullName();
            $student->tutorPhone = $tutor->getPhone();
            $student->tutorEmail = $tutor->getEmail();
        } else {
            $student->tutorName = '';
            $student->tutorPhone = '';
            $student->tutorEmail = '';
        }

        // Générer la vue ou le PDF pour la carte scolaire
        $html = $this->renderView('registration_card/print.html.twig', [
            'student' => $student,
            'studentInfos' => $studentInfos,
            'qrCode' => $qrCodeDataUri,
            'imageBase64' => $imageBase64,
            'imageLogoBase64' => $imageLogoBase64,
            'school' => $school,
            'baseConfig' => $config,
            'imageHeadOfficerBase64' => $imageHeadOfficerBase64,
            'imageStudentPhotoBase64' => $imageStudentPhotoBase64,
        ]);

        // Ajouter des marges via CSS
        $html = '<style>@page { margin: 0cm; }</style>' . $html;


        // Charger le HTML dans DOMPDF
        $dompdf->loadHtml($html);

        // Définir la taille et l'orientation de la page
        $dompdf->setPaper([0, 0, 411, 278], 'portrait'); // Dimensions en points (1 pouce = 72 points)

        // Générer le PDF
        $dompdf->render();

        // Retourner le PDF en tant que réponse
        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="recu_paiement.pdf"',
        ]);
    }

    #[Route('/configuration', name: 'app_registration_card_configuration', methods: ['GET', 'POST'])]
    public function configureCard(SessionInterface $session, Request $request): Response
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $user = $this->getUser();
        $school = $this->currentSchool;

        if (!$school) {
            throw $this->createNotFoundException('École non trouvée.');
        }

        // Vérifier si une configuration existe déjà
        $config = $school->getRegistrationBaseConfig();
        if (!$config) {
            $config = new RegistrationCardBaseConfig();
            $school->setRegistrationBaseConfig($config); // Associer la configuration à l'école
            $this->entityManager->persist($config);
        }

        if ($request->isMethod('POST')) {
            // Récupérer les données du formulaire
            $cardHeader = $request->request->get('cardHeader');
            $nationalMotto = $request->request->get('nationalMotto');
            $signTitle = $request->request->get('signTitle');
            $cardHeaderA = $request->request->get('cardHeaderA');
            $nationalMottoA = $request->request->get('nationalMottoA');
            $headOfficerSignFile = $request->files->get('headOfficerSign');
            $cardBgFile = $request->files->get('cardBg');
            $doubleHeaderLayout = $request->request->get('doubleHeaderLayout') === '1' ? true : false;

            // Mettre à jour les propriétés de la configuration
            $config->setCardHeader($cardHeader);
            $config->setNationalMotto($nationalMotto);
            $config->setSignTitle($signTitle);
            $config->setCardHeaderA($cardHeaderA ?: null);
            $config->setNationalMottoA($nationalMottoA ?: null);
            $config->setDoubleHeaderLayout($doubleHeaderLayout);

            // Gérer les fichiers uploadés
            if ($headOfficerSignFile) {
                // Supprimer l'ancienne image si elle existe
                if ($config->getHeadOfficerSign()) {
                    $oldFilePath = $this->getParameter('uploads_directory') . '/' . $config->getHeadOfficerSign();
                    if (file_exists($oldFilePath)) {
                        unlink($oldFilePath);
                    }
                }

                // Enregistrer la nouvelle image
                $newFilename = uniqid() . '.' . $headOfficerSignFile->guessExtension();
                $filePath = $this->getParameter('uploads_directory') . '/' . $newFilename;
                $headOfficerSignFile->move($this->getParameter('uploads_directory'), $newFilename);

                // Optimiser l'image
                $this->imageOptimizer->optimize($filePath);

                $config->setHeadOfficerSign($newFilename);
            }

            if ($cardBgFile) {
                // Supprimer l'ancienne image si elle existe
                if ($config->getCardBg()) {
                    $oldFilePath = $this->getParameter('uploads_directory') . '/' . $config->getCardBg();
                    if (file_exists($oldFilePath)) {
                        unlink($oldFilePath);
                    }
                }

                // Enregistrer la nouvelle image
                $newFilename = uniqid() . '.' . $cardBgFile->guessExtension();
                $filePath = $this->getParameter('uploads_directory') . '/' . $newFilename;
                $cardBgFile->move($this->getParameter('uploads_directory'), $newFilename);

                // Optimiser l'image
                $this->imageOptimizer->optimize($filePath);

                $config->setCardBg($newFilename);
            }

            // Persister les modifications
            $school->setRegistrationBaseConfig($config); // Associer la configuration à l'école
            $this->entityManager->persist($school); // L'école est mise à jour avec la configuration
            try {
                $this->entityManager->flush();

                // Enregistrer l'opération dans les logs
                $this->operationLogger->log(
                    'MODIFICATION DE LA CONFIGURATION DE LA CARTE SCOLAIRE',
                    'success',
                    'RegistrationCardBaseConfig',
                    $config->getId(),
                    null,
                    [
                        'school_id' => $school->getId(),
                        'config' => [
                            'cardHeader' => $cardHeader,
                            'nationalMotto' => $nationalMotto,
                            'signTitle' => $signTitle,
                            'cardHeaderA' => $cardHeaderA,
                            'nationalMottoA' => $nationalMottoA,
                            'doubleHeaderLayout' => $doubleHeaderLayout,
                        ],
                    ]
                );

                $this->addFlash('success', 'Configuration enregistrée avec succès.');

                return $this->redirectToRoute('app_registration_card_configuration');
            } catch (\Exception $e) {
                if (!$this->entityManager->isOpen()) {
                    $this->entityManager = $doctrine->resetManager();
                }
                // logger le message d'erreur
                $this->operationLogger->log(
                    'MODIFICATION DE LA CONFIGURATION DE LA CARTE SCOLAIRE',
                    'error',
                    'RegistrationCardBaseConfig',
                    $config->getId(),
                    $e->getMessage(),
                    [
                        'school_id' => $school->getId(),
                        'config' => [
                            'cardHeader' => $cardHeader,
                            'nationalMotto' => $nationalMotto,
                            'signTitle' => $signTitle,
                            'cardHeaderA' => $cardHeaderA,
                            'nationalMottoA' => $nationalMottoA,
                            'doubleHeaderLayout' => $doubleHeaderLayout,
                        ],
                        'period' => $this->currentPeriod->getName(),
                        'school' => $school->getName(),
                    ]
                );
                $this->addFlash('error', 'Erreur lors de l\'enregistrement de la configuration : ' . $e->getMessage());
            }
        }

        return $this->render('registration_card/configuration.html.twig', [
            'config' => $config,
            'school' => $school,
        ]);
    }

    #[Route('/upload-photo', name: 'app_registration_card_upload_photo', methods: ['POST'])]
    public function uploadPhoto(Request $request, SessionInterface $session, EntityManagerInterface $entityManager,ManagerRegistry $doctrine): JsonResponse
    {
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $matricule = $request->request->get('matricule');
        $photoFile = $request->files->get('photo');

        if (!$matricule || !$photoFile) {
            return new JsonResponse(['error' => 'Matricule ou fichier manquant.'], 400);
        }

        // Rechercher l'élève par son matricule
        $student = $this->userRepository->findOneBy(['registrationNumber' => $matricule]);

        if (!$student) {
            return new JsonResponse(['error' => 'Élève non trouvé.'], 404);
        }

        // Supprimer l'ancienne photo si elle existe
        if ($student->getPhoto()) {
            $oldFilePath = $this->getParameter('uploads_directory') . '/' . $student->getPhoto();
            if (file_exists($oldFilePath)) {
                unlink($oldFilePath);
            }
        }

        // Enregistrer la nouvelle photo
        $newFilename = uniqid() . '.' . $photoFile->guessExtension();
        $filePath = $this->getParameter('uploads_directory') . '/' . $newFilename;
        $photoFile->move($this->getParameter('uploads_directory'), $newFilename);

        // Optimiser l'image
        $this->imageOptimizer->optimize($filePath);

        // Mettre à jour l'élève avec le nouveau nom de fichier
        $student->setPhoto($newFilename);
        $this->entityManager->persist($student);
        try {
            $this->entityManager->flush();
            // Enregistrer l'opération dans les logs
            $this->operationLogger->log(
                'MODIFICATION PHOTO DE L\'ÉLÈVE ' . $student->getFullName(),
                'success',
                'User',
                $student->getId(),
                null,
                [
                    'school_id' => $this->currentSchool->getId(),
                    'matricule' => $matricule,
                    'photo' => $newFilename,
                    'period' => $this->currentPeriod->getName(),
                    'school' => $this->currentSchool->getName(),
                ]
            );
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // logger le message d'erreur
            $this->operationLogger->log(
                'MODIFICATION PHOTO DE L\'ÉLÈVE ' . $student->getFullName(),
                'error',
                'User',
                $student->getId(),
                $e->getMessage(),
                [
                    'school_id' => $this->currentSchool->getId(),
                    'matricule' => $matricule,
                    'photo' => $newFilename,
                    'period' => $this->currentPeriod->getName(),
                    'school' => $this->currentSchool->getName(),
                ]
            );
            return new JsonResponse(['error' => 'Erreur lors de l\'upload de la photo : ' . $e->getMessage()], 500);
        }

        return new JsonResponse(['success' => 'Photo uploadée avec succès.', 'photo' => $newFilename]);
    }

    #[Route('/import-json', name: 'app_registration_card_import_json', methods: ['POST'])]
    public function importJson(Request $request,ManagerRegistry $doctrine): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !is_array($data)) {
            return new JsonResponse(['error' => 'Données JSON invalides.'], 400);
        }

        $errors = []; // Tableau pour collecter les erreurs

        foreach ($data as $index => $studentData) {
            // Vérifier les champs requis
            if (!isset($studentData[0], $studentData[1], $studentData[2])) {
                $errors[] = [
                    'index' => $index,
                    'message' => 'Données manquantes pour un ou plusieurs élèves.',
                    'data' => $studentData
                ];
                continue; // Passer à l'élève suivant
            }

            try {
                // Rechercher l'élève par son matricule ou créer un nouvel élève
                $student = $this->userRepository->findOneBy(['registrationNumber' => $studentData[0]]) ?? new User();

                // Mettre à jour les informations de l'élève
                $student->setRegistrationNumber($studentData[0]);
                $student->setFullName($studentData[1]);

                // Gérer la photo (Base64)
                if ($studentData[2]) {
                    $photoData = explode(',', $studentData[2]);
                    if (count($photoData) === 2) {
                        $photoContent = base64_decode($photoData[1]);
                        $photoFilename = uniqid() . '.png';
                        $photoPath = $this->getParameter('uploads_directory') . '/' . $photoFilename;

                        file_put_contents($photoPath, $photoContent);
                        $student->setPhoto($photoFilename);
                    } else {
                        $errors[] = [
                            'index' => $index,
                            'message' => 'Format de photo invalide.',
                            'data' => $studentData
                        ];
                        continue; // Passer à l'élève suivant
                    }
                }

                $this->entityManager->persist($student);
            } catch (\Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'message' => 'Erreur lors de l\'enregistrement de l\'élève : ' . $e->getMessage(),
                    'data' => $studentData
                ];
            }
        }

        try {
            $this->entityManager->flush();
            // Enregistrer l'opération dans les logs
            $this->operationLogger->log(
                'IMPORTATION DES DONNÉES DES ÉLÈVES',
                'success',
                'User',
                $student->getId(),
                null,
                [
                    'school_id' => $this->currentSchool->getId(),
                    'period' => $this->currentPeriod->getName(),
                    'school' => $this->currentSchool->getName(),
                    'student' => $student->getFullName(),
                ]
            );
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                        $this->entityManager = $doctrine->resetManager();
                    }
            // logger le message d'erreur
            $this->operationLogger->log(
                'IMPORTATION DES DONNÉES DES ÉLÈVES',
                'error',
                'User',
                $student->getId(),
                $e->getMessage(),
                [
                    'school_id' => $this->currentSchool->getId(),
                    'period' => $this->currentPeriod->getName(),
                    'school' => $this->currentSchool->getName(),
                    'student' => $student->getFullName(),
                ]
            );
            return new JsonResponse(['error' => 'Erreur lors de l\'upload de la photo : ' . $e->getMessage()], 500);
        }

        return new JsonResponse([
            'success' => 'Données importées avec succès.',
            'errors' => $errors // Transférer les erreurs à la vue
        ]);
    }

    #[Route('/print-all', name: 'app_registration_card_print_all', methods: ['GET'])]
    public function printAllCards(SessionInterface $session, Request $request): Response
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $matricules = json_decode($request->query->get('matricules', '[]'), true);
        if ($matricules !== null) {
            if (empty($matricules)) {
                throw $this->createNotFoundException('Aucune carte à imprimer.');
            }
        }

        $students = $this->userRepository->findBy(['registrationNumber' => $matricules]);

        $school = $this->currentSchool;
        if (!$school) {
            throw $this->createNotFoundException('École non trouvée.');
        }
        $config = $school->getRegistrationBaseConfig();
        if (!$config) {
            throw $this->createNotFoundException('Configuration de la carte non trouvée.');
        }
        foreach ($students as $student) {
            $gender = $student->getGender();
            $student->genderString = $gender instanceof \App\Contract\GenderEnum ? $gender->value : (string) $gender;

            $studentInfos[$student->getId()] = $this->studentRepository->findOneBy(['student' => $student]);

            // Générer le QR code
            $qrCode = new QrCode(
                "Matricule: {$student->getRegistrationNumber()}, Nom: {$student->getFullName()}, Classe: {$studentInfos[$student->getId()]->getSchoolClassPeriod()->getClassOccurence()->getName()}",
                new Encoding('UTF-8'),
                ErrorCorrectionLevel::Low,
                70,
                0,
                RoundBlockSizeMode::Margin,
                new Color(0, 0, 0),
                new Color(255, 255, 255)
            );

            $writer = new PngWriter();
            $qrCodeImage = $writer->write($qrCode);

            // Convertir le QR code en URI de données pour l'afficher dans Twig
            $qrCodeDataUri[$student->getId()] = $qrCodeImage->getDataUri();
            // Create generic label sans arguments nommés pour compatibilité PHP < 8.0
            $label = new Label(
                'Label',
                new \Endroid\QrCode\Label\Font\Font(__DIR__ . '/../../vendor/endroid/qr-code/assets/open_sans.ttf', 16),
                \Endroid\QrCode\Label\LabelAlignment::Center,
                new \Endroid\QrCode\Label\Margin\Margin(0, 10, 10, 10),
                new Color(255, 0, 0)
            );

            $result = $writer->write($qrCode, null, $label);

            // Validate the result
            $writer->validateResult($result, "Matricule: {$student->getRegistrationNumber()}, Nom: {$student->getFullName()}, Classe: {$studentInfos[$student->getId()]->getSchoolClassPeriod()->getClassOccurence()->getName()}");




            $imagePath = $this->getParameter('kernel.project_dir') . '/public/uploads/' . $student->getPhoto();
            if (!file_exists($imagePath) || !is_file($imagePath)) {
                // Si l'image n'existe pas, utiliser une image par défaut
                $imagePath = $this->getParameter('kernel.project_dir') . '/public/img/default_student.png'; // Chemin par défaut si l'image n'existe pas
            }
            $imageData = base64_encode(file_get_contents($imagePath));
            $imageStudentPhotoBase64[$student->getId()] = 'data:image/png;base64,' . $imageData;


            $tutor = $studentInfos[$student->getId()]->getStudent()->getTutor() !== null ? $this->userRepository->findOneBy(['id' => $studentInfos[$student->getId()]->getStudent()->getTutor()->getId()]) : null;
            if ($tutor) {
                $student->tutorName = $tutor->getFullName();
                $student->tutorPhone = $tutor->getPhone();
                $student->tutorEmail = $tutor->getEmail();
            } else {
                $student->tutorName = '';
                $student->tutorPhone = '';
                $student->tutorEmail = '';
            }
        }

        $config = $school->getRegistrationBaseConfig();

        $imagePath = $this->getParameter('kernel.project_dir') . '/public/uploads/' . $config->getCardBg();;
        $imageData = base64_encode(file_get_contents($imagePath));
        $imageBase64 = 'data:image/png;base64,' . $imageData;

        $imagePath = $this->getParameter('kernel.project_dir') . '/public/uploads/' . $school->getLogo();
        if (!file_exists($imagePath) || !is_file($imagePath)) {
            // Si l'image n'existe pas, utiliser une image par défaut
            $imagePath = $this->getParameter('kernel.project_dir') . '/public/img/logo_test.png'; // Chemin par défaut si l'image n'existe pas
        }
        $imageData = base64_encode(file_get_contents($imagePath));
        $imageLogoBase64 = 'data:image/png;base64,' . $imageData;

        $imagePath = $this->getParameter('kernel.project_dir') . '/public/uploads/' . $config->getHeadOfficerSign();;
        if (!file_exists($imagePath) || !is_file($imagePath)) {
            // Si l'image n'existe pas, utiliser une image par défaut
            $imagePath = $this->getParameter('kernel.project_dir') . '/public/img/logo_test.png'; // Chemin par défaut si l'image n'existe pas
        }
        $imageData = base64_encode(file_get_contents($imagePath));
        $imageHeadOfficerBase64 = 'data:image/png;base64,' . $imageData;
        //dd($studentInfos);

        return $this->render('registration_card/print_all.html.twig', [
            'students' => $students,
            'studentInfos' => $studentInfos,
            'qrCode' => $qrCodeDataUri,
            'imageBase64' => $imageBase64,
            'imageLogoBase64' => $imageLogoBase64,
            'school' => $school,
            'baseConfig' => $config,
            'imageHeadOfficerBase64' => $imageHeadOfficerBase64,
            'imageStudentPhotoBase64' => $imageStudentPhotoBase64,
        ]);
    }
}
