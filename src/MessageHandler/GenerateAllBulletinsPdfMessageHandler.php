<?php

namespace App\MessageHandler;

use App\Message\GenerateAllBulletinsPdfMessage;
use App\Repository\SchoolClassPeriodRepository;
use App\Repository\StudentClassRepository;
use App\Repository\ReportCardTemplateRepository;
use App\Repository\UserRepository;
use App\Repository\SchoolRepository;
use App\Repository\SchoolPeriodRepository;
use App\Service\BulletinGenerator;
use App\Repository\EvaluationRepository;
use App\Service\PdfGenerator;
use App\Service\BulletinProgressService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Filesystem\Filesystem;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\WindowsWorkerManager;

#[AsMessageHandler]
class GenerateAllBulletinsPdfMessageHandler
{
    public function __construct(
        private SchoolClassPeriodRepository $classRepo,
        private StudentClassRepository $studentRepo,
        private ReportCardTemplateRepository $templateRepo,
        private UserRepository $userRepo,
        private SchoolRepository $schoolRepo,
        private SchoolPeriodRepository $periodRepo,
        private BulletinGenerator $bulletinGenerator,
        private EvaluationRepository $evaluationRepo,
        private PdfGenerator $pdfGenerator,
        private BulletinProgressService $bulletinProgressService,
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager,
        private WindowsWorkerManager $windowsWorkerManager,
        private string $projectDir
    ) {}

    public function __invoke(GenerateAllBulletinsPdfMessage $message)
    {
        $taskId = $message->getTaskId();

        $this->entityManager->getConnection()->getConfiguration()->setSQLLogger(null);

        try {
            $this->logger->info('🎬 DEBUT - Traitement du message', ['taskId' => $taskId]);
            $this->bulletinProgressService->updateProgress($taskId, 0, 100, 'Initialisation...');

            // Récupérer les données avec logging
            $this->logger->info('🔍 Récupération des données...', ['taskId' => $taskId]);

            $class = $this->classRepo->find($message->getClassId());
            $template = $this->templateRepo->find($message->getTemplateId());
            $user = $this->userRepo->find($message->getUserId());
            $school = $this->schoolRepo->find($message->getSchoolId());
            $period = $this->periodRepo->find($message->getPeriodId());
            // Récupérer la langue du bulletin
            $bulLang = $message->getBulLang();
            $passNote = $message->passNote();

            $this->logger->info('📊 Données récupérées', [
                'taskId' => $taskId,
                'class' => $class ? $class->getId() : 'NULL',
                'template' => $template ? $template->getName() : 'NULL',
                'user' => $user ? $user->getId() : 'NULL',
                'school' => $school ? $school->getId() : 'NULL',
                'period' => $period ? $period->getId() : 'NULL'
            ]);

            if (!$class || !$template || !$user || !$school || !$period) {
                throw new \Exception('Données manquantes pour la génération PDF');
            }

            $students = $this->studentRepo->findBy(['schoolClassPeriod' => $class]);
            $totalStudents = count($students);

            $this->logger->info('👨‍🎓 Étudiants trouvés', [
                'taskId' => $taskId,
                'count' => $totalStudents
            ]);

            if ($totalStudents === 0) {
                throw new \Exception('Aucun étudiant trouvé dans cette classe');
            }

            $this->bulletinProgressService->updateProgress($taskId, 5, 100, 'Préparation des données...');

            $bulletinsHtml = [];
            gc_enable();

            // Générer les bulletins HTML avec logging détaillé
            $this->logger->info('📝 Début génération des bulletins', ['taskId' => $taskId]);
            $progressGenerationStart = 5;   // Début à 5%
            $progressGenerationEnd = 85;    // Fin à 85% (avant génération PDF)
            $progressRange = $progressGenerationEnd - $progressGenerationStart;

            


            foreach ($students as $index => $student) {

                //Calcul précis du pourcentage
                $currentProgress = $index + 1;
                $percentage = (int) round(
                    $progressGenerationStart +
                        (($currentProgress / $totalStudents) * $progressRange)
                );

                $this->logger->info("🔄 Génération bulletin étudiant", [
                    'taskId' => $taskId,
                    'student' => $student->getStudent()->getId(),
                    'progress' => "$currentProgress/$totalStudents",
                    'percentage' => $percentage,
                    'calculated' => $progressGenerationStart . ' + ((' . $currentProgress . '/' . $totalStudents . ') * ' . $progressRange . ')'
                ]);

                $this->bulletinProgressService->updateProgress(
                    $taskId,
                    $percentage,
                    100,
                    "Génération du bulletin " . $currentProgress . "/$totalStudents"
                );

                $this->windowsWorkerManager->startWorker();

                // Générer le bulletin avec gestion d'erreur
                try {
                    $this->logger->info("🎯 Appel generateBulletinA", [
                        'taskId' => $taskId,
                        'studentId' => $student->getStudent()->getId()
                    ]);

                    $htmlResult = $this->bulletinGenerator->generateBulletinA(
                        $student->getStudent()->getId(),
                        $message->getPeriodicityId(),
                        $message->getBulletinType(),
                        $message->getClassId(),
                        $user,
                        $school,
                        $period,
                        $this->evaluationRepo,
                        $template->getName(),
                        null,
                        $index + 1,
                        count($students),
                        $bulLang,
                        $passNote
                    );

                    $this->logger->info("✅ Bulletin généré", [
                        'taskId' => $taskId,
                        'studentId' => $student->getStudent()->getId(),
                        'resultType' => gettype($htmlResult),
                        'isArray' => is_array($htmlResult),
                        'count' => is_array($htmlResult) ? count($htmlResult) : 'N/A'
                    ]);

                    $htmlContent = '';
                    if ($template->getName() == 'A') {
                        if (is_array($htmlResult) && isset($htmlResult[0]) && file_exists($htmlResult[0])) {
                            $htmlContent = file_get_contents($htmlResult[0]);
                            $this->logger->info("📄 Fichier template A lu", [
                                'taskId' => $taskId,
                                'fileSize' => strlen($htmlContent)
                            ]);
                        } else {
                            throw new \Exception('Fichier template A non trouvé ou invalide');
                        }
                    } else {
                        $htmlContent = is_array($htmlResult) ? $htmlResult[0] : $htmlResult;
                        $this->logger->info("📄 Contenu template autre généré", [
                            'taskId' => $taskId,
                            'contentSize' => strlen($htmlContent)
                        ]);
                    }

                    $studentHeader = '<div class="student-header"></div>';
                    $bulletinsHtml[] = $studentHeader . $htmlContent;
                } catch (\Exception $e) {
                    $this->logger->error("❌ Erreur génération bulletin étudiant", [
                        'taskId' => $taskId,
                        'studentId' => $student->getStudent()->getId(),
                        'error' => $e->getMessage()
                    ]);
                    throw $e;
                }

                // Nettoyage mémoire
                unset($htmlResult, $htmlContent);
                if ($index % 2 === 0) {
                    gc_collect_cycles();
                }
            }

            $this->logger->info('🎉 Tous les bulletins générés', [
                'taskId' => $taskId,
                'totalBulletins' => count($bulletinsHtml)
            ]);

            $this->bulletinProgressService->updateProgress($taskId, 85, 100, 'Création du fichier PDF...');

            // Génération PDF avec logging
            $this->logger->info('📄 Début génération PDF', ['taskId' => $taskId]);

            $filename = 'bulletins_' . ($class ? $class->getClassOccurence()->getName() : 'classe') . '_' . $taskId . '.pdf';

            // Vérifier que le contenu n'est pas vide
            $nonEmptyBulletins = array_filter($bulletinsHtml, function ($html) {
                return !empty(trim(strip_tags($html)));
            });

            if (empty($nonEmptyBulletins)) {
                throw new \Exception('Aucun contenu HTML valide pour générer le PDF');
            }

            try {
                $this->logger->info('🖨️  Appel generateMultipleBulletinsPdf', [
                    'taskId' => $taskId,
                    'bulletinsCount' => count($nonEmptyBulletins)
                ]);

                // Générer le PDF et récupérer le chemin
                $fileUrl = $this->pdfGenerator->generateMultipleBulletinsPdf($nonEmptyBulletins, $filename);

                $this->logger->info('✅ PDF généré avec succès', [
                    'taskId' => $taskId,
                    'fileUrl' => $fileUrl,
                    'fileExists' => file_exists($this->projectDir . '/public' . $fileUrl),
                    'fileSize' => file_exists($this->projectDir . '/public' . $fileUrl) ? filesize($this->projectDir . '/public' . $fileUrl) : 0
                ]);

                // METTRE À JOUR LE STATUT AVEC L'URL DU FICHIER
                $this->bulletinProgressService->updateProgress(
                    $taskId,
                    100,
                    100,
                    'Génération PDF terminée avec succès',
                    $fileUrl // Ajouter l'URL du fichier
                );
            } catch (\Exception $e) {
                $this->logger->error('❌ Erreur génération PDF', [
                    'taskId' => $taskId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }

            $this->bulletinProgressService->updateProgress($taskId, 100, 100, 'Génération PDF terminée avec succès', $fileUrl);

            $this->logger->info('🏁 FIN - Traitement terminé avec succès', [
                'taskId' => $taskId,
                'students' => $totalStudents,
                'file' => $filename
            ]);
        } catch (\Exception $e) {
            $this->logger->error('💥 ERREUR CRITIQUE', [
                'taskId' => $taskId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            $this->bulletinProgressService->updateProgress(
                $taskId,
                0,
                100,
                'Erreur: ' . $e->getMessage()
            );

            throw $e;
        }
    }

    private function executeWindowsCommand(string $command): string
    {
        // Méthode pour exécuter des commandes Docker sous Windows
        $fullCommand = "cmd /c " . $command . " 2>&1";

        return shell_exec($fullCommand) ?? '';
    }
}
