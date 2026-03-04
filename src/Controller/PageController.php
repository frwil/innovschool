<?php

namespace App\Controller;

use App\Contract\UserRoleEnum;
use App\Service\AdminHomePageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\School;
use App\Entity\SchoolPeriod;

final class PageController extends AbstractController
{
    private School $currentSchool;
    private SchoolPeriod $currentPeriod;
    private SessionInterface $session;
    private EntityManagerInterface $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager
    ) {
        $this->entityManager = $entityManager;
    }

    #[Route('/', name: 'app_homepage')]
    public function homeAdmin(
        AdminHomePageService $adminHomePageService
    ): Response {

        if ($this->isGranted(UserRoleEnum::ADMIN) && !$this->isGranted(UserRoleEnum::SUPER_ADMIN)) {
            return $this->redirectToRoute('app_home_school_admin');
        }

        if ($this->isGranted(UserRoleEnum::TEACHER) && !$this->isGranted(UserRoleEnum::SUPER_ADMIN)) {
            return $this->redirectToRoute('app_home_teacher');
        }

        if ($this->isGranted(UserRoleEnum::EMPLOYEE) && !$this->isGranted(UserRoleEnum::SUPER_ADMIN)) {
            return $this->redirectToRoute('app_home_secretary');
        }

        $model = $adminHomePageService->getModel();
        return $this->render('page/index.html.twig', [
            'model' => $model,
        ]);
    }

    #[Route('/school-admin', name: 'app_home_school_admin')]
    public function homeSchoolAdmin(
        AdminHomePageService $adminHomePageService
    ): Response {
        $model = $adminHomePageService->getModel();
        return $this->render('page/school.admin.html.twig', [
            'model' => $model,
        ]);
    }

    #[Route('/school-teacher', name: 'app_home_teacher')]
    public function homeTeacher(): Response
    {
        return $this->render('page/school.teacher.html.twig', [
            'controller_name' => 'PageController',
        ]);
    }

    #[Route('/school-secretary', name: 'app_home_secretary')]
    public function homeSecretary(): Response
    {
        return $this->render('page/school.secretary.html.twig', [
            'controller_name' => 'PageController',
        ]);
    }
}
