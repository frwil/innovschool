<?php

namespace App\Repository;

use App\Entity\MigrationLog;
use App\Entity\School;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MigrationLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MigrationLog::class);
    }

    /** @return MigrationLog[] */
    public function findBySchool(School $school): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.school = :school')
            ->setParameter('school', $school)
            ->orderBy('m.executedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
