<?php

namespace App\Repository;

use App\Entity\EvaluationAppreciationBareme;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EvaluationAppreciationBareme>
 */
class EvaluationAppreciationBaremeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EvaluationAppreciationBareme::class);
    }

    // Ajoutez ici vos méthodes personnalisées si besoin
}