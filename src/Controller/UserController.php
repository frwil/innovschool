<?php

namespace App\Controller;

use App\Contract\GenderEnum;
use App\Contract\UserRoleEnum;
use App\Entity\ClassOccurence;
use App\Entity\SchoolClassPeriod;
use App\Entity\SchoolClassSubject;
use App\Entity\SchoolPeriod;
use App\Entity\StudentClass;
use App\Entity\StudyLevel;
use App\Entity\User;
use App\Form\UserType; // Ensure this class exists in the App.Form namespace
use App\Repository\UserRepository;
use App\Service\UserManager;
use App\Entity\UserBaseConfiguration;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Filesystem\Filesystem;
use App\Service\ImageOptimizer;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Entity\School;
use App\Entity\SchoolClassPaymentModal;
use App\Entity\SchoolClassAdmissionPayment;
use App\Service\StringHelper;
use App\Service\OperationLogger;
use App\Entity\AdmissionReductions;
use Doctrine\Persistence\ManagerRegistry;

#[Route('/users')]
final class UserController extends AbstractController
{
    private ImageOptimizer $imageOptimizer;
    private SchoolPeriod $currentPeriod;
    private School $currentSchool;
    private SessionInterface $session;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $userPasswordHasher;
    private $sectionRepository;

    public function __construct(UserPasswordHasherInterface $userPasswordHasher, \App\Repository\StudyLevelRepository $sectionRepository)
    {
        $this->userPasswordHasher = $userPasswordHasher;
        $this->imageOptimizer = new ImageOptimizer();
        $this->sectionRepository = $sectionRepository;
    }

    #[Route('/student', name: 'app_student_index', methods: ['GET'])]
    public function studentIndex(
        EntityManagerInterface $entityManager,
        Request $request,
        SessionInterface $session
    ): Response {
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));

        /** @var \App\Entity\User */
        $currentUser = $this->getUser();


        $period = $this->currentPeriod;
        $classId = $request->query->get('classId');
        $classes = $entityManager->getRepository(SchoolClassPeriod::class)->findBy(['school' => $this->currentSchool, 'period' => $period]);
        $students = $entityManager->getRepository(StudentClass::class)
            ->findBy(['schoolClassPeriod' => $classes]);
        $genders = GenderEnum::cases();
        $selClass = null;
        if ($classId) {
            $selClass = $entityManager->getRepository(SchoolClassPeriod::class)->find($classId);
        }

        return $this->render('user/index.student.html.twig', [
            'classes' => $classes,
            'students' => $students,
            'genders' => $genders,
            'selClass' => $selClass ? $selClass->getClassOccurence()->getName() ?? null : $selClass,
        ]);
    }

    #[Route('/admins/{role}/role', name: 'app_user_index_admins', methods: ['GET', 'POST'])]
    public function indexAdmins(
        string $role,
        UserRepository $userRepository
    ): Response {
        /** @var \App\Entity\User */
        $currentUser = $this->getUser();
        $savedRole = UserRoleEnum::getRole($role);
        $users = $userRepository->findByRole($savedRole->value);

        return $this->render('user/admins.index.html.twig', [
            'users' => $users,
            'role' => $savedRole->value,
        ]);
    }

    #[Route('/student/new', name: 'app_student_new', methods: ['POST'])]
    public function new(SessionInterface $session, Request $request, EntityManagerInterface $entityManager, OperationLogger $operationLogger, ManagerRegistry $doctrine): JsonResponse
    {
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $name = $request->request->get('lastName');
        $schoolClassId = $request->request->get('schoolClassPeriod');
        $parentPhone = $request->request->get('parentPhone');
        $parentName = $request->request->get('parentName');
        $parentType = $request->request->get('parentType');
        $tutorId = $request->request->get('tutor_id');
        $isRepeating = $request->request->get('isRepeating');
        $gender = $request->request->get('gender');
        $tutorGender = $request->request->get('parentGender');
        // Récupération du fichier photo
        /** @var UploadedFile|null $photoFile */
        $photoFile = $request->files->get('photo');

        $regNumber = $this->currentSchool->getAcronym() . 'E' . rand(1000, 9999);
        $checkRegNumber = $entityManager->getRepository(User::class)->findOneBy(['registrationNumber' => $regNumber]);
        // Si le numéro d'enregistrement existe déjà, on en génère un nouveau
        while ($checkRegNumber) {
            $regNumber = $this->currentSchool->getAcronym() . 'E' . rand(1000, 9999);
            $checkRegNumber = $entityManager->getRepository(User::class)->findOneBy(['registrationNumber' => $regNumber]);
        }
        if (!$tutorId) {
            // Si aucun tuteur n'est sélectionné, on crée un nouvel utilisateur pour le parent
            $tutor = new User();
            $tutor->setPhone($parentPhone);
            $tutor->setFullName($parentName ?: 'Parent inconnu');
            $tutor->setRoles(['ROLE_TUTOR']);
            $tutor->setSchool($this->currentSchool);
            $tutor->setResetPassword(true); // Oblige le parent à changer son mot de passe à la première connexion
            $tutor->setInfos(json_encode([
                'type' => $parentType,
            ]));
            $tutorRegNumber = $this->currentSchool->getAcronym() . 'P' . rand(1000, 9999);
            $checkTutorRegNumber = $entityManager->getRepository(User::class)->findOneBy(['registrationNumber' => $tutorRegNumber]);
            // Si le numéro d'enregistrement du tuteur existe déjà, on en génère un nouveau
            while ($checkTutorRegNumber) {
                $tutorRegNumber = $this->currentSchool->getAcronym() . 'P' . rand(1000, 9999);
                $checkTutorRegNumber = $entityManager->getRepository(User::class)->findOneBy(['registrationNumber' => $tutorRegNumber]);
            }
            $tutor->setRegistrationNumber($tutorRegNumber);
            $tutor->setNationalRegistrationNumber($request->request->get('parentNationalRegistrationNumber') ?: null);
            $tutorEmail = str_replace(' ', '.', strtolower($parentName)) . rand(1000, 9999) . '@parent.local';
            $checkTutorEmail = $entityManager->getRepository(User::class)->findOneBy(['username' => $tutorEmail]);
            while ($checkTutorEmail) {
                $tutorEmail = str_replace(' ', '.', strtolower($parentName)) . rand(1000, 9999) . '@parent.local';
                $checkTutorEmail = $entityManager->getRepository(User::class)->findOneBy(['username' => $tutorEmail]);
            }
            // Use a default password directly if Utils::getDefaultPassword() is not available
            $tutor->setPassword($this->userPasswordHasher->hashPassword($tutor, '000000'));
            $tutor->setUsername($tutorEmail);
            $tutor->setGender(\App\Contract\GenderEnum::from($tutorGender));
            $entityManager->persist($tutor);
        }

        // Validation simple
        if (!$name || !$schoolClassId) {
            return $this->json(['status' => 'error', 'message' => 'Champs obligatoires manquants.'], 400);
        }

        // Génération automatique de l'email
        $email = str_replace(' ', '.', strtolower($name)) . rand(1000, 9999) . '@eleve.local';
        $checkEmail = $entityManager->getRepository(User::class)->findOneBy(['username' => $email]);
        while ($checkEmail) {
            str_replace(' ', '.', strtolower($name)) . rand(1000, 9999) . '@eleve.local';
            $checkEmail = $entityManager->getRepository(User::class)->findOneBy(['username' => $email]);
        }

        // Création de l'élève
        $user = new User();
        $user->setFullName($name);
        $user->setEmail($email);
        $user->setRoles(['ROLE_STUDENT']);
        $user->setPhone($request->request->get('phone'));
        $user->setAddress($request->request->get('address'));
        $user->setSchool($this->currentSchool);
        $user->setUsername($email);
        $user->setEmail($email);
        $user->setDateOfBirth(new \DateTime($request->request->get('dateOfBirth')));
        $user->setPlaceOfBirth($request->request->get('placeOfBirth') ?: null);
        $user->setResetPassword(true); // Oblige l'utilisateur à changer son mot de passe à la première connexion
        $user->setRegistrationNumber($regNumber);
        $user->setPassword($this->userPasswordHasher->hashPassword($user, '111111')); // Mot de passe vide, à changer par l'utilisateur
        $user->setNationalRegistrationNumber($request->request->get('nationalRegistrationNumber') ?: null);
        $user->setInfos(json_encode([
            'isRepeating' => (bool)$isRepeating,
            'tutorId' => $tutorId,
            'parentType' => $parentType,
        ]));
        $user->setGender(\App\Contract\GenderEnum::from($gender));
        // Si un tuteur est sélectionné, on le lie à l'élève
        if ($tutorId) {
            $tutor = $entityManager->getRepository(User::class)->find($tutorId);
            if ($tutor) {
                $user->setTutor($tutor);
            } else {
                return $this->json(['status' => 'error', 'message' => 'Tuteur non trouvé.'], 404);
            }
        } else {
            // Si pas de tuteur, on lie l'élève au parent créé
            $user->setTutor($tutor);
        }
        if ($photoFile) {
            $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/';
            $filesystem = new Filesystem();

            // Suppression de l'ancienne photo si elle existe
            if ($user->getPhoto()) {
                $oldPhotoPath = $uploadsDir . $user->getPhoto();
                if ($filesystem->exists($oldPhotoPath)) {
                    $filesystem->remove($oldPhotoPath);
                }
            }

            // Génération d'un nom de fichier unique
            $newFilename = uniqid('student_', true) . '.' . $photoFile->guessExtension();

            // Déplacement du fichier uploadé
            $photoFile->move($uploadsDir, $newFilename);

            $this->imageOptimizer->optimize($uploadsDir . $newFilename);

            // Enregistrement du nom de fichier dans l'entité
            $user->setPhoto($newFilename);
        }

        $period = $this->currentPeriod;

        // Affectation à la classe
        $schoolClassPeriod = $entityManager->getRepository(\App\Entity\SchoolClassPeriod::class)->find($schoolClassId);
        if ($schoolClassPeriod) {
            $studentClass = new StudentClass();
            $studentClass->setStudent($user);
            $studentClass->setSchoolClassPeriod($schoolClassPeriod);
            $entityManager->persist($studentClass);
        }

        // Stocke la valeur sur l'entité élève
        $user->setRepeated((bool)$isRepeating);

        $entityManager->persist($user);
        try {
            $entityManager->flush();
            // Log the operation
            $operationLogger->log(
                'Enregistrement de l\'élève ' . $user->getFullName(),
                'INFO',
                'User',
                $user->getId(),
                null,
                [
                    'school' => $this->currentSchool->getId(),
                    'period' => $this->currentPeriod->getId()
                ]
            );
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // log the error
            $operationLogger->log(
                'Erreur lors de la création de l\'utilisateur ' . $user->getFullName(),
                'ERROR',
                'User',
                $user->getId(),
                $e->getMessage(),
                [
                    'school' => $this->currentSchool->getId(),
                    'period' => $this->currentPeriod->getId()
                ]
            );
            return $this->json(['status' => 'error', 'message' => 'Erreur lors de la création de l\'élève.'], 500);
        }

        $this->addFlash('success', 'Élève enregistré avec succès.');
        return $this->json(['status' => 'success', 'message' => 'Élève enregistré avec succès.']);
    }

    #[Route('/user/{id}/show', name: 'app_user_show_by_id', methods: ['GET'])]
    public function showById(SessionInterface $session, User $user, EntityManagerInterface $entityManager): Response
    {
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $period = $this->currentPeriod;
        $userClass = [];
        $classes = $entityManager->getRepository(SchoolClassPeriod::class)->findBy(['period' => $period, 'school' => $this->currentSchool]);
        if (in_array('ROLE_TEACHER', $user->getRoles())) {
            $teacherClass = $entityManager->getRepository(SchoolClassSubject::class)->findOneBy(['teacher' => $user, 'schoolClassPeriod' => $classes]);
            if ($teacherClass) {
                foreach ($teacherClass as $teacherC) {
                    if ($teacherC->getPeriod() === $period) {
                        $userClass[] = $teacherC;
                    }
                }
            }
        } elseif (in_array('ROLE_STUDENT', $user->getRoles())) {
            $userClass = $entityManager->getRepository(StudentClass::class)->findOneBy(['student' => $user, 'schoolClassPeriod' => $classes]);
        }
        $currentUserClass = $userClass ? $userClass->getSchoolClassPeriod() : [];
        $parentInfos = $user->getTutor() ? json_decode($user->getTutor()->getInfos(), true) : null;
        return $this->render('user/show.html.twig', [
            'user' => $user,
            'currentClass' => $currentUserClass,
            'classes' => $classes,
            'parentInfos' => $parentInfos,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);



        return $this->render('user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager, OperationLogger $operationLogger, SessionInterface $session, \Doctrine\Persistence\ManagerRegistry $doctrine): Response
    {
        $user_role = in_array('ROLE_STUDENT', $user->getRoles()) ? 'student' : (in_array('ROLE_TEACHER', $user->getRoles()) ? 'teacher' : 'admin');
        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->getPayload()->getString('_token'))) {
            $session = $session;
            $this->entityManager = $entityManager;
            $this->currentSchool = $this->entityManager->getRepository(School::class)->find($session->get('school_id'));
            $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($session->get('period_id'));

            $entityManager->remove($user);
            try {
                $entityManager->flush();
                $this->addFlash('success', 'Utilisateur supprimé avec succès.');
                // Log the operation
                $operationLogger->log(
                    'Suppression de l\'utilisateur ' . $user->getFullName(),
                    'INFO',
                    'User',
                    $user->getId(),
                    null,
                    ['school' => $this->currentSchool->getId(), 'period' => $this->currentPeriod->getId()]
                );
                return $this->redirectToRoute($user_role == 'student' ? 'app_student_index' : 'app_teacher_index', [], Response::HTTP_SEE_OTHER);
            } catch (\Exception $e) {
                if (!$this->entityManager->isOpen()) {
                    $this->entityManager = $doctrine->resetManager();
                }
                // Log the error
                $operationLogger->log(
                    'Erreur lors de la suppression de l\'utilisateur ' . $user->getFullName(),
                    'ERROR',
                    'User',
                    $user->getId(),
                    $e->getMessage(),
                    ['school' => $this->currentSchool->getId(), 'period' => $this->currentPeriod->getId()]
                );
                // cas de l'erreur 1451
                if ($e->getCode() === 1451) {
                    $this->addFlash('danger', 'Cet ' . ($user_role == 'student' ? 'élève' : 'enseignant') . ' ne peut pas être supprimé car il est lié à d\'autres entités. Veuillez d\'abord dissocier cet utilisateur des entités associées : Paiements, Classes, Notes, etc.');
                } else {
                    $this->addFlash('danger', 'Erreur lors de la suppression de l\'utilisateur : ' . $e->getMessage());
                }
                return $this->redirectToRoute($user_role == 'student' ? 'app_student_index' : 'app_teacher_index', [], Response::HTTP_SEE_OTHER);
            }
        } else {
            $this->addFlash('danger', 'Token CSRF invalide. Veuillez réessayer.');
            return $this->redirectToRoute($user_role == 'student' ? 'app_student_index' : 'app_teacher_index', [], Response::HTTP_SEE_OTHER);
        }
    }

    #[Route('/user/manage', name: 'app_user_manage')]
    public function manage(EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        // Récupérer les utilisateurs depuis la base de données
        $users = $entityManager->getRepository(User::class)->findAll();

        if (in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles())) {
            // Récupérer les sections et les classes depuis la base de données
            $sections = $entityManager->getRepository(StudyLevel::class)->findAll();
            $classes = $entityManager->getRepository(SchoolClassPeriod::class)->findAll();
        } else {
            $user = $this->getUser();
            $config = $entityManager->getRepository(UserBaseConfiguration::class)->findBy(['user' => $user]);
            if (count($config) > 0) {
                $sections = $entityManager->getRepository(StudyLevel::class)->findBy(['id' => count($config[0]->getSectionList()) > 0 ? $config[0]->getSectionList() : array_map(fn($sl) => $sl->getId(), $entityManager->getRepository(StudyLevel::class)->findAll())]);
                $classes = $entityManager->getRepository(SchoolClassPeriod::class)->findBy(['classOccurence' => count($config[0]->getClassList()) > 0 ? $config[0]->getClassList() : array_map(fn($sc) => $sc->getId(), $entityManager->getRepository(SchoolClassPeriod::class)->findAll())]);
            } else {
                $sections = [];
                $classes = [];
            }
        }

        return $this->render('user/manage.html.twig', [
            'users' => $users,
            'sections' => $sections,
            'classes' => $classes,
        ]);
    }

    #[Route('/user/{id}/manage', name: 'app_user_manage_edit', methods: ['GET', 'POST'])]
    public function manageEdit(int $id, Request $request, EntityManagerInterface $entityManager, OperationLogger $operationLogger, SessionInterface $session, ManagerRegistry $doctrine): JsonResponse
    {
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $user = $entityManager->getRepository(User::class)->find($id);

        if (!$user) {
            return $this->json(['error' => 'Utilisateur introuvable.'], Response::HTTP_NOT_FOUND);
        }

        if ($request->isMethod('POST')) {
            // Récupérer les données du formulaire
            $data = $request->request->all();
            $user->setFullname(strtoupper($data['lastName']) ?? $user->getFullname());
            $user->setEmail(strtolower($data['email']) ?? $user->getEmail());
            $user->setPhone($data['phone'] ?? $user->getPhone());
            $user->setAddress($data['address'] ?? $user->getAddress());
            $user->setUserName(strtolower($data['email']) ?? $user->getUsername());
            $user->setGender(\App\Contract\GenderEnum::from($data['gender'] ?? $user->getGender()->value));
            $user->setDateOfBirth((isset($data['birthDate']) ? new \DateTime($data['birthDate']) ?? $user->getDateOfBirth() : null));
            $user->setPlaceOfBirth($data['birthPlace'] ?? $user->getPlaceOfBirth());
            $user->setRepeated($data['isRepeating'] ?? $user->isRepeated());
            $user->setNationalRegistrationNumber($data['nationalRegistrationNumber'] ? $data['nationalRegistrationNumber'] : null);
            if (isset($data['parentPhone'])) {
                $user->getTutor() ? $user->getTutor()->setPhone($data['parentPhone'] ?? $user->getTutor()->getPhone()) : ($data['parentPhone'] ? $user->setTutor((new User())
                        ->setPhone($data['parentPhone'])
                        ->setFullName(strtoupper($data['parentName']) ?? null)
                        ->setRoles(['ROLE_TUTOR'])
                        ->setSchool($this->currentSchool)
                        ->setResetPassword(true)
                        ->setInfos(json_encode(['type' => $data['parentType'] ?? '']))
                        ->setGender(\App\Contract\GenderEnum::from($data['parentGender'] ?? 'unknown'))
                        ->setNationalRegistrationNumber($data['parentNationalRegistrationNumber'] ?? null)
                ) : null);
                $user->getTutor()->setFullName(strtoupper($data['parentName']) ?? $user->getTutor()->getFullName());
                $user->getTutor()->setGender(\App\Contract\GenderEnum::from($data['parentGender'] ?? $user->getTutor()->getGender()->value));
                $user->getTutor()->setInfos(json_encode([
                    'type' => $data['parentType'] ?? null,
                ]));
            }
            $photoFile = $request->files->get('photo');
            if ($photoFile instanceof UploadedFile) {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/';
                $filesystem = new Filesystem();

                // Suppression de l'ancienne photo si elle existe
                if ($user->getPhoto()) {
                    $oldPhotoPath = $uploadsDir . $user->getPhoto();
                    if ($filesystem->exists($oldPhotoPath)) {
                        $filesystem->remove($oldPhotoPath);
                    }
                }

                // Génération d'un nom de fichier unique
                $newFilename = uniqid('user_', true) . '.' . $photoFile->guessExtension();

                // Déplacement du fichier uploadé
                $photoFile->move($uploadsDir, $newFilename);
                $this->imageOptimizer->optimize($uploadsDir . $newFilename);

                // Enregistrement du nom de fichier dans l'entité
                $user->setPhoto($newFilename);
                if ($data['password'] !== '' && $data['password'] !== null) {
                    $password = $data['password'];
                    $user->setPassword($this->userPasswordHasher->hashPassword($user, $password));
                }
            }
            if (isset($data["currentSchoolPeriod"]) && $data['currentSchoolPeriod'] !== null) {
                $schoolClassPeriod = $entityManager->getRepository(SchoolClassPeriod::class)->find($data['currentSchoolPeriod']);
                $currentSchoolClassPeriod = $schoolClassPeriod;
                if ($schoolClassPeriod) {
                    // Vérifier si l'utilisateur est déjà affecté à cette classe
                    $existingStudentClass = $entityManager->getRepository(StudentClass::class)->findOneBy(['student' => $user, 'schoolClassPeriod' => $schoolClassPeriod]);
                    if (!$existingStudentClass) {
                        // Si l'utilisateur n'est pas déjà affecté, on crée une nouvelle relation
                        $studentClass = new StudentClass();
                        $studentClass->setStudent($user);
                        $studentClass->setSchoolClassPeriod($schoolClassPeriod);
                        $entityManager->persist($studentClass);
                    } else {
                        $schoolClassPeriod = $entityManager->getRepository(SchoolClassPeriod::class)->find($data['schoolClassPeriod']);
                        // Si l'utilisateur est déjà affecté, on met à jour la classe
                        $existingStudentClass->setSchoolClassPeriod($schoolClassPeriod);
                        $entityManager->persist($existingStudentClass);
                    }
                    // tous les paiements effectués par l'élève sont transférés vers la nouvelle classe
                    $payments = array_filter($user->getAdmissionPayments()->toArray(), fn($payment) => $payment->getSchoolClass() === $currentSchoolClassPeriod);
                    foreach ($payments as $payment) {
                        $payment->setSchoolClass($schoolClassPeriod);
                        $entityManager->persist($payment);
                    }
                }
            }

            // Enregistrer les modifications
            $entityManager->persist($user);
            try {
                $entityManager->flush();
                // Log the operation
                $operationLogger->log(
                    'Mise à jour de l\'utilisateur ' . $user->getFullName(),
                    'INFO',
                    'User',
                    $user->getId(),
                    null,
                    ['school' => $this->currentSchool->getId(), 'period' => $this->currentPeriod->getId()]
                );
            } catch (\Exception $e) {
                if (!$this->entityManager->isOpen()) {
                    $this->entityManager = $doctrine->resetManager();
                }
                // Log the error
                $operationLogger->log(
                    'Erreur lors de la mise à jour de l\'utilisateur ' . $user->getFullName(),
                    'ERROR',
                    'User',
                    $user->getId(),
                    $e->getMessage(),
                    ['school' => $this->currentSchool->getId(), 'period' => $this->currentPeriod->getId()]
                );
                return $this->json(['error' => 'Erreur lors de la mise à jour de l\'utilisateur : ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return $this->json(['success' => 'Utilisateur mis à jour avec succès.']);
        }

        // Récupérer la configuration de base de l'utilisateur
        $configuration = $entityManager->getRepository(UserBaseConfiguration::class)->findOneBy(['user' => $user]);

        $sections = [];
        $classes = [];

        if ($configuration) {
            $sections = array_map(fn($section) => $section, count($configuration->getSectionList()) > 0 ? $configuration->getSectionList() : array_map(fn($sl) => $sl->getId(), $this->sectionRepository->findAll()));
            $classes = array_map(fn($class) => $class, count($configuration->getClassList()) > 0 ? $configuration->getClassList() : array_map(fn($sc) => $sc->getId(), $entityManager->getRepository(SchoolClassPeriod::class)->findAll()));
        }

        // Retourner les données utilisateur sous forme de JSON
        return $this->json([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'fullname' => $user->getFullname(),
            'email' => $user->getEmail(),
            'phone' => $user->getPhone(),
            'address' => $user->getAddress(),
            'sections' => $sections,
            'classes' => $classes,
            'roles' => $user->getRoles()
        ]);
    }

    #[Route('/user/get-classes', name: 'app_get_classes', methods: ['POST'])]
    public function getClasses(SessionInterface $session, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $sectionIds = $request->request->all('sections');
        $this->session = $session;
        $this->currentPeriod = $entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $this->currentSchool = $entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        //dd($sectionIds);

        if (empty($sectionIds)) {
            return $this->json(['error' => 'Aucune section sélectionnée.'], Response::HTTP_BAD_REQUEST);
        }

        // Récupérer les classes associées aux sections sélectionnées
        $classes = $entityManager->getRepository(SchoolClassPeriod::class)
            ->createQueryBuilder('c')
            ->innerJoin('c.classOccurence', 's')
            ->innerJoin('s.classe', 'cl')
            ->where('cl.studyLevel IN (:sections)')
            ->andWhere('c.period = :period')
            ->andWhere('c.school = :school')
            ->setParameter('sections', $sectionIds)
            ->setParameter('school', $this->currentSchool)
            ->setParameter('period', $this->currentPeriod)
            ->getQuery()
            ->getResult();

        $classData = [];
        foreach ($classes as $class) {
            $classData[] = [
                'id' => $class->getId(),
                'name' => $class->getClassOccurence()->getName(),
            ];
        }

        return $this->json($classData);
    }

    #[Route('/user/save-configuration', name: 'app_user_save_configuration', methods: ['POST'])]
    public function saveUserConfiguration(Request $request, EntityManagerInterface $entityManager, OperationLogger $operationLogger, ManagerRegistry $doctrine): JsonResponse
    {
        // Décoder le contenu JSON de la requête
        $data = json_decode($request->getContent(), true);

        // Récupérer les données nécessaires
        $userId = $data['id'] ?? null;
        $sectionIds = $data['sections'] ?? [];
        $classIds = $data['classes'] ?? [];

        // Vérifier que l'utilisateur existe
        $user = $entityManager->getRepository(User::class)->find($userId);
        if (!$user) {
            return $this->json(['error' => 'Utilisateur introuvable.'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que les sections ne sont pas vides
        if (empty($sectionIds)) {
            return $this->json(['error' => 'La section ne peut pas être vide.'], Response::HTTP_BAD_REQUEST);
        }

        // Récupérer les sections et classes depuis la base de données
        $sections = $entityManager->getRepository(StudyLevel::class)->findBy(['id' => $sectionIds]);
        $classes = $entityManager->getRepository(SchoolClassPeriod::class)->findBy(['id' => $classIds]);

        // Extraire uniquement les IDs des sections et classes
        $sectionsArray = array_map(fn($section) => $section->getId(), $sections);
        $classesArray = array_map(fn($class) => $class->getId(), $classes);

        // Vérifier si une configuration existe déjà pour cet utilisateur
        $configuration = $entityManager->getRepository(UserBaseConfiguration::class)->findOneBy(['user' => $user]);

        if (!$configuration) {
            // Créer une nouvelle configuration si elle n'existe pas
            $configuration = new UserBaseConfiguration();
            $configuration->setUser($user);
        }

        // Mettre à jour les sections et classes dans la configuration
        $configuration->setSectionList($sectionsArray); // Sections sous forme d'IDs
        $configuration->setClassList($classesArray);    // Classes sous forme d'IDs
        $configuration->setSchool($this->currentSchool);

        // Sauvegarder la configuration
        $entityManager->persist($configuration);
        try {
            $entityManager->flush();
            // Log the operation
            $operationLogger->log(
                'Enregistrement de la configuration utilisateur pour ' . $user->getFullName(),
                'INFO',
                'UserBaseConfiguration',
                $configuration->getId(),
                null,
                ['school' => $this->currentSchool->getId(), 'period' => $this->currentPeriod->getId()]
            );
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // Log the error
            $operationLogger->log(
                'Erreur lors de la sauvegarde de la configuration utilisateur pour ' . $user->getFullName(),
                'ERROR',
                'UserBaseConfiguration',
                $configuration->getId(),
                $e->getMessage(),
                ['school' => $this->currentSchool->getId(), 'period' => $this->currentPeriod->getId()]
            );
            return $this->json(['error' => 'Erreur lors de la sauvegarde de la configuration : ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['success' => 'Configuration enregistrée avec succès.']);
    }

    #[Route('/parent/search', name: 'app_parent_search')]
    public function searchParent(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $phone = $request->query->get('phone');
        $parents = $em->getRepository(User::class)->createQueryBuilder('u')
            ->where('u.phone LIKE :phone')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('phone', '%' . $phone . '%')
            ->setParameter('role', '%ROLE_TUTOR%')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($parents as $parent) {
            $type = null;
            $infos = $parent->getInfos();
            if ($infos) {
                // $infos peut être un string JSON ou déjà un array
                if (is_string($infos)) {
                    $infosArr = json_decode($infos, true);
                } else {
                    $infosArr = $infos;
                }
                if (isset($infosArr['type'])) {
                    $type = $infosArr['type'];
                }
            }
            $result[] = [
                'id' => $parent->getId(),
                'fullName' => $parent->getFullName(),
                'phone' => $parent->getPhone(),
                'type' => $type,
                'gender' => $parent->getGender()->value,
            ];
        }
        return new JsonResponse($result);
    }

    #[Route('/user/{id}/reset-password', name: 'app_user_reset_password', methods: ['POST'])]
    public function resetPassword(User $user, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher, OperationLogger $operationLogger, ManagerRegistry $doctrine): Response
    {
        // Nouveau mot de passe temporaire (exemple : 111111)
        $newPassword = '111111';
        $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
        $user->setResetPassword(true); // Oblige l'utilisateur à changer son mot de passe à la prochaine connexion

        try {
            $entityManager->flush();
            // Log the operation
            $operationLogger->log(
                'Réinitialisation du mot de passe pour ' . $user->getFullName(),
                'INFO',
                'User',
                $user->getId(),
                null,
                ['school' => $this->currentSchool->getId(), 'period' => $this->currentPeriod->getId()]
            );

            $this->addFlash('success', 'Le mot de passe a été réinitialisé. L\'utilisateur devra le changer à la prochaine connexion.');


            // Redirection vers la page de l'utilisateur
            return $this->redirectToRoute('app_user_show_by_id', ['id' => $user->getId()]);
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // log the operation
            $operationLogger->log(
                'Erreur lors de la réinitialisation du mot de passe pour ' . $user->getFullName(),
                'ERROR',
                'User',
                $user->getId(),
                $e->getMessage(),
                ['school' => $this->currentSchool->getId(), 'period' => $this->currentPeriod->getId()]
            );
            $this->addFlash('error', 'Erreur lors de la réinitialisation du mot de passe : ' . $e->getMessage());
            return $this->redirectToRoute('app_user_show_by_id', ['id' => $user->getId()]);
        }
    }

    #[Route('/user/{id}/force-password-reset', name: 'app_force_password_reset', methods: ['GET', 'POST'])]
    public function forcePasswordReset(Request $request, User $user, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher, OperationLogger $operationLogger, ManagerRegistry $doctrine): Response
    {
        if ($request->isMethod('POST')) {
            $newPassword = $request->request->get('newPassword');
            $confirmPassword = $request->request->get('confirmPassword');

            if (!$newPassword || $newPassword !== $confirmPassword) {
                $this->addFlash('danger', 'Les mots de passe ne correspondent pas.');
            } else {
                $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
                $user->setResetPassword(false);
                try {
                    $entityManager->flush();
                    // Log the operation
                    $operationLogger->log(
                        'Modification du mot de passe pour ' . $user->getFullName(),
                        'INFO',
                        'User',
                        $user->getId(),
                        null,
                        ['school' => $this->currentSchool->getId(), 'period' => $this->currentPeriod->getId()]
                    );
                    $this->addFlash('success', 'Votre mot de passe a été modifié avec succès. Vous pouvez maintenant vous connecter.');
                    return $this->redirectToRoute('app_login');
                } catch (\Exception $e) {
                    if (!$this->entityManager->isOpen()) {
                        $this->entityManager = $doctrine->resetManager();
                    }
                    // Log the error
                    $operationLogger->log(
                        'Erreur lors de la modification du mot de passe pour ' . $user->getFullName(),
                        'ERROR',
                        'User',
                        $user->getId(),
                        $e->getMessage(),
                        ['school' => $this->currentSchool->getId(), 'period' => $this->currentPeriod->getId()]
                    );
                    $this->addFlash('error', 'Erreur lors de la modification du mot de passe : ' . $e->getMessage());
                    return $this->redirectToRoute('app_force_password_reset', ['id' => $user->getId()]);
                }
            }
        }

        return $this->render('user/force_password_reset.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/teacher', name: 'app_teacher_index')]
    public function teacherIndex(SessionInterface $session, EntityManagerInterface $entityManager): Response
    {
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $currentUser = $this->getUser();
        $period = $this->currentPeriod;
        $classes = $entityManager->getRepository(SchoolClassPeriod::class)
            ->findBy(['school' => $this->currentSchool, 'period' => $period]);
        $teachers = $entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->andWhere('u.school = :school')
            ->setParameter('role', '%ROLE_TEACHER%')
            ->setParameter('school', $this->currentSchool)
            ->getQuery()
            ->getResult();


        return $this->render('user/index.teacher.html.twig', [
            'classes' => $classes,
            'teachers' => $teachers,
        ]);
    }

    #[Route('/teacher/new', name: 'app_teacher_new', methods: ['POST'])]
    public function teacherNew(SessionInterface $session, Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher, OperationLogger $operationLogger, ManagerRegistry $doctrine): JsonResponse
    {
        $lastName = $request->request->get('lastName');
        $phone = $request->request->get('phone');
        $gender = $request->request->get('gender');

        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));

        if (!$lastName) {
            return $this->json(['status' => 'error', 'message' => 'Champs obligatoires manquants.'], 400);
        }

        $email = str_replace(' ', '.', strtolower($lastName)) . rand(1000, 9999) . '@teacher.local';
        $checkEmail = $entityManager->getRepository(User::class)->findBy(['email' => $email]);
        while ($checkEmail) {
            $email = str_replace(' ', '.', strtolower($lastName)) . rand(1000, 9999) . '@teacher.local';
            $checkEmail = $entityManager->getRepository(User::class)->findBy(['email' => $email]);
        }

        $regNumber = $this->currentSchool->getAcronym() . rand(1000, 9999);
        $checkRegNumber = $entityManager->getRepository(User::class)->findBy(['registrationNumber' => $regNumber]);
        while ($checkRegNumber) {
            $regNumber = $this->currentSchool->getAcronym() . rand(1000, 9999);
            $checkRegNumber = $entityManager->getRepository(User::class)->findBy(['registrationNumber' => $regNumber]);
        }

        $user = new User();
        $user->setFullName(strtoupper($lastName));
        $user->setEmail(strtolower($email));
        $user->setUsername(strtolower($email));
        $user->setPhone($phone);
        $user->setRoles(['ROLE_TEACHER']);
        $user->setSchool($this->currentSchool);
        $user->setGender(\App\Contract\GenderEnum::from($gender));
        $user->setRegistrationNumber($regNumber);
        $user->setPassword($passwordHasher->hashPassword($user, '222222')); // Mot de passe temporaire

        $entityManager->persist($user);
        try {
            $entityManager->flush();
            // Log the operation
            $operationLogger->log(
                'Enregistrement de l\'enseignant ' . $user->getFullName(),
                'INFO',
                'User',
                $user->getId(),
                null,
                ['school' => $this->currentSchool->getId(), 'period' => $this->currentPeriod->getId()]
            );
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // Log the error
            $operationLogger->log(
                'Erreur lors de l\'enregistrement de l\'enseignant ' . $user->getFullName(),
                'ERROR',
                'User',
                $user->getId(),
                $e->getMessage(),
                ['school' => $this->currentSchool->getId(), 'period' => $this->currentPeriod->getId()]
            );
            $this->addFlash('error', 'Erreur lors de l\'enregistrement de l\'enseignant : ' . $e->getMessage());
            return $this->json(['status' => 'error', 'message' => 'Erreur lors de l\'enregistrement de l\'enseignant.'], 500);
        }

        $this->addFlash('success', 'Enseignant enregistré avec succès.');
        return $this->json(['status' => 'success', 'message' => 'Enseignant enregistré avec succès.']);
    }

    #[Route('/student/export', name: 'app_student_export', methods: ['POST'])]
    public function exportStudents(SessionInterface $session, EntityManagerInterface $entityManager, Request $request): StreamedResponse
    {
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $period = $this->currentPeriod;
        $classId = $request->request->get('exportClass');
        if ($classId !== 'all' && $classId !== null) {
            // Si une classe spécifique est sélectionnée, on la récupère
            /** @var SchoolClassPeriod|null */
            $class = $entityManager->getRepository(SchoolClassPeriod::class)->findBy(['classOccurence' => $classId]);
        } else {
            $class = $entityManager->getRepository(SchoolClassPeriod::class)->findBy(['period' => $period, 'school' => $this->currentSchool]);
        }
        $students = $entityManager->getRepository(StudentClass::class)
            ->findBy(['schoolClassPeriod' => $class]);

        $gender = $request->request->get('exportGender');
        if ($gender !== 'all' && $gender !== null) {
            // Filtrer les étudiants par genre si un genre spécifique est sélectionné
            $students = array_filter($students, function (StudentClass $studentClass) use ($gender) {
                return $studentClass->getStudent()->getGender()->value === $gender;
            });
        }

        $format = $request->request->get('exportFormat');

        if ($format === 'csv') {
            $response = new StreamedResponse(function () use ($students) {
                $handle = fopen('php://output', 'w+');
                // En-tête CSV
                fputcsv($handle, ['Nom', 'Email', 'Telephone', 'Classe', 'Sexe', 'Matricule', 'Adresse', 'Parent', 'Contact Parent']);

                foreach ($students as $studentClass) {
                    $student = $studentClass->getStudent();
                    $class = $studentClass->getSchoolClass();
                    fputcsv($handle, [
                        $student->getFullName(),
                        $student->getEmail(),
                        $student->getPhone(),
                        $class ? $class->getName() : '',
                        $student->getGender() ? ($student->getGender()->value === 'male' ? 'Homme' : ($student->getGender()->value === 'female' ? 'Femme' : 'Inconnu')) : '',
                        $student->getRegistrationNumber(),
                        $student->getAddress() ?: '',
                        $student->getTutor() ? $student->getTutor()->getFullName() : '-',
                        $student->getTutor() ? $student->getTutor()->getPhone() : '-',
                    ]);
                }
                fclose($handle);
            });

            $filename = 'eleves_' . str_replace(' ', '_', is_array($class) ? 'toutes_classes' : $class->getClassOccurence()->getName()) . '_' . date('Ymd_His') . '.csv';
            $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        } else if ($format === 'xlsx') {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            // En-tête
            $sheet->setCellValue('A1', 'Liste des élèves de la classe ' . (is_array($class) ? '(toutes_classes)' : $class->getClassOccurence()->getName()) . ' - Année scolaire ' . $period->getName() . ' - ' . $this->currentSchool->getName());
            $sheet->mergeCells('A1:I1');
            $sheet->getStyle('A1:I1')->getFont()->setBold(true);
            $sheet->fromArray(['Nom', 'Email', 'Telephone', 'Classe', 'Sexe', 'Matricule', 'Adresse', 'Parent', 'Contact Parent'], null, 'A2');
            $row = 3;
            foreach ($students as $studentClass) {
                $student = $studentClass->getStudent();
                $classObj = $studentClass->getSchoolClassPeriod();
                $sheet->setCellValue('A' . $row, $student->getFullName());
                $sheet->setCellValue('B' . $row, $student->getEmail());
                $sheet->setCellValue('C' . $row, $student->getPhone());
                $sheet->setCellValue('D' . $row, $classObj ? $classObj->getClassOccurence()->getName() : '');
                $sheet->setCellValue('E' . $row, $student->getGender() ? ($student->getGender()->value === 'male' ? 'Homme' : ($student->getGender()->value === 'female' ? 'Femme' : 'Inconnu')) : '');
                $sheet->setCellValue('F' . $row, $student->getRegistrationNumber());
                $sheet->setCellValue('G' . $row, $student->getAddress() ?: '');
                $sheet->setCellValue('H' . $row, $student->getTutor() ? $student->getTutor()->getFullName() : '-');
                $sheet->setCellValue('I' . $row, $student->getTutor() ? $student->getTutor()->getPhone() : '-');
                $row++;
            }

            $writer = new Xlsx($spreadsheet);
            $filename = 'eleves_' . str_replace(' ', '_', is_array($class) ? 'toutes_classes' : $class->getClassOccurence()->getName()) . '_' . date('Ymd_His') . '.xlsx';

            $response = new StreamedResponse(function () use ($writer) {
                $writer->save('php://output');
            });

            $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        } else {
            // Gérer le cas d'un format inconnu
            throw new \InvalidArgumentException('Format d\'export inconnu.');
        }

        return $response;
    }

    #[Route('/teacher/export', name: 'app_teacher_export', methods: ['POST'])]
    public function exportTeachers(Request $request, EntityManagerInterface $entityManager): Response
    {
        $format = $request->request->get('exportFormat', 'csv');
        $teachers = $entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_TEACHER%')
            ->getQuery()
            ->getResult();

        if ($format === 'csv') {
            $response = new StreamedResponse(function () use ($teachers) {
                $handle = fopen('php://output', 'w+');
                fputcsv($handle, ['Nom', 'Email', 'Téléphone', 'Sexe', 'Matricule', 'Classes']);
                foreach ($teachers as $teacher) {
                    fputcsv($handle, [
                        $teacher->getFullName(),
                        $teacher->getEmail(),
                        $teacher->getPhone(),
                        $teacher->getGender() ? $teacher->getGender()->value : '',
                        $teacher->getRegistrationNumber(),
                        implode(chr(10), array_merge(array_map(function ($classSubject) {
                            return $classSubject ? $classSubject->getName() . ' (Professeur Principal)' : '';
                        }, $teacher->getClassMasters()->toArray()), array_map(function ($classSubject) {
                            return $classSubject->getSchoolClass() ? $classSubject->getSchoolClass()->getName() : '';
                        }, $teacher->getTeacherSchoolClassSubjects()->toArray()))), // Utilisation de chr(10) pour le retour à la ligne dans CSV
                    ]);
                }
                fclose($handle);
            });
            $filename = 'enseignants_' . date('Ymd_His') . '.csv';
            $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
            return $response;
        } elseif ($format === 'xlsx') {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray(['Nom', 'Email', 'Téléphone', 'Sexe', 'Matricule', 'Classes'], null, 'A1');
            $row = 2;
            foreach ($teachers as $teacher) {
                $sheet->setCellValue('A' . $row, $teacher->getFullName());
                $sheet->setCellValue('B' . $row, $teacher->getEmail());
                $sheet->setCellValue('C' . $row, $teacher->getPhone());
                $sheet->setCellValue('D' . $row, $teacher->getGender() ? ($teacher->getGender()->value === 'male' ? 'Homme' : ($teacher->getGender()->value === 'female' ? 'Femme' : 'Inconnu')) : '');
                $sheet->setCellValue('E' . $row, $teacher->getRegistrationNumber());
                $classes = [];
                $classMasters = array_map(function ($classSubject) {
                    return $classSubject ? $classSubject->getClassOccurence()->getName() . ' (Professeur Principal)' : '';
                }, $teacher->getClassMasters()->toArray());
                $classSubjects = array_map(function ($classSubject) {
                    return $classSubject->getSchoolClassPeriod() ? $classSubject->getSchoolClassPeriod()->getClassOccurence()->getName() : '';
                }, $teacher->getTeacherSchoolClassSubjects()->toArray());
                $classes = array_merge($classMasters, $classSubjects);
                $sheet->setCellValue('F' . $row, implode(chr(10), $classes));
                $sheet->getStyle('F' . $row)->getAlignment()->setWrapText(true); // Pour le retour à la ligne dans Excel
                $row++;
            }
            $writer = new Xlsx($spreadsheet);
            $filename = 'enseignants_' . date('Ymd_His') . '.xlsx';
            $response = new StreamedResponse(function () use ($writer) {
                $writer->save('php://output');
            });
            $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
            return $response;
        } else {
            throw new \InvalidArgumentException('Format d\'export inconnu.');
        }
    }

    #[Route('/student/import/excel', name: 'app_student_import_excel', methods: ['POST'])]
    public function importStudentsExcel(SessionInterface $session, Request $request, EntityManagerInterface $entityManager, OperationLogger $operationLogger, ManagerRegistry $doctrine): Response
    {
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        /** @var UploadedFile $file */
        $file = $request->files->get('importFile');
        if (!$file) {
            $this->addFlash('danger', 'Aucun fichier envoyé.');
            return $this->redirectToRoute('app_student_index');
        }
        ini_set('max_execution_time', 300); // Augmente le temps d'exécution pour les gros fichiers
        ini_set('memory_limit', '512M'); // Augmente la mémoire disponible pour le script

        try {
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // Supposons que la première ligne est l'en-tête
            foreach (array_slice($rows, 2) as $row) {
                // Adapte l'index selon la structure de ton fichier Excel
                $fullName = $row[1] ?? null;
                $email = null;
                $phone = $row[8] ?? null;
                $className = $row[3] ?? null;
                $gender = strtolower($row[6]) ?? null;
                $regNumber = $row[0] ?? null;
                $fullNameArray = explode(' ', $fullName);
                $fullName = "";
                $i = 0;
                foreach ($fullNameArray as $fname) {
                    if ($i > 0) $fullName .= ' ';
                    $fullName .= trim(str_replace(' ', '', $fname));
                    $i++;
                }
                $fullName = trim($fullName);
                if ($regNumber) {
                    $existingUser = $entityManager->getRepository(User::class)->findOneBy(['registrationNumber' => $regNumber]);
                    if ($existingUser) {
                        continue; // Ignore this row if the registration number already exists
                    }
                }
                $tutorName = $row[4] ?? null;
                $dateOfBirth = $row[5] ?? null; // Date de naissance, si disponible
                $placeOfBirth = $row[9] ?? null; // Lieu de naissance, si disponible

                if (!$fullName || !$className) {
                    continue;
                }

                $class = $entityManager->getRepository(ClassOccurence::class)->findOneBy(['name' => $className]);
                if (!$class) {
                    continue;
                }

                $period = $this->currentPeriod;

                $schoolClassPeriod = $entityManager->getRepository(SchoolClassPeriod::class)->findOneBy(['classOccurence' => $class, 'period' => $period, 'school' => $this->currentSchool]);
                if (!$schoolClassPeriod) {
                    continue; // Ignore this row if the class does not exist in the current period
                }

                $user = new User();
                $user->setFullName($fullName);
                $user->setEmail($email ?: strtolower(str_replace(' ', '.', $fullName)) . rand(1000, 9999) . '@eleve.local');
                $user->setUsername($user->getEmail());
                $user->setPhone($phone);
                $user->setRoles(['ROLE_STUDENT']);
                $user->setSchool($this->currentSchool);
                $user->setPassword($this->userPasswordHasher->hashPassword($user, '111111'));
                $user->setResetPassword(true); // Force le changement de mot de passe à la première connexion
                $user->setGender(\App\Contract\GenderEnum::from($gender ?? 'unknown'));
                $user->setRegistrationNumber($regNumber);
                $user->setDateOfBirth(new \DateTime($dateOfBirth)); // Date de naissance, si disponible
                $user->setPlaceOfBirth($placeOfBirth); // Lieu de naissance, si disponible
                $tutorEmailTemplate = strtolower(str_replace(' ', '.', $tutorName ?: $fullName)) . rand(1000, 9999) . '@parent.local';
                $tutorCheck = $entityManager->getRepository(User::class)->findOneBy(['email' => $tutorEmailTemplate]);
                while ($tutorCheck) {
                    $tutorEmailTemplate = strtolower(str_replace(' ', '.', $tutorName ?: $fullName)) . rand(1000, 9999) . '@parent.local';
                    $tutorCheck = $entityManager->getRepository(User::class)->findOneBy(['email' => $tutorEmailTemplate]);
                }
                $user->setTutor((new User())
                        ->setFullName($tutorName ?: strtoupper($fullName) . ' Parent')
                        ->setPassword($this->userPasswordHasher->hashPassword(new User(), '000000')) // Mot de passe temporaire pour le parent
                        ->setEmail($tutorEmailTemplate)
                        ->setUsername($tutorEmailTemplate)
                        ->setRoles(['ROLE_TUTOR'])
                        ->setSchool($this->currentSchool)
                        ->setResetPassword(true)
                        ->setInfos(json_encode(['type' => 'parent']))
                        ->setGender(\App\Contract\GenderEnum::from('male'))
                );
                $entityManager->persist($user);

                $period = $this->currentPeriod;
                $studentClass = new StudentClass();
                $studentClass->setStudent($user);
                $studentClass->setSchoolClassPeriod($schoolClassPeriod);
                $entityManager->persist($studentClass);
            }

            $entityManager->flush();
            // Log the operation
            $operationLogger->log(
                'Importation des élèves depuis Excel',
                'INFO',
                'User',
                null,
                null,
                ['school' => $this->currentSchool->getId(), 'period' => $this->currentPeriod->getId()]
            );
            $this->addFlash('success', 'Importation Excel réussie.');
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // Log the error
            $operationLogger->log(
                'Erreur lors de l\'importation des élèves depuis Excel',
                'ERROR',
                'User',
                null,
                $e->getMessage(),
                ['school' => $this->currentSchool->getId(), 'period' => $this->currentPeriod->getId()]
            );
            $this->addFlash('danger', 'Erreur lors de l\'importation : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_student_index');
    }

    #[Route('/student/import/json', name: 'app_student_import_json', methods: ['POST'])]
    public function importStudentsJson(SessionInterface $session, Request $request, EntityManagerInterface $entityManager, OperationLogger $operationLogger, ManagerRegistry $doctrine): Response
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));

        /** @var UploadedFile $file */
        $file = $request->files->get('importFile');
        if (!$file) {
            $this->addFlash('danger', 'Aucun fichier envoyé.');
            return $this->redirectToRoute('app_student_index');
        }

        try {
            $jsonContent = file_get_contents($file->getPathname());
            $students = json_decode($jsonContent, true);

            if (!is_array($students)) {
                throw new \Exception('Le fichier JSON n\'est pas valide.');
            }

            foreach ($students as $studentData) {
                $fullName = $studentData['fullName'] ?? null;
                $email = $studentData['email'] ?? null;
                $phone = $studentData['phone'] ?? null;
                $className = $studentData['className'] ?? null;

                if (!$fullName || !$className) {
                    continue;
                }

                $class = $entityManager->getRepository(ClassOccurence::class)->findOneBy(['name' => $className]);
                if (!$class) {
                    continue;
                }

                $user = new \App\Entity\User();
                $user->setFullName($fullName);
                $user->setEmail($email ?: strtolower(str_replace(' ', '.', $fullName)) . rand(1000, 9999) . '@eleve.local');
                $user->setUsername($user->getEmail());
                $user->setPhone($phone);
                $user->setRoles(['ROLE_STUDENT']);
                $user->setSchool($class->getSchool());
                $user->setPassword($this->userPasswordHasher->hashPassword($user, '111111'));
                $entityManager->persist($user);

                $period = $this->currentPeriod;
                $studentClass = new \App\Entity\StudentClass();
                $studentClass->setStudent($user);
                $studentClass->setSchoolClassPeriod($entityManager->getRepository(SchoolClassPeriod::class)
                    ->findOneBy(['classOccurence' => $class, 'period' => $period, 'school' => $this->currentSchool]));
                $entityManager->persist($studentClass);
            }

            $entityManager->flush();
            // Log the operation
            $operationLogger->log(
                'Importation des élèves depuis JSON',
                'INFO',
                'User',
                null,
                null,
                ['school' => $this->currentSchool->getId(), 'period' => $this->currentPeriod->getId()]
            );
            $this->addFlash('success', 'Importation JSON réussie.');
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // Log the error
            $operationLogger->log(
                'Erreur lors de l\'importation des élèves depuis JSON',
                'ERROR',
                'User',
                null,
                $e->getMessage(),
                ['school' => $this->currentSchool->getId(), 'period' => $this->currentPeriod->getId()]
            );
            $this->addFlash('danger', 'Erreur lors de l\'importation : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_student_index');
    }

    #[Route('/student/check', name: 'app_student_check', methods: ['GET'])]
    public function checkStudent(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $fullName = $request->query->get('fullName');
        if (!$fullName) {
            return new JsonResponse(['exists' => false]);
        }
        $student = $em->getRepository(User::class)->createQueryBuilder('u')
            ->where('LOWER(u.fullName) = :fullName')
            ->setParameter('fullName', mb_strtolower($fullName))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$student) {
            return new JsonResponse(['exists' => false]);
        }

        // Préparation des infos tuteur si existant
        $tutorPhone = null;
        $tutorName = null;
        $tutorType = null;
        $tutorGender = null;
        if ($student->getTutor()) {
            $tutor = $student->getTutor();
            $tutorPhone = $tutor->getPhone();
            $tutorName = $tutor->getFullName();
            $tutorGender = $tutor->getGender()->value;
            $infos = $tutor->getInfos();
            if ($infos) {
                if (is_string($infos)) {
                    $infosArr = json_decode($infos, true);
                } else {
                    $infosArr = $infos;
                }
                if (isset($infosArr['type'])) {
                    $tutorType = $infosArr['type'];
                }
            }
        }

        return new JsonResponse([
            'exists' => true,
            'dateOfBirth' => $student->getDateOfBirth() ? $student->getDateOfBirth()->format('Y-m-d') : null,
            'placeOfBirth' => $student->getPlaceOfBirth(),
            'gender' => $student->getGender(),
            'tutorPhone' => $tutorPhone,
            'tutorName' => $tutorName,
            'tutorType' => $tutorType,
            'tutorGender' => $tutorGender,
        ]);
    }

    #[Route('/study-levels/list', name: 'app_study_levels_list', methods: ['GET'])]
    public function studyLevelsList(EntityManagerInterface $em): JsonResponse
    {
        $levels = $em->getRepository(\App\Entity\StudyLevel::class)->findAll();
        $result = [];
        foreach ($levels as $level) {
            $result[] = [
                'id' => $level->getId(),
                'name' => $level->getName(),
            ];
        }
        return new JsonResponse($result);
    }

    #[Route('/classes/by-level', name: 'app_classes_by_level', methods: ['GET'])]
    public function classesByLevel(SessionInterface $session, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $levelId = $request->query->get('level');
        if (!$levelId) {
            return new JsonResponse([]);
        }
        $this->session = $session;
        $this->currentPeriod = $em->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $this->currentSchool = $em->getRepository(School::class)->find($this->session->get('school_id'));
        $classes = $em->getRepository(\App\Entity\SchoolClassPeriod::class)
            ->createQueryBuilder('c')
            ->join('c.classOccurence', 'co')
            ->join('co.classe', 'cl')
            ->join('cl.studyLevel', 'sl')
            ->where('sl.id = :level')
            ->andWhere('c.period = :period')
            ->andWhere('c.school=:school')
            ->setParameter('level', $levelId)
            ->setParameter('period', $this->currentPeriod)
            ->setParameter('school', $this->currentSchool)
            ->getQuery()
            ->getResult();
        $result = [];
        foreach ($classes as $class) {
            $result[] = [
                'id' => $class->getId(),
                'name' => $class->getClassOccurence()->getName(),
            ];
        }
        return new JsonResponse($result);
    }

    #[Route('/class/payment-modals', name: 'app_class_payment_modals', methods: ['GET'])]
    public function classPaymentModals(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $classId = $request->query->get('classId');
        if (!$classId) {
            return new JsonResponse([]);
        }
        $classPeriod = $em->getRepository(\App\Entity\SchoolClassPeriod::class)->find($classId);
        if (!$classPeriod) {
            return new JsonResponse([]);
        }
        $modals = $classPeriod->getPaymentModals()->toArray();
        $result = [];
        //tri des modals par priorité
        usort($modals, function ($a, $b) {
            return $a->getModalPriority() <=> $b->getModalPriority();
        });
        foreach ($modals as $modal) {
            $result[] = [
                'label' => $modal->getLabel(),
                'amount' => $modal->getAmount(),
                'modalType' => $modal->getModalType(),
                'dueDate' => $modal->getDueDate() ? $modal->getDueDate()->format('Y-m-d') : null,
            ];
        }
        return new JsonResponse($result);
    }

    #[Route('/student/register', name: 'app_student_register', methods: ['POST'])]
    public function registerStudent(Request $request, EntityManagerInterface $em, SessionInterface $session, StringHelper $stringHelper, OperationLogger $operationLogger, ManagerRegistry $doctrine): JsonResponse
    {
        $data = $request->request;
        $this->session = $session;
        $this->currentSchool = $em->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $em->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));

        // Cas élève existant (sélectionné dans le DataTable)
        if ($request->request->get('studentId')) {
            $student = $em->getRepository(User::class)->find($request->request->get('studentId'));
            if (!$student) {
                return $this->json(['error' => 'Élève non trouvé.'], 404);
            }
            // Lier à la classe sélectionnée (StudentClass)
            $classPeriod = $em->getRepository(SchoolClassPeriod::class)->find($data->get('classSelect'));
            if ($classPeriod) {
                $studentClass = new StudentClass();
                $studentClass->setStudent($student);
                $studentClass->setSchoolClassPeriod($classPeriod);
                // Log the operation
                $operationLogger->log(
                    'Enregistrement de l\'élève ' . $student->getFullName(),
                    'Success',
                    'User',
                    $studentClass->getId(),
                    null,
                    [
                        'student' => $student->getId(),
                        'school' => $this->currentSchool->getId()
                    ]
                );
                $em->persist($studentClass);
            }
            // Paiement initial (SchoolClassAdmissionPayment)
            $initialPayment = floatval($data->get('initialPayment'));
            $paymentDate = $data->get('dateInscription') ? new \DateTime($data->get('dateInscription')) : new \DateTime();
            if ($initialPayment > 0 && $classPeriod) {
                $modalities = $em->getRepository(SchoolClassPaymentModal::class)->findBy(
                    ['schoolClassPeriod' => $classPeriod, 'school' => $this->currentSchool, 'schoolPeriod' => $this->currentPeriod, 'modalType' => 'base'],
                    ['modalPriority' => 'ASC']
                );
                if (!$modalities) {
                    return $this->json(['error' => 'Aucune modalité trouvée pour cette classe.'], Response::HTTP_NOT_FOUND);
                }
                $totalToPay = array_reduce($modalities, function ($carry, $modal) {
                    return $carry + $modal->getAmount();
                }, 0);
                $school = $this->currentSchool;
                $schoolPeriod = $this->currentPeriod;
                $totalRemainingToPay = array_reduce($modalities, function ($carry, $modal) use ($em, $student, $classPeriod, $school, $schoolPeriod) {
                    $totalPaidForModal = $em->getRepository(SchoolClassAdmissionPayment::class)->getTotalPaidForModal($modal, $student, $classPeriod, $school, $schoolPeriod);
                    $remainingToPay = $modal->getAmount() - $totalPaidForModal;
                    return $carry + max(0, $remainingToPay);
                }, 0);
                if ($initialPayment > $totalToPay) {
                    return $this->json([
                        'error' => 'Le montant émis dépasse le total à payer global pour les modalités de type base. Total à payer : ' . number_format($totalToPay, 2, ',', ' ') . ' €',
                    ], Response::HTTP_BAD_REQUEST);
                }
                if ($initialPayment > $totalRemainingToPay) {
                    return $this->json([
                        'error' => 'Le montant émis dépasse le reste à payer global pour les modalités de base. Reste à payer : ' . number_format($totalRemainingToPay, 2, ',', ' ') . ' €',
                    ], Response::HTTP_BAD_REQUEST);
                }
                $remainingAmount = $initialPayment;
                foreach ($modalities as $modal) {
                    // Récupère le montant total des réductions approuvées pour cette modalité et cet élève
                    $totalReduction = $em->getRepository(AdmissionReductions::class)
                        ->getTotalReductionForModal($modal, $student, $classPeriod, $school, $schoolPeriod, true);

                    // Montant à payer pour la modalité après réduction
                    $modalAmount = max(0, $modal->getAmount() - $totalReduction);

                    $totalPaidForModal = $em->getRepository(SchoolClassAdmissionPayment::class)
                        ->getTotalPaidForModal($modal, $student, $classPeriod, $school, $schoolPeriod);

                    $remainingToPay = $modalAmount - $totalPaidForModal;

                    if ($remainingToPay > 0 && $remainingAmount > 0) {
                        $paymentAmount = min($remainingToPay, $remainingAmount);
                        $payment = new SchoolClassAdmissionPayment();
                        $payment->setPaymentModal($modal);
                        $payment->setPaymentAmount($paymentAmount);
                        $payment->setPaymentDate($paymentDate);
                        $payment->setSchoolClass($classPeriod);
                        $payment->setSchool($school);
                        $payment->setSchoolPeriod($schoolPeriod);
                        $payment->setStudent($student);
                        $payment->setModalType('base');
                        $em->persist($payment);
                        $remainingAmount -= $paymentAmount;
                        // Log the operation
                        $operationLogger->log(
                            'Paiement pour modalité ' . $modal->getLabel() . ' de l\'élève ' . $student->getFullName(),
                            'Success',
                            'SchoolClassAdmissionPayment',
                            $payment->getId(),
                            null,
                            [
                                'student' => $student->getId(),
                                'schoolclassperiod' => $classPeriod->getId(),
                                'school' => $this->currentSchool->getId(),
                                'period' => $this->currentPeriod->getId(),
                                'amount' => $paymentAmount,
                            ]
                        );
                    }
                    if ($remainingAmount <= 0) {
                        break;
                    }
                }
            }
            try {
                $em->flush();
                // Log the operation
                $operationLogger->log(
                    'Enregistrement de l\'élève ' . $student->getFullName(),
                    'INFO',
                    'User',
                    $student->getId(),
                    null,
                    [
                        'student' => $student->getId(),
                        'schoolclassperiod' => $classPeriod->getId(),
                        'school' => $this->currentSchool->getId(),
                        'period' => $this->currentPeriod->getId()
                    ]
                );
            } catch (\Exception $e) {
                if (!$this->entityManager->isOpen()) {
                    $this->entityManager = $doctrine->resetManager();
                }
                // Log the error
                $operationLogger->log(
                    'Erreur lors de la sauvegarde des paiements',
                    'ERROR',
                    'SchoolClassAdmissionPayment',
                    null,
                    $e->getMessage(),
                    [
                        'student' => $student->getId(),
                        'schoolclassperiod' => $classPeriod->getId(),
                        'school' => $this->currentSchool->getId(),
                        'period' => $this->currentPeriod->getId()
                    ]
                );
                return $this->json([
                    'error' => "Erreur lors de la sauvegarde des paiements : " . $e->getMessage()
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
            // Récupérer le dernier paiement créé pour cet élève, cette classe, cette période
            $lastPayment = $em->getRepository(SchoolClassAdmissionPayment::class)->findBy([
                'student' => $student,
                'schoolClassPeriod' => $classPeriod,
                'schoolPeriod' => $this->currentPeriod
            ], ['id' => 'DESC']);
            $paymentIds = "[";
            $i = 0;
            foreach ($lastPayment as $payment) {
                if ($i > 0) {
                    $paymentIds .= ",";
                }
                $paymentIds .= $payment->getId();
                $i++;
            }
            $paymentIds .= "]";
            return $this->json([
                'success' => true,
                'message' => 'Inscription élève existant réussie.',
                'paymentId' => $paymentIds,
                'studentId' => $student->getId(),
                'classId' => $classPeriod ? $classPeriod->getId() : null
            ]);
        }

        // Création de l'élève (User)
        $student = new User();
        $student->setDateOfBirth($data->get('dateOfBirth') ? new \DateTime($data->get('dateOfBirth')) : null);
        $student->setPlaceOfBirth($data->get('placeOfBirth'));
        $student->setGender(GenderEnum::from($data->get('gender')));
        $student->setNationalRegistrationNumber($data->get('nationalRegistrationNumber') ?? null);
        // Génération du registrationNumber unique
        $acronym = $this->currentSchool->getAcronym();
        $registrationNumber = null;
        $maxTries = 10;
        do {
            $random = str_pad(strval(random_int(0, 9999)), 4, '0', STR_PAD_LEFT);
            $candidate = $acronym . $random;
            $exists = $em->getRepository(User::class)->findOneBy(['registrationNumber' => $candidate]);
            if (!$exists) {
                $registrationNumber = $candidate;
                break;
            }
            $maxTries--;
        } while ($maxTries > 0);
        $student->setRegistrationNumber($registrationNumber);
        $photo = $request->files->get('photo');
        if ($photo instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            try {
                $photoFile = $photo->getClientOriginalName();
                $photoPath = 'uploads/' . $photoFile;
                $photo->move('public/' . $photoPath);
                $student->setPhoto($photoFile);
                $imageOptimizer = new ImageOptimizer();
                $imageOptimizer->optimize('public/' . $photoPath);
            } catch (\Exception $e) {
                return $this->json([
                    'error' => "Erreur lors de l'upload de la photo : " . $e->getMessage()
                ], \Symfony\Component\HttpFoundation\Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        $student->setRoles(['ROLE_STUDENT']);
        $student->setSchool($this->currentSchool);
        $student->setEnabled(true);

        // Nettoyage du nom de l'élève avec StringHelper
        $fullName = $stringHelper->toUpperNoAccent($data->get('fullName'));
        $student->setFullName($fullName);

        // Génération automatique de l'email de l'élève (un seul point entre les éléments, email unique)
        $studentEmailBase = strtolower(preg_replace('/\s+/', '.', trim($data->get('fullName'))));
        do {
            $studentEmail = $studentEmailBase . random_int(1000, 9999) . '@eleve.local';
            $emailExists = $em->getRepository(User::class)->findOneBy(['email' => $studentEmail]);
        } while ($emailExists);
        $student->setEmail($studentEmail);
        $student->setUsername($studentEmail);
        $student->setPassword($this->userPasswordHasher->hashPassword($student, '111111')); // Mot de passe temporaire

        // Gestion du tuteur
        $tutorName = $data->get('tutorName') ? $stringHelper->toUpperNoAccent($data->get('tutorName')) : null;
        $tutorPhone = $data->get('tutorPhone');
        $tutorType = $data->get('tutorType');
        $tutorGender = $data->get('tutorGender');
        $tutor = null;
        if ($tutorPhone) {
            $tutor = $em->getRepository(User::class)->findOneBy(['phone' => $tutorPhone, 'fullName' => $tutorName]);
            if (!$tutor) {
                $tutor = new User();
                $tutor->setFullName($tutorName);
                $tutor->setPhone($tutorPhone);
                $tutor->setGender(GenderEnum::from($tutorGender));
                $tutor->setRoles(['ROLE_TUTOR']);
                $tutor->setSchool($this->currentSchool);
                $tutor->setEnabled(true);
                $tutor->setInfos(json_encode(['type' => $tutorType]));
                // Génération automatique de l'email du tuteur (un seul point entre les éléments, email unique)
                $tutorEmailBase = strtolower(preg_replace('/\s+/', '.', trim($data->get('tutorName'))));
                do {
                    $tutorEmail = $tutorEmailBase . random_int(1000, 9999) . '@parent.local';
                    $emailExists = $em->getRepository(User::class)->findOneBy(['email' => $tutorEmail]);
                } while ($emailExists);
                $tutor->setEmail($tutorEmail);
                $tutor->setUsername($tutorEmail);
                $tutor->setPassword($this->userPasswordHasher->hashPassword($tutor, '000000')); // Mot de passe temporaire
                $em->persist($tutor);
                // Log the operation
                $operationLogger->log(
                    'Enregistrement du parent de l\'élève ' . $student->getFullName(),
                    'Success',
                    'User',
                    $tutor->getId(),
                    null,
                    [
                        'student' => $student->getId(),
                        'school' => $this->currentSchool->getId(),
                        'period' => $this->currentPeriod->getId()
                    ]
                );
            }
            $student->setTutor($tutor);
        }
        $em->persist($student);
        try {
            $em->flush();
            // Log the operation
            $operationLogger->log(
                'Enregistrement de l\'élève ' . $student->getFullName(),
                'Success',
                'User',
                $student->getId(),
                null,
                [
                    'tutor' => $tutor->getId(),
                    'school' => $this->currentSchool->getId(),
                    'period' => $this->currentPeriod->getId()
                ]
            );
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // log the error
            $operationLogger->log(
                'Enregistrement de l\'élève ' . $student->getFullName(),
                'ERROR',
                'User',
                $student->getId(),
                $e->getMessage(),
                [
                    'tutor' => $tutor->getId(),
                    'school' => $this->currentSchool->getId()
                ]
            );
            return $this->json([
                'error' => "Erreur lors de l'enregistrement de l'élève : " . $e->getMessage()
            ], \Symfony\Component\HttpFoundation\Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Lier à la classe sélectionnée (StudentClass)
        $classPeriod = $em->getRepository(SchoolClassPeriod::class)->find($data->get('classSelect'));
        if ($classPeriod) {
            $studentClass = new StudentClass();
            $studentClass->setStudent($student);
            $studentClass->setSchoolClassPeriod($classPeriod);
            $em->persist($studentClass);
        }

        // Paiement initial (SchoolClassAdmissionPayment)
        $initialPayment = floatval($data->get('initialPayment'));
        if ($initialPayment > 0 && $classPeriod) {
            $modalities = $em->getRepository(SchoolClassPaymentModal::class)->findBy(
                ['schoolClassPeriod' => $classPeriod, 'school' => $this->currentSchool, 'schoolPeriod' => $this->currentPeriod, 'modalType' => 'base'],
                ['modalPriority' => 'ASC']
            );

            if (!$modalities) {
                return $this->json(['error' => 'Aucune modalité trouvée pour cette classe.'], Response::HTTP_NOT_FOUND);
            }

            // Calculer le total global à payer pour les modalités de type 'base'
            $totalToPay = array_reduce($modalities, function ($carry, $modal) {
                return $carry + $modal->getAmount();
            }, 0);

            $school = $this->currentSchool;
            $schoolPeriod = $this->currentPeriod;

            // Calculer le reste à payer global pour les modalités de type 'base'
            $totalRemainingToPay = array_reduce($modalities, function ($carry, $modal) use ($em, $student, $classPeriod, $school, $schoolPeriod) {
                $totalPaidForModal = $em->getRepository(SchoolClassAdmissionPayment::class)->getTotalPaidForModal($modal, $student, $classPeriod, $school, $schoolPeriod);
                $remainingToPay = $modal->getAmount() - $totalPaidForModal;
                return $carry + max(0, $remainingToPay); // Ajouter uniquement les montants restants positifs
            }, 0);

            // Vérifier que le montant émis n'est pas supérieur au total global
            if ($initialPayment > $totalToPay) {
                return $this->json([
                    'error' => 'Le montant émis dépasse le total à payer global pour les modalités de type base. Total à payer : ' . number_format($totalToPay, 2, ',', ' ') . ' €',
                ], Response::HTTP_BAD_REQUEST);
            }

            // Vérifier que le montant émis n'est pas supérieur au reste à payer global
            if ($initialPayment > $totalRemainingToPay) {
                return $this->json([
                    'error' => 'Le montant émis dépasse le reste à payer global pour les modalités de base. Reste à payer : ' . number_format($totalRemainingToPay, 2, ',', ' ') . ' €',
                ], Response::HTTP_BAD_REQUEST);
            }

            $remainingAmount = $initialPayment; // Montant restant à allouer
            $payments = []; // Stocker les paiements effectués
            foreach ($modalities as $modal) {
                // Récupère le montant total des réductions approuvées pour cette modalité et cet élève
                $totalReduction = $em->getRepository(AdmissionReductions::class)
                    ->getTotalReductionForModal($modal, $student, $classPeriod, $school, $schoolPeriod, true);

                // Montant à payer pour la modalité après réduction
                $modalAmount = max(0, $modal->getAmount() - $totalReduction);

                $totalPaidForModal = $em->getRepository(SchoolClassAdmissionPayment::class)
                    ->getTotalPaidForModal($modal, $student, $classPeriod, $school, $schoolPeriod);

                $remainingToPay = $modalAmount - $totalPaidForModal;

                if ($remainingToPay > 0 && $remainingAmount > 0) {
                    $paymentAmount = min($remainingToPay, $remainingAmount);
                    $payment = new SchoolClassAdmissionPayment();
                    $payment->setPaymentModal($modal);
                    $payment->setPaymentAmount($paymentAmount);
                    $payment->setPaymentDate(new \DateTime());
                    $payment->setSchoolClass($classPeriod);
                    $payment->setSchool($school);
                    $payment->setSchoolPeriod($schoolPeriod);
                    $payment->setStudent($student);
                    $payment->setModalType('base');

                    $em->persist($payment);

                    // Ajouter le paiement à la liste des paiements
                    $payments[] = [
                        'modalId' => $modal->getId(),
                        'modalLabel' => $modal->getLabel(),
                        'paidAmount' => $paymentAmount,
                    ];

                    // Décrémenter le montant restant
                    $remainingAmount -= $paymentAmount;
                    // Log the operation
                    $operationLogger->log(
                        'Paiement pour modalité',
                        'Success',
                        'SchoolClassAdmissionPayment',
                        $payment->getId(),
                        null,
                        [
                            'student' => $student->getId(),
                            'schoolclassperiod' => $classPeriod->getId()
                        ]
                    );
                }



                // Si le montant restant est 0, arrêter la boucle
                if ($remainingAmount <= 0) {
                    break;
                }
            }
        }

        try {
            $em->flush();
            // log the operation
            $operationLogger->log(
                'Enregistrement de l\'élève ' . $student->getFullName(),
                'SUCCESS',
                'User',
                $student->getId(),
                null,
                [
                    'tutor' => $tutor->getId(),
                    'school' => $this->currentSchool->getId(),
                    'period' => $this->currentPeriod->getId()
                ]
            );
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // log the error
            $operationLogger->log(
                'Erreur lors de l\'enregistrement de l\'élève ' . $student->getFullName(),
                'ERROR',
                'User',
                $student->getId(),
                $e->getMessage(),
                [
                    'tutor' => $tutor->getId(),
                    'school' => $this->currentSchool->getId(),
                    'period' => $this->currentPeriod->getId()
                ]
            );
            return $this->json([
                'error' => "Erreur lors de l'enregistrement de l'élève : " . $e->getMessage()
            ], \Symfony\Component\HttpFoundation\Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        // Récupérer le dernier paiement créé pour cet élève, cette classe, cette période
        $lastPayment = $em->getRepository(SchoolClassAdmissionPayment::class)->findBy([
            'student' => $student,
            'schoolClassPeriod' => $classPeriod,
            'schoolPeriod' => $this->currentPeriod
        ], ['id' => 'DESC']);
        $paymentIds = "[";
        $i = 0;
        foreach ($lastPayment as $payment) {
            if ($i > 0) $paymentIds .= ",";
            $paymentIds .= $payment->getId();
            $i++;
        }
        $paymentIds .= "]";
        return new JsonResponse([
            'success' => true,
            'message' => 'Élève inscrit avec succès.',
            'paymentId' => $paymentIds,
            'studentId' => $student->getId(),
            'classId' => $classPeriod ? $classPeriod->getId() : null
        ]);
    }

    #[Route('/student/similar-names', name: 'app_student_similar_names', methods: ['GET'])]
    public function getSimilarStudentNames(Request $request, EntityManagerInterface $em, \App\Service\StudentNameSimilarityService $similarityService): JsonResponse
    {
        $input = trim($request->query->get('name', ''));
        if (mb_strlen($input) < 5) {
            return $this->json([]);
        }
        $students = $em->getRepository(User::class)->findAll();
        $students = array_filter($students, function ($student) {
            return in_array('ROLE_STUDENT', $student->getRoles());
        });
        $names = array_unique(array_map(fn($s) => $s->getFullName(), $students));
        $matches = $similarityService->findSimilarNames($input, $names, 90);
        return $this->json($matches);
    }

    #[Route('/user/update', name: 'app_user_update', methods: ['POST'])]
    public function updateUser(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        OperationLogger $operationLogger,
        ManagerRegistry $doctrine
    ): JsonResponse {
        $userId = $request->request->get('id');
        $username = $request->request->get('username');
        $fullname = $request->request->get('fullname');
        $email = $request->request->get('email');
        $phone = $request->request->get('phone');
        $address = $request->request->get('address');
        $password = $request->request->get('password');

        if (!$userId) {
            return new JsonResponse(['error' => 'Utilisateur introuvable.'], 404);
        }
        $user = $entityManager->getRepository(User::class)->find($userId);
        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur introuvable.'], 404);
        }

        $user->setUsername($username);
        $user->setFullName($fullname);
        $user->setEmail($email);
        $user->setPhone($phone);
        $user->setAddress($address);
        if ($password) {
            $user->setPassword($passwordHasher->hashPassword($user, $password));
        }

        $entityManager->persist($user);
        try {
            $entityManager->flush();
            // log the operation
            $operationLogger->log(
                'Mise à jour de l\'utilisateur ' . $user->getFullName(),
                'SUCCESS',
                'User',
                $user->getId(),
                null,
                [
                    'school' => $this->currentSchool->getId(),
                    'period' => $this->currentPeriod->getId()
                ]
            );
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // log the error
            $operationLogger->log(
                'Erreur lors de la mise à jour de l\'utilisateur ' . $user->getFullName(),
                'ERROR',
                'User',
                $user->getId(),
                $e->getMessage(),
                [
                    'school' => $this->currentSchool->getId(),
                    'period' => $this->currentPeriod->getId()
                ]
            );
            return new JsonResponse(['error' => 'Erreur lors de la mise à jour de l\'utilisateur : ' . $e->getMessage()], 500);
        }

        return new JsonResponse(['success' => 'Utilisateur mis à jour avec succès.']);
    }
}
