<?php

namespace App\Repository;

use App\Entity\EvaluationAppreciationTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EvaluationAppreciationTemplate>
 */
class EvaluationAppreciationTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EvaluationAppreciationTemplate::class);
    }

    // Ajoutez ici vos méthodes personnalisées si besoin
}