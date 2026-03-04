<?php

namespace App\EventSubscriber;

use App\Repository\SchoolRepository;
use App\Repository\SchoolPeriodRepository;
use App\Service\LicenseManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Environment;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\AppLicenseRepository;
use App\Entity\School;
use App\Entity\SchoolPeriod;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\AppLicense;

class GlobalTemplateVariablesSubscriber implements EventSubscriberInterface
{
    private $twig;
    private $schoolRepo;
    private $periodRepo;
    private $licenseManager;
    private $security;
    private $appLicenseRepo;
    private School $currentSchool;
    private SchoolPeriod $currentPeriod;
    private EntityManagerInterface $entityManager;

    public function __construct(
        Environment $twig,
        SchoolRepository $schoolRepo,
        SchoolPeriodRepository $periodRepo,
        LicenseManager $licenseManager,
        Security $security,
        AppLicenseRepository $licenseRepo,
        EntityManagerInterface $entityManager
    ) {
        $this->twig = $twig;
        $this->schoolRepo = $schoolRepo;
        $this->periodRepo = $periodRepo;
        $this->licenseManager = $licenseManager;
        $this->security = $security;
        $this->appLicenseRepo = $licenseRepo;
        $this->entityManager = $entityManager;
    }

    public function onKernelController(ControllerEvent $event)
    {
        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        $excludedRoutes = [
            'app_login',
            'app_logout',
            'app_register',
            'app_show_school',
            // Ajoute ici toutes les routes publiques ou de sécurité
        ];
        if (in_array($route, $excludedRoutes, true)) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->getSession();

        $this->currentSchool = new School();
        $this->currentPeriod = new SchoolPeriod();

        if ($session->has('school_id')) {
            $this->currentSchool = $this->schoolRepo->find($session->get('school_id'));
        }
        if ($session->has('period_id')) {
            $this->currentPeriod = $this->periodRepo->find($session->get('period_id'));
        }



        /* $this->twig->addGlobal('schools', $schools);
        $this->twig->addGlobal('periods', $periods);
        $this->twig->addGlobal('currentSchool', $this->currentSchool);
        $this->twig->addGlobal('currentPeriod', $currentPeriod); */
        if ($request->isMethod('POST') && $request->attributes->get('_route')=='app_renew_license') {
            $license = $this->appLicenseRepo->findOneBy(['school' => $this->currentSchool, 'enabled' => true]);
            $token = $request->request->get('license_token');
            $this->licenseManager->validateAndApplyToken($license, $token,$this->currentSchool,$this->entityManager);
        }
        // Vérification de la licence
        $isLicenseExpired = false;
        try {
            if ($this->currentSchool && $session->has('school_id')) {
                $license = $this->appLicenseRepo->findOneBy(['school' => $this->currentSchool, 'enabled' => true]);
                try {
                    if ($license) {
                        if ($license->getLicenceDuration() == 0) {
                            $isLicenseExpired = false;
                        } else {
                            try {
                                $this->licenseManager->checkLicense($this->currentSchool, $license);
                            } catch (\Exception $e) {
                                $isLicenseExpired = true;
                                throw $e;
                            }
                        }
                    } else {
                        $isLicenseExpired = true;
                        throw new \Exception("Licence invalide ou non initialisée.");
                    }
                } catch (\Exception $e) {
                    $isLicenseExpired = true;
                    throw new \Exception("Licence invalide ou expirée. Veuillez contacter l'administrateur.");
                }
            }
        } catch (\Exception $e) {
            $isLicenseExpired = true;
            $user = $this->security->getUser();
            $isSuperAdmin = $user && method_exists($user, 'getRoles') && in_array('ROLE_SUPER_ADMIN', $user->getRoles());

            $licenceStartAt = new \DateTime();
            $duration=540;

            // Récupérer ou créer la licence
            $license = $this->appLicenseRepo->findOneBy(['school' => $this->currentSchool, 'enabled' => true]);
            if (!$license) {
                $license = new AppLicense();
                $license->setSchool($this->currentSchool);
                $license->setEnabled(true);
            }

            // Sauvegarder l'ancien hash pour générer le token
            $oldLicenseHash = $license->getLicenseHash();

            // Mettre à jour les données de la licence
            $license->setLicenceStartAt($licenceStartAt);
            $license->setLicenceDuration($duration);
            $license->setLicenceAmount(0);

            $this->entityManager->persist($license);
            $this->entityManager->flush();

            // ⚠️ Utiliser la MÊME méthode que dans validateAndApplyToken
            $token = $this->licenseManager->generateActivationToken($licenceStartAt, $duration, $oldLicenseHash);

            $response = new Response(
                $this->twig->render(
                    $isSuperAdmin
                        ? 'license/expired_superadmin.html.twig'
                        : 'license/expired.html.twig',
                    [
                        'message' => $e->getMessage(),
                        'school' => $this->currentSchool,
                        'isLicenseExpired' => $isLicenseExpired,
                        'token' => $isSuperAdmin ? $token : '',
                        'licenceStartAt' => $licenceStartAt->format('Y-m-d H:i:s'),
                    ]
                )
            );
            $event->setController(function () use ($response) {
                return $response;
            });
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            'kernel.controller' => 'onKernelController',
        ];
    }
}
