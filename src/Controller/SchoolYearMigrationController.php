<?php

namespace App\Controller;

use App\Entity\School;
use App\Entity\SchoolClassPeriod;
use App\Entity\SchoolPeriod;
use App\Repository\SchoolClassPeriodRepository;
use App\Repository\SchoolPeriodRepository;
use App\Service\SchoolYearMigrationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/year-migration')]
final class SchoolYearMigrationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SchoolYearMigrationService $migrationService,
    ) {}

    #[Route('', name: 'app_year_migration_index', methods: ['GET'])]
    public function index(
        SchoolPeriodRepository $periodRepo,
        SessionInterface $session
    ): Response {
        $school = $this->getSchool($session);
        if (!$school) {
            $this->addFlash('danger', 'Aucune école sélectionnée.');
            return $this->redirectToRoute('app_school_period_index');
        }

        $periods = $periodRepo->findAll();

        return $this->render('school_year_migration/index.html.twig', [
            'periods' => $periods,
            'school' => $school,
        ]);
    }

    /**
     * Preview: show student eligibility per class + class mapping form.
     */
    #[Route('/preview', name: 'app_year_migration_preview', methods: ['POST'])]
    public function preview(
        Request $request,
        SchoolPeriodRepository $periodRepo,
        SchoolClassPeriodRepository $scpRepo,
        SessionInterface $session
    ): Response {
        $school = $this->getSchool($session);
        if (!$school) {
            $this->addFlash('danger', 'Aucune école sélectionnée.');
            return $this->redirectToRoute('app_year_migration_index');
        }

        $sourcePeriodId = $request->request->get('source_period');
        $targetPeriodId = $request->request->get('target_period');
        $passingGrade   = (float) ($request->request->get('passing_grade', 10));
        $options = [
            'subject_groups' => (bool) $request->request->get('opt_subject_groups', true),
            'classes'        => (bool) $request->request->get('opt_classes', true),
            'subjects'       => (bool) $request->request->get('opt_subjects', true),
            'modules'        => (bool) $request->request->get('opt_modules', true),
            'payment_modals' => (bool) $request->request->get('opt_payment_modals', true),
        ];

        $sourcePeriod = $periodRepo->find($sourcePeriodId);
        $targetPeriod = $periodRepo->find($targetPeriodId);

        if (!$sourcePeriod || !$targetPeriod) {
            $this->addFlash('danger', 'Périodes invalides.');
            return $this->redirectToRoute('app_year_migration_index');
        }

        if ($sourcePeriod === $targetPeriod) {
            $this->addFlash('danger', 'La période source et cible doivent être différentes.');
            return $this->redirectToRoute('app_year_migration_index');
        }

        $preview = $this->migrationService->previewStudentMigration($school, $sourcePeriod, $passingGrade);

        // Target period classes for the mapping dropdowns
        $targetClassOptions = $this->migrationService->getTargetClassOptions($school, $targetPeriod);

        return $this->render('school_year_migration/preview.html.twig', [
            'school'            => $school,
            'sourcePeriod'      => $sourcePeriod,
            'targetPeriod'      => $targetPeriod,
            'passingGrade'      => $passingGrade,
            'options'           => $options,
            'preview'           => $preview,
            'targetClassOptions' => $targetClassOptions,
        ]);
    }

    /**
     * Execute the full migration (configuration + students).
     */
    #[Route('/execute', name: 'app_year_migration_execute', methods: ['POST'])]
    public function execute(
        Request $request,
        SchoolPeriodRepository $periodRepo,
        SessionInterface $session
    ): Response {
        if (!$this->isCsrfTokenValid('year_migration', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_year_migration_index');
        }

        $school = $this->getSchool($session);
        if (!$school) {
            $this->addFlash('danger', 'Aucune école sélectionnée.');
            return $this->redirectToRoute('app_year_migration_index');
        }

        $sourcePeriodId = $request->request->get('source_period');
        $targetPeriodId = $request->request->get('target_period');
        $passingGrade   = (float) ($request->request->get('passing_grade', 10));
        $options = [
            'subject_groups' => (bool) $request->request->get('opt_subject_groups'),
            'classes'        => (bool) $request->request->get('opt_classes'),
            'subjects'       => (bool) $request->request->get('opt_subjects'),
            'modules'        => (bool) $request->request->get('opt_modules'),
            'payment_modals' => (bool) $request->request->get('opt_payment_modals'),
        ];

        // classMapping: [sourceSchoolClassPeriodId => targetSchoolClassPeriodId]
        $classMapping = $request->request->all('class_mapping');

        $sourcePeriod = $periodRepo->find($sourcePeriodId);
        $targetPeriod = $periodRepo->find($targetPeriodId);

        if (!$sourcePeriod || !$targetPeriod || $sourcePeriod === $targetPeriod) {
            $this->addFlash('danger', 'Périodes invalides.');
            return $this->redirectToRoute('app_year_migration_index');
        }

        try {
            $configSummary = $this->migrationService->migrateConfiguration(
                $school,
                $sourcePeriod,
                $targetPeriod,
                $options
            );

            $studentStats = $this->migrationService->migrateStudents(
                $school,
                $sourcePeriod,
                $targetPeriod,
                $passingGrade,
                $classMapping
            );
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erreur lors de la migration : ' . $e->getMessage());
            return $this->redirectToRoute('app_year_migration_index');
        }

        return $this->render('school_year_migration/result.html.twig', [
            'school'        => $school,
            'sourcePeriod'  => $sourcePeriod,
            'targetPeriod'  => $targetPeriod,
            'configSummary' => $configSummary,
            'studentStats'  => $studentStats,
            'passingGrade'  => $passingGrade,
        ]);
    }

    private function getSchool(SessionInterface $session): ?School
    {
        $schoolId = $session->get('school_id');
        return $schoolId ? $this->em->getRepository(School::class)->find($schoolId) : null;
    }
}
