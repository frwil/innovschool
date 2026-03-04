<?php

namespace App\Controller;

use App\Entity\SchoolClassPeriod;
use App\Entity\SchoolPeriod;
use App\Entity\StudyLevel;
use App\Entity\UserBaseConfiguration;
use App\Entity\SchoolClassNumberingType;
use App\Entity\User;
use App\Repository\SchoolClassPeriodRepository;
use App\Repository\SchoolPeriodRepository;
use App\Repository\StudyLevelRepository;
use App\Service\OperationLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use App\Service\StringHelper;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Entity\Classe;
use App\Entity\ClassOccurence;
use App\Entity\SubjectGroup;
use App\Repository\ClasseRepository;
use App\Entity\School;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

#[Route('/school-class-period')]
final class SchoolClassPeriodController extends AbstractController
{
    private School $currentSchool;
    private SchoolPeriod $currentPeriod;
    private EntityManagerInterface $entityManager;
    private SessionInterface $session;

    public function __construct(
        EntityManagerInterface $entityManager,
    ) {
        $this->entityManager = $entityManager;
    }

    #[Route('/new', name: 'app_school_class_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        OperationLogger $operationLogger,
        SessionInterface $session
    ): Response {
        if ($request->isMethod('POST')) {
            $numberingType = $request->request->get('numbering_type');
            $classe = $entityManager->getRepository(Classe::class)->find($request->request->get('section_category'));
            $this->session = $session;
            $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
            $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
            $period = $this->currentPeriod;
            $school = $this->currentSchool;

            // Cas SANS numérotation : création d'une seule SchoolClassPeriod
            if (!$numberingType) {
                $name = $request->request->get('name');
                if ($name && $classe && $period && $school) {
                    $existingClassOccurence = $entityManager->getRepository(ClassOccurence::class)
                        ->findOneBy(['name' => $name]);
                    if (!$existingClassOccurence) {
                        $newClassOccurence = new ClassOccurence();
                        $newClassOccurence->setName($name);
                        $newClassOccurence->setSlug($slugger->slug($name));
                        $newClassOccurence->setClasse($classe);
                        $entityManager->persist($newClassOccurence);
                    } else {
                        $newClassOccurence = $existingClassOccurence;
                    }
                    $class = new SchoolClassPeriod();
                    $class->setSchool($school);
                    $class->setPeriod($period);
                    $class->setClassOccurence($newClassOccurence);
                    $class->setEvaluationAppreciationTemplate($school->getEvaluationAppreciationTemplate());
                    $entityManager->persist($class);
                    try {
                        $entityManager->flush();
                        $this->addFlash('success', 'Classe créée avec succès.');
                        // Log l'opération de création
                        $operationLogger->log(
                            'CREATION CLASSE (SANS NUMÉROTATION) ' . $class->getClassOccurence()->getName(),
                            'SUCCESS',
                            'SchoolClassPeriod',
                            $class->getId(),
                            null,
                            ['name' => $class->getClassOccurence()->getName()]
                        );
                        return $this->json([
                            'status' => 'success',
                            'message' => 'Classe créée avec succès.',
                        ]);
                    } catch (\Exception $e) {
                        if (!$this->entityManager->isOpen()) {
                            $this->entityManager = $doctrine->resetManager();
                        }
                        // code erreur 1062 : Duplicate entry
                        if ($e->getCode() === 1062) {
                            return $this->json([
                                'status' => 'error',
                                'message' => 'Cette classe existe déjà.',
                            ], 400);
                        }
                        $this->addFlash('danger', 'Erreur lors de la création de la classe : ' . $e->getMessage());
                        return $this->json([
                            'status' => 'error',
                            'message' => 'Erreur lors de la création de la classe : ' . $e->getMessage(),
                        ], 500);
                        // Log l'erreur
                        $operationLogger->log(
                            'CREATION CLASSE (SANS NUMÉROTATION) ' . $class->getClassOccurence()->getName(),
                            'ERROR',
                            'SchoolClassPeriod',
                            null,
                            null,
                            ['error' => $e->getMessage(), 'classe' => $classe->getName(), 'numberingType' => $numberingType, 'classCount' => $classCount, 'period' => $period->getName(), 'school' => $school->getName()]
                        );
                    }
                }
                $this->addFlash('danger', 'Veuillez remplir tous les champs.');
                return $this->json([
                    'status' => 'error',
                    'message' => 'Veuillez remplir tous les champs.',
                ], 400);
            }

            // Cas AVEC numérotation : comportement existant (création multiple)
            $classCount = (int) $request->request->get('class_count');
            $name = $classe->getName();
            $nType = $entityManager->getRepository(SchoolClassNumberingType::class)
                ->findOneBy(['classe' => $classe, 'school' => $school]);
            if (!$nType) {
                $nType = new SchoolClassNumberingType();
                $nType->setClasse($classe);
                $nType->setSchool($school);
                $nType->setNumberingType($numberingType);
                $entityManager->persist($nType);
            }
            $existingClasses = $entityManager->getRepository(SchoolClassPeriod::class)
                ->createQueryBuilder('c')
                ->join('c.classOccurence', 'co')
                ->where('c.school = :school')
                ->andWhere('co.classe = :classe')
                ->andWhere('c.period = :period')
                ->andWhere('co.name LIKE :namePattern')
                ->setParameter('classe', $classe)
                ->setParameter('school', $school)
                ->setParameter('period', $period->getId())
                ->setParameter('namePattern', $name . '%')
                ->getQuery()
                ->getResult();

            $existingSuffixes = [];
            foreach ($existingClasses as $class) {
                $suffix = trim(str_replace($name, '', $class->getClassOccurence()->getName()));
                if ($numberingType === 'numeric' && is_numeric($suffix)) {
                    $existingSuffixes[] = (int)$suffix;
                } elseif ($numberingType === 'alpha' && preg_match('/^[A-Z]$/', $suffix)) {
                    $existingSuffixes[] = ord($suffix) - 64; // A=0, B=1, ...
                }
            }
            $existingSuffixes = array_unique($existingSuffixes);
            sort($existingSuffixes);
            $createdClasses = [];
            for ($i = 0, $created = 0; $created < $classCount; $i++) {
                if (in_array($i + 1, $existingSuffixes)) {
                    continue; // Suffixe déjà utilisé
                }
                if ($numberingType === 'numeric') {
                    $suffix = ' ' . ($i + 1);
                } else { // alpha
                    $suffix = ' ' . chr(64 + $i + 1); // 65 = 'A'
                }

                $classOccurenceExist = $entityManager->getRepository(ClassOccurence::class)->findOneBy(['name' => $name . $suffix]);
                if (!$classOccurenceExist) {
                    $newClassOccurence = new ClassOccurence();
                    $newClassOccurence->setName($name . $suffix);
                    $newClassOccurence->setSlug($slugger->slug($name . $suffix));
                    $newClassOccurence->setClasse($classe);
                    $entityManager->persist($newClassOccurence);
                    $classOccurenceExist = $newClassOccurence;
                }
                $newClass = new SchoolClassPeriod();
                $newClass->setSchool($school);
                $newClass->setPeriod($period);
                $newClass->setClassOccurence($classOccurenceExist);
                $newClass->setEvaluationAppreciationTemplate($school->getEvaluationAppreciationTemplate());
                $entityManager->persist($newClass);
                $createdClasses[] = $name . $suffix;
                $created++;
            }
            try {
                $entityManager->flush();
            } catch (\Exception $e) {
                // code erreur 1062 : Duplicate entry
                if ($e->getCode() === 1062) {
                    $this->addFlash('danger', 'Une ou plusieurs classes existent déjà.');
                    return $this->json([
                        'status' => 'error',
                        'message' => 'Une ou plusieurs classes existent déjà.',
                    ], 400);
                }
                $this->addFlash('danger', 'Erreur lors de la création des classes : ' . $e->getMessage());
                return $this->json([
                    'status' => 'error',
                    'message' => 'Erreur lors de la création des classes : ' . $e->getMessage(),
                ], 500);

                if (!$this->entityManager->isOpen()) {
                    $this->entityManager = $doctrine->resetManager();
                }
                // Log l'erreur
                $operationLogger->log(
                    'CREATION OCCURENCES CLASSES ' . $classe->getName(),
                    'ERROR',
                    'SchoolClassPeriod',
                    null,
                    $e->getMessage(),
                    ['error' => $e->getMessage(), 'classe' => $classe->getName(), 'numberingType' => $numberingType, 'classCount' => $classCount, 'period' => $period->getName(), 'school' => $school->getName()]
                );
            }
            // Log l'opération de création multiple
            $operationLogger->log(
                'CREATION OCCURENCES CLASSES ' . $classe->getName(),
                'SUCCESS',
                'SchoolClassPeriod',
                null,
                null,
                [
                    'classe' => $classe->getName(),
                    'createdClasses' => $createdClasses,
                    'numberingType' => $numberingType,
                    'classCount' => $classCount,
                    'period' => $period->getName(),
                    'school' => $school->getName(),
                ]
            );

            return $this->json([
                'status' => 'success',
                'message' => 'Classes créées avec succès.',
            ]);
        }

        $schoolClassPeriod = new SchoolClassPeriod();
        if (!in_array('ROLE_ADMIN', $this->getUser()->getRoles()) && !in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles())) {
            $sectionIds = $entityManager->getRepository(UserBaseConfiguration::class)
                ->findOneBy(['user' => $this->getUser()])
                ->map(fn(UserBaseConfiguration $ubc) => count($ubc->getSectionList()) > 0 ? $ubc->getSectionList() : array_map(fn($sl) => $sl->getId(), $entityManager->getRepository(StudyLevel::class)->findAll()))
                ->flatten()
                ->toArray();
        } else {
            $sectionIds = $entityManager->getRepository(StudyLevel::class)->findAll();
        }
        $sections = $entityManager->getRepository(StudyLevel::class)->findBy(['id' => $sectionIds]);
        return $this->render('school_class/new.html.twig', [
            'school_class' => $schoolClassPeriod,
            'sections' => $sections,
        ]);
    }

    #[Route('/', name: 'app_school_class_index', methods: ['GET'])]
    public function index(SessionInterface $session, ClasseRepository $classeRepo, SchoolClassPeriodRepository $schoolClassPeriodRepo, SchoolPeriodRepository $periodRepo, StudyLevelRepository $sectionRepo): Response
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $period = $this->currentPeriod;
        $classes = $classeRepo->findAll();
        $sectionC = [];
        foreach ($classes as $classe) {
            $sectionC[$classe->getId()] = count($schoolClassPeriodRepo->findBy(['school' => $this->currentSchool, 'period' => $period, 'classOccurence' => $classe->getClassOccurences()->toArray()]));
        }

        if (in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles())) {
            $sections = $sectionRepo->findAll();
        } else {
            $config = $this->getConnectedUser()->getBaseConfigurations()->toArray();
            if (count($config) > 0) {
                $sections = $sectionRepo->findBy(['id' => count($config[0]->getSectionList()) > 0 ? $config[0]->getSectionList() : array_map(fn($sl) => $sl->getId(), $sectionRepo->findAll())]);
            } else {
                $sections = [];
            }
        }


        return $this->render('school_class/index.html.twig', [
            'classes' => $classes,
            'period' => $period,
            'sectionC' => $sectionC,
            'studyLevels' => $sections,
        ]);
    }

    public function getConnectedUser(): User
    {
        return $this->getUser();
    }

    #[Route('/classes/by-section', name: 'app_classes_by_section')]
    public function getClassesBySection(SessionInterface $session, Request $request, SchoolClassPeriodRepository $schoolClassRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $sectionId = $request->query->get('sectionId');
        $sectionCategories = $entityManager->getRepository(Classe::class)->findBy(['studyLevel' => $sectionId]);
        $classOccurences = $entityManager->getRepository(ClassOccurence::class)->findBy(['classe' => $sectionCategories]);
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $period = $this->currentPeriod;

        //dd($period->getId(),$this->currentSchool->getId(),array_map(fn($class)=>$class->getId(),$classOccurences));
        if (in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles())) {
            // Récupérer les classes associées à la section
            $classes = $this->entityManager->getRepository(SchoolClassPeriod::class)->findBy(['classOccurence' => $classOccurences, 'period' => $period, 'school' => $this->currentSchool]);
        } else {
            $config = $this->getConnectedUser()->getBaseConfigurations()->toArray();
            if (count($config) > 0) {
                if (count($config[0]->getClassList()) > 0) {
                    $classes = $this->entityManager->getRepository(SchoolClassPeriod::class)->findBy(['classOccurence' => $classOccurences, 'id' => $config[0]->getClassList(), 'period' => $period, 'school' => $this->currentSchool]);
                } else {
                    $classes = $this->entityManager->getRepository(SchoolClassPeriod::class)->findBy(['classOccurence' => $classOccurences, 'period' => $period, 'school' => $this->currentSchool]);
                }
            } else {

                $classes = [];
            }
        }

        $data = [];
        foreach ($classes as $class) {
            $data[] = [
                'id' => $class->getId(),
                'name' => $class->getClassOccurence()->getName(),
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/generic-classes/by-section', name: 'app_generic_classes_by_section')]
    public function getGenericClassesBySection(Request $request, SchoolClassPeriodRepository $schoolClassRepository, EntityManagerInterface $entityManager, SessionInterface $session): JsonResponse
    {
        $sectionId = $request->query->get('sectionId');
        $classes = $entityManager->getRepository(Classe::class)->findBy(['studyLevel' => $sectionId]);
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        if (in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles())) {
            $schoolClassPeriod = $this->entityManager->getRepository(SchoolClassPeriod::class)->findBy(['period' => $this->currentPeriod, 'school' => $this->currentSchool]);
            foreach ($schoolClassPeriod as $scp) {
                $studentClasses[] = $scp->getStudentClasses()->toArray();
            }

            if ($studentClasses) {
                $classes = [];
                $c_id = [];
                foreach ($studentClasses as $sc) {
                    foreach ($sc as $co) {
                        if ($co->getSchoolClassPeriod()->getClassOccurence()->getClasse()->getStudyLevel()->getId() == $sectionId && !in_array($co->getSchoolClassPeriod()->getClassOccurence()->getClasse()->getId(), $c_id)) $classes[] = $co->getSchoolClassPeriod()->getClassOccurence()->getClasse();
                        $c_id[] = $co->getSchoolClassPeriod()->getClassOccurence()->getClasse()->getId();
                    }
                }
            }
        } else {
            $config = $this->getConnectedUser()->getBaseConfigurations()->toArray();
            if (count($config) > 0) {
                $ids = array_map(function ($c) {
                    return $c->getId();
                }, $classes);
                $classes = $entityManager->getRepository(ClassOccurence::class)->findBy(['classe' => $ids]);
                $classes = array_map(function ($c) {
                    return $c->getId();
                }, $classes);
                $classes = $entityManager->getRepository(SchoolClassPeriod::class)->findBy(['classOccurence' => $classes]);

                $classes = array_filter($classes, function ($class) use ($config, $sectionId, $classes) {
                    return in_array($class->getId(), count($config[0]->getClassList()) > 0 ? $config[0]->getClassList() : array_map(fn($c) => $c->getId(), $classes)) && $class->getClassOccurence()->getClasse()->getStudyLevel()->getId() == $sectionId;
                });
                $ids = [];
                foreach ($classes as $class) {
                    $ids[] = $class->getClassOccurence()->getClasse()->getId();
                }
                $classes = $entityManager->getRepository(Classe::class)->findBy(['id' => $ids]);
            } else {
                $classes = [];
            }
        }

        $data = [];
        foreach ($classes as $class) {
            $data[] = [
                'id' => $class->getId(),
                'name' => $class->getName(),
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/classes/by-section-with-students', name: 'app_classes_by_section_with_students')]
    public function getClassesBySectionWithStudents(SessionInterface $session, Request $request, SchoolClassPeriodRepository $schoolClassRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $sectionId = $request->query->get('sectionId');
        $sectionCategories = $entityManager->getRepository(Classe::class)->findBy(['studyLevel' => $sectionId]);
        $sectionCategories = array_map(function ($classe) {
            return $classe->getId();
        }, $sectionCategories);
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));

        $classes = $schoolClassRepository->findBy(['school' => $this->currentSchool, 'period' => $this->currentPeriod]);
        if (in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles())) {
            // Récupérer les classes associées à la section
            $classes = $schoolClassRepository->findBy(['school' => $this->currentSchool, 'period' => $this->currentPeriod]);
            $classes = array_filter($classes, function (SchoolClassPeriod $class) use ($sectionCategories) {
                return $class->getStudentClasses()->count() > 0 && in_array($class->getClassOccurence()->getClasse()->getId(), $sectionCategories); // Filtrer les classes avec des étudiants
            });
        } else {
            $config = $this->getConnectedUser()->getBaseConfigurations()->toArray();
            if (count($config) > 0) {
                $classes = $schoolClassRepository->findBy(['id' => count($config[0]->getClassList()) > 0 ? $config[0]->getClassList() : $classes]);
                $classes = array_filter($classes, function (SchoolClassPeriod $class) use ($sectionCategories) {
                    return count($class->getStudentClasses()->toArray()) > 0 && in_array($class->getClassOccurence()->getClasse()->getId(), $sectionCategories); // Filtrer les classes avec des étudiants
                });
            } else {
                $classes = [];
            }
        }

        $data = [];
        foreach ($classes as $class) {
            $data[] = [
                'id' => $class->getId(),
                'name' => $class->getClassOccurence()->getName(),
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/section/{id}/categories', name: 'get_section_categories', methods: ['GET'])]
    public function getSectionCategories(StudyLevel $section): JsonResponse
    {
        $categories = $section->getClasses(); // ou adapte selon ta relation
        $data = [];
        foreach ($categories as $category) {
            $data[] = [
                'id' => $category->getId(),
                'name' => $category->getName(),
            ];
        }
        return $this->json($data);
    }

    #[Route('/{id}', name: 'app_school_class_show', methods: ['GET'])]
    public function show(SessionInterface $session, Classe $class, EntityManagerInterface $entityManager): Response
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $period = $this->currentPeriod;
        $classes =  $entityManager->getRepository(SchoolClassPeriod::class)->findBy(['classOccurence' => $class->getClassOccurences()->toArray(), 'period' => $period, 'school' => $this->currentSchool]);
        $filtered = $class->getSchoolClassNumberingTypes()->filter(function (SchoolClassNumberingType $numberingType) use ($class) {
            return $numberingType->getClasse()->getId() === $class->getId()
                && $numberingType->getSchool() === $this->currentSchool;
        });
        $numberingType = null;
        if (!$filtered->isEmpty()) {
            $numberingType = $filtered->first()->getNumberingType();
        }
        $hasNumberingType = !$filtered->isEmpty();
        //dd($classes);
        //dd($numberingType);
        return $this->render('school_class/show.html.twig', [
            'classes' => $classes,
            'class' => $class,
            'section' => $class->getStudyLevel(),
            'numberingType' => $numberingType,
            'hasNumberingType' => $hasNumberingType,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_school_class_edit', methods: ['GET', 'POST'])]
    public function edit(SessionInterface $session, Request $request, SchoolClassPeriod $class, EntityManagerInterface $entityManager, SluggerInterface $slugger, OperationLogger $operationLogger, \Doctrine\Persistence\ManagerRegistry $doctrine): Response
    {
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        if ($request->isMethod('POST')) {
            $name = $request->request->get('name');
            $classMaster = $entityManager->getRepository(User::class)->find($request->request->get('classMaster'));
            $classOccurenceExist = $entityManager->getRepository(ClassOccurence::class)->findOneBy(['name' => $name]);
            if ($classOccurenceExist) {
                $class->setClassOccurence($classOccurenceExist);
            } else {
                $class->getClassOccurence()->setName($name);
            }
            if ($classMaster) {
                $class->setClassMaster($classMaster);
            }


            // Ajoute ici les autres champs à éditer si besoin

            try {
                $entityManager->flush();

                // Log l'opération
                if ($classMaster) {
                    $operationLogger->log(
                        'MODIFICATION D\'ATTRIBUTION D\'UN ENSEIGNANT (' . $class->getClassMaster()->getFullName() . ') DANS LA CLASSE ' . $class->getClassOccurence()->getName(), // Type d'opération
                        'SUCCESS',      // Statut
                        'SchoolClassPeriod',  // Nom de l'entité
                        $class->getClassOccurence()->getId(), // ID de l'entité
                        null,           // Pas d'erreur
                        ['name' => $class->getClassOccurence()->getName(), 'teacher' => $class->getClassMaster()->getFullName(), 'period' => $this->currentPeriod->getName(), 'school' => $this->currentSchool->getName()] // Données additionnelles (optionnel)
                    );
                } else {
                    $operationLogger->log(
                        'MODIFICATION D\'UNE OCCURENCE DE CLASSE ' . $class->getClassOccurence()->getName(), // Type d'opération
                        'SUCCESS',      // Statut
                        'SchoolClassPeriod',  // Nom de l'entité
                        $class->getClassOccurence()->getId(), // ID de l'entité
                        null,           // Pas d'erreur
                        ['name' => $class->getClassOccurence()->getName(), 'period' => $this->currentPeriod->getName(), 'school' => $this->currentSchool->getName()] // Données additionnelles (optionnel)
                    );
                }
            } catch (\Exception $e) {
                // Log l'erreur
                if ($classMaster) {
                    if (!$this->entityManager->isOpen()) {
                        $this->entityManager = $doctrine->resetManager();
                    }
                    $operationLogger->log(
                        'MODIFICATION D\'ATTRIBUTION D\'UN ENSEIGNANT (' . $class->getClassMaster()->getFullName() . ') DANS LA CLASSE ' . $class->getClassOccurence()->getName(), // Type d'opération
                        'ERROR',        // Statut
                        'SchoolClassPeriod',  // Nom de l'entité
                        $class->getClassOccurence()->getId(), // ID de l'entité
                        $e->getMessage(), // Message d'erreur
                        ['name' => $class->getClassOccurence()->getName(), 'teacher' => $class->getClassMaster()->getFullName(), 'period' => $this->currentPeriod->getName(), 'school' => $this->currentSchool->getName()] // Données additionnelles (optionnel)
                    );
                    $this->addFlash('danger', 'Erreur lors de la modification de la classe : ' . $e->getMessage());
                    return $this->redirectToRoute('app_school_class_show', ['id' => $class->getClassOccurence()->getClasse()->getId()]);
                } else {
                    if (!$this->entityManager->isOpen()) {
                        $this->entityManager = $doctrine->resetManager();
                    }
                    $operationLogger->log(
                        'MODIFICATION D\'UNE OCCURENCE DE CLASSE ' . $class->getClassOccurence()->getName(), // Type d'opération
                        'ERROR',        // Statut
                        'SchoolClassPeriod',  // Nom de l'entité
                        $class->getClassOccurence()->getId(), // ID de l'entité
                        $e->getMessage(), // Message d'erreur
                        ['name' => $class->getClassOccurence()->getName(), 'period' => $this->currentPeriod->getName(), 'school' => $this->currentSchool->getName()] // Données additionnelles (optionnel)
                    );
                    $this->addFlash('danger', 'Erreur lors de la modification de la classe : ' . $e->getMessage());
                    return $this->redirectToRoute('app_school_class_show', ['id' => $class->getClassOccurence()->getClasse()->getId()]);
                }
            }
            $this->addFlash('success', 'Classe modifiée avec succès.');
            return $this->redirectToRoute('app_school_class_show', ['id' => $class->getClassOccurence()->getClasse()->getId()]);
        }
        $this->session = $session;
        $this->currentSchool = $entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $teachers = $entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->andWhere('u.school = :school')
            ->setParameter('role', '%ROLE_TEACHER%')
            ->setParameter('school', $this->currentSchool)
            ->getQuery()
            ->getResult();
        return $this->render('school_class/edit.html.twig', [
            'class' => $class,
            'teachers' => $teachers,
            'section' => $class->getClassOccurence()->getClasse()
        ]);
    }

    #[Route('/{id}/delete', name: 'app_school_class_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        SchoolClassPeriod $class,
        EntityManagerInterface $entityManager,
        OperationLogger $operationLogger,
        SessionInterface $session,
        \Doctrine\Persistence\ManagerRegistry $doctrine
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $class->getId(), $request->request->get('_token'))) {
            $className = $class->getClassOccurence()->getName();
            $this->entityManager = $entityManager;
            $this->session = $session;
            $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
            $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
            $classeId = $class->getClassOccurence()->getClasse()->getId();
            // 1. Supprimer d'abord le SchoolClassPeriod
            $this->entityManager->remove($class);
            try {
                $this->entityManager->flush();
            } catch (\Exception $e) {
                if (!$this->entityManager->isOpen()) {
                    $this->entityManager = $doctrine->resetManager();
                }
                $operationLogger->log(
                    'SUPPRESSION D\'AFFECTATION D\'UNE CLASSE ' . $className,
                    'ERROR',
                    'SchoolClassPeriod',
                    null,
                    $e->getMessage(),
                    ['name' => $className, 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()]
                );
                $this->addFlash('danger', 'Erreur lors de la suppression de la classe (étape 1) : ' . $e->getMessage());
                return $this->redirectToRoute('app_school_class_show', ['id' => $classeId]);
            }

            // 2. Puis supprimer le ClassOccurence s'il n'est plus référencé
            try {
                $classOccurence = $class->getClassOccurence();
                // Vérifier s'il existe encore des SchoolClassPeriod pour ce ClassOccurence
                $remaining = $this->entityManager->getRepository(SchoolClassPeriod::class)->findBy(['classOccurence' => $classOccurence]);
                if (count($remaining) === 0) {
                    $this->entityManager->remove($classOccurence);
                    $this->entityManager->flush();
                }
                $operationLogger->log(
                    'SUPPRESSION D\'AFFECTATION D\'UNE CLASSE ' . $className,
                    'SUCCESS',
                    'SchoolClassPeriod',
                    null,
                    null,
                    ['name' => $className, 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()]
                );
                $this->addFlash('success', 'Classe supprimée avec succès.');
            } catch (\Exception $e) {
                if (!$this->entityManager->isOpen()) {
                    $this->entityManager = $doctrine->resetManager();
                }
                $operationLogger->log(
                    'SUPPRESSION D\'AFFECTATION D\'UNE CLASSE ' . $className,
                    'ERROR',
                    'SchoolClassPeriod',
                    null,
                    $e->getMessage(),
                    ['name' => $className, 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()]
                );
                $this->addFlash('danger', 'Erreur lors de la suppression de l\'occurrence de classe : ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_school_class_show', ['id' => $classeId]);
    }

    #[Route('/section-category/{id}/edit', name: 'app_school_classes_edit', methods: ['GET', 'POST'])]
    public function editStudyLevel(
        Request $request,
        Classe $classe,
        EntityManagerInterface $entityManager,
        OperationLogger $operationLogger,
        SessionInterface $session
    ): Response {
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        if ($request->isMethod('POST')) {
            $name = $request->request->get('name');
            $sectionId = $request->request->get('sections');
            // Ajoute ici d'autres champs si besoin

            $classe->setName($name);
            $classe->setSlug($classe->getSlug() ?: strtolower(str_replace(' ', '-', $name)));
            $classe->setStudyLevel($sectionId ? $entityManager->getRepository(StudyLevel::class)->find($sectionId) : null);

            try {
                $entityManager->flush();

                // Log l'opération de modification
                $operationLogger->log(
                    'MODIFICATION CLASSE ' . $classe->getName(), // Type d'opération
                    'SUCCESS',      // Statut
                    'Classe', // Nom de l'entité
                    $classe->getId(), // ID de l'entité
                    null,           // Pas d'erreur
                    ['name' => $classe->getName(), 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()] // Données additionnelles (optionnel)
                );

                $this->addFlash('success', 'Classe modifiée avec succès.');
                return $this->redirectToRoute('app_school_class_show', ['id' => $classe->getId()]);
            } catch (\Exception $e) {
                if (!$this->entityManager->isOpen()) {
                    $this->entityManager = $doctrine->resetManager();
                }
                // Log l'erreur
                $operationLogger->log(
                    'MODIFICATION CLASSE ' . $classe->getName(), // Type d'opération
                    'ERROR',        // Statut
                    'Classe',   // Nom de l'entité
                    null,           // Pas d'ID d'entité
                    $e->getMessage(), // Message d'erreur
                    ['name' => $name, 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()] // Données additionnelles (optionnel)
                );
                $this->addFlash('danger', 'Erreur lors de la modification de la classe : ' . $e->getMessage());
                return $this->redirectToRoute('app_school_class_show', ['id' => $classe->getId()]);
            }
        }

        $sections = $entityManager->getRepository(StudyLevel::class)->findAll();

        return $this->render('school_class/edit.class.html.twig', [
            'classe' => $classe,
            'sections' => $sections,
        ]);
    }

    #[Route('/class/new', name: 'app_class_new', methods: ['POST'])]
    public function createClass(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        OperationLogger $operationLogger,
        StringHelper $stringHelper,
        SessionInterface $session
    ): JsonResponse {
        $name = $request->request->get('name');
        $sectionId = $request->request->get('section');
        $section = $entityManager->getRepository(StudyLevel::class)->find($sectionId);
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $period = $this->currentPeriod;
        $school = $this->currentSchool;

        if ($name && $section && $period && $school) {
            $class = new Classe();
            $class->setName($stringHelper->toUpperNoAccent($name));
            $class->setStudyLevel($section);
            $class->setSlug($slugger->slug($name));
            $entityManager->persist($class);
            try {
                $entityManager->flush();
                // Log l'opération de création
                $operationLogger->log(
                    'CREATION CLASSE ' . $class->getName(),
                    'SUCCESS',
                    'Classe',
                    $class->getId(),
                    null,
                    ['name' => $class->getName()]
                );
                return new JsonResponse([
                    'status' => 'success',
                    'message' => 'Classe créée avec succès.'
                ]);
            } catch (\Exception $e) {
                // Erreur 1062 : Duplicate entry
                if ($e->getCode() === 1062) {
                    return new JsonResponse([
                        'status' => 'error',
                        'message' => 'Cette classe existe déjà.'
                    ], 400);
                }

                if (!$this->entityManager->isOpen()) {
                    $this->entityManager = $doctrine->resetManager();
                }
                // Log l'erreur
                $operationLogger->log(
                    'CREATION CLASSE ' . $class->getName(),
                    'ERROR',
                    'Classe',
                    null,
                    $e->getMessage(),
                    ['name' => $name]
                );
                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'Erreur lors de la création de la classe : ' . $e->getMessage()
                ], 500);
            }
        }

        return new JsonResponse([
            'status' => 'error',
            'message' => 'Veuillez remplir tous les champs.'
        ], 400);
    }

    #[Route('/import/excel', name: 'app_class_import_excel', methods: ['POST'])]
    public function importClassesExcel(SessionInterface $session, Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger, OperationLogger $operationLogger, ManagerRegistry $doctrine): Response
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        /** @var UploadedFile $file */
        $file = $request->files->get('importFile');
        if (!$file) {
            $this->addFlash('danger', 'Aucun fichier envoyé.');
            return $this->redirectToRoute('app_school_class_index');
        }

        try {
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // Supposons que la première ligne est l'en-tête
            foreach (array_slice($rows, 1) as $row) {
                // Adapte l'index selon la structure de ton fichier Excel
                $name = trim($row[0]) ?? null;
                $sectionCategoryName = trim($row[1]) ?? null;
                $sectionName = trim($row[2]) ?? null;

                if (!$name || !$sectionName) {
                    continue;
                }

                $section = $entityManager->getRepository(\App\Entity\StudyLevel::class)->findOneBy(['name' => $sectionName]);
                if (!$section) {
                    $section = new \App\Entity\StudyLevel();
                    $section->setName($sectionName);
                    $section->setSlug($slugger->slug($sectionName));
                    $entityManager->persist($section);
                    try {
                        $entityManager->flush();
                    } catch (\Exception $e) {
                        $this->addFlash('danger', 'Erreur lors de la création du niveau d\'études : ' . $e->getMessage());
                    }
                }

                $class = $entityManager->getRepository(Classe::class)->findOneBy(['name' => $sectionCategoryName, 'studyLevel' => $section]);
                if (!$class) {
                    $class = new \App\Entity\Classe();
                    $class->setName($sectionCategoryName);
                    $class->setStudyLevel($section);
                    $class->setSlug($slugger->slug($sectionCategoryName));
                    $entityManager->persist($class);
                    try {
                        $entityManager->flush();
                        // Log l'opération de création
                        $operationLogger->log(
                            'IMPORTATION CLASSE EXCEL (' . $sectionCategoryName . ')',
                            'INFO',
                            'Classe',
                            null,
                            'Création réussie',
                            ['file' => $file->getClientOriginalName()]
                        );
                    } catch (\Exception $e) {
                        $this->addFlash('danger', 'Erreur lors de la création de la classe : ' . $e->getMessage());
                        if (!$this->entityManager->isOpen()) {
                            $this->entityManager = $doctrine->resetManager();
                        }
                        // Log l'erreur
                        $operationLogger->log(
                            'IMPORTATION CLASSE EXCEL (' . $sectionCategoryName . ')',
                            'ERROR',
                            'Classe',
                            null,
                            $e->getMessage(),
                            ['error' => $e->getMessage(), 'file' => $file->getClientOriginalName()]
                        );
                    }
                }
                $subClass = $entityManager->getRepository(ClassOccurence::class)->findOneBy(['name' => $name, 'classe' => $class]);
                if (!$subClass) {
                    $subClass = new \App\Entity\ClassOccurence();
                    $subClass->setName($name);
                    $subClass->setSlug($slugger->slug($name));
                    $subClass->setClasse($class);
                    $entityManager->persist($subClass);
                    try {
                        $entityManager->flush();
                        // Log l'opération de création
                        $operationLogger->log(
                            'IMPORTATION OCCURENCE DE CLASSE EXCEL (' . $name . ')',
                            'INFO',
                            'ClassOccurence',
                            null,
                            'Création réussie',
                            ['file' => $file->getClientOriginalName(), 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()]
                        );
                    } catch (\Exception $e) {
                        $this->addFlash('danger', 'Erreur lors de la création de l\'occurrence de classe : ' . $e->getMessage());
                        if (!$this->entityManager->isOpen()) {
                            $this->entityManager = $doctrine->resetManager();
                        }
                        // Log l'erreur
                        $operationLogger->log(
                            'IMPORTATION OCCURENCE DE CLASSE EXCEL (' . $name . ')',
                            'ERROR',
                            'ClassOccurence',
                            null,
                            $e->getMessage(),
                            ['error' => $e->getMessage(), 'file' => $file->getClientOriginalName(), 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()]
                        );
                    }
                }

                $schoolClassPeriod = $entityManager->getRepository(SchoolClassPeriod::class)->findOneBy([
                    'classOccurence' => $subClass,
                    'school' => $this->currentSchool,
                    'period' => $this->currentPeriod
                ]);
                if (!$schoolClassPeriod) {
                    $schoolClassPeriod = new SchoolClassPeriod();
                    $schoolClassPeriod->setSchool($this->currentSchool);
                    $schoolClassPeriod->setPeriod($this->currentPeriod);
                    $schoolClassPeriod->setClassOccurence($subClass);
                    $schoolClassPeriod->setEvaluationAppreciationTemplate($this->currentSchool->getEvaluationAppreciationTemplate());
                    $entityManager->persist($schoolClassPeriod);
                }
            }

            try {
                $entityManager->flush();
                $this->addFlash('success', 'Importation des classes (Excel) réussie.');
                // Log l'opération d'importation
                $operationLogger->log(
                    'IMPORTATION CLASSES EXCEL',
                    'SUCCESS',
                    'SchoolClassPeriod',
                    null,
                    'Importation réussie',
                    ['file' => $file->getClientOriginalName(), 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()]
                );
            } catch (\Exception $e) {
                $this->addFlash('danger', 'Erreur lors de l\'importation : ' . $e->getMessage());
                if (!$this->entityManager->isOpen()) {
                    $this->entityManager = $doctrine->resetManager();
                }
                // Log l'erreur
                $operationLogger->log(
                    'IMPORTATION CLASSES EXCEL',
                    'ERROR',
                    'SchoolClassPeriod',
                    null,
                    $e->getMessage(),
                    ['error' => $e->getMessage(), 'file' => $file->getClientOriginalName()]
                );
            }
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erreur lors de l\'importation : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_school_class_index');
    }

    #[Route('/import/json', name: 'app_class_import_json', methods: ['POST'])]
    public function importClassesJson(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger, OperationLogger $operationLogger, ManagerRegistry $doctrine): Response
    {
        /** @var UploadedFile $file */
        $file = $request->files->get('importFile');
        if (!$file) {
            $this->addFlash('danger', 'Aucun fichier envoyé.');
            return $this->redirectToRoute('app_school_class_index');
        }

        try {
            $jsonContent = file_get_contents($file->getPathname());
            $classes = json_decode($jsonContent, true);

            if (!is_array($classes)) {
                throw new \Exception('Le fichier JSON n\'est pas valide.');
            }

            foreach ($classes as $classData) {
                $name = $classData['name'] ?? null;
                $sectionName = $classData['sectionName'] ?? null;

                if (!$name || !$sectionName) {
                    continue;
                }

                $section = $entityManager->getRepository(\App\Entity\StudyLevel::class)->findOneBy(['name' => $sectionName]);
                if (!$section) {
                    continue;
                }

                $class = new \App\Entity\StudyLevel();
                $class->setName($name);
                $class->setSlug($slugger->slug($name));
                $entityManager->persist($class);
            }

            $entityManager->flush();
            $this->addFlash('success', 'Importation des classes (JSON) réussie.');
            // Log l'opération d'importation
            $operationLogger->log(
                'IMPORTATION CLASSES JSON',
                'SUCCESS',
                'SchoolClassPeriod',
                null,
                'Importation réussie',
                ['file' => $file->getClientOriginalName(), 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()]
            );
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erreur lors de l\'importation : ' . $e->getMessage());
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // Log l'erreur
            $operationLogger->log(
                'IMPORTATION CLASSES JSON',
                'ERROR',
                'SchoolClassPeriod',
                null,
                $e->getMessage(),
                ['error' => $e->getMessage(), 'file' => $file->getClientOriginalName(), 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()]
            );
        }

        return $this->redirectToRoute('app_school_class_index');
    }

    #[Route('/assigned-subjects/{id}/by-class', name: 'app_get_assigned_subjects_by_class', methods: ['GET'])]
    public function getAssignedSubjectsByClass(
        Classe $class,
        EntityManagerInterface $entityManager,
        SessionInterface $session
    ): JsonResponse {

        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $schoolClassPeriods = $entityManager->getRepository(SchoolClassPeriod::class)->findBy(['classOccurence' => $class->getClassOccurences()->toArray(), 'school' => $this->currentSchool, 'period' => $this->currentPeriod]);
        // On suppose que la relation s'appelle getSchoolClassSubjects()

        $data = [];
        foreach ($schoolClassPeriods as $schoolClassPeriod) {
            $subjects = $schoolClassPeriod->getSchoolClassSubjects(); // adapte selon ta structure
            foreach ($subjects as $subject) {
                $data[] = [
                    'classe' => $subject->getSchoolClassPeriod()->getClassOccurence()->getName(),
                    'name' => $subject->getStudySubject()->getName(), // adapte selon ta structure
                    'coefficient' => $subject->getCoefficient(), // adapte selon ta structure
                    'group' => $subject->getGroup() ? $subject->getGroup()->getDescription() : null, // adapte selon ta structure
                    'teacher' => $subject->getTeacher() ? $subject->getTeacher()->getFullName() : null, // adapte selon ta structure
                    'skills' => $subject->getAwaitedSkills(), // adapte selon ta structure
                    'id' => $subject->getId(),
                    'group_id' => $subject->getGroup() ? $subject->getGroup()->getId() : null, // adapte selon ta structure
                    'teacher_id' => $subject->getTeacher() ? $subject->getTeacher()->getId() : null, // adapte selon ta structure
                    // Ajoute d'autres champs si besoin
                ];
            }
        }

        return new JsonResponse($data);
    }

    #[Route('/assigned-subjects/{id}/delete', name: 'app_delete_assigned_subject', methods: ['DELETE'])]
    public function deleteAssignedSubject(
        int $id,
        EntityManagerInterface $entityManager,
        OperationLogger $operationLogger,
        SessionInterface $session
    ): JsonResponse {

        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));

        $subjectAssignment = $entityManager->getRepository(\App\Entity\SchoolClassSubject::class)->find($id);

        if (!$subjectAssignment) {
            return new JsonResponse(['error' => 'Affectation de matière introuvable.'], 404);
        }

        try {
            $entityManager->remove($subjectAssignment);
            $entityManager->flush();
            // Log l'opération de suppression
            $operationLogger->log(
                'SUPPRESSION AFFECTATION MATIERE ' . $subjectAssignment->getStudySubject()->getName(),
                'SUCCESS',
                'SchoolClassSubject',
                $subjectAssignment->getId(),
                'Affectation de matière supprimée avec succès.',
                ['subject' => $subjectAssignment->getStudySubject()->getName(), 'class' => $subjectAssignment->getSchoolClassPeriod()->getClassOccurence()->getName(), 'period' => $this->currentPeriod->getName(), 'school' => $this->currentSchool->getName()]
            );
            return new JsonResponse(['success' => true, 'message' => 'Affectation de matière supprimée avec succès.']);
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // Log l'erreur
            $operationLogger->log(
                'SUPPRESSION AFFECTATION MATIERE ' . $subjectAssignment->getStudySubject()->getName(),
                'ERROR',
                'SchoolClassSubject',
                $subjectAssignment->getId(),
                'Erreur lors de la suppression de l\'affectation de matière.',
                ['subject' => $subjectAssignment->getStudySubject()->getName(), 'class' => $subjectAssignment->getSchoolClassPeriod()->getClassOccurence()->getName(), 'period' => $this->currentPeriod->getName(), 'school' => $this->currentSchool->getName()]
            );
            return new JsonResponse(['error' => 'Erreur lors de la suppression.'], 500);
        }
    }

    #[Route('/assigned-subjects/{id}/edit', name: 'app_edit_subject_assignment', methods: ['POST'])]
    public function editSubjectAssignment(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        OperationLogger $operationLogger,
        SessionInterface $session
    ): JsonResponse {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));

        $subjectAssignment = $entityManager->getRepository(\App\Entity\SchoolClassSubject::class)->find($id);

        if (!$subjectAssignment) {
            return new JsonResponse(['error' => 'Affectation de matière introuvable.'], 404);
        }

        $coefficient = $request->request->get('coefficient');
        $group = $request->request->get('group');
        $teacher = $request->request->get('teacher');
        $skills = $request->request->get('skills');
        $assignTeacherMethod = $request->request->get('assignTeacherMethod');

        $subjectAssignment->setCoefficient($coefficient);

        // Si group et teacher sont des entités, il faut les rechercher par leur ID ou nom
        if ($group) {
            $subjectAssignment->setGroup($entityManager->getRepository(SubjectGroup::class)->find($group)); // adapte si c'est une entité
        }
        if ($teacher) {
            if ($assignTeacherMethod === 'all_empty') {
                $subjectAssignments = $entityManager->getRepository(\App\Entity\SchoolClassSubject::class)->findBy([
                    'teacher' => null,
                    'schoolClassPeriod' => $subjectAssignment->getSchoolClassPeriod()
                ]);
                foreach ($subjectAssignments as $assignment) {
                    $assignment->setTeacher($entityManager->getRepository(User::class)->find($teacher)); // adapte si c'est une entité
                }
            } elseif ($assignTeacherMethod === 'all') {
                $subjectAssignments = $entityManager->getRepository(\App\Entity\SchoolClassSubject::class)->findBy([
                    'schoolClassPeriod' => $subjectAssignment->getSchoolClassPeriod()
                ]);
                foreach ($subjectAssignments as $assignment) {
                    $assignment->setTeacher($entityManager->getRepository(User::class)->find($teacher)); // adapte si c'est une entité
                }
            } else {
                $subjectAssignment->setTeacher($entityManager->getRepository(User::class)->find($teacher)); // adapte si c'est une entité
            }
        }
        $subjectAssignment->setAwaitedSkills($skills);

        try {
            $entityManager->flush();
            // Log l'opération de modification
            $operationLogger->log(
                'MODIFICATION AFFECTATION MATIERE ' . $subjectAssignment->getStudySubject()->getName(),
                'SUCCESS',
                'SchoolClassSubject',
                $subjectAssignment->getId(),
                'Affectation de matière modifiée avec succès.',
                ['subject' => $subjectAssignment->getStudySubject()->getName(), 'class' => $subjectAssignment->getSchoolClassPeriod()->getClassOccurence()->getName(), 'period' => $this->currentPeriod->getName(), 'school' => $this->currentSchool->getName()]
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Affectation de matière modifiée avec succès.',
            ]);
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // Log l'erreur
            $operationLogger->log(
                'MODIFICATION AFFECTATION MATIERE ' . $subjectAssignment->getStudySubject()->getName(),
                'ERROR',
                'SchoolClassSubject',
                $subjectAssignment->getId(),
                'Erreur lors de la modification de l\'affectation de matière.',
                ['subject' => $subjectAssignment->getStudySubject()->getName(), 'class' => $subjectAssignment->getSchoolClassPeriod()->getClassOccurence()->getName(), 'period' => $this->currentPeriod->getName(), 'school' => $this->currentSchool->getName()]
            );
            return new JsonResponse(['error' => 'Erreur lors de la modification.'], 500);
        }
    }

    #[Route('/classe/{id}/delete', name: 'app_school_class_classe_delete', methods: ['POST'])]
    public function deleteClasse(
        Request $request,
        Classe $classe,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $classe->getId(), $request->request->get('_token'))) {
            try {
                $entityManager->remove($classe);
                $entityManager->flush();
                $this->addFlash('success', 'Classe supprimée avec succès.');
            } catch (\Exception $e) {
                $this->addFlash('danger', 'Erreur lors de la suppression de la classe : ' . $e->getMessage());
            }
        } else {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
        }
        return $this->redirectToRoute('app_school_class_index');
    }
}
