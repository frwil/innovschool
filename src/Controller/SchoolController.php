<?php

namespace App\Controller;

use App\Contract\UserRoleEnum;
use App\Entity\School;
use App\Entity\SchoolSection;
use App\Entity\StudyLevel;
use App\Entity\User;
use App\Form\SchoolType;
use App\Entity\SchoolPeriod;
use App\Repository\SchoolRepository;
use App\Service\EmailGeneratorService;
use App\Service\ImageOptimizer;
use App\Service\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\ImageOptimizerService;
use App\Service\LicenseManager;
use Doctrine\ORM\EntityManager;
use App\Service\OperationLogger;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

#[IsGranted("ROLE_SUPER_ADMIN")]
final class SchoolController extends AbstractController
{
    private School $currentSchool;
    private SchoolSection $currentSection;
    private SessionInterface $session;
    private EntityManagerInterface $entityManager;
    private SchoolPeriod $currentPeriod;

    public function __construct(
        EntityManagerInterface $entityManager,
    ) {
        $this->entityManager = $entityManager;
        }
    
    #[Route('/school',name: 'app_school_index', methods: ['GET'])]
    public function index(
        SchoolRepository $schoolRepository,
        \App\Repository\AppLicenseRepository $appLicenseRepo
    ): Response
    {
        $schools= $schoolRepository->findAll();
        $schoolsIds= [];
        foreach ($schools as $school) {
            $schoolsIds[] = $school->getId();
        }
        $licenses = $appLicenseRepo->findBy(['school' => $schoolsIds,'enabled' => true]);
        
        
        return $this->render('school/index.html.twig', [
            'schools' => $schoolRepository->findAll(),
            'licenses' => $licenses,
        ]);
    }

    #[Route('/new', name: 'app_school_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        EmailGeneratorService $emailGenerator,
        ImageOptimizer $imageOptimizer,
        LicenseManager $licenseManager,
        OperationLogger $operationLogger,
        SessionInterface $session
    ): Response {
        $school = new School();

        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        if ($request->isMethod('POST')) {
            $school->setName($request->request->get('name'));
            $school->setAcronym($request->request->get('acronym'));
            $school->setAddress($request->request->get('address'));
            $school->setContactName($request->request->get('contactName'));
            $school->setContactEmail($request->request->get('contactEmail'));
            $school->setContactPhone($request->request->get('contactPhone'));
            $school->setCreatedAt(new \DateTime());

            // Gestion du logo (optionnel)
            $logoFile = $request->files->get('logo');
            if ($logoFile) {
                // Supprimer l'ancien logo si présent (cas rare à la création, mais utile si tu réutilises ce code)
                if ($school->getLogo()) {
                    $oldLogoPath = $this->getParameter('kernel.project_dir') . '/public/uploads/logos/' . $school->getLogo();
                    if (file_exists($oldLogoPath)) {
                        unlink($oldLogoPath);
                    }
                }

                $logoName = uniqid() . '.' . $logoFile->guessExtension();
                $logoFile->move($this->getParameter('kernel.project_dir') . '/public/uploads/logos', $logoName);

                // Optimisation de l'image
                $imageOptimizer->optimize(
                    $this->getParameter('kernel.project_dir') . '/public/uploads/logos/' . $logoName
                );

                $school->setLogo($logoName);
            }

            $entityManager->persist($school);

            // Création d'une licence d'évaluation de 30 jours pour l'école
            $license = new \App\Entity\AppLicense();
            $license->setSchool($school);
            $license->setLicenceStartAt(new \DateTime());
            $license->setLicenceAmount(0);
            $license->setEnabled(true); // La licence d'évaluation est active par défaut
            $license->setLicenseType('trial'); // Si tu as ce champ
            $license->setLicenseHash(hash('sha256', uniqid($school->getAcronym(), true)));
            $school->setLicenseHash($licenseManager->generateLicenseHash($license));
            $school->setSchoolNumber(substr(MD5($request->request->get('acronym')), 1, 8));

            $entityManager->persist($license);

            $entityManager->flush();

            // Log l'opération de création d'école
            $operationLogger->log(
                'CREATION LICENSE', // Type d'opération
                'SUCCESS',  // Statut
                'School',   // Nom de l'entité
                $school->getId(), // ID de l'entité
                null,       // Pas d'erreur
                [
                    'name' => $school->getName(),
                    'acronym' => $school->getAcronym(),
                    'contactEmail' => $school->getContactEmail(),
                    'school' => $school->getName(),
                    'period' => $this->currentPeriod->getName()
                ]
            );

            return $this->json([
                'status' => 'success',
                'message' => 'École créée avec succès.',
                'schoolId' => $school->getId(),
            ]);
        }

        return $this->render('school/new.html.twig', [
            'school' => $school
        ]);
    }

   
    #[Route('/{id}/edit', name: 'app_school_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, School $school, EntityManagerInterface $entityManager,ImageOptimizer $imageOptimizer, \App\Service\OperationLogger $operationLogger,SessionInterface $session): Response
    {
        $this->session=$session;
        $this->entityManager=$entityManager;
        $this->currentPeriod=$this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $this->currentSchool=$this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        if ($request->isMethod('POST')) {
            $school->setName($request->request->get('name'));
            $school->setAcronym($request->request->get('acronym'));
            $school->setAddress($request->request->get('address'));
            $school->setContactName($request->request->get('contactName'));
            $school->setContactEmail($request->request->get('contactEmail'));
            $school->setContactPhone($request->request->get('contactPhone'));

            // Gestion du logo (optionnel)
            $logoFile = $request->files->get('logo');
            if ($logoFile) {
                // Supprimer l'ancien logo si présent
                if ($school->getLogo()) {
                    $oldLogoPath = $this->getParameter('kernel.project_dir') . '/public/uploads/logos/' . $school->getLogo();
                    if (file_exists($oldLogoPath)) {
                        unlink($oldLogoPath);
                    }
                }

                $logoName = uniqid() . '.' . $logoFile->guessExtension();
                $logoFile->move($this->getParameter('kernel.project_dir') . '/public/uploads/logos', $logoName);

                // Optimisation de l'image
                $imageOptimizer->optimize(
                    $this->getParameter('kernel.project_dir') . '/public/uploads/logos/' . $logoName
                );

                $school->setLogo($logoName);
            }

            $entityManager->flush();

            // Log l'opération de modification d'école
            $operationLogger->log(
                'MODIFICATION', // Type d'opération
                'SUCCESS',      // Statut
                'School',       // Nom de l'entité
                $school->getId(), // ID de l'entité
                null,           // Pas d'erreur
                [
                    'name' => $school->getName(),
                    'acronym' => $school->getAcronym(),
                    'contactEmail' => $school->getContactEmail(),
                    'school' => $school->getName(),
                    'period' => $this->currentPeriod->getName()
                ]
            );

            return $this->json([
                'status' => 'success',
                'message' => 'École mise à jour avec succès.',
            ]);
        }
        $schoolId=$request->get('id');
        $school= $entityManager->getRepository(School::class)->find($schoolId);

        return $this->render('school/edit.html.twig', [
            'school' => $school,
        ]);
    }

     #[Route('/{id}/delete', name: 'app_school_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        School $school,
        EntityManagerInterface $entityManager,
        OperationLogger $operationLogger // <-- Ajoute ceci
    ): Response
    {
        if ($this->isCsrfTokenValid('delete' . $school->getId(), $request->getPayload()->getString('_token'))) {
            $schoolId = $school->getId();
            $schoolName = $school->getName();

            $entityManager->remove($school);
            $entityManager->flush();

            // Log l'opération de suppression d'école
            $operationLogger->log(
                'SUPPRESSION D\'ÉCOLE '.$schoolName,         // Type d'opération
                'SUCCESS',             // Statut
                'School',              // Nom de l'entité
                $schoolId,             // ID de l'entité
                null,                  // Pas d'erreur
                ['name' => $schoolName, 'school' => $this->currentSchool->getName(), 'period' => $this->currentPeriod->getName()]
            );
        }

        return $this->redirectToRoute('app_school_index', [], Response::HTTP_SEE_OTHER);
    } 
}
