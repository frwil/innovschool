<?php

namespace App\Controller;

use App\Entity\Module;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Entity\School;
use App\Entity\SchoolPeriod;

#[Route('/admin/modules', name: 'app_module_')]
class ModuleController extends AbstractController
{
    private SessionInterface $session;
    private EntityManagerInterface $entityManager;
    private School $currentSchool;
    private SchoolPeriod $currentPeriod;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }
    #[Route('/', name: 'index')]
    public function index(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $modules = $em->getRepository(Module::class)->findAll();
        return $this->render('module/index.html.twig', [
            'modules' => $modules,
        ]);
    }

    #[Route('/toggle/{name}', name: 'toggle', methods: ['POST'])]
    public function toggle(string $name, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $module = $em->getRepository(Module::class)->find($name);
        if (!$module) {
            throw $this->createNotFoundException('Module non trouvé');
        }
        $module->setEnabled(!$module->isEnabled());
        $em->flush();

        return $this->redirectToRoute('app_module_index');
    }
}
