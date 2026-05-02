<?php

namespace App\Repository;

use App\Entity\SchoolClassSubject;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SchoolClassSubject>
 */
class SchoolClassSubjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SchoolClassSubject::class);
    }

    // Ajoute ici tes méthodes personnalisées si besoin
}