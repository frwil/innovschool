<?php

namespace App\Controller;

use App\Entity\MigrationLog;
use App\Entity\School;
use App\Entity\SchoolClassPeriod;
use App\Entity\SchoolPeriod;
use App\Entity\StudentClass;
use App\Repository\MigrationLogRepository;
use App\Repository\SchoolClassPeriodRepository;
use App\Repository\SchoolPeriodRepository;
use App\Service\SchoolYearMigrationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
    // WIZARD PAR ÉTAPES (start → wizard → step… → finish → result)
    // ─────────────────────────────────────────────────────────────────────────

    #[Route('/start', name: 'app_year_migration_start', methods: ['POST'])]
    public function start(
        Request $request,
        SchoolPeriodRepository $periodRepo,
        SessionInterface $session
    ): Response {
        $this->denyAccessUnlessGranted('perm', 'year_migration.execute');

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

        if (!$sourcePeriod || !$targetPeriod || $sourcePeriod === $targetPeriod) {
            $this->addFlash('danger', 'Périodes invalides ou identiques.');
            return $this->redirectToRoute('app_year_migration_index');
        }

        // Brouillon déjà en cours pour ces périodes ? La config soumise sera ignorée.
        $resumed = (bool) $this->em->getRepository(MigrationLog::class)->findOneBy([
            'school'       => $school,
            'sourcePeriod' => $sourcePeriod,
            'targetPeriod' => $targetPeriod,
            'status'       => 'in_progress',
        ]);

        // Avertissement si une migration a déjà été exécutée pour ces périodes :
        // les étapes ne créeront que les éléments manquants (aucun doublon).
        $previousLog = $this->em->getRepository(MigrationLog::class)->createQueryBuilder('m')
            ->andWhere('m.school = :school')->setParameter('school', $school)
            ->andWhere('m.sourcePeriod = :source')->setParameter('source', $sourcePeriod)
            ->andWhere('m.targetPeriod = :target')->setParameter('target', $targetPeriod)
            ->andWhere('m.status IN (:statuses)')->setParameter('statuses', ['executed', 'corrected'])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($previousLog) {
            $this->addFlash('warning', 'Une migration a déjà été exécutée pour ces périodes : seuls les éléments manquants seront créés.');
        }

        $user = $this->getUser();
        try {
            $log = $this->migrationService->startMigration(
                $school,
                $sourcePeriod,
                $targetPeriod,
                $passingGrade,
                $options,
                $user ? $user->getUserIdentifier() : 'inconnu'
            );
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erreur lors de la préparation de la migration : ' . $e->getMessage());
            return $this->redirectToRoute('app_year_migration_index');
        }

        if ($resumed) {
            $this->addFlash('info', 'Brouillon de migration existant repris : la progression précédente est conservée.');
        }

        return $this->redirectToRoute('app_year_migration_wizard', ['id' => $log->getId()]);
    }

    #[Route('/{id}/wizard', name: 'app_year_migration_wizard', methods: ['GET'])]
    public function wizard(MigrationLog $log, SessionInterface $session): Response
    {
        $this->denyAccessUnlessGranted('perm', 'year_migration.view');

        $school = $this->getSchool($session);
        if (!$school || $log->getSchool() !== $school) {
            throw $this->createAccessDeniedException();
        }

        if ($log->getStatus() !== 'in_progress') {
            return $log->getStatus() === 'cancelled'
                ? $this->redirectToRoute('app_year_migration_index')
                : $this->redirectToRoute('app_year_migration_manage', ['id' => $log->getId()]);
        }

        // Les étapes en attente sans élément à traiter (0 dans la source) sont
        // ignorées automatiquement : le wizard repart sur la prochaine étape utile.
        $this->migrationService->autoSkipEmptySteps($log);

        $currentKey = $this->migrationService->getNextStepKey($log);
        $context    = $currentKey ? $this->migrationService->getStepContext($log, $currentKey) : [];

        $blockedKeys = [];
        foreach (SchoolYearMigrationService::STEPS as $key => $label) {
            if ($this->migrationService->isStepBlocked($log, $key)) {
                $blockedKeys[$key] = true;
            }
        }

        $state     = $log->getStepsState() ?? [];
        $hasFailed = false;
        foreach (SchoolYearMigrationService::STEPS as $key => $label) {
            if (($state[$key]['status'] ?? null) === 'failed') { $hasFailed = true; break; }
        }

        return $this->render('school_year_migration/wizard.html.twig', [
            'log'         => $log,
            'school'      => $school,
            'steps'       => SchoolYearMigrationService::STEPS,
            'stepsState'  => $state,
            'currentKey'  => $currentKey,
            'context'     => $context,
            'blockedKeys' => $blockedKeys,
            'canFinish'   => !$hasFailed,
            'canSkip'     => $currentKey !== null && ($state[$currentKey]['status'] ?? null) === 'failed',
        ]);
    }

    #[Route('/{id}/wizard/step/{stepKey}', name: 'app_year_migration_step_execute', methods: ['POST'], requirements: ['stepKey' => '[a-z_]+'])]
    public function stepExecute(MigrationLog $log, string $stepKey, Request $request, SessionInterface $session): Response
    {
        $this->denyAccessUnlessGranted('perm', 'year_migration.execute');

        if (!$this->isCsrfTokenValid('wizard_step_' . $log->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_year_migration_wizard', ['id' => $log->getId()]);
        }

        $school = $this->getSchool($session);
        if (!$school || $log->getSchool() !== $school) {
            throw $this->createAccessDeniedException();
        }

        $classMapping   = $request->request->all('class_mapping');
        $studentMapping = $request->request->all('student_mapping');

        try {
            $result = $this->migrationService->executeStep($log, $stepKey, $classMapping, $studentMapping);
            $label  = SchoolYearMigrationService::STEPS[$stepKey] ?? $stepKey;
            if ($result['status'] === 'done') {
                $this->addFlash('success', sprintf('Étape « %s » réussie : %s', $label, $result['message']));
            } elseif ($result['status'] === 'skipped') {
                $this->addFlash('info', sprintf('Étape « %s » ignorée automatiquement : %s', $label, $result['message'] ?? '0 élément à traiter.'));
            } else {
                $this->addFlash('danger', sprintf('Échec de l\'étape « %s » : %s', $label, $result['message']));
            }
        } catch (\LogicException $e) {
            $this->addFlash('warning', $e->getMessage());
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erreur inattendue : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_year_migration_wizard', ['id' => $log->getId()]);
    }

    /**
     * AJAX de l'étape Élèves : moyenne de passage individuelle d'une occurrence.
     * Persiste le delta dans stepsState et renvoie les compteurs recalculés.
     */
    #[Route('/{id}/wizard/step/students/grade/{occId}', name: 'app_year_migration_wizard_students_grade', methods: ['POST'], requirements: ['occId' => '\d+'])]
    public function wizardStudentsGrade(MigrationLog $log, int $occId, Request $request, SessionInterface $session): JsonResponse
    {
        if (!$this->isGranted('perm', 'year_migration.execute')) {
            return $this->json(['ok' => false, 'message' => 'Accès refusé'], 403);
        }

        if (!$this->isCsrfTokenValid('wizard_grade_' . $log->getId(), $request->request->get('_token'))) {
            return $this->json(['ok' => false, 'message' => 'Jeton de sécurité invalide.'], 403);
        }

        $school = $this->getSchool($session);
        if (!$school || $log->getSchool() !== $school) {
            throw $this->createAccessDeniedException();
        }

        if ($log->getStatus() !== 'in_progress') {
            return $this->json(['ok' => false, 'message' => 'La migration n\'est plus modifiable.']);
        }

        $raw   = $request->request->get('grade');
        $grade = ($raw === null || trim((string) $raw) === '') ? null : (float) $raw;

        if ($grade !== null && ($grade < 0 || $grade > 20)) {
            return $this->json(['ok' => false, 'message' => 'La moyenne de passage doit être comprise entre 0 et 20.']);
        }

        // Un delta égal à la note globale n'a pas besoin d'être persisté.
        if ($grade !== null && $grade == $log->getPassingGrade()) {
            $grade = null;
        }

        $this->migrationService->setClassPassingGrade($log, $occId, $grade);
        $this->em->flush();

        return $this->json(['ok' => true, 'rows' => $this->migrationService->getClassGradePreview($log, $occId)]);
    }

    /**
     * AJAX de l'étape Élèves : promotion forcée d'un redoublant vers une classe
     * cible choisie (target vide = annulation). Persiste dans stepsState et
     * renvoie l'aperçu recalculé du détail.
     */
    #[Route('/{id}/wizard/step/students/force-promote/{studentClassId}', name: 'app_year_migration_wizard_students_force_promote', methods: ['POST'], requirements: ['studentClassId' => '\d+'])]
    public function wizardStudentsForcePromote(MigrationLog $log, int $studentClassId, Request $request, SessionInterface $session): JsonResponse
    {
        if (!$this->isGranted('perm', 'year_migration.execute')) {
            return $this->json(['ok' => false, 'message' => 'Accès refusé'], 403);
        }

        if (!$this->isCsrfTokenValid('wizard_force_' . $log->getId(), $request->request->get('_token'))) {
            return $this->json(['ok' => false, 'message' => 'Jeton de sécurité invalide.'], 403);
        }

        $school = $this->getSchool($session);
        if (!$school || $log->getSchool() !== $school) {
            throw $this->createAccessDeniedException();
        }

        if ($log->getStatus() !== 'in_progress') {
            return $this->json(['ok' => false, 'message' => 'La migration n\'est plus modifiable.']);
        }

        $studentClass = $this->em->getRepository(StudentClass::class)->find($studentClassId);
        $sourceSCP    = $studentClass?->getSchoolClassPeriod();
        if (!$studentClass || !$sourceSCP || $sourceSCP->getSchool() !== $school || $sourceSCP->getPeriod() !== $log->getSourcePeriod()) {
            return $this->json(['ok' => false, 'message' => 'Élève introuvable dans la période source.'], 404);
        }

        $raw      = $request->request->get('target');
        $targetId = ($raw === null || trim((string) $raw) === '') ? null : (int) $raw;
        $target   = null;
        if ($targetId !== null) {
            $target = $this->em->getRepository(SchoolClassPeriod::class)->find($targetId);
            if (!$target || $target->getSchool() !== $school || $target->getPeriod() !== $log->getTargetPeriod()) {
                return $this->json(['ok' => false, 'message' => 'Classe cible invalide.'], 400);
            }
        }

        $this->migrationService->setStudentForcePromotion($log, $studentClassId, $targetId);
        $this->em->flush();

        $occId = $sourceSCP->getClassOccurence()?->getId();

        return $this->json([
            'ok'             => true,
            'studentClassId' => $studentClassId,
            'forced'         => $targetId !== null,
            'targetName'     => $target?->getClassOccurence()?->getName(),
            'rows'           => $occId !== null ? $this->migrationService->getClassGradePreview($log, $occId) : [],
        ]);
    }

    #[Route('/{id}/wizard/step/{stepKey}/skip', name: 'app_year_migration_step_skip', methods: ['POST'], requirements: ['stepKey' => '[a-z_]+'])]
    public function stepSkip(MigrationLog $log, string $stepKey, Request $request, SessionInterface $session): Response
    {
        $this->denyAccessUnlessGranted('perm', 'year_migration.execute');

        if (!$this->isCsrfTokenValid('wizard_skip_' . $log->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_year_migration_wizard', ['id' => $log->getId()]);
        }

        $school = $this->getSchool($session);
        if (!$school || $log->getSchool() !== $school) {
            throw $this->createAccessDeniedException();
        }

        try {
            $this->migrationService->skipFailedStep($log, $stepKey);
            $label = SchoolYearMigrationService::STEPS[$stepKey] ?? $stepKey;
            $this->addFlash('warning', sprintf('Étape « %s » ignorée : la migration continue sans les données de cette étape.', $label));
        } catch (\LogicException $e) {
            $this->addFlash('warning', $e->getMessage());
        }

        return $this->redirectToRoute('app_year_migration_wizard', ['id' => $log->getId()]);
    }

    #[Route('/{id}/wizard/cancel', name: 'app_year_migration_wizard_cancel', methods: ['POST'])]
    public function wizardCancel(MigrationLog $log, Request $request, SessionInterface $session): Response
    {
        $this->denyAccessUnlessGranted('perm', 'year_migration.cancel');

        if (!$this->isCsrfTokenValid('cancel_migration_' . $log->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_year_migration_wizard', ['id' => $log->getId()]);
        }

        $school = $this->getSchool($session);
        if (!$school || $log->getSchool() !== $school) {
            throw $this->createAccessDeniedException();
        }

        if ($log->getStatus() !== 'in_progress') {
            $this->addFlash('warning', 'Ce brouillon a déjà été clôturé.');
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

    #[Route('/{id}/wizard/finish', name: 'app_year_migration_wizard_finish', methods: ['POST'])]
    public function wizardFinish(MigrationLog $log, Request $request, SessionInterface $session): Response
    {
        $this->denyAccessUnlessGranted('perm', 'year_migration.execute');

        if (!$this->isCsrfTokenValid('wizard_finish_' . $log->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_year_migration_wizard', ['id' => $log->getId()]);
        }

        $school = $this->getSchool($session);
        if (!$school || $log->getSchool() !== $school) {
            throw $this->createAccessDeniedException();
        }

        try {
            $this->migrationService->finishMigration($log);
            $this->addFlash('success', 'Migration terminée.');
        } catch (\LogicException $e) {
            $this->addFlash('warning', $e->getMessage());
            return $this->redirectToRoute('app_year_migration_wizard', ['id' => $log->getId()]);
        }

        return $this->redirectToRoute('app_year_migration_result', ['id' => $log->getId()]);
    }

    #[Route('/{id}/result', name: 'app_year_migration_result', methods: ['GET'])]
    public function result(MigrationLog $log, SessionInterface $session): Response
    {
        $this->denyAccessUnlessGranted('perm', 'year_migration.view');

        $school = $this->getSchool($session);
        if (!$school || $log->getSchool() !== $school) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('school_year_migration/result.html.twig', [
            'school'        => $school,
            'sourcePeriod'  => $log->getSourcePeriod(),
            'targetPeriod'  => $log->getTargetPeriod(),
            'configSummary' => $log->getConfigSummary(),
            'studentStats'  => $log->getStudentStats(),
            'passingGrade'  => $log->getPassingGrade(),
            'log'           => $log,
        ]);
    }

    #[Route('', name: 'app_year_migration_index', methods: ['GET'])]
    public function index(
        Request $request,
        SchoolPeriodRepository $periodRepo,
        MigrationLogRepository $logRepo,
        SessionInterface $session
    ): Response {
        $this->denyAccessUnlessGranted('perm', 'year_migration.view');

        $school = $this->getSchool($session);
        if (!$school) {
            $this->addFlash('danger', 'Aucune école sélectionnée.');
            return $this->redirectToRoute('app_school_period_index');
        }

        return $this->render('school_year_migration/index.html.twig', [
            'periods'  => $periodRepo->findAll(),
            'school'   => $school,
            'logs'     => $logRepo->findBySchool($school),
            // Présélection quand le wizard est déclenché après la création d'une période
            'source_period_id' => $request->query->getInt('source'),
            'target_period_id' => $request->query->getInt('target'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GESTION D'UNE MIGRATION (annuler ou corriger)
    // ─────────────────────────────────────────────────────────────────────────

    #[Route('/{id}/manage', name: 'app_year_migration_manage', methods: ['GET'])]
    public function manage(MigrationLog $log, SessionInterface $session): Response
    {
        $this->denyAccessUnlessGranted('perm', 'year_migration.view');

        $school = $this->getSchool($session);
        if (!$school || $log->getSchool() !== $school) {
            throw $this->createAccessDeniedException();
        }

        // Un brouillon in_progress se reprend dans le wizard, pas sur cette page.
        if ($log->getStatus() === 'in_progress') {
            return $this->redirectToRoute('app_year_migration_wizard', ['id' => $log->getId()]);
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
        $this->denyAccessUnlessGranted('perm', 'year_migration.cancel');

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
        $this->denyAccessUnlessGranted('perm', 'year_migration.correct');

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
        $this->denyAccessUnlessGranted('perm', 'year_migration.correct');

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
            $message = sprintf(
                'Correction appliquée : %d promu(s), %d rétrogradé(s), %d ajouté(s).',
                $applied['promoted'],
                $applied['demoted'],
                $applied['added']
            );
            if (($applied['payments'] ?? 0) > 0) {
                $message .= sprintf(' %d premier(s) versement(s) recréé(s).', $applied['payments']);
            }
            $this->addFlash('success', $message);
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
            'evaluations'    => (bool) $request->request->get('opt_evaluations'),
        ];
    }
}
