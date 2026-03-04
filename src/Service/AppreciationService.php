<?php

namespace App\Service;

use App\Entity\EvaluationAppreciationTemplate;
use App\Entity\EvaluationAppreciationBareme;
use Doctrine\ORM\EntityManagerInterface;

class AppreciationService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Retourne l'appréciation correspondant à une note pour un template donné.
     */
    public function getAppreciationByNote(EvaluationAppreciationTemplate $template, $note): ?string
    {
        $baremes = $this->entityManager->getRepository(EvaluationAppreciationBareme::class)
            ->findBy(['evaluationAppreciationTemplate' => $template], ['evaluationAppreciationMaxNote' => 'ASC']);

        foreach ($baremes as $bareme) {
            if ($note <= $bareme->getEvaluationAppreciationMaxNote()) {
                return $bareme->getEvaluationAppreciationValue();
            }
        }
        return null;
    }
}