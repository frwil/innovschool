<?php

namespace App\Repository;

use App\Entity\StudentClassAttendance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StudentClassAttendance>
 */
class StudentClassAttendanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StudentClassAttendance::class);
    }

    // Ajoutez ici vos méthodes personnalisées si besoin
}