<?php

namespace App\Repository;

use App\Entity\SchoolClassNumberingType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SchoolClassNumberingType>
 */
class SchoolClassNumberingTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SchoolClassNumberingType::class);
    }

    // Ajoute ici tes méthodes personnalisées si besoin
}