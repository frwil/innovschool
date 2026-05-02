<?php

namespace App\Service;

use App\DTO\BulletinRequestDTO;
use App\Entity\ReportCardTemplate;
use App\Repository\ReportCardTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\SchoolEvaluationTimeRepository;
use App\Repository\SchoolEvaluationFrameRepository;
use App\Repository\EvaluationRepository;
use App\Repository\SubjectGroupRepository;
use App\Repository\SchoolClassSubjectRepository;
use App\Service\BulletinGenerationService;
use App\Service\BulletinDataService;
use App\Entity\SchoolEvaluationTime;
use App\Entity\SchoolEvaluationFrame;
use App\Entity\Evaluation;
use App\Entity\SubjectGroup;
use App\Entity\SchoolClassSubject;

class BulletinRenderService
{
    public function __construct(
        private BulletinGenerationService $generationService,
        private BulletinDataService $dataService,
        private EntityManagerInterface $entityManager,
        private ReportCardTemplateRepository $templateRepo,
        private SchoolEvaluationTimeRepository $timeRepo,
        private SchoolEvaluationFrameRepository $frameRepo,
        private EvaluationRepository $evaluationRepo,
        private SubjectGroupRepository $subjectGroupRepo,
        private SchoolClassSubjectRepository $schoolClassSubjectRepo
    ) {}

    public function renderIndividualBulletin(BulletinRequestDTO $dto, BulletinContextService $context): array
    {
        try {
            error_log("=== DEBUT renderIndividualBulletin ===");
            error_log("StudentId: " . $dto->studentId);
            error_log("ClassId: " . $dto->classId);
            error_log("TemplateId: " . $dto->templateId);
            error_log("PrintType: " . $dto->printType);

            $result = $this->generationService->generateIndividualBulletin($dto, $context);
            error_log("Génération réussie, template: " . $result['template']->getName());

            if ($result['template']->getName() == 'A') {
                error_log("Utilisation template A");
                return $this->renderTemplateA($dto, $context, $result);
            } else {
                error_log("Utilisation template autre");
                return $this->renderTemplateOther($result);
            }
        } catch (\Exception $e) {
            error_log("ERREUR dans renderIndividualBulletin: " . $e->getMessage());
            error_log("TRACE: " . $e->getTraceAsString());
            throw $e;
        }
    }

    private function renderTemplateA(BulletinRequestDTO $dto, BulletinContextService $context, array $result): array
    {
        try {
            $class = $this->dataService->getClass($dto->classId);
            $school = $context->getCurrentSchool();
            $period = $context->getCurrentPeriod();
            
            // Initialisation des variables communes
            $currentStudent = null;
            $students = [];
            
            // Si printType=full, nous n'avons pas besoin d'un étudiant spécifique
            if ($dto->printType !== 'full' && $dto->studentId) {
                $students = $this->dataService->getStudentsByClass($dto->classId);
                
                // DEBUG: Vérifiez la structure des étudiants
                if (empty($students)) {
                    throw new \InvalidArgumentException('Aucun étudiant trouvé dans la classe');
                }

                // Recherche de l'étudiant
                foreach ($students as $student) {
                    if ($student['id'] === $dto->studentId) {
                        $currentStudent = $student;
                        break;
                    }
                }

                if (!$currentStudent) {
                    throw new \InvalidArgumentException('Étudiant non trouvé dans la classe sélectionnée');
                }

                // DEBUG: Vérifiez la structure de l'étudiant
                if (!isset($currentStudent['id']) || !isset($currentStudent['name'])) {
                    throw new \InvalidArgumentException('Structure de données étudiant invalide');
                }
            }

            // Récupération des périodicités
            if ($dto->bulletinType == 'sub-period') {
                $periodicity = $this->timeRepo->findBy(['id' => $dto->periodicityId]);
                $periods = $periodicity;
            } else {
                $periodicity = $this->frameRepo->findBy(['id' => $dto->periodicityId]);
                $times = $this->timeRepo->findBy(['evaluationFrame' => $periodicity]);
                $evaluations = $this->evaluationRepo->findBy(['time' => $times]);
                $periodList = [];
                foreach ($evaluations as $evaluation) {
                    $timeId = $evaluation->getTime()->getId();
                    if (!in_array($timeId, $periodList)) {
                        $periodList[] = $timeId;
                    }
                }
                $periods = $this->timeRepo->findBy(['id' => $periodList]);
            }

            $bulLang = $dto->bulLang ?? 'fr';
            $printType = $dto->printType ?? null;

            // Construction des données pour le template
            $data = [
                'template' => 'evaluation/bulletin.frameB.html.twig',
                'data' => [
                    'student' => $currentStudent,
                    'periodicity' => $periodicity,
                    'evaluations' => $evaluations ?? [],
                    'school' => $school,
                    'schoolPeriod' => $period ? $period->getName() : 'Non défini',
                    'periodName' => !empty($periodicity) ? $periodicity[0]->getName() : 'Non défini',
                    'class' => $class,
                    'subjectGroups' => $subjectGroups ?? [],
                    'periods' => $periods ?? [],
                    'subjects' => $subjects ?? [],
                    'htmlContent' => $result['html'],
                    'nbLignes' => $result['metadata'][0] ?? null,
                    'lignesEntete' => $result['metadata'][1] ?? null,
                    'lastcolumn' => $result['metadata'][2] ?? null,
                    'lastRow' => $result['metadata'][3] ?? null,
                    'studentPhoto' => isset($currentStudent['photo']) && $currentStudent['photo'] ? '/uploads/' . $currentStudent['photo'] : '/img/default-student.jpg',
                    'schoolLogo' => $school && $school->getLogo() ? '/img/' . $school->getLogo() : '/img/logo_test.png',
                    'bulLang' => $bulLang,
                    'printType' => $printType,
                ]
            ];
            
            // Si c'est une génération complète, ajouter les variables spécifiques
            if ($dto->printType === 'full' && isset($result['is_full']) && $result['is_full']) {
                $data['data']['is_full'] = true;
                $data['data']['student_count'] = $result['student_count'] ?? 0;
                $data['data']['all_metadata'] = $result['metadata'] ?? [];
            } else {
                $data['data']['is_full'] = false;
            }

            return $data;
        } catch (\Exception $e) {
            // Log l'erreur complète
            error_log("Erreur dans renderTemplateA: " . $e->getMessage());
            error_log("Trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    private function renderTemplateOther(array $result): array
    {
        // Pour les templates autres que A, nous devons aussi passer is_full si présent
        $data = [
            'template' => 'evaluation/bulletin.frameB.html.twig',
            'data' => [
                'htmlContent' => $result['html']
            ]
        ];
        
        // Ajouter les variables pour printType=full si présentes
        if (isset($result['is_full']) && $result['is_full']) {
            $data['data']['is_full'] = true;
            $data['data']['student_count'] = $result['student_count'] ?? 0;
        } else {
            $data['data']['is_full'] = false;
        }
        
        return $data;
    }

    public function getTemplate(int $templateId): ReportCardTemplate
    {
        $template = $this->templateRepo->find($templateId);
        if (!$template) {
            throw new \InvalidArgumentException('Template non trouvé');
        }
        return $template;
    }
}