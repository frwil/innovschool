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
        private EvaluationRepository $evaluationRepo
    ) {}

    public function generateIndividualBulletin(BulletinRequestDTO $dto, BulletinContextService $context): array
    {
        $dto->validateForIndividualGeneration();

        $template = $this->templateRepo->find($dto->templateId);
        if (!$template) {
            throw new \InvalidArgumentException('Template non trouvé');
        }

        // Si printType=full, on génère tous les bulletins de la classe
        if ($dto->printType === 'full') {
            return $this->generateAllBulletinsForClass($dto, $context, $template);
        }

        // Sinon, génération individuelle normale
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
        );

        return [
            'html' => $template->getName() === 'A' ? file_get_contents($htmlResult[0]) : $htmlResult[0],
            'template' => $template,
            'metadata' => array_slice($htmlResult, 1)
        ];
    }

    private function generateAllBulletinsForClass(BulletinRequestDTO $dto, BulletinContextService $context, ReportCardTemplate $template): array
    {
        $class = $this->classRepo->find($dto->classId);
        if (!$class) {
            throw new \InvalidArgumentException('Classe non trouvée');
        }
        $studentIds=null;
        if($dto->studentIds) $studentIds=$dto->studentIds;


        if($studentIds){
            $students = $this->studentRepo->findBy(['schoolClassPeriod' => $class,'student'=>$studentIds]);
        }else{
            $students = $this->studentRepo->findBy(['schoolClassPeriod' => $class]);
        }
        
        if (empty($students)) {
            throw new \InvalidArgumentException('Aucun étudiant trouvé dans cette classe');
        }

        $allBulletinsHtml = '';
        $bulletinsMetadata = [];
        //dd($students);

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
                0, // isMass = 0 pour génération complète
                count($students),
                $index + 1,
                $dto->bulLang ?? 'fr',
                $dto->passNote ?? 10,
                $dto->printType ?? 'full',
            );

            $htmlContent = $template->getName() === 'A' ? file_get_contents($htmlResult[0]) : $htmlResult[0];
            
            // Ajouter un conteneur pour chaque bulletin avec saut de page
            $allBulletinsHtml .= '<div class="bulletin-container" style="page-break-after: always;">';
            $allBulletinsHtml .= $htmlContent;
            $allBulletinsHtml .= '</div>';
            
            // Ajouter un saut de page sauf pour le dernier bulletin
            if ($index < count($students) - 1) {
                $allBulletinsHtml .= '<div style="page-break-before: always;"></div>';
            }

            $bulletinsMetadata[] = [
                'student_id' => $student->getStudent()->getId(),
                'student_name' => $student->getStudent()->getFullName(),
                'metadata' => array_slice($htmlResult, 1)
            ];

            // Gestion mémoire
            unset($htmlResult, $htmlContent);
            if ($index % 3 === 0) {
                gc_collect_cycles();
            }
        }

        return [
            'html' => $allBulletinsHtml,
            'template' => $template,
            'metadata' => $bulletinsMetadata,
            'is_full' => true,
            'student_count' => count($students)
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
            );

            $htmlContent = $template->getName() === 'A' ? file_get_contents($htmlResult[0]) : $htmlResult[0];
            $studentHeader = '<div class="student-header"></div>';
            $bulletinsHtml[] = $studentHeader . $htmlContent;

            // Gestion mémoire
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
        $student = $this->studentRepo->findByStudent($studentId);
        if (!$student) {
            throw new \InvalidArgumentException('Étudiant non trouvé');
        }
        return $student;
    }
}