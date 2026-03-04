<?php

namespace App\Repository;

use App\Entity\SchoolEvaluationTimeType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SchoolEvaluationTimeType>
 *
 * @method SchoolEvaluationTimeType|null find($id, $lockMode = null, $lockVersion = null)
 * @method SchoolEvaluationTimeType|null findOneBy(array $criteria, array $orderBy = null)
 * @method SchoolEvaluationTimeType[]    findAll()
 * @method SchoolEvaluationTimeType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SchoolEvaluationTimeTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SchoolEvaluationTimeType::class);
    }

    // Add custom repository methods here if needed
}