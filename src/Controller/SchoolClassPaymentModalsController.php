<?php
// filepath: src/Controller/SchoolClassPaymentModalsController.php
namespace App\Controller;

use App\Entity\SchoolClassAdmissionPayment;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\SecurityBundle\Security;
use App\Repository\SchoolClassPaymentModalRepository;
use App\Repository\SchoolClassPeriodRepository;
use App\Repository\SchoolPeriodRepository;
use App\Entity\SchoolClassPaymentModal;
use App\Entity\ModalitiesSubscriptions;
use App\Repository\StudentClassRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use App\Repository\SchoolClassAdmissionPaymentRepository;
use App\Repository\ModalitiesSubscriptionsRepository;
use App\Entity\AdmissionReductions;
use App\Entity\StudyLevel;
use App\Repository\AdmissionReductionsRepository;
use App\Service\OperationLogger;
use App\Repository\UserBaseConfigurationsRepository;
use App\Repository\StudyLevelRepository;
use App\Repository\ClasseRepository;
use App\Repository\ClassOccurenceRepository;
use App\Entity\SchoolPeriod;
use App\Entity\SchoolClassPeriod;
use App\Entity\ClassOccurence;
use Doctrine\ORM\EntityManager;
use App\Entity\School;
use Doctrine\Persistence\ManagerRegistry;
use Mpdf\Shaper\Sea;
use PhpParser\Node\Expr\Instanceof_;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SchoolClassPaymentModalsController extends AbstractController
{
    private $paymentModalsRepository;
    private $schoolClassRepository;
    private $schoolPeriodRepository;
    private $logger;
    private $studentRepository;
    private $paymentRepository;
    private $modalitiesSubscriptionsRepository;
    private $userRepository;
    private $userBaseRepository;
    private $operationLogger;
    private School $currentSchool;
    private SchoolPeriod $currentPeriod;
    private SessionInterface $session;
    private EntityManagerInterface $entityManager;
    private StudyLevelRepository $sectionRepository;

    public function __construct(
        SchoolClassPaymentModalRepository $paymentModalsRepository,
        SchoolClassPeriodRepository $schoolClassRepository,
        EntityManagerInterface $entityManager,
        SchoolPeriodRepository $schoolPeriodRepository,
        LoggerInterface $logger,
        ModalitiesSubscriptionsRepository $modalitiesSubscriptionsRepository,
        SchoolClassAdmissionPaymentRepository $paymentRepository,
        StudentClassRepository $studentRepository,
        UserBaseConfigurationsRepository $userBaseRepository,
        OperationLogger $operationLogger,
        UserRepository $userRepository,
        StudyLevelRepository $sectionRepository
    ) {
        $this->paymentModalsRepository = $paymentModalsRepository;
        $this->schoolClassRepository = $schoolClassRepository;
        $this->entityManager = $entityManager;
        $this->schoolPeriodRepository = $schoolPeriodRepository;
        $this->paymentRepository = $paymentRepository;
        $this->modalitiesSubscriptionsRepository = $modalitiesSubscriptionsRepository;
        $this->studentRepository = $studentRepository;
        $this->logger = $logger;
        $this->operationLogger = $operationLogger;
        $this->userBaseRepository = $userBaseRepository;
        $this->userRepository = $userRepository;
        $this->sectionRepository = $sectionRepository;
    }
    #[Route('/reject-admission-reduction', name: 'app_reject_admission_reduction', methods: ['POST'])]
    public function rejectAdmissionReduction(Request $request): JsonResponse
    {
        $id = $request->request->get('id');
        if (!$id) {
            return new JsonResponse(['error' => 'ID de réduction manquant.'], 400);
        }
        $reduction = $this->entityManager->getRepository(AdmissionReductions::class)->find($id);
        if (!$reduction) {
            return new JsonResponse(['error' => 'Réduction introuvable.'], 404);
        }
        try {
            $this->entityManager->remove($reduction);
            $this->entityManager->flush();
            // Log l'opération de rejet
            $this->operationLogger->log(
                'reject',
                'success',
                'AdmissionReductions',
                $id,
                null,
                [
                    'amount' => $reduction->getAmount(),
                    'requestedBy' => $reduction->getRequestedBy(),
                    'schoolClassPaymentModal' => $reduction->getSchoolClassPaymentModal() ? $reduction->getSchoolClassPaymentModal()->getId() : null
                ]
            );
            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            $this->operationLogger->log(
                'reject',
                'error',
                'AdmissionReductions',
                $id,
                $e->getMessage(),
                []
            );
            return new JsonResponse(['error' => 'Erreur lors du rejet de la réduction.'], 500);
        }
    }

    #[Route('/approve-admission-reduction', name: 'app_approve_admission_reduction', methods: ['POST'])]
    public function approveAdmissionReduction(Request $request): JsonResponse
    {
        $id = $request->request->get('id');
        if (!$id) {
            return new JsonResponse(['error' => 'ID de réduction manquant.'], 400);
        }
        $reduction = $this->entityManager->getRepository(AdmissionReductions::class)->find($id);
        if (!$reduction) {
            return new JsonResponse(['error' => 'Réduction introuvable.'], 404);
        }
        try {
            $reduction->setApproved(true);
            $reduction->setPendingApproval(false);
            $reduction->setApprovedBy($this->getUser());
            $this->entityManager->flush();
            // Log l'opération d'approbation
            $this->operationLogger->log(
                'approve',
                'success',
                'AdmissionReductions',
                $id,
                null,
                [
                    'amount' => $reduction->getAmount(),
                    'requestedBy' => $reduction->getRequestedBy(),
                    'approvedBy' => $this->getUser(),
                    'schoolClassPaymentModal' => $reduction->getSchoolClassPaymentModal() ? $reduction->getSchoolClassPaymentModal()->getId() : null
                ]
            );
            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            $this->operationLogger->log(
                'approve',
                'error',
                'AdmissionReductions',
                $id,
                $e->getMessage(),
                []
            );
            return new JsonResponse(['error' => 'Erreur lors de l\'approbation de la réduction.'], 500);
        }
    }

    // Routes pour la configuration des modalités de paiement
    #[Route('/classes/payment', name: 'app_classes_payment_modals')]
    public function payment(): Response
    {
        return $this->render('school_class_payment_modals/index.html.twig', [
            'controller_name' => 'SchoolClassPaymentModalsController',
        ]);
    }

    #[Route('/school-class-payment-modals', name: 'app_school_class_payment_modals')]
    public function index(
        StudyLevelRepository $studyLevelRepository,
        SchoolPeriodRepository $schoolPeriodRepository,
        Security $security,
        SessionInterface $session,
        EntityManagerInterface $entityManager
    ): Response {

        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        // Récupérer l'utilisateur connecté
        $user = $security->getUser();

        // Récupérer l'école associée à l'utilisateur connecté
        $school = $this->currentSchool; // Assurez-vous que la méthode `getSchool()` existe dans l'entité User

        // Récupérer la configuration de base de l'utilisateur
        $configuration = $this->userBaseRepository->findOneBy(['user' => $user, 'school' => $school]);

        $sections = [];

        if ($configuration) {
            // Récupérer les sections et classes associées à l'utilisateur
            $sections = count($configuration->getSectionList()) > 0 ? $this->entityManager->getRepository(StudyLevel::class)->findBy(['id' => $configuration->getSectionList()]) : array_map(fn($sl) => $sl->getId(), $this->sectionRepository->findAll());
        }
        // Récupérer toutes les sections
        $sections = in_array('ROLE_ADMIN', $this->getUser()->getRoles()) ? $studyLevelRepository->findAll() : $studyLevelRepository->findBy(['id' => $sections]);



        //dd($sections);

        return $this->render('school_class_payment_modals/index.html.twig', [
            'sections' => $sections,
            'currentSchoolPeriod' => $this->currentPeriod, // Transmettre l'année scolaire en cours au template
            'school' => $school, // Transmettre l'école au template
        ]);
    }

    #[Route('/school-class-student-admission', name: 'app_student_admission')]
    public function admissionIncex(
        StudyLevelRepository $schoolSectionRepository,
        SchoolPeriodRepository $schoolPeriodRepository,
        Security $security,
        SessionInterface $session,
        EntityManagerInterface $entityManager
    ): Response {
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        // Récupérer l'utilisateur connecté
        $user = $security->getUser();

        // Récupérer l'école associée à l'utilisateur connecté
        $school = $this->currentSchool; // Assurez-vous que la méthode `getSchool()` existe dans l'entité User

        $configuration = $this->userBaseRepository->findOneBy(['user' => $user, 'school' => $school]);
        $sections = [];
        if ($configuration) {
            // Récupérer les sections et classes associées à l'utilisateur
            $sections = count($configuration->getSectionList()) > 0 ? $this->entityManager->getRepository(StudyLevel::class)->findBy(['id' => $configuration->getSectionList()]) : array_map(fn($sl) => $sl->getId(), $this->sectionRepository->findAll());
        }
        // Récupérer toutes les sections
        $sections = in_array('ROLE_ADMIN', $this->getUser()->getRoles()) ? $schoolSectionRepository->findAll() : $schoolSectionRepository->findBy(['id' => $sections]);


        return $this->render('school_class_payment_modals/admission.html.twig', [
            'sections' => $sections,
            'currentSchoolPeriod' => $this->currentPeriod, // Transmettre l'année scolaire en cours au template
            'school' => $school, // Transmettre l'école au template
        ]);
    }

    #[Route('/payment-modals/data', name: 'app_payment_modals_data')]
    public function getPaymentModalsData(SessionInterface $session, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $sectionId = $request->query->get('section');
        $classId = $request->query->get('class');


        // Vérifier si les IDs sont valides
        $sectionId = is_numeric($sectionId) ? (int) $sectionId : null;
        $classId = is_numeric($classId) ? (int) $classId : null;
        if (!$sectionId || !$classId) {
            return new JsonResponse(['data' => []]);
        }

        $schoolPeriod = $this->currentPeriod;
        if (!$schoolPeriod) {
            return new JsonResponse(['error' => 'Année scolaire invalide.'], 400);
        }

        $paymentModals = $entityManager->getRepository(SchoolClassPaymentModal::class)->findBy(['schoolClassPeriod' => $classId]);
        if (!$paymentModals) $paymentModals = [];

        $data = [];
        foreach ($paymentModals as $modal) {
            $data[] = [
                'id' => $modal->getId(),
                'label' => $modal->getLabel(),
                'amount' => $modal->getAmount(),
                'dueDate' => $modal->getDueDate()->format('Y-m-d'),
                'className' => $modal->getSchoolClassPeriod()->getClassOccurence()->getName(),
                'sectionName' => $modal->getSchoolClassPeriod()->getClassOccurence()->getClasse()->getStudyLevel()->getName(),
                'modalType' => $modal->getModalType(),
                'modalPriority' => $modal->getModalPriority(),
            ];
        }
        // Retourner une réponse JSON avec une clé "data", même si elle est vide
        return new JsonResponse(['data' => $data]);
    }

    #[Route('/classes/by-section', name: 'app_classes_by_section')]
    public function getClassesBySection(SessionInterface $session, Request $request, ClasseRepository $classeRepository, ClassOccurenceRepository $classOccurenceRepository, SchoolClassPeriodRepository $schoolClassRepository, SchoolPeriodRepository $schoolPeriodRepository): JsonResponse
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $sectionId = $request->query->get('sectionId');

        $period = $this->currentPeriod;
        // Récupérer les classes associées à la section via Classe
        $classes = $classeRepository->findBy(['studyLevel' => $sectionId]);
        $classesOccurences = $classOccurenceRepository->findBy(['classe' => $classes]);
        $classesPeriodes = $schoolClassRepository->findBy(['classOccurence' => $classesOccurences, 'period' => $period, 'school' => $this->currentSchool]);
        // Convertir $classes en tableau contenant uniquement les IDs
        $classesArray = array_map(function ($class) {
            return $class->getId();
        }, $classesPeriodes);
        $authorizedClasses = in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles()) ? $classesArray : (count($this->userBaseRepository->findOneBy(['user' => $this->getUser()])->getClassList()) > 0 ? $this->userBaseRepository->findOneBy(['user' => $this->getUser()])->getClassList() : $classesArray);
        $classesPeriodes = array_filter($classesPeriodes, function ($class) use ($authorizedClasses) {
            return in_array($class->getClassOccurence()->getId(), $authorizedClasses);
        });

        $data = [];
        foreach ($classesPeriodes as $class) {
            $data[] = [
                'id' => $class->getClassOccurence()->getId(),
                'name' => $class->getClassOccurence()->getName(),
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/add-payment-modal', name: 'app_add_payment_modal', methods: ['POST'])]
    public function addPaymentModal(SessionInterface $session, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $modalType = $request->request->get('modalType') === '0' ? 'base' : 'additional'; // Récupérer la valeur de modalType
        $modalPriority = (int) $request->request->get('modalPriority', 100); // Récupérer la priorité de la modalité, par défaut 0
        // Valider la valeur de modalType
        if (!in_array($modalType, ['base', 'additional', 'transport'], true)) {
            return new JsonResponse(['error' => 'Type de modalité invalide.'], 400);
        }

        // Créer une nouvelle entité SchoolClassPaymentModal
        $paymentModal = new SchoolClassPaymentModal();
        $paymentModal->setModalType($modalType); // Convertir en booléen

        // Récupérer l'utilisateur connecté
        $user = $this->getUser();

        // Récupérer l'école associée à l'utilisateur connecté
        $school = $this->currentSchool;
        $schoolId = $school ? $school->getId() : null;
        if (!$school) {
            return new JsonResponse(['error' => 'École invalide.'], 400);
        }

        // Récupérer l'année scolaire en cours
        $schoolPeriod = $this->currentPeriod;
        if (!$schoolPeriod) {
            return new JsonResponse(['error' => 'Année scolaire invalide.'], 400);
        }

        // Récupérer les données du formulaire
        $label = $request->request->get('label');
        $amount = $request->request->get('amount');
        $dueDate = $request->request->get('dueDate');
        $sectionId = $request->request->get('sectionId');
        $classId = $request->request->get('classId');

        // Récupérer les entités StudyLevel et SchoolClassPeriod
        $section = $entityManager->getRepository(StudyLevel::class)->find($sectionId);
        $schoolClassPeriod = $entityManager->getRepository(SchoolClassPeriod::class)->find($classId);

        if (!$section || !$schoolClassPeriod) {
            return new JsonResponse(['error' => 'StudyLevel ou classe invalide.'], 400);
        }

        // Ajouter les autres champs
        $paymentModal->setLabel($label)
            ->setAmount($amount)
            ->setDueDate(new \DateTime($dueDate))
            ->setSchoolPeriod($schoolPeriod)
            ->setSchool($school)
            ->setModalPriority($modalPriority)
            ->setSchoolClassPeriod($schoolClassPeriod);


        $logger = $this->operationLogger;

        // Gestion des erreurs lors de l'enregistrement
        try {
            $this->entityManager->persist($paymentModal);
            $this->entityManager->flush();

            // Enregistrer l'historique de l'opération
            $logger->log(
                'add',
                'success',
                'SchoolClassAdmissionPayment',
                $paymentModal->getId(),
                null,
                ['label' => $label, 'amount' => $amount]
            );

            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // Enregistrer l'erreur dans l'historique
            $logger->log(
                'add',
                'error',
                'SchoolClassPaymentModal',
                null,
                $e->getMessage(),
                ['label' => $label, 'amount' => $amount]
            );

            return new JsonResponse(['error' => 'Une erreur est survenue lors de l\'enregistrement.'], 500);
        }
    }
    #[Route('/delete-payment-modal', name: 'app_delete_payment_modal', methods: ['POST'])]
    public function deletePaymentModal(Request $request): JsonResponse
    {
        //dd($request);
        $id = $request->request->get('id');
        $paymentModal = $this->entityManager->getRepository(SchoolClassPaymentModal::class)->find($id);

        if (!$paymentModal) {
            return new JsonResponse(['error' => 'Modalité introuvable.'], 404);
        }

        try {
            $this->entityManager->remove($paymentModal);
            $this->entityManager->flush();

            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Une erreur est survenue lors de la suppression.'], 500);
        }
    }

    #[Route('/get-payment-modal', name: 'app_get_payment_modal', methods: ['GET'])]
    public function getPaymentModal(Request $request): JsonResponse
    {
        $id = $request->query->get('id');
        $paymentModal = $this->paymentModalsRepository->find($id);

        if (!$paymentModal) {
            return new JsonResponse(['error' => 'Modalité introuvable.'], 404);
        }

        return new JsonResponse([
            'id' => $paymentModal->getId(),
            'label' => $paymentModal->getLabel(),
            'amount' => $paymentModal->getAmount(),
            'dueDate' => $paymentModal->getDueDate()->format('Y-m-d'),
            'modalType' => $paymentModal->getModalType(),
            'modalPriority' => $paymentModal->getModalPriority(),
        ]);
    }

    #[Route('/update-payment-modal', name: 'app_update_payment_modal', methods: ['POST'])]
    public function updatePaymentModal(Request $request): JsonResponse
    {
        $id = $request->request->get('id');
        $paymentModal = $this->paymentModalsRepository->find($id);

        if (!$paymentModal) {
            return new JsonResponse(['error' => 'Modalité introuvable.'], 404);
        }

        // Mettre à jour les données
        $paymentModal->setLabel($request->request->get('label'));
        $paymentModal->setAmount($request->request->get('amount'));
        $paymentModal->setDueDate(new \DateTime($request->request->get('dueDate')));
        $paymentModal->setModalType($request->request->get('modalType') == '0' ? 'base' : 'additional');
        $modalPriority = (int) $request->request->get('modalPriority');

        // Si la priorité est 0, appliquer une logique spécifique (par exemple, définir une priorité par défaut ou ignorer)
        if ($modalPriority === 0) {
            // Logique spécifique pour la priorité 0
            $modalPriority = 0;
        }

        $paymentModal->setModalPriority($modalPriority);

        try {
            $this->entityManager->flush();
            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Une erreur est survenue lors de la mise à jour.'], 500);
        }
    }

    #[Route('/students-data', name: 'app_students_data', methods: ['GET'])]
    public function getStudentsData(SessionInterface $session, Request $request): JsonResponse
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $classId = $request->query->get('class');
        // Récupérer l'année scolaire en cours
        $schoolPeriod = $this->currentPeriod;
        if (!$schoolPeriod) {
            return new JsonResponse(['error' => 'Année scolaire invalide.'], 400);
        }
        $schoolClassPeriod = $this->schoolClassRepository->find($classId);
        $students = $this->studentRepository->findBy(['schoolClassPeriod' => $schoolClassPeriod]);
        // Trier les étudiants par fullname (ordre alphabétique)
        usort($students, function ($a, $b) {
            return strcmp($a->getStudent()->getFullName(), $b->getStudent()->getFullName());
        });
        //dd($students);
        return new JsonResponse(['data' => array_map(function ($student, $index) {
            return [
                'orderNumber' => $index + 1,
                'matricule' => $student->getStudent()->getRegistrationNumber(),
                'name' => $student->getStudent()->getFullName(),
                'id' => $student->getStudent()->getId(),
            ];
        }, $students, array_keys($students))]);
    }

    #[Route('/optional-modals', name: 'app_optional_modals', methods: ['GET'])]
    public function getOptionalModals(SessionInterface $session, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $user = $this->getUser();

        // Récupérer l'école associée à l'utilisateur connecté
        $school = $this->currentSchool;
        if (!$school) {
            return new JsonResponse(['error' => 'École invalide.'], 400);
        }

        // Récupérer l'année scolaire en cours
        $schoolPeriod = $this->currentPeriod;
        if (!$schoolPeriod) {
            return new JsonResponse(['error' => 'Année scolaire invalide.'], 400);
        }

        $classId = $request->query->get('classId');
        if (!$classId) {
            return new JsonResponse(['error' => 'Classe invalide.'], 400);
        }
        $schoolClassPeriod = $entityManager->getRepository(SchoolClassPeriod::class)->find($classId);

        $studentId = $request->query->get('studentId');
        if (!$studentId) {
            return new JsonResponse(['error' => 'Élève invalide.'], 400);
        }

        // Récupérer l'élève
        $student = $this->userRepository->find($studentId);
        if (!$student) {
            return new JsonResponse(['error' => 'Élève introuvable.'], 404);
        }

        // Récupérer toutes les modalités additionnelles pour la classe, l'école et l'année scolaire
        $modals = $this->paymentModalsRepository->findBy([
            'modalType' => ['additional', 'transport'], // Modalités additionnelles
            'school' => $school,
            'schoolPeriod' => $schoolPeriod,
            'schoolClassPeriod' => [$schoolClassPeriod, null],
        ]);

        // Récupérer les paiements effectués pour cet élève
        $payments = $this->paymentRepository->findBy([
            'student' => $student,
            'schoolClassPeriod' => $schoolClassPeriod,
            'schoolPeriod' => $schoolPeriod,
            'school' => $school,
            'modalType' => ['additional', 'transport'], // Modalités additionnelles
        ]);

        // Créer un tableau associatif des paiements pour un accès rapide
        $paymentsByModalId = [];
        $p = [];
        foreach ($payments as $payment) {
            $modalId = $payment->getPaymentModal()->getId();
            if (!isset($paymentsByModalId[$modalId]['amount'])) {
                $paymentsByModalId[$modalId] = []; // Initialiser le tableau si pas encore défini
                $paymentsByModalId[$modalId]['amount'] = 0; // Initialiser le montant si pas encore défini
            }
            $paymentsByModalId[$modalId]['amount'] += $payment->getPaymentAmount(); // Montant du paiement
        }


        // Récupérer les souscriptions actives pour cet élève
        $subscriptions = $this->modalitiesSubscriptionsRepository->findBy([
            'student' => $student,
            'schoolClassPeriod' => $schoolClassPeriod,
            'schoolPeriod' => $schoolPeriod,
        ]);

        // Créer un tableau associatif des souscriptions pour un accès rapide
        $subscriptionsByModalId = [];
        foreach ($subscriptions as $subscription) {
            $subscriptionsByModalId[$subscription->getPaymentModal()->getId()] = $subscription;
        }

        // Préparer les données des modalités avec leur statut
        $data = array_map(function ($modal) use ($subscriptionsByModalId, $paymentsByModalId) {
            $subscription = $subscriptionsByModalId[$modal->getId()] ?? null;
            $amount = $modal->getAmount();

            // Ajuster le montant si la souscription existe et isFull est false
            if ($subscription && !$subscription->getIsFull()) {
                $amount /= 2; // Diviser le montant par 2
            }

            return [
                'id' => $modal->getId(),
                'label' => $modal->getLabel(),
                'amount' => $amount,
                'enabled' => $subscriptionsByModalId[$modal->getId()] ?? false, // Par défaut, non souscrit
                'hasPayments' => $paymentsByModalId[$modal->getId()] ?? false, // Par défaut, aucun paiement
                'totalPaid' => $paymentsByModalId[$modal->getId()]['amount'] ?? 0, // Montant total payé
            ];
        }, $modals);

        return new JsonResponse($data);
    }

    #[Route('/principales-modals', name: 'app_principales_modals', methods: ['GET'])]
    public function getPrincipalesModals(Request $request): JsonResponse
    {
        $studentId = $request->query->get('studentId');
        $classId = $request->query->get('classId');

        // Récupérer les modalités principales pour l'élève
        $modals = $this->paymentModalsRepository->findBy([
            'modalType' => 'base', // Modalités principales
            'schoolClassPeriod' => $classId, // Aucune classe spécifique
        ]);

        $data = array_map(function ($modal) {
            return [
                'id' => $modal->getId(),
                'label' => $modal->getLabel(),
                'amount' => $modal->getAmount()
            ];
        }, $modals);

        return new JsonResponse($data);
    }

    /*  #[Route('/save-payment', name: 'app_save_payment', methods: ['POST'])]
    public function savePayment(Request $request): JsonResponse
    {
        $studentId = $request->request->get('studentId');
        $paymentDate = $request->request->get('paymentDate');
        $amount = $request->request->get('amount');
        $optionalModals = $request->request->get('optionalModals', []);

        // Logique pour enregistrer le paiement principal et les modalités secondaires

        return new JsonResponse(['success' => true]);
    } */
    /* 
    #[Route('/subscriptions', name: 'app_subscriptions', methods: ['GET'])]
    public function getSubscriptions(Request $request): JsonResponse
    {
        $studentId = $request->query->get('studentId');
        $subscriptions = $this->paymentModalsRepository->findBy(['id' => $studentId]);

        return new JsonResponse(array_map(function ($subscription) {
            return [
                'id' => $subscription->getId(),
                'label' => $subscription->getLabel(),
                'amount' => $subscription->getAmount()
            ];
        }, $subscriptions));
    }
 */
    #[Route('/save-principales-payment', name: 'app_save_principales_payment', methods: ['POST'])]
    public function savePrincipalesPayment(SessionInterface $session, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $studentId = $request->request->get('studentId');
        $paymentDate = $request->request->get('paymentDate');
        $amount = $request->request->get('amount');
        $classId = $request->request->get('classId');
        $modalId = $request->request->get('modalId');

        $schoolPeriod = $this->currentPeriod;
        if (!$schoolPeriod) {
            return new JsonResponse(['error' => 'Année scolaire introuvable.'], 404);
        }
        // Récupérer l'utilisateur connecté
        $user = $this->getUser();
        // Récupérer l'école associée à l'utilisateur

        $school = $this->currentSchool;

        // Récupérer l'élève
        $student = $this->userRepository->find($studentId);
        if (!$student) {
            return new JsonResponse(['error' => 'Élève introuvable.'], 404);
        }

        $schoolClassPeriod = $entityManager->getRepository(SchoolClassPeriod::class)->find($classId);
        if (!$schoolClassPeriod) {
            return new JsonResponse(['error' => 'Classe introuvable.'], 404);
        }

        // Récupérer la modalité
        $modal = $this->paymentModalsRepository->find($modalId);
        if (!$modal) {
            return new JsonResponse(['error' => 'Modalité introuvable.'], 404);
        }

        // Calculer le montant total payé pour cette modalité
        $payments = $this->paymentRepository->findBy([
            'student' => $student,
            'schoolClassPeriod' => $schoolClassPeriod,
            'schoolPeriod' => $schoolPeriod,
            'school' => $school,
            'modalType' => ['additional', 'transport'], // Modalités additionnelles
        ]);

        $totalPaid = 0;
        foreach ($payments as $payment) {
            $totalPaid += $payment->getPaymentAmount();
        }

        // Calculer le reste à payer
        $remaining = $modal->getAmount() - $totalPaid;

        // Vérifier que le montant versé est inférieur ou égal au reste à payer
        if ($amount > $remaining) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Le montant versé dépasse le reste à payer. <br>Reste à payer : ' . number_format($remaining, 2, ',', ' ') . ' €',
            ], 400);
        }



        // Créer un nouvel enregistrement de paiement
        $payment = new SchoolClassAdmissionPayment();
        $payment->setStudent($student);
        $payment->setPaymentDate(new \DateTime($paymentDate));
        $payment->setPaymentAmount($amount);
        $payment->setSchoolClass($schoolClassPeriod);
        $payment->setSchoolPeriod($schoolPeriod);
        $payment->setSchool($school);
        $payment->setPaymentModal($modal);
        $payment->setModalType('base'); // Type de modalité principale


        $logger = $this->operationLogger;

        try {
            $this->entityManager->persist($payment);
            $this->entityManager->flush();

            // Enregistrer l'historique de l'opération
            $logger->log(
                'add',
                'success',
                'SchoolClassAdmissionPayment',
                $payment->getId(),
                null,
                ['amount' => $amount, 'studentId' => $studentId]
            );

            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // Enregistrer l'erreur dans l'historique

            $logger->log(
                'add',
                'error',
                'SchoolClassPaymentModal',
                null,
                $e->getMessage(),
                ['amount' => $amount, 'studentId' => $studentId]
            );

            return new JsonResponse(['error' => 'Une erreur est survenue lors de l\'enregistrement.'], 500);
        }
    }

    #[Route('/save-secondaires-payment', name: 'app_save_secondaires_payment', methods: ['POST'])]
    public function saveSecondairesPayment(SessionInterface $session, Request $request, OperationLogger $logger, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $studentId = $request->request->get('studentId');
        $paymentDate = $request->request->get('paymentDate');
        $modalId = $request->request->get('modalId');
        $classId = $request->request->get('classId');
        $amount = (int) $request->request->get('amount');

        // Récupérer l'élève
        $student = $this->userRepository->find($studentId);
        if (!$student) {
            return new JsonResponse(['error' => 'Élève introuvable.'], 404);
        }

        // Récupérer l'année scolaire en cours
        $schoolPeriod = $this->currentPeriod;
        if (!$schoolPeriod) {
            return new JsonResponse(['error' => 'Année scolaire introuvable.'], 404);
        }

        // Récupérer l'école associée à l'utilisateur
        $school = $this->currentSchool;
        if (!$school) {
            return new JsonResponse(['error' => 'École introuvable.'], 404);
        }

        // Récupérer la classe de l'élève
        $schoolClassPeriod = $entityManager->getRepository(SchoolClassPeriod::class)->find($classId);
        if (!$schoolClassPeriod) {
            return new JsonResponse(['error' => 'Classe introuvable.'], 404);
        }

        // Récupérer la modalité
        $modal = $this->paymentModalsRepository->find($modalId);
        if (!$modal) {
            return new JsonResponse(['error' => 'Modalité introuvable.'], 404);
        }

        // Récupérer la souscription pour cette modalité
        $subscription = $this->modalitiesSubscriptionsRepository->findOneBy([
            'student' => $student,
            'schoolClassPeriod' => $schoolClassPeriod,
            'schoolPeriod' => $schoolPeriod,
            'paymentModal' => $modal,
        ]);

        // Calculer le montant à payer en tenant compte de la souscription
        $amountToPay = $modal->getAmount();
        if ($subscription && !$subscription->getIsFull()) {
            $amountToPay /= 2; // Diviser le montant par 2 si la souscription n'est pas "full"
        }

        // Calculer le montant total payé pour cette modalité
        $payments = $this->paymentRepository->findBy([
            'student' => $student,
            'schoolClassPeriod' => $schoolClassPeriod,
            'schoolPeriod' => $schoolPeriod,
            'school' => $school,
        ]);

        $totalPaid = 0;
        foreach ($payments as $payment) {
            $totalPaid += $payment->getPaymentAmount();
        }

        // Calculer le reste à payer
        $remaining = $amountToPay - $totalPaid;
        //dd($remaining, $totalPaid, $amountToPay, $amount);

        // Vérifier que le montant versé est inférieur ou égal au reste à payer
        if ($amount > $remaining) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Le montant versé dépasse le reste à payer. <br>Reste à payer : ' . number_format($remaining, 2, ',', ' ') . ' €',
            ], 400);
        }

        //dd($modal->getModalType());

        // Créer un nouvel enregistrement de paiement
        $payment = new SchoolClassAdmissionPayment();
        $payment->setStudent($student);
        $payment->setPaymentDate(new \DateTime($paymentDate));
        $payment->setPaymentAmount($amount);
        $payment->setSchoolClass($schoolClassPeriod);
        $payment->setSchoolPeriod($schoolPeriod);
        $payment->setSchool($school);
        $payment->setPaymentModal($modal);
        $payment->setModalType($modal->getModalType());
        $payment->setCreatedAt(new \DateTime());
        $payment->setUpdatedAt(new \DateTime());

        $logger = $this->operationLogger;

        try {
            $this->entityManager->persist($payment);
            $this->entityManager->flush();

            $logger->log(
                'add',
                'success',
                'SchoolClassAdmissionPayment',
                $payment->getId(),
                null,
                ['amount' => $amount, 'studentId' => $studentId]
            );

            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            $logger->log(
                'add',
                'error',
                'SchoolClassPaymentModal',
                null,
                $e->getMessage(),
                ['amount' => $amount, 'studentId' => $studentId]
            );

            return new JsonResponse(['error' => 'Une erreur est survenue lors de l\'enregistrement.' . $e->getMessage()], 500);
        }
    }

    #[Route('/payment-history', name: 'app_payment_history', methods: ['GET'])]
    public function getPaymentHistory(Request $request): JsonResponse
    {
        $studentId = $request->query->get('studentId');

        // Récupérer l'élève
        $student = $this->userRepository->find($studentId);
        if (!$student) {
            return new JsonResponse(['error' => 'Élève introuvable.'], 404);
        }

        // Récupérer les paiements de l'élève
        $payments = $this->paymentRepository->findBy(['student' => $student]);

        $data = array_map(function ($payment) {
            return [
                'date' => $payment->getPaymentDate()->format('Y-m-d'),
                'amount' => $payment->getPaymentAmount(),
                'id' => $payment->getId(),
            ];
        }, $payments);

        return new JsonResponse($data);
    }

    #[Route('/payment-summary', name: 'app_payment_summary', methods: ['GET'])]
    public function getPaymentSummary(SessionInterface $session, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));

        $classId = $request->query->get('classId');
        $studentId = $request->query->get('studentId');

        // Récupérer l'utilisateur connecté
        $user = $this->getUser();

        // Récupérer l'école associée à l'utilisateur
        $school = $this->currentSchool;

        if (!$school) {
            return new JsonResponse(['error' => 'École introuvable.'], 404);
        }

        // Récupérer l'année scolaire en cours
        $schoolPeriod = $this->currentPeriod;

        if (!$schoolPeriod) {
            return new JsonResponse(['error' => 'Année scolaire introuvable.'], 404);
        }

        // Récupérer l'élève
        $student = $this->userRepository->find($studentId);
        if (!$student) {
            return new JsonResponse(['error' => 'Élève introuvable.'], 404);
        }

        $schoolClassPeriod = $entityManager->getRepository(SchoolClassPeriod::class)->find($classId);

        // Récupérer les modalités standard (pas besoin de souscription)
        $standardModalities = $this->paymentModalsRepository->findBy([
            'schoolClassPeriod' => $schoolClassPeriod,
            'school' => $school,
            'schoolPeriod' => $schoolPeriod,
            'modalType' => 'base', // Modalités standard
        ]);

        // Récupérer les souscriptions actives pour les modalités additionnelles
        $subscriptions = $this->modalitiesSubscriptionsRepository->findBy([
            'student' => $student,
            'schoolClassPeriod' => $schoolClassPeriod,
            'schoolPeriod' => $schoolPeriod,
            'enabled' => true, // Souscriptions actives uniquement
        ]);

        // Extraire les IDs des modalités additionnelles souscrites
        $subscribedModalitiesIds = array_map(function ($subscription) {
            return $subscription->getPaymentModal()->getId();
        }, $subscriptions);

        // Récupérer les modalités additionnelles souscrites
        $additionalModalities = $this->paymentModalsRepository->findBy([
            'schoolClassPeriod' => $schoolClassPeriod,
            'school' => $school,
            'schoolPeriod' => $schoolPeriod,
            'modalType' => 'additional', // Modalités additionnelles
        ]);

        // Filtrer les modalités additionnelles pour inclure uniquement celles souscrites
        $filteredAdditionalModalities = array_filter($additionalModalities, function ($modal) use ($subscribedModalitiesIds) {
            return in_array($modal->getId(), $subscribedModalitiesIds);
        });

        // Fusionner les modalités standard et additionnelles souscrites
        $allModalities = array_merge($standardModalities, $filteredAdditionalModalities);

        // Récupérer les paiements effectués
        $payments = $this->paymentRepository->findBy([
            'student' => $student,
            'schoolClassPeriod' => $schoolClassPeriod,
            'schoolPeriod' => $schoolPeriod,
        ]);

        // Préparer les données des modalités
        $modalitiesData = [];
        foreach ($allModalities as $modal) {
            $type = $modal->getModalType();
            $totalPaid = 0;

            // Calculer le montant payé pour cette modalité
            foreach ($payments as $payment) {
                if ($payment->getPaymentModal() && $payment->getPaymentModal()->getId() === $modal->getId()) {
                    $totalPaid += $payment->getPaymentAmount();
                }
            }

            // Ajuster le montant pour les modalités additionnelles souscrites
            $amount = $modal->getAmount();
            if ($type === 'Additionnel') {
                $subscription = array_filter($subscriptions, function ($sub) use ($modal) {
                    return $sub->getPaymentModal()->getId() === $modal->getId();
                });

                if (!empty($subscription)) {
                    $subscription = array_values($subscription)[0]; // Récupérer la souscription correspondante
                    if (!$subscription->getIsFull()) {
                        $amount /= 2; // Diviser le montant par 2 si la souscription n'est pas "full"
                    }
                }
            }

            $remaining = $amount - $totalPaid;

            $approvedReductions = array_filter($modal->getAdmissionReductions()->toArray(), function ($reduction) use ($student) {
                return $reduction->getStudent() && $reduction->getStudent()->getId() === $student->getId() && $reduction->isApproved();
            });
            $reduction = array_sum(array_map(function ($m) {
                return $m->getReductionAmount();
            }, $approvedReductions));
            $has_reduction = count($approvedReductions) > 0;

            $modalitiesData[] = [
                'id' => $modal->getId(),
                'label' => $modal->getLabel(),
                'amount' => $amount,
                'totalPaid' => $totalPaid,
                'remaining' => $remaining - ($has_reduction ? $reduction : 0),
                'type' => $type,
                'hasReduction' => $has_reduction, // Indique si une réduction approuvée est appliquée
                'reductionAmount' => $reduction, // Montant de la réduction approuvée
            ];
        }

        // Trier les modalités par type (Standard en premier)
        usort($modalitiesData, function ($a, $b) {
            return $a['type'] === $b['type'] ? 0 : ($a['type'] === 'Standard' ? -1 : 1);
        });

        return new JsonResponse($modalitiesData);
    }

    #[Route('/save-configuration', name: 'app_save_configuration', methods: ['POST'])]
    public function saveConfiguration(Request $request): JsonResponse
    {
        $studentId = $request->request->get('studentId');
        $classId = $request->request->get('classId');
        $selectedModalities = $request->request->all('modalities');

        // Récupérer l'élève
        $student = $this->userRepository->find($studentId);
        if (!$student) {
            return new JsonResponse(['error' => 'Élève introuvable.'], 404);
        }

        // Supprimer les souscriptions existantes pour cet élève
        $existingSubscriptions = $this->entityManager->getRepository(ModalitiesSubscriptions::class)
            ->findBy(['student' => $student]);
        foreach ($existingSubscriptions as $subscription) {
            $this->entityManager->remove($subscription);
        }

        // Ajouter les nouvelles souscriptions
        foreach ($selectedModalities as $modalId) {
            $modal = $this->paymentModalsRepository->find($modalId);
            if ($modal) {
                $subscription = new ModalitiesSubscriptions();
                $subscription->setStudent($student);
                $subscription->setPaymentModal($modal);
                $subscription->setSubscriptionDate(new \DateTime());
                $subscription->setUpdatedAt(new \DateTime());
                $subscription->setCreatedAt(new \DateTime());

                $this->entityManager->persist($subscription);
            }
        }

        try {
            $this->entityManager->flush();
            // Enregistrer l'historique de l'opération
            $this->operationLogger->log(
                'ENREGISTREMENT SOUSCRIPTION DE L\'ÉLÈVE ' . $student->getFullName(),
                'success',
                'ModalitiesSubscriptions',
                null,
                null,
                ['studentId' => $studentId, 'classId' => $classId, 'modalities' => $selectedModalities, 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()]
            );

            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Erreur lors de la sauvegarde.'], 500);
        }
    }

    #[Route('/toggle-subscription', name: 'app_toggle_subscription', methods: ['POST'])]
    public function toggleSubscription(SessionInterface $session, Request $request, EntityManagerInterface $entityManager, ManagerRegistry $doctrine): JsonResponse
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $studentId = $request->request->get('studentId');
        $modalId = $request->request->get('modalId');
        $classId = $request->request->get('classId');

        $schoolClassPeriod = $entityManager->getRepository(SchoolClassPeriod::class)->find($classId);
        if (!$schoolClassPeriod) {
            return new JsonResponse(['error' => 'Classe introuvable.'], 404);
        }
        /* $schoolPeriodId = $request->request->get('schoolPeriodId');
        $schoolId = $request->request->get('schoolId'); */
        $school = $this->currentSchool;
        $schoolPeriod = $this->currentPeriod;
        if (!$schoolPeriod) {
            return new JsonResponse(['error' => 'Année scolaire introuvable.'], 404);
        }

        // Récupérer l'élève
        $student = $this->userRepository->find($studentId);
        if (!$student) {
            return new JsonResponse(['error' => 'Élève introuvable.'], 404);
        }

        // Récupérer la modalité
        $modal = $this->paymentModalsRepository->find($modalId);
        if (!$modal) {
            return new JsonResponse(['error' => 'Modalité introuvable.'], 404);
        }

        // Vérifier si une souscription existe déjà
        $subscription = $this->modalitiesSubscriptionsRepository->findOneBy([
            'student' => $student,
            'paymentModal' => $modal,
            'schoolClassPeriod' => $schoolClassPeriod,
            'schoolPeriod' => $schoolPeriod,
            'schoolClassPeriod' => $school,
        ]);

        if ($subscription) {
            // Si la souscription existe, inverser le champ "enabled"
            $subscription->setEnabled(!$subscription->isEnabled());
        } else {
            // Sinon, créer une nouvelle souscription
            $subscription = new ModalitiesSubscriptions();
            $subscription->setStudent($student);
            $subscription->setPaymentModal($modal);
            $subscription->setSchoolClass($schoolClassPeriod);
            $subscription->setSchoolPeriod($schoolPeriod);
            $subscription->setEnabled(true);
            $subscription->setSubscriptionDate(new \DateTime());

            $this->entityManager->persist($subscription);
        }

        try {
            $this->entityManager->flush();
            // Enregistrer l'historique de l'opération
            $this->operationLogger->log(
                ($subscription->isEnabled() ? 'ACTIVATION' : 'DÉSINSCRIPTION') . ' SOUSCRIPTION DE L\'ÉLÈVE ' . $student->getFullName(),
                'success',
                'ModalitiesSubscriptions',
                $subscription->getId(),
                null,
                ['studentId' => $studentId, 'modalId' => $modalId, 'classId' => $classId, 'enabled' => $subscription->isEnabled(), 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()]
            );
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // Enregistrer l'erreur dans l'historique
            $this->operationLogger->log(
                ($subscription->isEnabled() ? 'ACTIVATION' : 'DÉSINSCRIPTION') . ' SOUSCRIPTION DE L\'ÉLÈVE ' . $student->getFullName(),
                'error',
                'ModalitiesSubscriptions',
                null,
                $e->getMessage(),
                ['studentId' => $studentId, 'modalId' => $modalId, 'classId' => $classId, 'enabled' => $subscription->isEnabled(), 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()]
            );

            return new JsonResponse(['error' => 'Erreur lors de la sauvegarde.'], 500);
        }

        return new JsonResponse(['success' => true, 'enabled' => $subscription->isEnabled()]);
    }

    #[Route('/available-priorities', name: 'app_get_available_priorities', methods: ['GET'])]
    public function getAvailablePriorities(SessionInterface $session, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $classId = $request->query->get('classId');
        $sectionId = $request->query->get('sectionId');
        $schoolId = $this->currentSchool->getId(); // Récupérer l'école de l'utilisateur connecté
        if (!$classId || !$sectionId) {
            return new JsonResponse(['error' => 'Classe ou section invalide.'], 400);
        }
        $schoolPeriod = $this->currentPeriod;
        if (!$schoolPeriod) {
            return new JsonResponse(['error' => 'Année scolaire invalide.'], 400);
        }

        $classesIds = $this->entityManager->getRepository(SchoolClassPeriod::class)->findBy(['id' => $classId]);
        $classesIds = array_map(function ($class) {
            return $class->getClassOccurence()->getId();
        }, $classesIds);

        $schoolClassPeriod = $this->schoolClassRepository->find($classId);


        // Récupérer les priorités déjà utilisées
        if ($schoolClassPeriod) {
            $usedPriorities = $this->paymentModalsRepository->findUsedPriorities($schoolId, $schoolClassPeriod->getId(), $schoolPeriod->getId());
        } else {
            $usedPriorities = [];
        }

        // Calculer les priorités disponibles
        $allPriorities = range(1, 10);
        $availablePriorities = array_diff($allPriorities, $usedPriorities);
        array_push($allPriorities, 100); // Ajouter la priorité 100 à la liste des priorités disponibles

        return new JsonResponse([
            'availablePriorities' => $allPriorities,
        ]);
    }

    #[Route('/subscriptions/load', name: 'app_load_subscriptions', methods: ['GET'])]
    public function loadSubscriptions(SessionInterface $session, Request $request, ModalitiesSubscriptionsRepository $subscriptionsRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));

        $studentId = $request->query->get('studentId');
        $classId = $request->query->get('classId');

        if (!$studentId || !$classId) {
            return new JsonResponse(['error' => 'Les paramètres studentId et classId sont requis.'], 400);
        }

        $modal = $this->paymentModalsRepository->findBy(['modalType' => 'additional']);
        $schoolPeriod = $this->currentPeriod;
        $school = $this->currentSchool;

        // Récupérer les souscriptions pour l'élève et la classe
        $subscriptions = $subscriptionsRepository->findBy([
            'student' => $studentId,
            'schoolClassPeriod' => $entityManager->getRepository(SchoolClassPeriod::class)->find($classId),
            'enabled' => true,
            'paymentModal' => $modal,
        ]);

        // Préparer les données pour le DataTable
        $data = array_map(function ($subscription) {
            return [
                'id' => $subscription->getId(),
                'modalLabel' => $subscription->getPaymentModal()->getLabel(),
                'subscriptionType' => $subscription->getIsFull() ? 'full' : 'half',
                'periodicity' => $subscription->getIsFullPeriod(),
                'amount' => $subscription->getPaymentModal()->getAmount(),
            ];
        }, $subscriptions);

        return new JsonResponse($data);
    }

    #[Route('/modalities/available', name: 'app_load_available_modalities', methods: ['GET'])]
    public function loadAvailableModalities(
        Request $request,
        SchoolClassPaymentModalRepository $modalitiesRepository,
        ModalitiesSubscriptionsRepository $subscriptionsRepository,
        EntityManagerInterface $entityManager,
        SessionInterface $session
    ): JsonResponse {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $studentId = $request->query->get('studentId');
        $classId = $request->query->get('classId');

        if (!$studentId || !$classId) {
            return new JsonResponse(['error' => 'Les paramètres studentId et classId sont requis.'], 400);
        }

        $schoolPeriod = $this->currentPeriod;
        $school = $this->currentSchool;

        $schoolClassPeriod = $entityManager->getRepository(SchoolClassPeriod::class)->find($classId);
        // Récupérer toutes les modalités pour la classe
        $allModalities = $modalitiesRepository->findBy(['schoolClassPeriod' => $schoolClassPeriod, 'modalType' => 'additional']);

        // Récupérer les souscriptions de l'élève pour la classe
        $subscribedModalities = $subscriptionsRepository->findBy([
            'student' => $studentId,
            'schoolClassPeriod' => $schoolClassPeriod,
        ]);

        // Extraire les IDs des modalités souscrites avec `enabled = 1`
        $enabledSubscribedModalitiesIds = array_map(function ($subscription) {
            return $subscription->getPaymentModal()->getId();
        }, array_filter($subscribedModalities, function ($subscription) {
            return $subscription->isEnabled(); // Vérifie si la souscription est activée
        }));

        // Filtrer les modalités disponibles (non souscrites ou souscrites mais désactivées)
        $availableModalities = array_filter($allModalities, function ($modal) use ($enabledSubscribedModalitiesIds) {
            return !in_array($modal->getId(), $enabledSubscribedModalitiesIds);
        });

        // Préparer les données pour la réponse JSON
        $data = array_map(function ($modal) {
            return [
                'id' => $modal->getId(),
                'label' => $modal->getLabel(),
                'amount' => $modal->getAmount(),
            ];
        }, $availableModalities);

        return new JsonResponse($data);
    }

    #[Route('/subscriptions/save', name: 'app_save_subscription', methods: ['POST'])]
    public function saveSubscription(
        Request $request,
        EntityManagerInterface $entityManager,
        SchoolClassPaymentModalRepository $modalitiesRepository,
        SchoolClassPeriodRepository $classRepository,
        ModalitiesSubscriptionsRepository $subscriptionsRepository,
        OperationLogger $logger,
        SessionInterface $session,
        ManagerRegistry $doctrine
    ): JsonResponse {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $studentId = $request->request->get('studentId');
        $classId = $request->request->get('classId');
        $modalId = $request->request->get('modalId');
        $subscriptionType = $request->request->get('subscriptionType');
        $periodicity = $request->request->get('periodicity');

        // Validation des données
        if (!$studentId || !$classId || !$modalId || !$subscriptionType || !$periodicity) {
            return new JsonResponse(['error' => 'Tous les champs sont obligatoires.'], 400);
        }

        // Récupérer l'élève
        $student = $this->userRepository->find($studentId);
        if (!$student) {
            return new JsonResponse(['error' => 'Élève introuvable.'], 404);
        }

        $schoolPeriod = $this->currentPeriod;
        $school = $this->currentSchool;
        // Récupérer la classe
        $class = $entityManager->getRepository(SchoolClassPeriod::class)->find($classId);
        if (!$class) {
            return new JsonResponse(['error' => 'Classe introuvable.'], 404);
        }

        // Récupérer la modalité
        $modal = $modalitiesRepository->find($modalId);
        if (!$modal) {
            return new JsonResponse(['error' => 'Modalité introuvable.'], 404);
        }
        // Vérifier si une souscription existe déjà
        $subscription = $subscriptionsRepository->findOneBy([
            'student' => $student,
            'schoolClassPeriod' => $class,
            'paymentModal' => $modal,
            'schoolPeriod' => $schoolPeriod,
        ]);

        try {
            if ($subscription) {
                // Mettre à jour la souscription existante
                $subscription->setEnabled(true);
                $subscription->setIsFull($subscriptionType === 'full');
                $subscription->setIsFullPeriod($periodicity);
                $subscription->setUpdatedAt(new \DateTime());
            } else {
                // Créer une nouvelle souscription
                $subscription = new ModalitiesSubscriptions();
                $subscription->setStudent($student);
                $subscription->setSchoolClass($class);
                $subscription->setPaymentModal($modal);
                $subscription->setIsFull($subscriptionType === 'full');
                $subscription->setIsFullPeriod($periodicity);
                $subscription->setEnabled(true); // Activer la souscription par défaut
                $subscription->setSubscriptionDate(new \DateTime());
                $subscription->setSchoolPeriod($schoolPeriod);
                $subscription->setCreatedAt(new \DateTime());
                $subscription->setUpdatedAt(new \DateTime());

                $entityManager->persist($subscription);
            }

            // Sauvegarder les modifications
            $entityManager->flush();

            // Enregistrer l'historique de l'opération
            $logger->log(
                ($subscription->getId() ? 'MODIFICATION' : '') . ' SOUSCRIPTION DE L\'ÉLÈVE ' . $student->getFullName(),
                'success',
                'ModalitiesSubscriptions',
                $subscription->getId(),
                null,
                [
                    'studentId' => $studentId,
                    'classId' => $classId,
                    'modalId' => $modalId,
                    'subscriptionType' => $subscriptionType,
                    'periodicity' => $periodicity,
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );

            return new JsonResponse(['success' => 'Souscription enregistrée avec succès.']);
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // Enregistrer l'erreur dans l'historique
            $logger->log(
                ($subscription ? 'MODIFICATION' : '') . ' SOUSCRIPTION DE L\'ÉLÈVE ' . $student->getFullName(),
                'error',
                'ModalitiesSubscriptions',
                null,
                $e->getMessage(),
                [
                    'studentId' => $studentId,
                    'classId' => $classId,
                    'modalId' => $modalId,
                    'subscriptionType' => $subscriptionType,
                    'periodicity' => $periodicity,
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );

            return new JsonResponse(['error' => 'Une erreur est survenue lors de l\'enregistrement de la souscription.'], 500);
        }
    }

    #[Route('/subscriptions/cancel', name: 'app_cancel_subscription', methods: ['POST'])]
    public function cancelSubscription(
        Request $request,
        ModalitiesSubscriptionsRepository $subscriptionsRepository,
        OperationLogger $logger
    ): JsonResponse {
        $subscriptionId = $request->request->get('subscriptionId');
        $cancelType = $request->request->get('cancelType'); // "partial" ou "definitive"

        // Validation des paramètres
        if (!$subscriptionId || !$cancelType) {
            return new JsonResponse(['error' => 'Les paramètres subscriptionId et cancelType sont requis.'], 400);
        }

        // Récupérer la souscription
        $subscription = $subscriptionsRepository->find($subscriptionId);
        if (!$subscription) {
            return new JsonResponse(['error' => 'Souscription introuvable.'], 404);
        }

        try {
            if ($cancelType === 'partial') {
                // Annulation partielle : désactiver la souscription
                $subscription->setEnabled(false);
                $subscription->setUpdatedAt(new \DateTime());
            } elseif ($cancelType === 'definitive') {
                // Annulation définitive : supprimer la souscription
                $this->entityManager->remove($subscription);
            } else {
                return new JsonResponse(['error' => 'Type d\'annulation invalide.'], 400);
            }

            // Sauvegarder les modifications
            $this->entityManager->flush();

            // Enregistrer l'historique de l'opération
            $logger->log(
                ($cancelType === 'partial' ? 'MODIFICATION' : 'SUPPRESSION') . ' SOUSCRIPTION DE L\'ÉLÈVE ' . $subscription->getStudent()->getFullName(),
                'success',
                'ModalitiesSubscriptions',
                $subscriptionId,
                null,
                [
                    'cancelType' => $cancelType,
                    'studentId' => $subscription->getStudent()->getId(),
                    'classId' => $subscription->getSchoolClass()->getId(),
                    'modalId' => $subscription->getPaymentModal()->getId(),
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );

            return new JsonResponse(['success' => 'Souscription annulée avec succès.']);
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // Enregistrer l'erreur dans l'historique
            $logger->log(
                $cancelType === 'partial' ? 'MODIFICATION' : 'SUPPRESSION' . ' SOUSCRIPTION DE L\'ÉLÈVE ' . $subscription->getStudent()->getFullName(),
                'error',
                'ModalitiesSubscriptions',
                $subscriptionId,
                $e->getMessage(),
                [
                    'cancelType' => $cancelType,
                    'subscriptionId' => $subscriptionId,
                    'studentId' => $subscription->getStudent()->getId(),
                    'classId' => $subscription->getSchoolClass()->getId(),
                    'modalId' => $subscription->getPaymentModal()->getId(),
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );

            return new JsonResponse(['error' => 'Une erreur est survenue lors de l\'annulation de la souscription.'], 500);
        }
    }

    #[Route('/reductions/save', name: 'app_save_reduction', methods: ['POST'])]
    public function saveReduction(
        Request $request,
        EntityManagerInterface $entityManager,
        AdmissionReductionsRepository $reductionsRepository,
        OperationLogger $logger,
        SessionInterface $session
    ): JsonResponse {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $studentId = $request->request->get('studentId');
        $classId = $request->request->get('classId');
        $reductionModalId = $request->request->get('reductionModalId');
        $reductionAmount = $request->request->get('reductionAmount');

        if (!$studentId || !$classId || !$reductionModalId || !$reductionAmount) {
            return new JsonResponse(['error' => 'Tous les champs sont obligatoires.'], 400);
        }

        $student = $this->userRepository->find($studentId);
        if (!$student) {
            return new JsonResponse(['error' => 'Élève introuvable.'], 404);
        }

        $schoolPeriod = $this->currentPeriod;
        $school = $this->currentSchool;

        $class = $entityManager->getRepository(SchoolClassPeriod::class)->find($classId);
        if (!$class) {
            return new JsonResponse(['error' => 'Classe introuvable.'], 404);
        }

        $reductionModal = $this->paymentModalsRepository->find($reductionModalId);
        if (!$reductionModal) {
            return new JsonResponse(['error' => 'Modalité de réduction introuvable.'], 404);
        }

        $schoolPeriod = $this->currentPeriod;
        if (!$schoolPeriod) {
            return new JsonResponse(['error' => 'Année scolaire introuvable.'], 404);
        }

        $reduction = new AdmissionReductions();
        $reduction->setStudent($student);
        $reduction->setSchoolClass($class);
        $reduction->setReductionModal($reductionModal);
        $reduction->setReductionAmount($reductionAmount);
        $reduction->setSchoolPeriod($schoolPeriod);
        $reduction->setSchool($this->currentSchool);

        $currentUser = $this->getUser();
        $isSuperAdmin = in_array('ROLE_SUPER_ADMIN', $currentUser->getRoles());

        $reduction->setApproved($isSuperAdmin);
        $reduction->setPendingApproval(!$isSuperAdmin);
        $reduction->setRequestedBy($currentUser);
        // Pour le moment, approvalOwners = tableau contenant l'utilisateur super_admin (id=1)
        $superAdminUser = $entityManager->getRepository(\App\Entity\User::class)->find(1);
        $reduction->setApprovalOwners([$superAdminUser]);
        $reduction->setApprovedBy($isSuperAdmin ? $currentUser : null);

        try {
            $entityManager->persist($reduction);
            $entityManager->flush();

            // Enregistrer l'historique de l'opération
            $logger->log(
                'INSERTION',
                'success',
                'AdmissionReductions',
                $reduction->getId(),
                null,
                [
                    'studentId' => $studentId,
                    'classId' => $classId,
                    'reductionModalId' => $reductionModalId,
                    'reductionAmount' => $reductionAmount,
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );

            return new JsonResponse(['success' => 'Réduction enregistrée avec succès.']);
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // Enregistrer l'erreur dans l'historique
            $logger->log(
                'INSERTION',
                'error',
                'AdmissionReductions',
                null,
                $e->getMessage(),
                [
                    'studentId' => $studentId,
                    'classId' => $classId,
                    'reductionModalId' => $reductionModalId,
                    'reductionAmount' => $reductionAmount,
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );

            return new JsonResponse(['error' => 'Une erreur est survenue lors de l\'enregistrement de la réduction.'], 500);
        }
    }

    #[Route('/reductions/list', name: 'app_list_reductions', methods: ['GET'])]
    public function listReductions(
        Request $request,
        AdmissionReductionsRepository $reductionsRepository,
        EntityManagerInterface $entityManager,
        SessionInterface $session
    ): JsonResponse {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $classId = $request->query->get('classId');
        $studentId = $request->query->get('studentId');

        $schoolPeriod = $this->currentPeriod;
        $school = $this->currentSchool;

        $class = $entityManager->getRepository(SchoolClassPeriod::class)->find($classId);
        if (!$class) {
            return new JsonResponse(['error' => 'Classe introuvable.'], 404);
        }

        $student = $this->userRepository->find($studentId);
        if (!$student) {
            return new JsonResponse(['error' => 'Élève introuvable.'], 404);
        }

        $reductions = $reductionsRepository->findBy([
            'schoolClassPeriod' => $class,
            'student' => $student,
            'approved' => true,
        ]);

        $data = array_map(function ($reduction) {
            return [
                'id' => $reduction->getId(),
                'type' => $reduction->getReductionModal()->getLabel(),
                'amount' => $reduction->getReductionAmount(),
                'date' => $reduction->getDateCreated()->format('Y-m-d'),
                'modalId' => $reduction->getReductionModal()->getId(),
            ];
        }, $reductions);

        return new JsonResponse($data);
    }

    #[Route('/reductions/delete/{id}', name: 'app_delete_reduction', methods: ['DELETE'])]
    public function deleteReduction(
        AdmissionReductions $reduction,
        EntityManagerInterface $entityManager,
        OperationLogger $logger
    ): JsonResponse {
        try {
            // Supprimer la réduction
            $entityManager->remove($reduction);
            $entityManager->flush();

            // Enregistrer l'historique de l'opération
            $logger->log(
                'SUPPRESSION DE LA RÉDUCTION DE L\'ÉLÈVE ' . $reduction->getStudent()->getFullName(),
                'success',
                'AdmissionReductions',
                $reduction->getId(),
                null,
                [
                    'studentId' => $reduction->getStudent()->getId(),
                    'classId' => $reduction->getSchoolClass()->getId(),
                    'reductionModalId' => $reduction->getReductionModal()->getId(),
                    'reductionAmount' => $reduction->getReductionAmount(),
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );

            return new JsonResponse(['success' => 'Réduction supprimée avec succès.']);
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // Enregistrer l'erreur dans l'historique
            $logger->log(
                'SUPPRESSION DE LA RÉDUCTION DE L\'ÉLÈVE ' . $reduction->getStudent()->getFullName(),
                'error',
                'AdmissionReductions',
                $reduction->getId(),
                $e->getMessage(),
                [
                    'reductionId' => $reduction->getId(),
                    'studentId' => $reduction->getStudent()->getId(),
                    'classId' => $reduction->getSchoolClass()->getId(),
                    'modalId' => $reduction->getReductionModal()->getId(),
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );

            return new JsonResponse(['error' => 'Une erreur est survenue lors de la suppression de la réduction.'], 500);
        }
    }

    #[Route('/modalities/all', name: 'app_load_all_modalities', methods: ['GET'])]
    public function loadAllModalities(SessionInterface $session, Request $request, SchoolClassPaymentModalRepository $modalitiesRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $classId = $request->query->get('classId');
        $studentId = $request->query->get('studentId');

        $schoolPeriod = $this->currentPeriod;
        $school = $this->currentSchool;
        $schoolClassPeriod = $entityManager->getRepository(SchoolClassPeriod::class)->find($classId);
        // Récupérer toutes les modalités (standard et additionnelles)
        $modalities = $modalitiesRepository->findBy(['schoolClassPeriod' => $schoolClassPeriod]);

        $data = array_map(function ($modal) {
            return [
                'id' => $modal->getId(),
                'label' => $modal->getLabel(),
                'amount' => $modal->getAmount(),
            ];
        }, $modalities);

        return new JsonResponse($data);
    }

    #[Route('/reductions/validate', name: 'app_validate_reduction', methods: ['POST'])]
    public function validateReduction(
        Request $request,
        SchoolClassPaymentModalRepository $modalitiesRepository,
        SchoolClassAdmissionPaymentRepository $paymentRepository,
        EntityManagerInterface $entityManager,
        SessionInterface $session
    ): JsonResponse {

        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $reductionModalId = $request->request->get('reductionModalId');
        $reductionAmount = (float) $request->request->get('reductionAmount');
        $studentId = $request->request->get('studentId');
        $classId = $request->request->get('classId');

        // Vérifier si la modalité existe
        $modal = $modalitiesRepository->find($reductionModalId);
        if (!$modal) {
            return new JsonResponse(['valid' => false, 'message' => 'Modalité introuvable.']);
        }
        $modalReduction = $this->entityManager->getRepository(AdmissionReductions::class)->findBy(['reductionModal' => $modal, 'student' => $studentId, 'schoolClassPeriod' => $classId]);
        if ($modalReduction && !$request->request->get('reductionId')) {
            return new JsonResponse(['valid' => false, 'message' => 'Une réduction existe déjà pour cette association Modalité/Elève.']);
        }

        // Vérifier si le montant de la réduction dépasse le montant de la modalité
        if ($reductionAmount > $modal->getAmount()) {
            return new JsonResponse(['valid' => false, 'message' => 'Le montant de la réduction ne peut pas dépasser le montant de la modalité.']);
        }



        $student = $this->userRepository->find($studentId);
        if (!$student) {
            return new JsonResponse(['valid' => false, 'message' => 'Élève introuvable.']);
        }

        $schoolPeriod = $this->currentPeriod;
        $school = $this->currentSchool;

        $class = $entityManager->getRepository(SchoolClassPeriod::class)->find($classId);
        if (!$class) {
            return new JsonResponse(['valid' => false, 'message' => 'Classe introuvable.']);
        }

        // Calculer le total des montants déjà payés par l'élève pour l'école, la classe et l'année scolaire
        $totalPaid = $paymentRepository->getTotalPaidByStudentAndClassAndSchoolAndPeriod(
            $student,
            $class,
            $school,
            $schoolPeriod
        );
        $totalToPay = 0;
        $paymentModals = $class->getpaymentModals()->toArray();
        foreach ($paymentModals as $modal) {
            if ($modal->getModalType() === 'base') {
                $totalToPay += $modal->getAmount();
            }
        }



        // Vérifier si le montant de la réduction dépasse le total payé
        if ($reductionAmount > ($totalToPay - $totalPaid)) {
            return new JsonResponse(['valid' => false, 'message' => 'Le montant de la réduction ne peut pas dépasser le total des montants déjà payés.']);
        }

        return new JsonResponse(['valid' => true]);
    }

    #[Route('/reductions/update/{id}', name: 'app_update_reduction', methods: ['POST'])]
    public function updateReduction(
        AdmissionReductions $reduction,
        Request $request,
        SchoolClassPaymentModalRepository $modalitiesRepository,
        EntityManagerInterface $entityManager,
        OperationLogger $logger
    ): JsonResponse {
        $reductionModalId = $request->request->get('reductionModalId');
        $reductionAmount = (float) $request->request->get('reductionAmount');

        // Vérifier si la modalité existe
        $modal = $modalitiesRepository->find($reductionModalId);
        if (!$modal) {
            return new JsonResponse(['error' => 'Modalité introuvable.'], 404);
        }

        try {
            // Mettre à jour les données de la réduction
            $reduction->setReductionModal($modal);
            $reduction->setReductionAmount($reductionAmount);
            $reduction->setDateUpdated(new \DateTime());

            $entityManager->flush();

            // Enregistrer l'historique de l'opération
            $logger->log(
                'MODIFICATION DE LA RÉDUCTION DE L\'ÉLÈVE ' . $reduction->getStudent()->getFullName(),
                'success',
                'AdmissionReductions',
                $reduction->getId(),
                null,
                [
                    'studentId' => $reduction->getStudent()->getId(),
                    'classId' => $reduction->getSchoolClass()->getId(),
                    'reductionModalId' => $reductionModalId,
                    'reductionAmount' => $reductionAmount,
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );

            return new JsonResponse(['success' => 'Réduction mise à jour avec succès.']);
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // Enregistrer l'erreur dans l'historique
            $logger->log(
                'MODIFICATION DE LA RÉDUCTION DE L\'ÉLÈVE ' . $reduction->getStudent()->getFullName(),
                'error',
                'AdmissionReductions',
                null,
                $e->getMessage(),
                [
                    'reductionId' => $reduction->getId(),
                    'reductionModalId' => $reductionModalId,
                    'reductionAmount' => $reductionAmount,
                    'studentId' => $reduction->getStudent()->getId(),
                    'classId' => $reduction->getSchoolClass()->getId(),
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );

            return new JsonResponse(['error' => 'Une erreur est survenue lors de la mise à jour de la réduction.'], 500);
        }
    }

    #[Route('/batch-payment', name: 'app_batch_payment', methods: ['POST'])]
    public function batchPayment(SessionInterface $session, Request $request, EntityManagerInterface $entityManager, OperationLogger $logger, ManagerRegistry $doctrine): JsonResponse
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $studentId = $request->request->get('studentId');
        $classId = $request->request->get('classId');
        $amount = (float) $request->request->get('amount');

        if ($amount <= 0) {
            return $this->json(['error' => 'Le montant doit être supérieur à 0.'], Response::HTTP_BAD_REQUEST);
        }

        // Récupérer l'école et l'année scolaire en cours
        $school = $this->currentSchool;
        $schoolPeriod = $this->currentPeriod;

        if (!$school || !$schoolPeriod) {
            return $this->json(['error' => 'École ou année scolaire introuvable.'], Response::HTTP_BAD_REQUEST);
        }

        // Récupérer l'élève
        $student = $this->userRepository->find($studentId);
        if (!$student) {
            return $this->json(['error' => 'Élève introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $class = $entityManager->getRepository(SchoolClassPeriod::class)->find($classId);
        if (!$class) {
            return $this->json(['error' => 'Classe introuvable.'], Response::HTTP_NOT_FOUND);
        }

        // Récupérer les modalités de paiement pour la classe, triées par priorité croissante
        $modalities = $this->paymentModalsRepository->findBy(
            ['schoolClassPeriod' => $class, 'school' => $school, 'schoolPeriod' => $schoolPeriod, 'modalType' => 'base'],
            ['modalPriority' => 'ASC']
        );

        if (!$modalities) {
            return $this->json(['error' => 'Aucune modalité trouvée pour cette classe.'], Response::HTTP_NOT_FOUND);
        }

        // Calculer le total global à payer pour les modalités de type 'base'
        $totalToPay = array_reduce($modalities, function ($carry, $modal) {
            return $carry + $modal->getAmount();
        }, 0);

        // Calculer le reste à payer global pour les modalités de type 'base'
        $totalRemainingToPay = array_reduce($modalities, function ($carry, $modal) use ($student, $class, $school, $schoolPeriod) {
            $totalPaidForModal = $this->paymentRepository->getTotalPaidForModal($modal, $student, $class, $school, $schoolPeriod);
            $remainingToPay = $modal->getAmount() - $totalPaidForModal;
            return $carry + max(0, $remainingToPay); // Ajouter uniquement les montants restants positifs
        }, 0);

        // Vérifier que le montant émis n'est pas supérieur au total global
        if ($amount > $totalToPay) {
            return $this->json([
                'error' => 'Le montant émis dépasse le total à payer global pour les modalités de type base. Total à payer : ' . number_format($totalToPay, 2, ',', ' ') . ' €',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier que le montant émis n'est pas supérieur au reste à payer global
        if ($amount > $totalRemainingToPay) {
            return $this->json([
                'error' => 'Le montant émis dépasse le reste à payer global pour les modalités de base. Reste à payer : ' . number_format($totalRemainingToPay, 2, ',', ' ') . ' €',
            ], Response::HTTP_BAD_REQUEST);
        }

        $remainingAmount = $amount; // Montant restant à allouer
        $payments = []; // Stocker les paiements effectués

        try {
            foreach ($modalities as $modal) {
                // Calculer le reste à payer pour cette modalité
                $totalPaidForModal = $this->paymentRepository->getTotalPaidForModal($modal, $student, $class, $school, $schoolPeriod);
                $remainingToPay = $modal->getAmount() - $totalPaidForModal;

                if ($remainingToPay > 0 && $remainingAmount > 0) {
                    // Calculer le montant à payer pour cette modalité
                    $paymentAmount = min($remainingToPay, $remainingAmount);

                    // Créer un nouveau paiement
                    $payment = new SchoolClassAdmissionPayment();
                    $payment->setPaymentModal($modal);
                    $payment->setPaymentAmount($paymentAmount);
                    $payment->setPaymentDate(new \DateTime());
                    $payment->setSchoolClass($class);
                    $payment->setSchool($school);
                    $payment->setSchoolPeriod($schoolPeriod);
                    $payment->setStudent($student);
                    $payment->setModalType('base');

                    $entityManager->persist($payment);

                    // Ajouter le paiement à la liste des paiements
                    $payments[] = [
                        'modalId' => $modal->getId(),
                        'modalLabel' => $modal->getLabel(),
                        'paidAmount' => $paymentAmount,
                    ];

                    // Décrémenter le montant restant
                    $remainingAmount -= $paymentAmount;
                }

                // Si le montant restant est 0, arrêter la boucle
                if ($remainingAmount <= 0) {
                    break;
                }
            }

            // Sauvegarder les modifications
            $entityManager->flush();

            // Enregistrer l'historique de l'opération
            $logger->log(
                'INSERTION PAIEMENT BATCH DE L\'ÉLÈVE ' . $student->getFullName(),
                'success',
                'SchoolClassAdmissionPayment',
                null,
                null,
                [
                    'classId' => $classId,
                    'amount' => $amount,
                    'payments' => $payments,
                    'remainingAmount' => $remainingAmount,
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );

            // Retourner une réponse JSON
            return $this->json([
                'success' => true,
                'message' => 'Paiement batch effectué avec succès.',
                'payments' => $payments,
                'remainingAmount' => $remainingAmount,
            ]);
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // Enregistrer l'erreur dans l'historique
            $logger->log(
                'INSERTION PAIEMENT BATCH DE L\'ÉLÈVE ' . $student->getFullName(),
                'error',
                'SchoolClassAdmissionPayment',
                null,
                $e->getMessage(),
                [
                    'classId' => $classId,
                    'amount' => $amount,
                    'payments' => $payments,
                    'remainingAmount' => $remainingAmount,
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );

            return $this->json(['error' => 'Une erreur est survenue lors du paiement batch.'], 500);
        }
    }
    #[Route('/transport-modals/data', name: 'app_transport_modals_data', methods: ['GET'])]
    public function getTransportModalsData(
        Request $request,
        SchoolClassPaymentModalRepository $paymentModalsRepository
    ): JsonResponse {
        // Récupérer les modalités de type "transport"
        $transportModals = $paymentModalsRepository->findBy(['modalType' => 'transport']);

        // Préparer les données pour le DataTable
        if ($transportModals) {
            $data = array_map(function ($modal) {
                return [
                    'id' => $modal->getId(),
                    'label' => $modal->getLabel(),
                    'amount' => $modal->getAmount(),
                    'dueDate' => $modal->getDueDate()->format('Y-m-d'),
                    'priority' => $modal->getModalPriority(),
                ];
            }, $transportModals);
        } else {
            $data = [
                'id' => '',
                'label' => '',
                'amount' => '',
                'dueDate' => '',
                'priority' => ''
            ];
        }

        return new JsonResponse(['data' => $data]);
    }

    #[Route('/transport-modals/add', name: 'app_add_transport_modal', methods: ['POST'])]
    public function addTransportModal(
        Request $request,
        EntityManagerInterface $entityManager,
        OperationLogger $logger,
        SessionInterface $session
    ): JsonResponse {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        // Récupérer les données du formulaire
        $label = $request->request->get('label');
        $amount = $request->request->get('amount');
        $dueDate = $request->request->get('dueDate');
        $priority = $request->request->get('priority');

        // Validation des données
        if (!$label || !$amount || !$dueDate || !$priority) {
            return new JsonResponse(['error' => 'Tous les champs sont obligatoires.'], 400);
        }


        // Récupérer l'école associée à l'utilisateur connecté
        $school = $this->currentSchool;
        if (!$school) {
            return new JsonResponse(['error' => 'École introuvable.'], 404);
        }

        // Récupérer l'année scolaire en cours
        $schoolPeriod = $this->currentPeriod;
        if (!$schoolPeriod) {
            return new JsonResponse(['error' => 'Année scolaire introuvable.'], 404);
        }

        $schoolClassPeriod = $this->entityManager->getRepository(SchoolClassPeriod::class)->findOneBy(['school' => $school, 'period' => $schoolPeriod]);
        // Créer une nouvelle modalité de transport
        $transportModal = new SchoolClassPaymentModal();
        $transportModal->setLabel('Transport - (' . $label . ')')
            ->setAmount($amount)
            ->setDueDate(new \DateTime($dueDate))
            ->setModalType('transport') // Type de modalité : transport
            ->setModalPriority($priority)
            ->setSchool($school)
            ->setSchoolPeriod($schoolPeriod)
            ->setSchoolClassPeriod($schoolClassPeriod); // Pas de classe associée pour les modalités de transport

        try {
            // Sauvegarder la modalité
            $entityManager->persist($transportModal);
            $entityManager->flush();

            // Enregistrer l'historique de l'opération
            $logger->log(
                'INSERTION MODALITÉ DE TRANSPORT',
                'success',
                'SchoolClassPaymentModal',
                $transportModal->getId(),
                null,
                [
                    'label' => $label,
                    'amount' => $amount,
                    'dueDate' => $dueDate,
                    'priority' => $priority,
                    'modalId' => $transportModal->getId(),
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );

            return new JsonResponse(['success' => 'Modalité de transport ajoutée avec succès.']);
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // Enregistrer l'erreur dans l'historique
            $logger->log(
                'INSERTION MODALITÉ DE TRANSPORT',
                'error',
                'SchoolClassPaymentModal',
                null,
                $e->getMessage(),
                [
                    'label' => $label,
                    'amount' => $amount,
                    'dueDate' => $dueDate,
                    'priority' => $priority,
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );

            return new JsonResponse(['error' => 'Une erreur est survenue lors de l\'ajout de la modalité de transport.'], 500);
        }
    }

    #[Route('/transport-modals/delete', name: 'app_delete_transport_modal', methods: ['POST'])]
    public function deleteTransportModal(
        Request $request,
        SchoolClassPaymentModalRepository $paymentModalsRepository,
        EntityManagerInterface $entityManager,
        OperationLogger $logger,
        SessionInterface $session
    ): JsonResponse {

        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        // Récupérer l'ID de la modalité à supprimer
        $modalId = $request->request->get('id');

        if (!$modalId) {
            return new JsonResponse(['error' => 'ID de la modalité manquant.'], 400);
        }

        // Récupérer la modalité de transport
        $transportModal = $paymentModalsRepository->find($modalId);

        if (!$transportModal) {
            return new JsonResponse(['error' => 'Modalité de transport introuvable.'], 404);
        }

        try {
            // Supprimer la modalité
            $entityManager->remove($transportModal);
            $entityManager->flush();

            // Enregistrer l'historique de l'opération
            $logger->log(
                'SUPPRESSION MODALITÉ DE TRANSPORT',
                'success',
                'SchoolClassPaymentModal',
                $modalId,
                null,
                [
                    'label' => $transportModal->getLabel(),
                    'amount' => $transportModal->getAmount(),
                    'dueDate' => $transportModal->getDueDate()->format('Y-m-d'),
                    'priority' => $transportModal->getModalPriority(),
                    'modalId' => $modalId,
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );

            return new JsonResponse(['success' => 'Modalité de transport supprimée avec succès.']);
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // Enregistrer l'erreur dans l'historique
            $logger->log(
                'SUPPRESSION MODALITÉ DE TRANSPORT',
                'error',
                'SchoolClassPaymentModal',
                null,
                $e->getMessage(),
                [
                    'modalId' => $modalId,
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );

            return new JsonResponse(['error' => 'Une erreur est survenue lors de la suppression de la modalité.'], 500);
        }
    }

    #[Route('/transport-modals/get/{id}', name: 'app_get_transport_modal', methods: ['GET'])]
    public function getTransportModal(
        SchoolClassPaymentModal $transportModal
    ): JsonResponse {
        return new JsonResponse([
            'id' => $transportModal->getId(),
            'label' => str_replace(')', '', str_replace('Transport - (', '', $transportModal->getLabel())),
            'amount' => $transportModal->getAmount(),
            'dueDate' => $transportModal->getDueDate()->format('Y-m-d'),
            'priority' => $transportModal->getModalPriority(),
        ]);
    }


    #[Route('/transport-modals/update/{id}', name: 'app_update_transport_modal', methods: ['POST'])]
    public function updateTransportModal(
        Request $request,
        SchoolClassPaymentModal $transportModal,
        EntityManagerInterface $entityManager,
        OperationLogger $logger,
        SessionInterface $session
    ): JsonResponse {

        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        // Récupérer les données du formulaire
        $label = 'Transport - (' . $request->request->get('label') . ')';
        $amount = $request->request->get('amount');
        $dueDate = $request->request->get('dueDate');
        $priority = $request->request->get('priority');

        // Validation des données
        if (!$label || !$amount || !$dueDate || !$priority) {
            return new JsonResponse(['error' => 'Tous les champs sont obligatoires.'], 400);
        }

        try {
            // Mettre à jour les données de la modalité
            $transportModal->setLabel($label)
                ->setAmount($amount)
                ->setDueDate(new \DateTime($dueDate))
                ->setModalPriority($priority);

            $entityManager->flush();

            // Enregistrer l'historique de l'opération
            $logger->log(
                'MODIFICATION MODALITÉ DE TRANSPORT',
                'success',
                'SchoolClassPaymentModal',
                $transportModal->getId(),
                null,
                [
                    'label' => $label,
                    'amount' => $amount,
                    'dueDate' => $dueDate,
                    'priority' => $priority,
                    'modalId' => $transportModal->getId(),
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );

            return new JsonResponse(['success' => 'Modalité de transport mise à jour avec succès.']);
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // Enregistrer l'erreur dans l'historique
            $logger->log(
                'MODIFICATION MODALITÉ DE TRANSPORT',
                'error',
                'SchoolClassPaymentModal',
                null,
                $e->getMessage(),
                [
                    'id' => $transportModal->getId(),
                    'label' => $label,
                    'amount' => $amount,
                    'dueDate' => $dueDate,
                    'priority' => $priority,
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );

            return new JsonResponse(['error' => 'Une erreur est survenue lors de la mise à jour de la modalité.'], 500);
        }
    }

    #[Route('/transport-subscriptions/save', name: 'app_save_transport_subscription', methods: ['POST'])]
    public function saveTransportSubscription(
        Request $request,
        EntityManagerInterface $entityManager,
        SchoolClassPaymentModalRepository $modalitiesRepository,
        SchoolClassPeriodRepository $classRepository,
        ModalitiesSubscriptionsRepository $subscriptionsRepository,
        OperationLogger $logger,
        SessionInterface $session,
        ManagerRegistry $doctrine
    ): JsonResponse {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $studentId = $request->request->get('studentId');
        $classId = $request->request->get('classId');
        $modalId = $request->request->get('modalId');
        $subscriptionType = $request->request->get('subscriptionType');
        $periodicity = $request->request->get('periodicity');

        // Validation des données
        if (!$studentId || !$classId || !$modalId || !$subscriptionType || !$periodicity) {
            return new JsonResponse(['error' => 'Tous les champs sont obligatoires.'], 400);
        }

        // Récupérer l'élève
        $student = $this->userRepository->find($studentId);
        if (!$student) {
            return new JsonResponse(['error' => 'Élève introuvable.'], 404);
        }

        // Récupérer l'année scolaire en cours
        $schoolPeriod = $this->currentPeriod;
        if (!$schoolPeriod) {
            return new JsonResponse(['error' => 'Année scolaire introuvable.'], 404);
        }
        $school = $this->currentSchool;

        // Récupérer la classe
        $class = $entityManager->getRepository(SchoolClassPeriod::class)->find($classId);
        if (!$class) {
            return new JsonResponse(['error' => 'Classe introuvable.'], 404);
        }

        // Récupérer la modalité
        $modal = $modalitiesRepository->find($modalId);
        if (!$modal) {
            return new JsonResponse(['error' => 'Modalité introuvable.'], 404);
        }


        // Vérifier si une souscription existe déjà
        $subscription = $subscriptionsRepository->findOneBy([
            'student' => $student,
            'schoolClassPeriod' => $class,
            'paymentModal' => $modal,
            'schoolPeriod' => $schoolPeriod,
        ]);

        try {
            if ($subscription) {
                // Mettre à jour la souscription existante
                $subscription->setEnabled(true);
                $subscription->setIsFull($subscriptionType === 'full');
                $subscription->setIsFullPeriod($periodicity);
                $subscription->setUpdatedAt(new \DateTime());
            } else {
                // Créer une nouvelle souscription
                $subscription = new ModalitiesSubscriptions();
                $subscription->setStudent($student);
                $subscription->setSchoolClass($class);
                $subscription->setPaymentModal($modal);
                $subscription->setIsFull($subscriptionType === 'full');
                $subscription->setIsFullPeriod($periodicity);
                $subscription->setEnabled(true); // Activer la souscription par défaut
                $subscription->setSubscriptionDate(new \DateTime());
                $subscription->setSchoolPeriod($schoolPeriod);
                $subscription->setCreatedAt(new \DateTime());
                $subscription->setUpdatedAt(new \DateTime());

                $entityManager->persist($subscription);
            }

            // Sauvegarder les modifications
            $entityManager->flush();

            // Enregistrer l'historique de l'opération
            $logger->log(
                $subscription->getId() ? 'MODIFICATION' : 'AJOUT' . ' DE LA SOUSCRIPTION DE L\'ÉLÈVE ' . $student->getFullName(),
                'success',
                'ModalitiesSubscriptions',
                $subscription->getId(),
                null,
                [
                    'studentId' => $studentId,
                    'classId' => $classId,
                    'modalId' => $modalId,
                    'subscriptionType' => $subscriptionType,
                    'periodicity' => $periodicity,
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );

            return new JsonResponse(['success' => 'Souscription enregistrée avec succès.']);
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // Enregistrer l'erreur dans l'historique
            $logger->log(
                $subscription ? 'MODIFICATION' : 'AJOUT' . ' DE LA SOUSCRIPTION DE L\'ÉLÈVE ' . $student->getFullName(),
                'error',
                'ModalitiesSubscriptions',
                null,
                $e->getMessage(),
                [
                    'studentId' => $studentId,
                    'classId' => $classId,
                    'modalId' => $modalId,
                    'subscriptionType' => $subscriptionType,
                    'periodicity' => $periodicity,
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );

            return new JsonResponse(['error' => 'Une erreur est survenue lors de l\'enregistrement de la souscription.'], 500);
        }
    }


    #[Route('/transport-subscriptions/load', name: 'app_load_transport_subscriptions', methods: ['GET'])]
    public function loadTransportSubscriptions(
        Request $request,
        ModalitiesSubscriptionsRepository $subscriptionsRepository,
        SchoolClassPaymentModalRepository $modalitiesRepository,
        EntityManagerInterface $entityManager,
        SessionInterface $session
    ): JsonResponse {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $studentId = $request->query->get('studentId');
        $classId = $request->query->get('classId');

        // Validation des paramètres
        if (!$studentId || !$classId) {
            return new JsonResponse(['error' => 'Les paramètres studentId et classId sont requis.'], 400);
        }

        $modal = $this->paymentModalsRepository->findBy(['modalType' => 'transport']);

        $schoolPeriod = $this->currentPeriod;
        $school = $this->currentSchool;
        $class = $entityManager->getRepository(SchoolClassPeriod::class)->find($classId);

        // Récupérer les souscriptions de transport pour l'élève et la classe
        $subscriptions = $subscriptionsRepository->findBy([
            'student' => $studentId,
            'schoolClassPeriod' => $classId,
            'enabled' => true,
            'paymentModal' => $modal,
        ]);

        // Préparer les données pour la réponse JSON
        $data = array_map(function ($subscription) {
            $modal = $subscription->getPaymentModal();
            return [
                'id' => $subscription->getId(),
                'modalLabel' => $modal->getLabel(),
                'subscriptionType' => $subscription->getIsFull() ? 'full' : 'half',
                'periodicity' => $subscription->getIsFullPeriod(),
                'amount' => $modal->getAmount(),
            ];
        }, $subscriptions);

        return new JsonResponse($data);
    }


    #[Route('/transport-modalities/available', name: 'app_load_available_transport_modalities', methods: ['GET'])]
    public function loadAvailableTransportModalities(
        Request $request,
        SchoolClassPaymentModalRepository $modalitiesRepository,
        ModalitiesSubscriptionsRepository $subscriptionsRepository
    ): JsonResponse {
        $studentId = $request->query->get('studentId');
        $classId = $request->query->get('classId');

        // Validation des paramètres
        if (!$studentId || !$classId) {
            return new JsonResponse(['error' => 'Les paramètres studentId et classId sont requis.'], 400);
        }

        // Récupérer les modalités de type transport pour la classe
        $transportModalities = $modalitiesRepository->findBy([
            'modalType' => 'transport',
        ]);

        // Filtrer les modalités non souscrites
        $availableModalities = array_filter($transportModalities, function ($modal) use ($subscriptionsRepository, $studentId) {
            $subscription = $subscriptionsRepository->findOneBy([
                'paymentModal' => $modal,
                'student' => $studentId,
                'enabled' => true,
            ]);
            return !$subscription; // Inclure uniquement les modalités non souscrites
        });

        // Préparer les données pour la réponse JSON
        $data = array_map(function ($modal) {
            return [
                'id' => $modal->getId(),
                'label' => $modal->getLabel(),
                'amount' => $modal->getAmount(),
            ];
        }, $availableModalities);

        return new JsonResponse($data);
    }

    #[Route('/transport-subscriptions/cancel', name: 'app_cancel_transport_subscription', methods: ['POST'])]
    public function cancelTransportSubscription(
        Request $request,
        ModalitiesSubscriptionsRepository $subscriptionsRepository,
        EntityManagerInterface $entityManager,
        OperationLogger $logger
    ): JsonResponse {
        $subscriptionId = $request->request->get('subscriptionId');

        // Validation des données
        if (!$subscriptionId) {
            return new JsonResponse(['error' => 'L\'ID de la souscription est requis.'], 400);
        }

        // Récupérer la souscription
        $subscription = $subscriptionsRepository->find($subscriptionId);
        if (!$subscription) {
            return new JsonResponse(['error' => 'Souscription introuvable.'], 404);
        }

        try {
            // Désactiver la souscription
            $subscription->setEnabled(false);
            $subscription->setUpdatedAt(new \DateTime());

            // Sauvegarder les modifications
            $entityManager->flush();

            // Enregistrer l'historique de l'opération
            $logger->log(
                'SUPPRESSION MODALITÉ DE TRANSPORT',
                'success',
                'ModalitiesSubscriptions',
                $subscription->getId(),
                null,
                [
                    'studentId' => $subscription->getStudent()->getId(),
                    'classId' => $subscription->getSchoolClass()->getId(),
                    'modalId' => $subscription->getPaymentModal()->getId(),
                    'subscriptionId' => $subscriptionId,
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );

            return new JsonResponse(['success' => 'Souscription annulée avec succès.']);
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // Enregistrer l'erreur dans l'historique
            $logger->log(
                'SUPPRESSION MODALITÉ DE TRANSPORT',
                'error',
                'ModalitiesSubscriptions',
                null,
                $e->getMessage(),
                [
                    'subscriptionId' => $subscriptionId,
                    'studentId' => $subscription->getStudent()->getId(),
                    'classId' => $subscription->getSchoolClass()->getId(),
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );

            return new JsonResponse(['error' => 'Une erreur est survenue lors de l\'annulation de la souscription.'], 500);
        }
    }


    #[Route('/payment-history/data', name: 'app_payment_history_data', methods: ['GET'])]
    public function getPaymentHistoryData(
        Request $request,
        SchoolClassAdmissionPaymentRepository $paymentRepository,
        EntityManagerInterface $entityManager,
        SessionInterface $session
    ): JsonResponse {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $studentId = $request->query->get('studentId');
        $classId = $request->query->get('classId');
        $modalType = $request->query->get('modalType'); // base, additional, transport

        // Validation des paramètres
        if (!$studentId || !$classId || !$modalType) {
            return new JsonResponse(['error' => 'Les paramètres studentId, classId et modalType sont requis.'], 400);
        }

        $schoolPeriod = $this->currentPeriod;
        $school = $this->currentSchool;
        $schoolClassPeriod = $entityManager->getRepository(SchoolClassPeriod::class)->find($classId);

        // Récupérer les paiements pour l'élève, la classe et le type de modalité
        $payments = $paymentRepository->findByModalTypeAndStudentAndClass($modalType, $studentId, $schoolClassPeriod->getId());

        // Préparer les données pour la réponse JSON
        $data = array_map(function ($payment) {
            return [
                'date' => $payment->getPaymentDate()->format('Y-m-d'),
                'amount' => $payment->getPaymentAmount(),
                'modalLabel' => $payment->getPaymentModal()->getLabel(),
                'id' => $payment->getId(),

            ];
        }, $payments);

        return new JsonResponse($data);
    }


    #[Route('/payment/cancel', name: 'app_cancel_payment', methods: ['POST'])]
    public function cancelPayment(Request $request, EntityManagerInterface $entityManager, OperationLogger $logger): JsonResponse
    {
        $paymentId = $request->request->get('paymentId');

        // Validation des données
        if (!$paymentId) {
            return new JsonResponse(['error' => 'L\'ID du paiement est requis.'], 400);
        }

        // Récupérer le paiement
        $payment = $this->paymentRepository->find($paymentId);
        if (!$payment) {
            return new JsonResponse(['error' => 'Paiement introuvable.'], 404);
        }

        try {
            // Supprimer ou désactiver le paiement
            $entityManager->remove($payment);
            $entityManager->flush();
            // Enregistrer l'historique de l'opération
            $logger->log(
                'SUPPRESSION DU PAIEMENT',
                'success',
                'SchoolClassAdmissionPayment',
                $paymentId,
                null,
                [
                    'school' => $this->currentSchool->getName(),
                    'student' => $payment->getStudent()->getFullName(),
                    'paymentId' => $paymentId,
                    'paymentAmount' => $payment->getPaymentAmount(),
                    'period' => $this->currentPeriod->getName()
                ],
            );

            return new JsonResponse(['success' => 'Paiement annulé avec succès.']);
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // Enregistrer l'erreur dans l'historique
            $logger->log(
                'SUPPRESSION DU PAIEMENT',
                'error',
                'SchoolClassAdmissionPayment',
                null,
                $e->getMessage(),
                [
                    'paymentId' => $paymentId,
                    'school' => $this->currentSchool->getName(),
                    'student' => $payment->getStudent()->getFullName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );

            return new JsonResponse(['error' => 'Une erreur est survenue lors de l\'annulation du paiement.'], 500);
        }
    }

    #[Route('/student/payments', name: 'app_student_payments', methods: ['GET'])]
    public function getStudentPayments(SessionInterface $session, Request $request): Response
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $registrationNumber = $request->query->get('regNum');

        // Validation des données
        if (!$registrationNumber) {
            return $this->render('admission/check_student.html.twig', [
                'error' => 'Le matricule est requis.',
                'student' => null,
                'payments' => [],
            ]);
        }

        // Récupérer l'élève à partir de son matricule
        $student = $this->userRepository->findOneBy(['registrationNumber' => $registrationNumber]);
        if (!$student) {
            return $this->render('admission/check_student.html.twig', [
                'error' => 'Élève introuvable.',
                'student' => null,
                'payments' => [],
            ]);
        }

        // Récupérer tous les paiements de l'élève
        $payments = $this->paymentRepository->findBy(['student' => $student, 'modalType' => 'base']);

        // Regrouper les paiements par modalité
        $groupedPayments = [];
        foreach ($payments as $payment) {
            $modalId = $payment->getPaymentModal()->getId();
            if (!isset($groupedPayments[$modalId])) {
                $groupedPayments[$modalId] = [
                    'modalLabel' => $payment->getPaymentModal()->getLabel(),
                    'amount' => $payment->getPaymentModal()->getAmount(),
                    'paid' => 0,
                    'dueDate' => $payment->getPaymentModal()->getDueDate()->format('Y-m-d'),
                    'modalPriority' => $payment->getPaymentModal()->getModalPriority(),
                ];
            }
            $groupedPayments[$modalId]['paid'] += $payment->getPaymentAmount();
        }

        // Trier les modalités par modalPriority
        uasort($groupedPayments, function ($a, $b) {
            return $a['modalPriority'] <=> $b['modalPriority'];
        });

        $schoolClassPeriod = $this->entityManager->getRepository(SchoolClassPeriod::class)->findOneBy(['school' => $this->currentSchool, 'period' => $this->currentPeriod]);

        $class = $this->studentRepository->findBy(['student' => $student, 'schoolClassPeriod' => $schoolClassPeriod]);
        $student->className = $class[0]->getSchoolClassPeriod()->getClassOccurence()->getName();


        return $this->render('admission/check_student.html.twig', [
            'error' => null,
            'student' => $student,
            'payments' => $groupedPayments,
        ]);
    }

    #[Route('/transport-payment/save', name: 'app_save_transport_payment', methods: ['POST'])]
    public function saveTransportPayment(
        Request $request,
        EntityManagerInterface $entityManager,
        OperationLogger $logger,
        SessionInterface $session
    ): JsonResponse {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));

        $subscriptionId = $request->request->get('subscriptionId');
        $paymentDate = $request->request->get('paymentDate');
        $amount = $request->request->get('amount');

        // Récupérer la souscription de transport
        $subscription = $entityManager->getRepository(\App\Entity\ModalitiesSubscriptions::class)->find($subscriptionId);
        if (!$subscription) {
            return new JsonResponse(['error' => 'Souscription de transport introuvable.'], 404);
        }

        // Récupérer l'élève et la modalité
        $student = $subscription->getStudent();
        $modal = $subscription->getPaymentModal();
        $schoolClassPeriod = $subscription->getSchoolClassPeriod();
        $schoolPeriod = $this->currentPeriod;
        $school = $this->currentSchool;

        // Validation des données
        // on vérifie que le montant est supérieur à 0 et inférieur ou égal au montant de la modalité
        if (!$paymentDate || !$amount || $amount <= 0 || $amount > $modal->getAmount()) {
            return new JsonResponse(['error' => 'Les données du paiement sont invalides.'], 400);
        }

        // on récupère le montant restant à payer pour la modalité
        $remainingToPay = $modal->getAmount() - array_sum(array_map(function ($m) {
            return $m->getPaymentAmount();
        }, array_filter($modal->getAdmissionPayments()->toArray(), function ($m) use ($student, $schoolClassPeriod) {
            return $m->getStudent() === $student && $m->getSchoolClass() === $schoolClassPeriod;
        })));

        // on vérifie que le montant du paiement est inférieur ou égal au montant restant à payer.
        if ($amount > $remainingToPay) {
            return new JsonResponse(['error' => 'Le montant du paiement ne peut pas dépasser le montant restant à payer.'], 400);
        }

        // Créer le paiement
        $payment = new \App\Entity\SchoolClassAdmissionPayment();
        $payment->setStudent($student);
        $payment->setPaymentDate(new \DateTime($paymentDate));
        $payment->setPaymentAmount($amount);
        $payment->setSchoolClass($schoolClassPeriod);
        $payment->setSchoolPeriod($schoolPeriod);
        $payment->setSchool($school);
        $payment->setPaymentModal($modal);
        $payment->setModalType('transport');
        $payment->setCreatedAt(new \DateTime());
        $payment->setUpdatedAt(new \DateTime());

        try {
            $entityManager->persist($payment);
            $entityManager->flush();

            $logger->log(
                'INSERTION PAIEMENT DE TRANSPORT DE L\'ÉLÈVE ' . $student->getFullName(),
                'success',
                'SchoolClassAdmissionPayment',
                $payment->getId(),
                null,
                [
                    'amount' => $amount,
                    'studentId' => $student->getId(),
                    'subscriptionId' => $subscriptionId,
                    'paymentDate' => $paymentDate,
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );

            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            $logger->log(
                'INSERTION PAIEMENT DE TRANSPORT DE L\'ÉLÈVE ' . $student->getFullName(),
                'error',
                'SchoolClassAdmissionPayment',
                null,
                $e->getMessage(),
                [
                    'amount' => $amount,
                    'studentId' => $student->getId(),
                    'subscriptionId' => $subscriptionId,
                    'paymentDate' => $paymentDate,
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );

            return new JsonResponse(['error' => 'Une erreur est survenue lors de l\'enregistrement du paiement.'], 500);
        }
    }
    #[Route('/pending-admission-reductions', name: 'app_pending_admission_reductions_ajax', methods: ['GET'])]
    public function getPendingAdmissionReductionsAjax(SessionInterface $session, EntityManagerInterface $entityManager, Request $request): JsonResponse
    {
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($session->get('period_id'));
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur non connecté.'], 401);
        }

        $reductions = $this->entityManager->getRepository(\App\Entity\AdmissionReductions::class)
            ->findPendingForApprovalOwner($user, $this->currentSchool, $this->currentPeriod);

        $data = [];
        foreach ($reductions as $reduction) {
            $data[] = [
                'id' => $reduction->getId(),
                'amount' => method_exists($reduction, 'getReductionAmount') ? $reduction->getReductionAmount() : null,
                'student' => $reduction->getStudent() ? (method_exists($reduction->getStudent(), 'getFullName') ? $reduction->getStudent()->getFullName() : null) : null,
                'requestedBy' => $reduction->getRequestedBy() ? (method_exists($reduction->getRequestedBy(), 'getFullName') ? $reduction->getRequestedBy()->getFullName() : null) : null,
                'createdAt' => method_exists($reduction, 'getDateCreated') && $reduction->getDateCreated() ? $reduction->getDateCreated()->format('Y-m-d H:i') : null,
                'approveUrl' => $this->generateUrl('app_approve_admission_reduction'),
                'rejectUrl' => $this->generateUrl('app_reject_admission_reduction'),
                'classe' => $reduction->getSchoolClass() ? $reduction->getSchoolClass()->getClassOccurence()->getName() : null,
                'period' => $reduction->getSchoolPeriod() ? $reduction->getSchoolPeriod()->getName() : null,
                'school' => $reduction->getSchool() ? $reduction->getSchool()->getName() : null,
                'modalite' => $reduction->getReductionModal() ? $reduction->getReductionModal()->getLabel() : null
            ];
        }

        return new JsonResponse(['reductions' => $data]);
    }

    /**
     * @Route("/paiement/info", name="app_get_payment_info", methods={"GET"})
     */
    #[Route('/paiement/info', name: 'app_get_payment_info', methods: ['GET'])]
    public function getPaymentInfo(EntityManagerInterface $em, \Symfony\Component\HttpFoundation\Request $request): JsonResponse
    {
        $paymentId = $request->query->get('id');
        $payment = $em->getRepository(\App\Entity\SchoolClassAdmissionPayment::class)->find($paymentId);
        if (!$payment) {
            return $this->json(['error' => 'Paiement introuvable'], 404);
        }
        // Récupérer la liste des modalités disponibles pour l'élève/la classe concernée
        $student = $payment->getStudent();
        $class = $payment->getSchoolClass();
        $modalites = $em->getRepository(\App\Entity\SchoolClassPaymentModal::class)->findBy(['schoolClassPeriod' => $class]);
        $modalitesArr = array_map(function ($m) {
            return [
                'id' => $m->getId(),
                'label' => $m->getLabel(),
            ];
        }, $modalites);
        return $this->json([
            'id' => $payment->getId(),
            'amount' => $payment->getPaymentAmount(),
            'date' => $payment->getPaymentDate() ? $payment->getPaymentDate()->format('Y-m-d') : '',
            'modaliteId' => $payment->getPaymentModal() ? $payment->getPaymentModal()->getId() : null,
            'modalites' => $modalitesArr,
        ]);
    }

    #[Route('/paiement/update', name: 'app_update_payment', methods: ['POST'])]
    public function updatePayment(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $paymentId = $request->request->get('paymentId');
        $amount = $request->request->get('amount');
        $date = $request->request->get('date');
        $modaliteId = $request->request->get('modalite');

        // Récupérer le paiement existant
        $payment = $em->getRepository(\App\Entity\SchoolClassAdmissionPayment::class)->find($paymentId);
        if (!$payment) {
            return $this->json(['error' => 'Paiement introuvable'], 404);
        }

        // Récupérer la modalité si besoin
        $modalite = $em->getRepository(\App\Entity\SchoolClassPaymentModal::class)->find($modaliteId);
        if (!$modalite) {
            return $this->json(['error' => 'Modalité introuvable'], 404);
        }

        // Mettre à jour les champs
        $payment->setPaymentAmount($amount);
        $payment->setPaymentDate(new \DateTime($date));
        $payment->setPaymentModal($modalite);

        try {
            $em->flush();
            // Enregistrer l'historique de l'opération
            $this->operationLogger->log(
                'MISE À JOUR DU PAIEMENT DE L\'ÉLÈVE ' . $payment->getStudent()->getFullName(),
                'success',
                'SchoolClassAdmissionPayment',
                $payment->getId(),
                null,
                [
                    'amount' => $amount,
                    'date' => $date,
                    'modaliteId' => $modaliteId,
                    'student' => $payment->getStudent()->getFullName(),
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );
            return $this->json(['success' => true]);
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // Enregistrer l'erreur dans l'historique
            $this->operationLogger->log(
                'ERREUR DE MISE À JOUR DU PAIEMENT DE L\'ÉLÈVE ' . $payment->getStudent()->getFullName(),
                'error',
                'SchoolClassAdmissionPayment',
                $payment->getId(),
                null,
                [
                    'amount' => $amount,
                    'date' => $date,
                    'modaliteId' => $modaliteId,
                    'student' => $payment->getStudent()->getFullName(),
                    'school' => $this->currentSchool->getName(),
                    'period' => $this->currentPeriod->getName(),
                ]
            );
            return $this->json(['error' => 'Erreur lors de la mise à jour du paiement.'], 500);
        }
    }
}
