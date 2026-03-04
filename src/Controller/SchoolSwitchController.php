<?php

namespace App\Controller;

use App\Entity\School;
use App\Repository\SchoolRepository;
use App\Repository\SchoolPeriodRepository; // Ajout de l'importation
use App\Repository\StudiesTypeRepository; // Add this at the top if not already present
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\ImageOptimizer; // à ajouter en haut
use App\Service\OperationLogger; // <-- Ajout ici
use App\Entity\SchoolStudyType;
use App\Service\LicenseManager; // <-- Ajout ici
use App\Entity\AppLicense;


class SchoolSwitchController extends AbstractController
{
    #[Route('/choose-school', name: 'app_choose_school')]
    public function chooseSchool(
        SchoolRepository $schoolRepo,
        SchoolPeriodRepository $schoolPeriodRepository,
        StudiesTypeRepository $studiesTypeRepo, // <-- Add this
        Request $request,
        SessionInterface $session,
        EntityManagerInterface $em
    ) {
        if (in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles())) {
            // Si l'utilisateur est super admin, on récupère toutes les écoles
            // Sinon, on récupère uniquement les écoles accessibles à l'utilisateur
            // (c'est-à-dire celles qui ne sont pas marquées comme admin-only)
            $schools = $schoolRepo->findBy(['activated' => true]);
        } else {
            $schools = $schoolRepo->findBy(['adminOnly' => false]);
        }
        $periods = $schoolPeriodRepository->findAll(); // Récupération des périodes scolaires
        $studytypes = $studiesTypeRepo->findAll();

        if ($request->isMethod('POST')) {
            $schoolId = $request->request->get('school_id');
            $periodId = $request->request->get('period_id');
            $session->set('school_id', $schoolId);
            $session->set('period_id', $periodId);
            $periodes = $schoolPeriodRepository->findBy(['enabled' => true]);
            foreach ($periodes as $period) {
                $period->setEnabled(false);
                $em->persist($period);
            }
            $period = $schoolPeriodRepository->find($periodId);
            if ($period) {
                $period->setEnabled(true);
                $em->persist($period);
            }
            // Met à jour lastAccesAt
            $school = $schoolRepo->find($schoolId);
            if ($school) {
                $school->setLastAccesAt(new \DateTime());
                $em->flush();
            }
            return $this->redirectToRoute('app_homepage');
        }


        return $this->render('school/choose.html.twig', [
            'schools' => $schools,
            'periods' => $periods, // Passage des périodes à la vue
            'studytypes' => $studytypes,
        ]);
    }

    #[Route('/create-school', name: 'app_create_school', methods: ['POST'])]
    public function createSchool(
        Request $request,
        SchoolRepository $schoolRepo,
        EntityManagerInterface $em,
        ImageOptimizer $imageOptimizer,
        OperationLogger $operationLogger,
        StudiesTypeRepository $studiesTypeRepo,
        LicenseManager $licenseManager // <-- Ajout ici
    ) {
        // Récupération des données du formulaire
        $name = $request->request->get('name');
        $acronym = $request->request->get('acronym');
        $address = $request->request->get('address');
        $contactName = $request->request->get('contactName');
        $contactEmail = $request->request->get('contactEmail');
        $contactPhone = $request->request->get('contactPhone');
        $logoFile = $request->files->get('logo');

        // Validation minimale
        if (!$name || !$acronym || !$address || !$contactName || !$contactEmail || !$contactPhone) {
            return $this->json(['success' => false, 'message' => 'Tous les champs sont obligatoires.']);
        }

        $school = new School();
        $school->setName($name);
        $school->setAcronym($acronym);
        $school->setAddress($address);
        $school->setContactName($contactName);
        $school->setContactEmail($contactEmail);
        $school->setContactPhone($contactPhone);
        $school->setCreatedAt(new \DateTime());

        $license = new \App\Entity\AppLicense();
        $license->setSchool($school);
        $license->setLicenceStartAt(new \DateTime());
        $license->setLicenceAmount(0);
        $license->setEnabled(true); // La licence d'évaluation est active par défaut
        $license->setLicenseType('trial'); // Si tu as ce champ
        $license->setLicenseHash(hash('sha256', uniqid($school->getAcronym(), true)));
        $school->setLicenseHash($licenseManager->generateLicenseHash($license));


        //$licenseHash = $licenseManager->generateLicenseHash($school);
        //$school->setLicenseHash($licenseHash);

        // Gestion du logo (optionnel)
        if ($logoFile) {
            // Supprimer l'ancien logo si l'école existe déjà et a un logo
            if ($school->getLogo()) {
                $oldLogoPath = $this->getParameter('kernel.project_dir') . '/public/uploads/logos/' . $school->getLogo();
                if (file_exists($oldLogoPath)) {
                    unlink($oldLogoPath);
                }
            }
            $logoName = uniqid() . '.' . $logoFile->guessExtension();
            $logoFile->move($this->getParameter('kernel.project_dir') . '/public/uploads/logos', $logoName);

            // Optimisation de l'image
            $imageOptimizer->optimize($this->getParameter('kernel.project_dir') . '/public/uploads/logos/' . $logoName);

            $school->setLogo($logoName);
        }

        // Génération d'un numéro unique pour l'école
        do {
            $schoolNumber = $acronym . random_int(100000, 999999);
            $existing = $schoolRepo->findOneBy(['schoolNumber' => $schoolNumber]);
        } while ($existing);

        $school->setSchoolNumber($schoolNumber);

        $em->persist($school);
        $em->flush();

        // Enregistrement des types d'enseignement
        $studytypeIds = $request->request->all('studytypeid');
        foreach ($studytypeIds as $studytypeId) {
            $studiesType = $studiesTypeRepo->find($studytypeId);
            if ($studiesType) {
                $schoolStudyType = new SchoolStudyType();
                $schoolStudyType->setSchool($school);
                $schoolStudyType->setStudiesType($studiesType);
                $em->persist($schoolStudyType);
            }
        }
        $em->flush();

        // Création d'une licence d'évaluation de 30 jours pour l'école
        $license = new AppLicense();
        $license->setSchool($school);
        $license->setLicenceStartAt(new \DateTime());
        $license->setLicenceDuration(30);
        $license->setLicenceAmount(100000);
        $license->setEnabled(true); // La licence d'évaluation est active par défaut

        // Génération d'un hash unique pour la licence
        $license->setLicenseHash(hash('sha256', uniqid('', true)));

        $em->persist($license);
        $em->flush();

        // Enregistrement du log APRÈS toutes les opérations
        $operationLogger->log(
            'Création d\'une école',
            'success',
            'School',
            $school->getId(),
            null,
            [
                'schoolNumber' => $school->getSchoolNumber(),
                'name' => $school->getName(),
                'studytypes' => $studytypeIds
            ]
        );

        return $this->json(['success' => true, 'school_id' => $school->getId()]);
    }

    #[Route('/create-period', name: 'period_create', methods: ['POST'])]
    public function createPeriod(
        Request $request,
        EntityManagerInterface $em,
        OperationLogger $operationLogger // <-- Ajout ici
    ) {
        $name = $request->request->get('name');
        $enabled = $request->request->get('enabled', 0);

        if (!$name) {
            return $this->json(['success' => false, 'message' => "Le nom de l'année scolaire est obligatoire."]);
        }

        $period = new \App\Entity\SchoolPeriod();
        $period->setName($name);
        $period->setEnabled((bool)$enabled);

        $em->persist($period);
        $em->flush();

        $operationLogger->log(
            'Création d\'une année scolaire',
            'success',
            'SchoolPeriod',
            $period->getId(),
            null,
            ['name' => $period->getName()]
        );

        return $this->json(['success' => true, 'period_id' => $period->getId()]);
    }
}
