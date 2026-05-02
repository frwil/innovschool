<?php

namespace App\Repository;

use App\Entity\StudiesType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StudiesType>
 */
class StudiesTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StudiesType::class);
    }

    // Ajoute ici tes méthodes personnalisées si besoin
}