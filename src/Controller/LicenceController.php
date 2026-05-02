<?php

namespace App\Controller;

use App\Entity\School;
use App\Repository\AppLicenseRepository;
use App\Repository\SchoolRepository;
use App\Service\LicenseManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/licence')]
class LicenceController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private LicenseManager $licenseManager,
        private AppLicenseRepository $licenseRepo,
        private SchoolRepository $schoolRepo,
    ) {}

    /** Dashboard : état de la licence + historique des paiements */
    #[Route('', name: 'app_licence_index', methods: ['GET'])]
    public function index(SessionInterface $session): Response
    {
        $school   = $this->getSchool($session);
        $license  = $school ? $this->licenseRepo->findOneBy(['school' => $school, 'enabled' => true]) : null;

        return $this->render('license/index.html.twig', [
            'school'        => $school,
            'license'       => $license,
            'endDate'       => $license ? $this->licenseManager->getEndDate($license) : null,
            'remainingDays' => $license ? $this->licenseManager->getRemainingDays($license) : 0,
            'isExpired'     => $license ? $this->licenseManager->isExpired($license) : true,
            'payments'      => $license ? $license->getPayments()->toArray() : [],
        ]);
    }

    /** Renouvellement par token */
    #[Route('/renew', name: 'app_renew_license', methods: ['POST'])]
    public function renewLicense(Request $request, SessionInterface $session): Response
    {
        if (!$this->isCsrfTokenValid('renew_license', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_licence_index');
        }

        $school  = $this->getSchool($session);
        $license = $school ? $this->licenseRepo->findOneBy(['school' => $school, 'enabled' => true]) : null;

        if (!$school || !$license) {
            $this->addFlash('danger', 'Aucune licence active trouvée.');
            return $this->redirectToRoute('app_licence_index');
        }

        $token = $request->request->get('license_token');

        if ($this->licenseManager->validateAndApplyToken($license, $token, $school, $this->em)) {
            $this->addFlash('success', 'Licence renouvelée avec succès.');
            return $this->redirectToRoute('app_licence_index');
        }

        $this->addFlash('danger', 'Token de licence invalide. Vérifiez le code fourni.');
        return $this->redirectToRoute('app_licence_index');
    }

    /** Enregistrement d'un paiement */
    #[Route('/payment/add', name: 'app_licence_add_payment', methods: ['POST'])]
    public function addPayment(Request $request, SessionInterface $session): Response
    {
        if (!$this->isCsrfTokenValid('add_payment', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_licence_index');
        }

        $school  = $this->getSchool($session);
        $license = $school ? $this->licenseRepo->findOneBy(['school' => $school, 'enabled' => true]) : null;

        if (!$license) {
            $this->addFlash('danger', 'Aucune licence active trouvée.');
            return $this->redirectToRoute('app_licence_index');
        }

        $amount        = (int) $request->request->get('amount', 0);
        $paymentMethod = $request->request->get('payment_method', 'cash');
        $dateStr       = $request->request->get('payment_date');
        $date          = $dateStr ? new \DateTime($dateStr) : new \DateTime();

        if ($amount <= 0) {
            $this->addFlash('danger', 'Le montant doit être supérieur à zéro.');
            return $this->redirectToRoute('app_licence_index');
        }

        $this->licenseManager->recordPayment($license, $amount, $paymentMethod, $this->em, $date);
        $this->addFlash('success', 'Paiement enregistré avec succès.');

        return $this->redirectToRoute('app_licence_index');
    }

    private function getSchool(SessionInterface $session): ?School
    {
        $id = $session->get('school_id');
        return $id ? $this->schoolRepo->find($id) : null;
    }
}
