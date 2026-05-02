<?php

namespace App\Repository;

use App\Entity\SchoolStudyType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SchoolStudyType>
 */
class SchoolStudyTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SchoolStudyType::class);
    }

    // Ajoute ici tes méthodes personnalisées si besoin
}