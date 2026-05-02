<?php

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Bundle\SecurityBundle\Security;
use App\Repository\SchoolRepository;

class SchoolSelectionListener
{
    private $router;
    private $security;
    private $schoolRepository;

    public function __construct(RouterInterface $router, Security $security, SchoolRepository $schoolRepository)
    {
        $this->router = $router;
        $this->security = $security;
        $this->schoolRepository = $schoolRepository;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $request = $event->getRequest();
        $user = $this->security->getUser();

        // Ignore si l'utilisateur n'est pas connecté ou n'est pas un objet User
        if (!$user || !is_object($user)) {
            return;
        }

        
        $route = $request->attributes->get('_route');
        $excludedRoutes = [
            'app_choose_school',
            'app_logout',
            'app_create_school',
            'period_create',
            'app_register',
            'api_login_check',
            'app_school_show',
            'app_force_password_reset',
            // Ajoute ici toutes les routes publiques ou de sécurité
        ];

        if (
            !$event->isMainRequest()
            || in_array($route, $excludedRoutes, true)
        ) {
            return;
        }
        // Si resetPassword est à true, déconnecte et redirige vers la page de réinitialisation
        if (method_exists($user, 'isResetPassword') && $user->isResetPassword()) {
            $session = $request->getSession();
            $session->invalidate(); // Déconnecte l'utilisateur
            $event->setResponse(new RedirectResponse($this->router->generate('app_force_password_reset', ['id' => $user->getId()])));
            return;
        }


        $session = $request->getSession();
        $schoolId = $session->get('school_id');
        $periodId = $session->get('period_id');
        $school = $schoolId ? $this->schoolRepository->find($schoolId) : null;

        // Ne set l'école que si elle existe
        if ($school) {
            $user->setSchool($school);
        }
        if (
            method_exists($user, 'getSchool') &&
            (!$school || !$schoolId || !$periodId)
        ) {
            $event->setResponse(new RedirectResponse($this->router->generate('app_choose_school')));
        }
    }
}
