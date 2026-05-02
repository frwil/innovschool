<?php

namespace App\Controller;

use App\Entity\MigrationLog;
use App\Entity\School;
use App\Entity\SchoolClassPeriod;
use App\Entity\SchoolPeriod;
use App\Repository\MigrationLogRepository;
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

    // ─────────────────────────────────────────────────────────────────────────
    // INDEX + PREVIEW + EXECUTE (inchangés sauf execute qui appelle executeMigration)
    // ─────────────────────────────────────────────────────────────────────────

    #[Route('', name: 'app_year_migration_index', methods: ['GET'])]
    public function index(
        SchoolPeriodRepository $periodRepo,
        MigrationLogRepository $logRepo,
        SessionInterface $session
    ): Response {
        $school = $this->getSchool($session);
        if (!$school) {
            $this->addFlash('danger', 'Aucune école sélectionnée.');
            return $this->redirectToRoute('app_school_period_index');
        }

        return $this->render('school_year_migration/index.html.twig', [
            'periods'  => $periodRepo->findAll(),
            'school'   => $school,
            'logs'     => $logRepo->findBySchool($school),
        ]);
    }

    #[Route('/preview', name: 'app_year_migration_preview', methods: ['POST'])]
    public function preview(
        Request $request,
        SchoolPeriodRepository $periodRepo,
        SessionInterface $session
    ): Response {
        $school = $this->getSchool($session);
        if (!$school) {
            $this->addFlash('danger', 'Aucune école sélectionnée.');
            return $this->redirectToRoute('app_year_migration_index');
        }

        $sourcePeriod = $periodRepo->find($request->request->get('source_period'));
        $targetPeriod = $periodRepo->find($request->request->get('target_period'));
        $passingGrade = (float) $request->request->get('passing_grade', 10);
        $options      = $this->extractOptions($request);

        if (!$sourcePeriod || !$targetPeriod || $sourcePeriod === $targetPeriod) {
            $this->addFlash('danger', 'Périodes invalides ou identiques.');
            return $this->redirectToRoute('app_year_migration_index');
        }

        return $this->render('school_year_migration/preview.html.twig', [
            'school'             => $school,
            'sourcePeriod'       => $sourcePeriod,
            'targetPeriod'       => $targetPeriod,
            'passingGrade'       => $passingGrade,
            'options'            => $options,
            'preview'            => $this->migrationService->previewStudentMigration($school, $sourcePeriod, $passingGrade),
            'targetClassOptions' => $this->migrationService->getTargetClassOptions($school, $targetPeriod),
        ]);
    }

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

        $sourcePeriod = $periodRepo->find($request->request->get('source_period'));
        $targetPeriod = $periodRepo->find($request->request->get('target_period'));
        $passingGrade = (float) $request->request->get('passing_grade', 10);
        $options      = $this->extractOptions($request);
        $classMapping = $request->request->all('class_mapping');

        if (!$sourcePeriod || !$targetPeriod || $sourcePeriod === $targetPeriod) {
            $this->addFlash('danger', 'Périodes invalides.');
            return $this->redirectToRoute('app_year_migration_index');
        }

        try {
            $user = $this->getUser();
            $log  = $this->migrationService->executeMigration(
                $school,
                $sourcePeriod,
                $targetPeriod,
                $passingGrade,
                $options,
                $classMapping,
                $user ? ($user->getUserIdentifier()) : 'inconnu'
            );
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erreur lors de la migration : ' . $e->getMessage());
            return $this->redirectToRoute('app_year_migration_index');
        }

        return $this->render('school_year_migration/result.html.twig', [
            'school'        => $school,
            'sourcePeriod'  => $sourcePeriod,
            'targetPeriod'  => $targetPeriod,
            'configSummary' => $log->getConfigSummary(),
            'studentStats'  => $log->getStudentStats(),
            'passingGrade'  => $passingGrade,
            'log'           => $log,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GESTION D'UNE MIGRATION (annuler ou corriger)
    // ─────────────────────────────────────────────────────────────────────────

    #[Route('/{id}/manage', name: 'app_year_migration_manage', methods: ['GET'])]
    public function manage(MigrationLog $log, SessionInterface $session): Response
    {
        $school = $this->getSchool($session);
        if (!$school || $log->getSchool() !== $school) {
            throw $this->createAccessDeniedException();
        }

        $state              = $this->migrationService->checkMigrationState($log);
        $targetClassOptions = $this->migrationService->getTargetClassOptions($school, $log->getTargetPeriod());

        return $this->render('school_year_migration/manage.html.twig', [
            'log'                => $log,
            'state'              => $state,
            'targetClassOptions' => $targetClassOptions,
            'school'             => $school,
        ]);
    }

    #[Route('/{id}/cancel', name: 'app_year_migration_cancel', methods: ['POST'])]
    public function cancel(MigrationLog $log, Request $request, SessionInterface $session): Response
    {
        if (!$this->isCsrfTokenValid('cancel_migration_' . $log->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_year_migration_manage', ['id' => $log->getId()]);
        }

        $school = $this->getSchool($session);
        if (!$school || $log->getSchool() !== $school) {
            throw $this->createAccessDeniedException();
        }

        if ($log->getStatus() !== 'executed') {
            $this->addFlash('warning', 'Cette migration a déjà été annulée ou corrigée.');
            return $this->redirectToRoute('app_year_migration_index');
        }

        try {
            $this->migrationService->cancelMigration($log);
            $this->addFlash('success', 'Migration annulée avec succès. Toutes les données créées ont été supprimées.');
        } catch (\LogicException $e) {
            $this->addFlash('danger', $e->getMessage());
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erreur lors de l\'annulation : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_year_migration_index');
    }

    #[Route('/{id}/correct-preview', name: 'app_year_migration_correct_preview', methods: ['POST'])]
    public function correctPreview(MigrationLog $log, Request $request, SessionInterface $session): Response
    {
        $school = $this->getSchool($session);
        if (!$school || $log->getSchool() !== $school) {
            throw $this->createAccessDeniedException();
        }

        $newPassingGrade    = (float) $request->request->get('new_passing_grade', $log->getPassingGrade());
        $changes            = $this->migrationService->previewCorrection($log, $newPassingGrade);
        $targetClassOptions = $this->migrationService->getTargetClassOptions($school, $log->getTargetPeriod());
        $sourceClassOptions = $this->migrationService->getTargetClassOptions($school, $log->getSourcePeriod());

        return $this->render('school_year_migration/correct_preview.html.twig', [
            'log'                => $log,
            'newPassingGrade'    => $newPassingGrade,
            'changes'            => $changes,
            'targetClassOptions' => $targetClassOptions,
            'sourceClassOptions' => $sourceClassOptions,
            'school'             => $school,
        ]);
    }

    #[Route('/{id}/correct', name: 'app_year_migration_correct', methods: ['POST'])]
    public function correct(MigrationLog $log, Request $request, SessionInterface $session): Response
    {
        if (!$this->isCsrfTokenValid('correct_migration_' . $log->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_year_migration_manage', ['id' => $log->getId()]);
        }

        $school = $this->getSchool($session);
        if (!$school || $log->getSchool() !== $school) {
            throw $this->createAccessDeniedException();
        }

        $newPassingGrade = (float) $request->request->get('new_passing_grade', $log->getPassingGrade());
        $classMapping    = $request->request->all('class_mapping');

        try {
            $applied = $this->migrationService->applyCorrection($log, $newPassingGrade, $classMapping);
            $this->addFlash('success', sprintf(
                'Correction appliquée : %d promu(s), %d rétrogradé(s), %d ajouté(s).',
                $applied['promoted'],
                $applied['demoted'],
                $applied['added']
            ));
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erreur lors de la correction : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_year_migration_manage', ['id' => $log->getId()]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function getSchool(SessionInterface $session): ?School
    {
        $id = $session->get('school_id');
        return $id ? $this->em->getRepository(School::class)->find($id) : null;
    }

    private function extractOptions(Request $request): array
    {
        return [
            'subject_groups' => (bool) $request->request->get('opt_subject_groups'),
            'classes'        => (bool) $request->request->get('opt_classes'),
            'subjects'       => (bool) $request->request->get('opt_subjects'),
            'modules'        => (bool) $request->request->get('opt_modules'),
            'payment_modals' => (bool) $request->request->get('opt_payment_modals'),
        ];
    }
}
