<?php

namespace App\Repository;

use App\Entity\ClassOccurenceSchoolConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClassOccurenceSchoolConfig>
 *
 * @method ClassOccurenceSchoolConfig|null find($id, $lockMode = null, $lockVersion = null)
 * @method ClassOccurenceSchoolConfig|null findOneBy(array $criteria, array $orderBy = null)
 * @method ClassOccurenceSchoolConfig[]    findAll()
 * @method ClassOccurenceSchoolConfig[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClassOccurenceSchoolConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClassOccurenceSchoolConfig::class);
    }
}
