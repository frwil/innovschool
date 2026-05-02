<<<<<<< HEAD
<?php

namespace App\Controller;

use App\Entity\SchoolClassPeriod;
use App\Entity\SchoolClassSubjectGroup;
use App\Entity\SectionCategorySubjectGroup;
use App\Form\SchoolClassSubjectGroupType;
use App\Repository\SchoolClassSubjectGroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Entity\School;
use App\Entity\SchoolPeriod;
use App\Service\OperationLogger;

#[Route('/schoolclass-subject-group')]
final class SchoolClassSubjectGroupController extends AbstractController
{
    private School $currentSchool;
    private SchoolPeriod $currentPeriod;
    private SessionInterface $session;
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
         }
    
    #[Route('/{id}/school-class', name: 'app_school_class_subject_group_index', methods: ['GET', 'POST'])]
    public function index(
        SchoolClassPeriod $schoolClassPeriod,
        SchoolClassSubjectGroupRepository $schoolClassSubjectGroupRepository,
        Request $request,
        EntityManagerInterface $entityManager,
        // Add the OperationLoggerInterface as a dependency
        OperationLogger $operationLogger,
    ): Response {
        $form = null;
        $user = $this->getUser();
        $school = $schoolClassPeriod->getSchool();

        if($request->query->get('section_subjet_group_id') && $request->query->get('school_class_id')){
            /** @var SectionCategorySubjectGroup */
            $sObjectGroup = $entityManager->getRepository(SectionCategorySubjectGroup::class)->find($request->query->get('section_subjet_group_id'));
            $schoolClassSubjectGroup = (new SchoolClassSubjectGroup())
                ->setSchoolClass($schoolClassPeriod)
                ->setSchool($school)
                ->setName($sObjectGroup->getName())
                ->setPosition(1)
                ->setSectionCategorySubjectGroup($sObjectGroup);

            $form = $this->createForm(SchoolClassSubjectGroupType::class, $schoolClassSubjectGroup,[
                'action' => $this->generateUrl('app_school_class_subject_group_index', [
                    'id' => $schoolClassPeriod->getId(),
                    'section_subjet_group_id' => $sObjectGroup->getId(),
                    'school_class_id' => $schoolClassPeriod->getId(),
                ])
            ]);

            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $entityManager->persist($schoolClassSubjectGroup);
                try{
                    $entityManager->flush();
                    $this->addFlash('success', 'Subject group created successfully.');
                    // Log the operation
                    $operationLogger->log(
                        'CREATE',
                        'SchoolClassSubjectGroup',
                        $schoolClassSubjectGroup->getId(),
                        null,
                        ['name' => $schoolClassSubjectGroup->getName(), 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()]
                    );
                    return $this->redirectToRoute('app_school_class_subject_group_index', ['id' => $schoolClassPeriod->getId()]);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'An error occurred while saving the subject group: ' . $e->getMessage());
                }   
            }
            
        }

        return $this->render('school_class_subject_group/index.html.twig', [
            'school_class_subject_groups' => $schoolClassPeriod->getSchoolClassSubjectGroups(),
            'form' => $form ? $form->createView() : null,
            'school_class' => $schoolClassPeriod,
        ]);
    }

    #[Route('/new/{id}/school-class', name: 'app_school_class_subject_group_new', methods: ['GET', 'POST'])]
    public function new(SchoolClassPeriod $schoolClassPeriod,Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $school = $schoolClassPeriod->getSchool();
        $schoolClassSubjectGroup = (new SchoolClassSubjectGroup())
            ->setSchoolClass($schoolClassPeriod)
            ->setSchool($school);
        $form = $this->createForm(SchoolClassSubjectGroupType::class, $schoolClassSubjectGroup);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($schoolClassSubjectGroup);
            $entityManager->flush();

            return $this->redirectToRoute('app_school_class_subject_group_new', ['id' => $schoolClassPeriod->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('school_class_subject_group/new.html.twig', [
            'school_class_subject_group' => $schoolClassSubjectGroup,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_school_class_subject_group_show', methods: ['GET'])]
    public function show(SchoolClassSubjectGroup $schoolClassSubjectGroup): Response
    {
        return $this->render('school_class_subject_group/show.html.twig', [
            'school_class_subject_group' => $schoolClassSubjectGroup,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_school_class_subject_group_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, SchoolClassSubjectGroup $schoolClassSubjectGroup, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SchoolClassSubjectGroupType::class, $schoolClassSubjectGroup);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_school_class_subject_group_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('school_class_subject_group/edit.html.twig', [
            'school_class_subject_group' => $schoolClassSubjectGroup,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_school_class_subject_group_delete', methods: ['POST'])]
    public function delete(Request $request, SchoolClassSubjectGroup $schoolClassSubjectGroup, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $schoolClassSubjectGroup->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($schoolClassSubjectGroup);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_school_class_subject_group_index', [], Response::HTTP_SEE_OTHER);
    }
}
=======
<?php

namespace App\Controller;

use App\Entity\SchoolClassPeriod;
use App\Entity\SchoolClassSubjectGroup;
use App\Entity\SectionCategorySubjectGroup;
use App\Form\SchoolClassSubjectGroupType;
use App\Repository\SchoolClassSubjectGroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Entity\School;
use App\Entity\SchoolPeriod;

#[Route('/schoolclass-subject-group')]
final class SchoolClassSubjectGroupController extends AbstractController
{
    private School $currentSchool;
    private SchoolPeriod $currentPeriod;
    private SessionInterface $session;
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
         }
    
    #[Route('/{id}/school-class', name: 'app_school_class_subject_group_index', methods: ['GET', 'POST'])]
    public function index(
        SchoolClassPeriod $schoolClassPeriod,
        SchoolClassSubjectGroupRepository $schoolClassSubjectGroupRepository,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $form = null;
        $user = $this->getUser();
        $school = $schoolClassPeriod->getSchool();

        if($request->query->get('section_subjet_group_id') && $request->query->get('school_class_id')){
            /** @var SectionCategorySubjectGroup */
            $sObjectGroup = $entityManager->getRepository(SectionCategorySubjectGroup::class)->find($request->query->get('section_subjet_group_id'));
            $schoolClassSubjectGroup = (new SchoolClassSubjectGroup())
                ->setSchoolClass($schoolClassPeriod)
                ->setSchool($school)
                ->setName($sObjectGroup->getName())
                ->setPosition(1)
                ->setSectionCategorySubjectGroup($sObjectGroup);

            $form = $this->createForm(SchoolClassSubjectGroupType::class, $schoolClassSubjectGroup,[
                'action' => $this->generateUrl('app_school_class_subject_group_index', [
                    'id' => $schoolClassPeriod->getId(),
                    'section_subjet_group_id' => $sObjectGroup->getId(),
                    'school_class_id' => $schoolClassPeriod->getId(),
                ])
            ]);

            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $entityManager->persist($schoolClassSubjectGroup);
                $entityManager->flush();
                return $this->redirectToRoute('app_school_class_subject_group_index', ['id' => $schoolClassPeriod->getId()]);
            }
            
        }

        return $this->render('school_class_subject_group/index.html.twig', [
            'school_class_subject_groups' => $schoolClassPeriod->getSchoolClassSubjectGroups(),
            'form' => $form ? $form->createView() : null,
            'school_class' => $schoolClassPeriod,
        ]);
    }

    #[Route('/new/{id}/school-class', name: 'app_school_class_subject_group_new', methods: ['GET', 'POST'])]
    public function new(SchoolClassPeriod $schoolClassPeriod,Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $school = $schoolClassPeriod->getSchool();
        $schoolClassSubjectGroup = (new SchoolClassSubjectGroup())
            ->setSchoolClass($schoolClassPeriod)
            ->setSchool($school);
        $form = $this->createForm(SchoolClassSubjectGroupType::class, $schoolClassSubjectGroup);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($schoolClassSubjectGroup);
            $entityManager->flush();

            return $this->redirectToRoute('app_school_class_subject_group_new', ['id' => $schoolClassPeriod->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('school_class_subject_group/new.html.twig', [
            'school_class_subject_group' => $schoolClassSubjectGroup,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_school_class_subject_group_show', methods: ['GET'])]
    public function show(SchoolClassSubjectGroup $schoolClassSubjectGroup): Response
    {
        return $this->render('school_class_subject_group/show.html.twig', [
            'school_class_subject_group' => $schoolClassSubjectGroup,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_school_class_subject_group_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, SchoolClassSubjectGroup $schoolClassSubjectGroup, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SchoolClassSubjectGroupType::class, $schoolClassSubjectGroup);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_school_class_subject_group_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('school_class_subject_group/edit.html.twig', [
            'school_class_subject_group' => $schoolClassSubjectGroup,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_school_class_subject_group_delete', methods: ['POST'])]
    public function delete(Request $request, SchoolClassSubjectGroup $schoolClassSubjectGroup, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $schoolClassSubjectGroup->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($schoolClassSubjectGroup);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_school_class_subject_group_index', [], Response::HTTP_SEE_OTHER);
    }
}
>>>>>>> claude/naughty-rubin-200ad9
