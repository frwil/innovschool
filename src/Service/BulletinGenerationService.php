<?php

namespace App\Service;

use App\DTO\BulletinRequestDTO;
use App\Entity\ReportCardTemplate;
use App\Entity\SchoolClassPeriod;
use App\Repository\EvaluationRepository;
use App\Repository\ReportCardTemplateRepository;
use App\Repository\SchoolClassPeriodRepository;
use App\Repository\StudentClassRepository;
use Doctrine\ORM\EntityManagerInterface;

class BulletinGenerationService
{
    public function __construct(
        private BulletinGenerator $bulletinGenerator,
        private EntityManagerInterface $entityManager,
        private SchoolClassPeriodRepository $classRepo,
        private StudentClassRepository $studentRepo,
        private ReportCardTemplateRepository $templateRepo,
        private EvaluationRepository $evaluationRepo,
        private BulletinDataService $bulletinDataService,
    ) {}

    public function generateIndividualBulletin(BulletinRequestDTO $dto, BulletinContextService $context): array
    {
        $dto->validateForIndividualGeneration();

        $template = $this->templateRepo->find($dto->templateId);
        if (!$template) {
            throw new \InvalidArgumentException('Template non trouvé');
        }

        // Gestion du mode "Annuel"
        $groupedFrames = null;
        if ($dto->periodicityId === 'all') {
            $groupedFrames = $this->bulletinDataService->getAllTimesGroupedByFrame($dto->classId, $context);
        }

        if ($dto->printType === 'full') {
            return $this->generateAllBulletinsForClass($dto, $context, $template, $groupedFrames);
        }

        $htmlResult = $this->bulletinGenerator->generateBulletinA(
            $dto->studentId,
            $dto->periodicityId,
            $dto->bulletinType,
            $dto->classId,
            $context->getCurrentUser(),
            $context->getCurrentSchool(),
            $context->getCurrentPeriod(),
            $this->evaluationRepo,
            $template->getName(),
            null,
            1,
            1,
            $dto->bulLang ?? 'fr',
            $dto->passNote ?? 10,
            $dto->printType ?? null,
            $groupedFrames,
        );

        return [
            'html' => $template->getName() === 'A' ? file_get_contents($htmlResult[0]) : $htmlResult[0],
            'template' => $template,
            'metadata' => array_slice($htmlResult, 1)
        ];
    }

    private function generateAllBulletinsForClass(BulletinRequestDTO $dto, BulletinContextService $context, ReportCardTemplate $template, ?array $groupedFrames = null): array
    {
        $class = $this->classRepo->find($dto->classId);
        if (!$class) {
            throw new \InvalidArgumentException('Classe non trouvée');
        }

        $students = $this->studentRepo->findBy(['schoolClassPeriod' => $class]);
        
        if (empty($students)) {
            throw new \InvalidArgumentException('Aucun étudiant trouvé dans cette classe');
        }

        // Générer tous les bulletins et collecter les moyennes pour le tri par mérite
        $bulletinsData = [];
        foreach ($students as $index => $student) {
            $htmlResult = $this->bulletinGenerator->generateBulletinA(
                $student->getStudent()->getId(),
                $dto->periodicityId,
                $dto->bulletinType,
                $dto->classId,
                $context->getCurrentUser(),
                $context->getCurrentSchool(),
                $context->getCurrentPeriod(),
                $this->evaluationRepo,
                $template->getName(),
                0,
                count($students),
                $index + 1,
                $dto->bulLang ?? 'fr',
                $dto->passNote ?? 10,
                $dto->printType ?? 'full',
                $groupedFrames,
            );

            $htmlContent = $template->getName() === 'A' ? file_get_contents($htmlResult[0]) : $htmlResult[0];
            $average = $htmlResult[1] ?? 0;

            $bulletinsData[] = [
                'html' => $htmlContent,
                'average' => $average,
                'student_id' => $student->getStudent()->getId(),
                'student_name' => $student->getStudent()->getFullName(),
                'metadata' => array_slice($htmlResult, 1),
            ];

            unset($htmlResult, $htmlContent);
            if ($index % 3 === 0) {
                gc_collect_cycles();
            }
        }

        // Trier par ordre de mérite (moyenne décroissante)
        usort($bulletinsData, function ($a, $b) {
            return $b['average'] <=> $a['average'];
        });

        // Assembler les bulletins dans l'ordre trié
        $allBulletinsHtml = '';
        $bulletinsMetadata = [];
        $total = count($bulletinsData);
        foreach ($bulletinsData as $index => $data) {
            $allBulletinsHtml .= '<div class="bulletin-container" style="page-break-after: always;">';
            $allBulletinsHtml .= $data['html'];
            $allBulletinsHtml .= '</div>';
            if ($index < $total - 1) {
                $allBulletinsHtml .= '<div style="page-break-before: always;"></div>';
            }
            $bulletinsMetadata[] = [
                'student_id' => $data['student_id'],
                'student_name' => $data['student_name'],
                'metadata' => $data['metadata'],
            ];
        }

        return [
            'html' => $allBulletinsHtml,
            'template' => $template,
            'metadata' => $bulletinsMetadata,
            'is_full' => true,
            'student_count' => $total
        ];
    }

    public function generateMassBulletins(BulletinRequestDTO $dto, BulletinContextService $context): array
    {
        $dto->validateForMassGeneration();
        
        $class = $this->classRepo->find($dto->classId);
        if (!$class) {
            throw new \InvalidArgumentException('Classe non trouvée');
        }

        $template = $this->templateRepo->find($dto->templateId);
        if (!$template) {
            throw new \InvalidArgumentException('Template non trouvé');
        }

        $groupedFrames = null;
        if ($dto->periodicityId === 'all') {
            $groupedFrames = $this->bulletinDataService->getAllTimesGroupedByFrame($dto->classId, $context);
        }

        $students = $this->studentRepo->findBy(['schoolClassPeriod' => $class]);
        $bulletinsHtml = [];

        foreach ($students as $index => $student) {
            $htmlResult = $this->bulletinGenerator->generateBulletinA(
                $student->getStudent()->getId(),
                $dto->periodicityId,
                $dto->bulletinType,
                $dto->classId,
                $context->getCurrentUser(),
                $context->getCurrentSchool(),
                $context->getCurrentPeriod(),
                $this->evaluationRepo,
                $template->getName(),
                0,
                count($students),
                $index + 1,
                $dto->bulLang ?? 'fr',
                $dto->passNote ?? 10,
                null,
                $groupedFrames,
            );

            $htmlContent = $template->getName() === 'A' ? file_get_contents($htmlResult[0]) : $htmlResult[0];
            $studentHeader = '<div class="student-header"></div>';
            $bulletinsHtml[] = $studentHeader . $htmlContent;

            unset($htmlResult, $htmlContent, $studentHeader);
            if ($index % 3 === 0) {
                gc_collect_cycles();
            }
        }

        return [
            'bulletins' => $bulletinsHtml,
            'class' => $class,
            'template' => $template,
            'studentCount' => count($students)
        ];
    }

    public function getStudentForFilename(int $studentId): array
    {
        $student = $this->studentRepo->findBy(['student' => $studentId]);
        if (!$student) {
            throw new \InvalidArgumentException('Étudiant non trouvé');
        }
        return $student;
    }
}