<?php
namespace App\Repository;

use App\Entity\StudentClassTimetablePresence;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class StudentClassTimetablePresenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
    parent::__construct($registry, StudentClassTimetablePresence::class);
    }
}
