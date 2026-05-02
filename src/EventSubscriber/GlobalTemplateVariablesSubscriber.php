<?php

namespace App\EventSubscriber;

use App\Entity\AppLicense;
use App\Entity\School;
use App\Entity\SchoolPeriod;
use App\Repository\AppLicenseRepository;
use App\Repository\SchoolPeriodRepository;
use App\Repository\SchoolRepository;
use App\Service\LicenseManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Twig\Environment;

class GlobalTemplateVariablesSubscriber implements EventSubscriberInterface
{
    /**
     * Routes exclues de la vérification de licence.
     * Inclut toutes les routes liées à l'authentification, à la sélection
     * d'école et aux pages de gestion de licence elles-mêmes.
     */
    private const EXCLUDED_ROUTES = [
        // Auth
        'app_login',
        'app_logout',
        'app_register',
        'app_force_password_reset',
        // Sélection d'école / période
        'app_choose_school',
        'app_create_school',
        'app_school_show',
        'app_show_school',
        'period_create',
        // Gestion de licence (évite les boucles de redirection)
        'app_licence_index',
        'app_renew_license',
        'app_licence_add_payment',
        // API
        'api_login_check',
    ];

    public function __construct(
        private Environment            $twig,
        private SchoolRepository       $schoolRepo,
        private SchoolPeriodRepository $periodRepo,
        private LicenseManager         $licenseManager,
        private Security               $security,
        private AppLicenseRepository   $appLicenseRepo,
        private EntityManagerInterface $entityManager,
    ) {}

    public function onKernelController(ControllerEvent $event): void
    {
        // Ignorer les sous-requêtes et les routes de debug
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route   = (string) $request->attributes->get('_route', '');

        // Routes internes Symfony (_wdt, _profiler…) et routes exclues
        if (str_starts_with($route, '_') || in_array($route, self::EXCLUDED_ROUTES, true)) {
            return;
        }

        $session = $request->getSession();

        // Pas d'école en session → SchoolSelectionListener s'en charge
        if (!$session->has('school_id')) {
            return;
        }

        $school = $this->schoolRepo->find($session->get('school_id'));
        if (!$school) {
            return;
        }

        // ── Vérification de la licence ────────────────────────────────────────
        $license = $this->appLicenseRepo->findOneBy(['school' => $school, 'enabled' => true]);

        // Durée 0 = licence illimitée (mode développement / démo)
        if ($license && $license->getLicenceDuration() === 0) {
            $this->touchLastAccess($school);
            return;
        }

        try {
            if (!$license) {
                throw new \RuntimeException('Aucune licence active trouvée pour cet établissement.');
            }

            $this->licenseManager->checkLicense($school, $license);

            // Mise à jour du lastAccesAt (anti-retour arrière horloge)
            $this->touchLastAccess($school);

        } catch (\RuntimeException $e) {
            $this->renderExpiredPage($event, $e->getMessage(), $school, $license);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Persiste la date du dernier accès pour détecter les retours arrière d'horloge.
     */
    private function touchLastAccess(School $school): void
    {
        $school->setLastAccesAt(new \DateTime());
        $this->entityManager->persist($school);
        $this->entityManager->flush();
    }

    /**
     * Remplace le contrôleur courant par la page "licence expirée".
     * Ne modifie PAS la licence en base — le superadmin doit utiliser
     * le token fourni par l'administrateur système (bin/console license:generate-token).
     */
    private function renderExpiredPage(
        ControllerEvent $event,
        string          $message,
        School          $school,
        ?AppLicense     $license
    ): void {
        $user         = $this->security->getUser();
        $isSuperAdmin = $user
            && method_exists($user, 'getRoles')
            && in_array('ROLE_SUPER_ADMIN', $user->getRoles());

        $template = $isSuperAdmin
            ? 'license/expired_superadmin.html.twig'
            : 'license/expired.html.twig';

        $response = new Response(
            $this->twig->render($template, [
                'message'        => $message,
                'school'         => $school,
                'licenceStartAt' => $license?->getLicenceStartAt() ?? new \DateTime(),
            ]),
            Response::HTTP_FORBIDDEN
        );

        $event->setController(static fn() => $response);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.controller' => 'onKernelController',
        ];
    }
}
