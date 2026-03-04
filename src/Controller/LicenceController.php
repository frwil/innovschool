<?php

namespace App\Controller;

use App\Repository\SchoolRepository;
use App\Service\LicenseManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\AppLicenseRepository;
use App\Entity\School;
use App\Entity\SchoolPeriod;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class LicenceController extends AbstractController
{
    private School $currentSchool;
    private SchoolPeriod $currentPeriod;
    private SessionInterface $session;
    
    public function __construct(
        SchoolRepository $schoolRepository,
        EntityManagerInterface $entityManager,
        LicenseManager $licenseManager,
        AppLicenseRepository $appLicenseRepository
    ) {
        }
    
    #[Route('/renew-license', name: 'app_renew_license', methods: ['POST'])]
    public function renewLicense(
        Request $request,
        SchoolRepository $schoolRepo,
        EntityManagerInterface $em,
        LicenseManager $licenseManager,
        AppLicenseRepository $licenseRepo
        
    ) {
        $schoolId = $request->getSession()->get('school_id');
        $school = $schoolRepo->find($schoolId);
        $license = $licenseRepo->findOneBy(['school' => $school, 'enabled' => true]);

        $token = $request->request->get('license_token');

        // Ici, tu dois valider le token (ex: via LicenseManager)
        // Exemple : si le token est valide, mets à jour la licence
        if ($licenseManager->validateAndApplyToken($license, $token,$school,$em)) {
            //$em->flush();
            $this->addFlash('success', 'Licence renouvelée avec succès.');
            return $this->redirectToRoute('app_home');
        }

        $this->addFlash('danger', 'Le token de licence est invalide.');
        return $this->redirectToRoute('app_choose_school');
    }
}
