<?php

namespace App\Controller;

use App\Entity\SchoolClassPeriod;
use App\Entity\SchoolClassSubject;
use App\Entity\SchoolPeriod;
use App\Entity\SectionCategorySubject;
use App\Entity\StudySubject;
use App\Form\SchoolClassSubjectType;
use App\Form\SectionCategoriesType;
use App\Repository\SchoolClassSubjectRepository;
use App\Service\SlipDTO;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use App\Service\StringHelper;
use App\Entity\SubjectGroup;
use App\Entity\User;
use App\Entity\Classe;
use App\Entity\ClassOccurence;
use App\Entity\School;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Service\OperationLogger;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Session\Session;

#[Route('/school-class-subject')]
final class SchoolClassSubjectController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private School $currentSchool;
    private SchoolPeriod $currentPeriod;
    private SessionInterface $session;
    public function __construct(
        EntityManagerInterface $entityManager
    ) {
        $this->entityManager = $entityManager;
    }

    #[Route('/study', name: 'app_study_show', methods: ['GET', 'POST'])]
    public function showStudies(
        Request $request,
        EntityManagerInterface $entityManager,
        \App\Service\OperationLogger $operationLogger, // <-- Ajoute ceci
        SluggerInterface $slugger,
        StringHelper $stringHelper,
        SessionInterface $session
    ): Response {
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $entityManager->getRepository(School::class)->find($session->get('school_id'));
        $this->currentPeriod = $entityManager->getRepository(SchoolPeriod::class)->find($session->get('period_id'));
        $subjects = $entityManager->getRepository(StudySubject::class)->findBy([], ['name' => 'ASC']);



        $period = $this->currentPeriod;
        $schoolClassPeriods = $entityManager->getRepository(SchoolClassPeriod::class)->findBy(['school' => $this->currentSchool, 'period' => $period]);
        $subjectsClasses = $entityManager->getRepository(SchoolClassSubject::class)->findBy(['schoolClassPeriod' => $schoolClassPeriods]);
        $usedStudysubject = [];
        $scb = [];
        foreach ($subjectsClasses as $sc) {
            if (!in_array($sc->getStudySubject()->getId(), $usedStudysubject)) {
                $usedStudysubject[] = $sc->getStudySubject()->getId();
                $scb[] = $sc;
            }
        }
        $classeIds = array_map(function ($class) {
            return $class->getClassOccurence()->getClasse()->getId();
        }, $schoolClassPeriods);
        // rendons les id de classe uniques
        $classeIds = array_unique($classeIds);
        $classes = $entityManager->getRepository(Classe::class)->findBy(['id' => $classeIds], ['name' => 'ASC']);
        $groups = $entityManager->getRepository(\App\Entity\SubjectGroup::class)->findBy(['school' => $this->currentSchool, 'period' => $period], ['description' => 'ASC']);
        $page = $request->query->getInt('page', 1);
        // Création du formulaire simple sans FormType (exemple rapide)
        if ($request->isMethod('POST')) {
            $name = $request->request->get('name');
            $slug = $request->request->get('slug');
            $slug = $slugger->slug(strtolower($name));
            if ($name && $slug) {
                $subject = new \App\Entity\StudySubject();
                $subject->setName($stringHelper->toUpperNoAccent($name));
                $subject->setSlug($slug);
                $entityManager->persist($subject);
                try {
                    $entityManager->flush();

                    // Log l'opération de création de matière
                    $operationLogger->log(
                        'CREATION DE MATIÈRE ' . $subject->getName(), // Type d'opération
                        'SUCCESS',  // Statut
                        'StudySubject', // Nom de l'entité
                        $subject->getId(), // ID de l'entité
                        null, // Pas d'erreur
                        ['name' => $subject->getName(), 'slug' => $subject->getSlug(), 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()] // Données additionnelles
                    );

                    $this->addFlash('success', 'Matière créée avec succès.');
                    return $this->redirectToRoute('app_study_show', ['page' => $page]);
                } catch (\Exception $e) {
                    if (!$this->entityManager->isOpen()) {
                        $this->entityManager = $doctrine->resetManager();
                    }
                    // Log de l'erreur
                    $operationLogger->log(
                        'CREATION DE MATIÈRE',
                        'ERROR',
                        'StudySubject',
                        null,
                        $e->getMessage(),
                        ['name' => $name, 'slug' => $slug, 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()]
                    );
                    $this->addFlash('danger', 'Erreur lors de la création de la matière : ' . $e->getMessage());
                }
            }
        }

        return $this->render('study_subject/show.html.twig', [
            'subjects' => $subjects,
            'all_school_classes' => $classes,
            'all_subject_groups' => $groups,
            'page' => $page,
            'subjectsClasses' => $scb
        ]);
    }

    #[Route('/study-subjects', name: 'app_get_study_subjects', methods: ['GET'])]
    public function getStudySubjectsList(
        EntityManagerInterface $entityManager,
    ): Response {
        $studySubjects = $entityManager->getRepository(StudySubject::class)->findBy([], ['name' => 'ASC']);
        return new JsonResponse(
            array_map(function ($studySubject) {
                return [
                    'id' => $studySubject->getId(),
                    'name' => $studySubject->getName(),
                    'slug' => $studySubject->getSlug()
                ];
            }, $studySubjects)

        );
    }

    #[Route('/study-subjects-classes', name: 'app_get_study_subjects_classes', methods: ['GET'])]
    public function getStudySubjectsClassesList(
        EntityManagerInterface $entityManager,
        SessionInterface $session
    ): Response {
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $entityManager->getRepository(School::class)->find($session->get('school_id'));
        $this->currentPeriod = $entityManager->getRepository(SchoolPeriod::class)->find($session->get('period_id'));
        $period = $this->currentPeriod;
        $schoolClassPeriods = $entityManager->getRepository(SchoolClassPeriod::class)->findBy(['school' => $this->currentSchool, 'period' => $period]);
        $studySubjects = $entityManager->getRepository(SchoolClassSubject::class)->findBy(['schoolClassPeriod' => $schoolClassPeriods]);
        $studySubjects = $entityManager->getRepository(SchoolClassSubject::class)->findBy(['schoolClassPeriod' => $schoolClassPeriods]);
        $usedStudysubject = [];
        $scb = [];
        foreach ($studySubjects as $sc) {
            if (!in_array($sc->getStudySubject()->getId(), $usedStudysubject)) {
                $usedStudysubject[] = $sc->getStudySubject()->getId();
                $scb[] = $sc;
            }
        }
        return new JsonResponse(
            array_map(function ($studySubject) {
                return [
                    'id' => $studySubject->getStudySubject()->getId(),
                    'name' => $studySubject->getStudySubject()->getName(),
                    'slug' => $studySubject->getStudySubject()->getSlug()
                ];
            }, $scb)

        );
    }

    #[Route('/study-subjects-groups', name: 'app_get_study_subjects_groups', methods: ['GET'])]
    public function getStudySubjectsGroupList(
        EntityManagerInterface $entityManager,
        SessionInterface $session
    ): Response {
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $entityManager->getRepository(School::class)->find($session->get('school_id'));
        $this->currentPeriod = $entityManager->getRepository(SchoolPeriod::class)->find($session->get('period_id'));
        $groups = $entityManager->getRepository(\App\Entity\SubjectGroup::class)->findBy(['school' => $this->currentSchool, 'period' => $this->currentPeriod], ['description' => 'ASC']);
        return new JsonResponse(
            array_map(function ($group) {
                return [
                    'id' => $group->getId(),
                    'name' => $group->getDescription()
                ];
            }, $groups)
        );
    }


    #[Route('/assign-subjects-to-classes', name: 'app_assign_subjects_to_classes', methods: ['POST'])]
    public function assignSubjectsToClasses(
        SessionInterface $session,
        Request $request,
        EntityManagerInterface $entityManager,
        \App\Service\OperationLogger $operationLogger,
        ManagerRegistry $doctrine
    ): Response {
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $entityManager->getRepository(School::class)->find($session->get('school_id'));
        $this->currentPeriod = $entityManager->getRepository(SchoolPeriod::class)->find($session->get('period_id'));

        $subjectIds = $request->request->all('subjects');
        $classIds = $request->request->all('classes');
        $coefficient = $request->request->get('coefficient', 1);
        $groupId = $request->request->get('group');
        $awaitedSkills = $request->request->get('awaited-skills');

        // Vérification des données
        if (!$subjectIds || !$classIds) {
            $message = 'Veuillez sélectionner au moins une matière et une classe.';
            $this->addFlash('danger', $message);

            // Si c'est une requête AJAX, retourner JSON
            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'status' => 'error',
                    'message' => $message,
                    'redirect' => $this->generateUrl('app_study_show')
                ]);
            }
            return $this->redirectToRoute('app_study_show');
        }

        $subjects = $entityManager->getRepository(StudySubject::class)->findBy(['id' => $subjectIds]);
        $classes = $entityManager->getRepository(Classe::class)->findBy(['id' => $classIds]);

        // Correction de la logique pour récupérer les classOccurences
        $classOccurences = [];
        foreach ($classes as $class) {
            $classOccurences = array_merge($classOccurences, $class->getClassOccurences()->toArray());
        }

        $schoolClassPeriods = $entityManager->getRepository(SchoolClassPeriod::class)->findBy([
            'classOccurence' => $classOccurences,
            'school' => $this->currentSchool,
            'period' => $this->currentPeriod
        ]);

        $group = $groupId ? $entityManager->getRepository(SubjectGroup::class)->find($groupId) : null;

        $existingAffectations = [];
        $totalAffectations = 0;
        $totalExisting = 0;

        foreach ($subjects as $subject) {
            foreach ($schoolClassPeriods as $class) {
                $totalAffectations++;
                $existingAffectation = $entityManager->getRepository(SchoolClassSubject::class)->findOneBy([
                    'studySubject' => $subject,
                    'schoolClassPeriod' => $class,
                ]);

                if ($existingAffectation) {
                    $existingAffectations[] = $subject->getName() . ' - ' . $class->getClassOccurence()->getName();
                    $totalExisting++;
                } else {
                    $scs = new SchoolClassSubject();
                    $scs->setStudySubject($subject);
                    $scs->setSchoolClassPeriod($class);
                    $scs->setCoefficient($coefficient);
                    $scs->setGroup($group);
                    $scs->setAwaitedSkills($awaitedSkills ?? null);
                    $entityManager->persist($scs);
                }
            }
        }

        // Gestion des différentes situations
        $isAjax = $request->isXmlHttpRequest();

        if ($totalExisting === $totalAffectations && $totalAffectations > 0) {
            $message = 'Toutes les affectations existent déjà!';
            $this->addFlash('info', $message);

            if ($isAjax) {
                return $this->json([
                    'status' => 'info',
                    'message' => $message,
                    'redirect' => $this->generateUrl('app_study_show')
                ]);
            }
        } elseif (count($existingAffectations) > 0) {
            $message = 'Affectations déjà existantes : ' . implode('<br>', $existingAffectations);
            $this->addFlash('warning', $message);

            $entityManager->flush();

            if ($isAjax) {
                return $this->json([
                    'status' => 'warning',
                    'message' => $message,
                    'redirect' => $this->generateUrl('app_study_show')
                ]);
            }
        } else {
            try {
                $entityManager->flush();

                // Log l'opération d'affectation des matières aux classes
                $operationLogger->log(
                    'AFFECTATION DE MATIÈRES AUX CLASSES',
                    'SUCCESS',
                    'SchoolClassSubject',
                    null,
                    null,
                    [
                        'subjects' => implode(', ', array_map(fn($s) => $s->getName(), $subjects)),
                        'classes' => implode(', ', array_map(fn($c) => $c->getName(), $classes)),
                        'school' => $this->currentSchool->getName(),
                        'period' => $this->currentPeriod->getName()
                    ]
                );

                $message = 'Affectation effectuée avec succès.';
                $this->addFlash('success', $message);

                if ($isAjax) {
                    return $this->json([
                        'status' => 'success',
                        'message' => $message,
                        'redirect' => $this->generateUrl('app_study_show')
                    ]);
                }
            } catch (\Exception $e) {
                if (!$this->entityManager->isOpen()) {
                    $this->entityManager = $doctrine->resetManager();
                }
                $operationLogger->log(
                    'AFFECTATION DE MATIÈRES AUX CLASSES',
                    'ERROR',
                    'SchoolClassSubject',
                    null,
                    $e->getMessage(),
                    [
                        'subjects' => implode(', ', array_map(fn($s) => $s->getName(), $subjects)),
                        'classes' => implode(', ', array_map(fn($c) => $c->getName(), $classes)),
                        'school' => $this->currentSchool->getName(),
                        'period' => $this->currentPeriod->getName()
                    ]
                );

                $errorMessage = 'Erreur lors de l\'affectation des matières aux classes : ' . $e->getMessage();
                $this->addFlash('danger', $errorMessage);

                if ($isAjax) {
                    return $this->json([
                        'status' => 'error',
                        'message' => $errorMessage,
                        'redirect' => $this->generateUrl('app_study_show')
                    ], 500);
                }
            }
        }

        // Redirection normale (non-AJAX)
        return $this->redirectToRoute('app_study_show');
    }

    #[Route('/study/create', name: 'app_study_create', methods: ['GET', 'POST'])]
    public function createStudySubject(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        StringHelper $stringHelper,
        OperationLogger $operationLogger,
        SessionInterface $session,
        \Doctrine\Persistence\ManagerRegistry $doctrine
    ): Response {
        if ($request->isMethod('POST')) {
            $name = $request->request->get('name');
        } else {
            $name = $request->query->get('name');
        }
        $slug = $name ? $slugger->slug($name) : null;
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        if ($name && $slug) {
            $subject = new StudySubject();
            $subject->setName($stringHelper->toUpperNoAccent($name));
            $subject->setSlug(strtolower($slug));
            $entityManager->persist($subject);
            try {
                $entityManager->flush();
                // Log l'opération de création de matière
                $operationLogger->log(
                    'CRÉATION DE MATIÈRE ' . $subject->getName(),
                    'SUCCESS',
                    'StudySubject',
                    $subject->getId(),
                    null,
                    ['name' => $subject->getName(), 'slug' => $subject->getSlug(), 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()]
                );

                return $this->json([
                    'status' => 'success',
                    'message' => 'Matière créée avec succès.',
                ]);
            } catch (\Exception $e) {
                if (!$this->entityManager->isOpen()) {
                    $this->entityManager = $doctrine->resetManager();
                }
                // Log de l'erreur
                $operationLogger->log(
                    'CRÉATION DE MATIÈRE ' . $subject->getName(),
                    'ERROR',
                    'StudySubject',
                    null,
                    null,
                    ['name' => $subject->getName(), 'slug' => $subject->getSlug(), 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()]
                );
                // cas de l'erreur 1062 
                if ($e->getCode() === 1062) {
                    return $this->json([
                        'status' => 'error',
                        'message' => 'La matière existe déjà.',
                    ], 400);
                }
                return $this->json([
                    'status' => 'error',
                    'message' => 'Erreur lors de la création de la matière : ' . $e->getMessage(),
                ], 500);
            }
        }

        return $this->json([
            'status' => 'error',
            'message' => 'Veuillez renseigner tous les champs obligatoires.',
        ], 400);
    }

    #[Route('/study/class-occurence-list', name: 'app_get_class_occurence_from_list', methods: ['GET'])]
    public function getClassOccurenceFromClasses(
        EntityManagerInterface $entityManager,
        SessionInterface $session,
        Request $request
    ): Response {
        $classe = explode(",", $request->query->get('class'));
        $classeOccurenceList = [];
        if ($classe) {
            $this->session = $session;
            $this->entityManager = $entityManager;
            $this->currentSchool = $entityManager->getRepository(School::class)->find($session->get('school_id'));
            $this->currentPeriod = $entityManager->getRepository(SchoolPeriod::class)->find($session->get('period_id'));
            $period = $this->currentPeriod;
            $schoolClassPeriods = $entityManager->getRepository(SchoolClassPeriod::class)->findBy(['school' => $this->currentSchool, 'period' => $period]);
            $classOccurenceIds = array_map(function ($item) {
                return $item->getClassOccurence()->getId();
            }, $schoolClassPeriods);
            $classOccurences = $entityManager->getRepository(ClassOccurence::class)->findBy(['classe' => $classe]);
            $classeOccurenceArray = array_map(function ($item) {
                return $item->getId();
            }, $classOccurences);
            $classOccurences = array_filter($classOccurences, function ($item) use ($classOccurenceIds) {
                return in_array($item->getId(), $classOccurenceIds);
            });
            $schoolClassSubjects = $entityManager->getRepository(SchoolClassSubject::class)->findBy(['schoolClassPeriod' => $schoolClassPeriods]);
            if ($schoolClassSubjects) {
                $existingClasses = array_unique(array_map(function ($item) {
                    return $item->getSchoolClassPeriod()->getClassOccurence()->getId();
                }, $schoolClassSubjects));
                $classOccurences = array_filter($classOccurences, function ($item) use ($existingClasses) {
                    return !in_array($item->getId(), $existingClasses);
                });
            }
            $classeOccurenceList = array_values(array_map(function ($item) {
                return [
                    'id' => $item->getId(),
                    'name' => $item->getName()
                ];
            }, $classOccurences));
        }
        //return new JsonResponse($classeOccurenceList);
        return new JsonResponse([]);
    }

    #[Route('/study/{id}/manage', name: 'app_study_manage', methods: ['GET'])]
    public function manageStudySubject(SessionInterface $session, StudySubject $subject, EntityManagerInterface $entityManager): Response
    {
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        // Récupère les SchoolClassSubject liés à cette matière
        $classSubjects = $subject->getSchoolClassSubjects();
        $period = $this->currentPeriod;
        $classes = $entityManager->getRepository(SchoolClassPeriod::class)->findBy(['school' => $this->currentSchool, 'period' => $period]);
        foreach ($classes as $class) {
            $classesIds[] = $class->getId();
        }
        $subjectGroups = [];
        if (!empty($classesIds) && $classesIds)
            $subjectGroups = $entityManager->getRepository(\App\Entity\SubjectGroup::class)->findBy(['school' => $this->currentSchool, 'period' => $period]);
        $all_teachers = $entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->andWhere('u.school = :school')
            ->setParameter('role', '%ROLE_TEACHER%')
            ->setParameter('school', $this->currentSchool)
            ->getQuery()
            ->getResult();
        return $this->render('study_subject/_subject_classes.html.twig', [
            'classSubjects' => $classSubjects,
            'subject' => $subject,
            'all_subject_groups' => $subjectGroups,
            'all_teachers' => $all_teachers,
        ]);
    }

    #[Route('/study/{id}/edit', name: 'app_study_edit', methods: ['GET', 'POST'])]
    public function editStudySubject(
        Request $request,
        EntityManagerInterface $entityManager,
        StudySubject $subject,
        SluggerInterface $slugger,
        StringHelper $stringHelper,
        \App\Service\OperationLogger $operationLogger,
        SessionInterface $session
    ): Response {
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        if ($request->isMethod('POST')) {
            $name = $request->request->get('name');
            $slug = $slugger->slug(strtolower($name));
            $page = $request->query->getInt('page', 1);
            if ($name && $slug) {
                $subject->setName($stringHelper->toUpperNoAccent($name));
                $subject->setSlug($slug);
                try {
                    $entityManager->flush();

                    $operationLogger->log(
                        'MODIFICATION DE MATIÈRE ' . $subject->getName(), // Type d'opération
                        'SUCCESS',
                        'StudySubject',
                        $subject->getId(),
                        null,
                        ['name' => $subject->getName(), 'slug' => $subject->getSlug(), 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()] // Données additionnelles
                    );

                    $this->addFlash('success', 'Matière modifiée avec succès.');
                    return $this->redirectToRoute('app_study_show', ['page' => $page]);
                } catch (\Exception $e) {
                    if (!$this->entityManager->isOpen()) {
                        $this->entityManager = $doctrine->resetManager();
                    }
                    // Log de l'erreur
                    $operationLogger->log(
                        'MODIFICATION DE MATIÈRE ' . $subject->getName(),
                        'ERROR',
                        'StudySubject',
                        null,
                        $e->getMessage(),
                        ['name' => $name, 'slug' => $slug, 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()]
                    );
                    $this->addFlash('danger', 'Erreur lors de la modification de la matière : ' . $e->getMessage());
                }
            } else {
                $this->addFlash('danger', 'Veuillez renseigner tous les champs obligatoires.');
            }
        }

        return $this->render('study_subject/edit.html.twig', [
            'subject' => $subject,
        ]);
    }

    #[Route('/study/{id}/delete', name: 'app_study_delete', methods: ['POST'])]
    public function deleteStudySubject(
        Request $request,
        EntityManagerInterface $entityManager,
        StudySubject $subject,
        SessionInterface $session,
        \App\Service\OperationLogger $operationLogger,
        \Doctrine\Persistence\ManagerRegistry $doctrine
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $subject->getId(), $request->request->get('_token'))) {
            $this->session = $session;
            $this->entityManager = $entityManager;
            $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
            $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
            $entityManager->remove($subject);
            try {
                $entityManager->flush();
                // Log l'opération de suppression de matière
                $operationLogger->log(
                    'SUPPRESSION DE MATIÈRE ' . $subject->getName(), // Type d'opération
                    'SUCCESS',  // Statut
                    'StudySubject',
                    $subject->getId(),
                    null,
                    ['name' => $subject->getName(), 'slug' => $subject->getSlug(), 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()]
                );
                $this->addFlash('success', 'Matière supprimée avec succès.');
            } catch (\Exception $e) {
                if (!$this->entityManager->isOpen()) {
                    $this->entityManager = $doctrine->resetManager();
                }
                // Log de l'erreur
                $operationLogger->log(
                    'SUPPRESSION DE MATIÈRE ' . $subject->getName(),
                    'ERROR',
                    'StudySubject',
                    null,
                    $e->getMessage(),
                    ['name' => $subject->getName(), 'slug' => $subject->getSlug(), 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()]
                );
                $this->addFlash('danger', 'Erreur lors de la suppression de la matière : ' . $e->getMessage());
            }
        } else {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
        }

        return $this->redirectToRoute('app_study_show', ['id']);
    }



    #[Route('/affectation/{id}/update', name: 'app_update_affectation', methods: ['POST'])]
    public function updateAffectation(
        Request $request,
        EntityManagerInterface $entityManager,
        SchoolClassSubject $affectation,
        \App\Service\OperationLogger $operationLogger,
        SessionInterface $session
    ): Response {
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $coefficient = $request->request->get('coefficient', 1);
        $groupId = $request->request->get('group_id');
        $teacher_id = $request->request->get('class_teacher');
        $teacher = null;
        if ($teacher_id)
            $teacher = $entityManager->getRepository(\App\Entity\User::class)->find($teacher_id);

        $affectation->setCoefficient((int)$coefficient);

        if ($groupId) {
            $group = $entityManager->getRepository(\App\Entity\SubjectGroup::class)->find($groupId);
            $affectation->setGroup($group);
        } else {
            $affectation->setGroup(null);
        }
        if ($teacher) {
            $affectation->setTeacher($teacher);
        } else {
            $affectation->setTeacher(null);
        }

        try {
            $entityManager->flush();
            // Log l'opération de modification de l'affectation
            $operationLogger->log(
                'MODIFICATION DE L\'AFFECTATION',
                'SUCCESS',
                'SchoolClassSubject',
                $affectation->getId(),
                null,
                ['coefficient' => $affectation->getCoefficient(), 'group' => $groupId, 'teacher' => $teacher ? $teacher->getUsername() : null, 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()]
            );
            $this->addFlash('success', 'Affectation modifiée avec succès.');
            // Retourne une réponse JSON pour une requête AJAX

            return $this->json([
                'status' => 'success',
                'message' => 'Affectation modifiée avec succès.',
            ]);
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // Log de l'erreur
            $operationLogger->log(
                'MODIFICATION DE L\'AFFECTATION',
                'ERROR',
                'SchoolClassSubject',
                $affectation->getId(),
                $e->getMessage(),
                ['coefficient' => $affectation->getCoefficient(), 'group' => $groupId, 'teacher' => $teacher ? $teacher->getUsername() : null, 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()]
            );
            $this->addFlash('danger', 'Erreur lors de la modification de l\'affectation : ' . $e->getMessage());
            // Retourne une réponse JSON pour une requête AJAX
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la modification de l\'affectation : ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/affectation/{id}/delete', name: 'app_delete_affectation', methods: ['POST'])]
    public function deleteAffectation(
        Request $request,
        EntityManagerInterface $entityManager,
        SchoolClassSubject $affectation,
        \App\Service\OperationLogger $operationLogger,
        SessionInterface $session
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $affectation->getId(), $request->request->get('_token'))) {
            $this->session = $session;
            $this->entityManager = $entityManager;
            $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
            $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
            $entityManager->remove($affectation);
            try {
                $entityManager->flush();
                // Log l'opération de suppression de l'affectation
                $operationLogger->log(
                    'SUPPRESSION DE L\'AFFECTATION',
                    'SUCCESS',
                    'SchoolClassSubject',
                    $affectation->getId(),
                    null,
                    ['school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()]
                );
                $this->addFlash('success', 'Affectation supprimée avec succès.');
                // Retourne une réponse JSON pour une requête AJAX

                return $this->json([
                    'status' => 'success',
                    'message' => 'Affectation supprimée avec succès.',
                ]);
            } catch (\Exception $e) {
                if (!$this->entityManager->isOpen()) {
                    $this->entityManager = $doctrine->resetManager();
                }
                // Log de l'erreur
                $operationLogger->log(
                    'SUPPRESSION DE L\'AFFECTATION',
                    'ERROR',
                    'SchoolClassSubject',
                    $affectation->getId(),
                    $e->getMessage(),
                    ['school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()]
                );
                $this->addFlash('danger', 'Erreur lors de la suppression de l\'affectation : ' . $e->getMessage());
                // Retourne une réponse JSON pour une requête AJAX
                return $this->json([
                    'status' => 'error',
                    'message' => 'Erreur lors de la suppression de l\'affectation : ' . $e->getMessage(),
                ], 500);
            }
        }

        return $this->json([
            'status' => 'error',
            'message' => 'Jeton CSRF invalide.',
        ], 400);
    }

    #[Route('/subject-groupe/new', name: 'app_school_class_subject_groupe_new', methods: ['POST'])]
    public function newSubjectGroup(
        SessionInterface $session,
        Request $request,
        EntityManagerInterface $entityManager,
        \App\Service\OperationLogger $operationLogger,
        StringHelper $stringHelper,
        \Doctrine\Persistence\ManagerRegistry $doctrine
    ): Response {
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        if ($request->isMethod('POST')) {
            $description = $request->request->get('description');
            $period = $this->currentPeriod;
            $school = $this->currentSchool;
            $numOrder = $request->request->get('numOrder', 0);


            if ($description && $period && $school) {
                $group = new SubjectGroup();
                $group->setDescription($stringHelper->toUpperNoAccent($description));
                $group->setPeriod($period);
                $group->setSchool($school);
                $group->setPosOrder((int)$numOrder);

                $entityManager->persist($group);


                try {
                    $entityManager->flush();
                    $this->addFlash('success', 'Groupe de matières créé avec succès.');
                    // Log l'opération de création de groupe de matières
                    $operationLogger->log(
                        'CREATION DE GROUPE DE MATIÈRES ' . $group->getDescription(),
                        'SUCCESS',
                        'SubjectGroup',
                        $group->getId(),
                        null,
                        ['description' => $group->getDescription(), 'period' => $period->getName(), 'school' => $school->getName()]
                    );
                } catch (\Exception $e) {
                    if (!$this->entityManager->isOpen()) {
                        $this->entityManager = $doctrine->resetManager();
                    }
                    // Log de l'erreur
                    $operationLogger->log(
                        'CREATION DE GROUPE DE MATIÈRES ' . $description,
                        'ERROR',
                        'SubjectGroup',
                        null,
                        $e->getMessage(),
                        ['description' => $description, 'period' => $period->getName(), 'school' => $school->getName()]
                    );
                    // cas de l'erreur 1062
                    if ($e->getCode() === 1062) {
                        return $this->json([
                            'status' => 'error',
                            'message' => 'Le groupe de matières existe déjà.',
                        ], 400);
                    } else {
                        return $this->json([
                            'status' => 'error',
                            'message' => 'Erreur lors de la création du groupe de matières : ' . $e->getMessage(),
                        ], 400);
                    }
                }
            } else {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Veuillez remplir tous les champs obligatoires.',
                ], 400);
            }
        }

        return $this->redirectToRoute('app_study_show');
    }
    #[Route('/study/manage-group', name: 'app_study_manage_group', methods: ['GET'])]
    public function manageStudySubjectGroup(SessionInterface $session, EntityManagerInterface $entityManager): Response
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $groups = $entityManager->getRepository(SubjectGroup::class)->findBy(['period' => $this->currentPeriod, 'school' => $this->currentSchool]);
        $groupIds = [];
        foreach ($groups as $group) {
            $groupIds[] = $group->getId();
        }
        $schoolClassSubjects = $entityManager->getRepository(SchoolClassSubject::class)->findBy(['group' => $groupIds]);
        return $this->render('study_subject/_subject_classes_groups.html.twig', [
            'groups' => $groups,
            'schoolClassSubjects' => $schoolClassSubjects
        ]);
    }
    #[Route('/subject-group/{id}/update', name: 'app_update_affectation_groupe', methods: ['POST'])]
    public function updateSubjectGroup(
        Request $request,
        EntityManagerInterface $entityManager,
        SubjectGroup $subjectGroup,
        \App\Service\OperationLogger $operationLogger,
        SessionInterface $session
    ): Response {
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));

        $posOrder = $request->request->get('posOrder');
        $groupDescription = strtoupper($request->request->get('groupDescription'));
        if ($posOrder !== null && $groupDescription !== null) {
            $subjectGroup->setPosOrder((int)$posOrder);
            $subjectGroup->setDescription($groupDescription);
            $entityManager->persist($subjectGroup);
            // Enregistre les modifications
            try {
                $entityManager->flush();

                // Log l'opération
                $operationLogger->log(
                    'MODIFICATION DE LA CONFIGURATION DU GROUPE DE MATIÈRES',
                    'SUCCESS',
                    'SubjectGroup',
                    $subjectGroup->getId(),
                    null,
                    [
                        'posOrder' => $subjectGroup->getPosOrder(),
                        'groupDescription' => $subjectGroup->getDescription(),
                        'school' => $this->currentSchool->getName(),
                        'period' => $this->currentPeriod->getName()
                    ]
                );

                return $this->json(['status' => 'success', 'message' => 'Ordre modifié.']);
            } catch (\Exception $e) {
                if (!$this->entityManager->isOpen()) {
                    $this->entityManager = $doctrine->resetManager();
                }
                // Log de l'erreur
                $operationLogger->log(
                    'MODIFICATION DE LA CONFIGURATION DU GROUPE DE MATIÈRES',
                    'ERROR',
                    'SubjectGroup',
                    $subjectGroup->getId(),
                    $e->getMessage(),
                    [
                        'posOrder' => $posOrder,
                        'groupDescription' => $groupDescription,
                        'school' => $this->currentSchool->getName(),
                        'period' => $this->currentPeriod->getName()
                    ]
                );
                return $this->json(['status' => 'error', 'message' => 'Erreur lors de la modification : ' . $e->getMessage()], 500);
            }
        }
        // log de l'erreur
        $operationLogger->log(
            'MODIFICATION DE LA CONFIGURATION DU GROUPE DE MATIÈRES',
            'ERROR',
            'SubjectGroup',
            $subjectGroup->getId(),
            null,
            [
                'error' => 'Valeur manquante pour posOrder ou groupDescription',
                'posOrder' => $posOrder,
                'groupDescription' => $groupDescription,
                'school' => $this->currentSchool->getName(),
                'period' => $this->currentPeriod->getName()
            ]
        );
        return $this->json(['status' => 'error', 'message' => 'Valeur manquante.'], 400);
    }
    #[Route('/subject-group/{id}/delete', name: 'app_delete_affectation_groupe', methods: ['POST'])]
    public function deleteAffectationGroupe(
        Request $request,
        EntityManagerInterface $entityManager,
        \App\Entity\SubjectGroup $subjectGroup,
        \App\Service\OperationLogger $operationLogger,
        SessionInterface $session,
        \Doctrine\Persistence\ManagerRegistry $doctrine
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $subjectGroup->getId(), $request->request->get('_token'))) {
            $id = $subjectGroup->getId();
            $desc = $subjectGroup->getDescription();
            $this->session = $session;
            $this->entityManager = $entityManager;
            $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
            $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
            // Vérifie si le groupe de matières est utilisé dans des SchoolClassSubject
            $schoolClassSubjects = $entityManager->getRepository(SchoolClassSubject::class)->findBy(['group' => $subjectGroup]);
            if (count($schoolClassSubjects) > 0) {
                // on set à null le groupe dans les SchoolClassSubject
                foreach ($schoolClassSubjects as $scs) {
                    $scs->setGroup(null);
                    $entityManager->persist($scs);
                }
                try {
                    $entityManager->flush();
                    // Log l'opération de suppression du groupe de matières
                    $operationLogger->log(
                        'SUPPRESSION DU GROUPE DE MATIÈRES DANS LES AFFECTATIONS',
                        'SUCCESS',
                        'SchoolClassSubject',
                        null,
                        null,
                        ['description' => $desc, 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()]
                    );
                } catch (\Exception $e) {
                    if (!$this->entityManager->isOpen()) {
                        $this->entityManager = $doctrine->resetManager();
                    }
                    // Log de l'erreur
                    $operationLogger->log(
                        'SUPPRESSION DU GROUPE DE MATIÈRES DANS LES AFFECTATIONS',
                        'ERROR',
                        'SchoolClassSubject',
                        null,
                        $e->getMessage(),
                        ['description' => $desc, 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()]
                    );
                    return $this->json([
                        'status' => 'error',
                        'message' => 'Erreur lors de la suppression du groupe de matières dans les affectations : ' . $e->getMessage(),
                    ], 500);
                }
            }
            // Supprime le groupe de matières
            $desc = $subjectGroup->getDescription();
            $id = $subjectGroup->getId();
            // Supprime le groupe de matières

            $entityManager->remove($subjectGroup);
            try {
                $entityManager->flush();

                // Log l'opération de suppression
                $operationLogger->log(
                    'SUPPRESSION DE LA CONFIGURATION DU GROUPE DE MATIÈRES',
                    'SUCCESS',
                    'SubjectGroup',
                    $id,
                    null,
                    ['description' => $desc, 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()]
                );

                return $this->json([
                    'status' => 'success',
                    'message' => 'Groupe supprimé avec succès.',
                ]);
            } catch (\Exception $e) {
                if (!$this->entityManager->isOpen()) {
                    $this->entityManager = $doctrine->resetManager();
                }
                // Log de l'erreur
                $operationLogger->log(
                    'SUPPRESSION DE LA CONFIGURATION DU GROUPE DE MATIÈRES',
                    'ERROR',
                    'SubjectGroup',
                    $id,
                    $e->getMessage(),
                    ['description' => $desc, 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()]
                );
                return $this->json([
                    'status' => 'error',
                    'message' => 'Erreur lors de la suppression du groupe : ' . $e->getMessage(),
                ], 500);
            }
        }

        // Log de l'erreur
        $operationLogger->log(
            'SUPPRESSION DE LA CONFIGURATION DU GROUPE DE MATIÈRES',
            'ERROR',
            'SubjectGroup',
            $subjectGroup->getId(),
            null,
            [
                'error' => 'Jeton CSRF invalide.',
                'school' => $this->currentSchool->getName(),
                'period' => $this->currentPeriod->getName()
            ]
        );

        return $this->json([
            'status' => 'error',
            'message' => 'Jeton CSRF invalide.',
        ], 400);
    }

    #[Route('/remove-class-from-group', name: 'app_remove_class_from_group', methods: ['POST'])]
    public function removeClassFromGroup(
        SessionInterface $session,
        Request $request,
        EntityManagerInterface $entityManager,
        SchoolClassSubjectRepository $schoolClassSubjectRepo,
        \App\Service\OperationLogger $operationLogger,
        \Doctrine\Persistence\ManagerRegistry $doctrine
    ): JsonResponse {
        $classId = $request->request->get('classId');
        $groupId = $request->request->get('groupId');
        $token = $request->request->get('_token');
        $this->session = $session;
        $this->entityManager = $entityManager;
        // Récupère l'école et la période actuelles
        $this->currentSchool = $entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));

        if (!$this->isCsrfTokenValid('remove_class_from_group', $token)) {
            return new JsonResponse(['error' => 'Token CSRF invalide.'], 400);
        }

        if (!$classId || !$groupId) {
            return new JsonResponse(['error' => 'Paramètres manquants.'], 400);
        }

        $class = $entityManager->getRepository(SchoolClassPeriod::class)->findBy(['classOccurence' => $classId, 'school' => $this->currentSchool, 'period' => $this->currentPeriod]);

        // Récupère tous les SchoolClassSubject concernés
        $subjects = $schoolClassSubjectRepo->findBy([
            'schoolClassPeriod' => $class,
            'group' => $groupId,
        ]);

        foreach ($subjects as $subject) {
            $subject->setGroup(null);
            $entityManager->persist($subject);
        }

        try {
            $entityManager->flush();
            // Log l'opération de suppression de la matière
            $operationLogger->log(
                'SUPPRESSION DU GROUPE DE MATIÈRES DANS LA CLASSE',
                'SUCCESS',
                'SchoolClassSubject',
                null,
                null,
                ['class' => $classId, 'group' => $groupId, 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()]
            );
        } catch (\Exception $e) {
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = $doctrine->resetManager();
            }
            // Log de l'erreur
            $operationLogger->log(
                'SUPPRESSION DU GROUPE DE MATIÈRES DANS LA CLASSE',
                'ERROR',
                'SchoolClassSubject',
                null,
                $e->getMessage(),
                ['class' => $classId, 'group' => $groupId, 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()]
            );
            // Retourne une réponse JSON avec l'erreur
            return new JsonResponse(['error' => 'Erreur lors de la suppression de la matière.' . $e->getMessage()], 500);
        }

        return new JsonResponse(['success' => true]);
    }
}
