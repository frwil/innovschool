<?php

namespace App\Repository;

use App\Entity\SchoolClassPeriod;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SchoolClassPeriod>
 */
class SchoolClassPeriodRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SchoolClassPeriod::class);
    }

    public function filter(SchoolClassPeriod $schoolClassPeriod): array
    {
        $qb = $this->createQueryBuilder('s');

        if ($schoolClassPeriod->getName() != '' || $schoolClassPeriod->getName() != null) {
            $qb->andWhere('s.name LIKE :name')
                ->setParameter('name', '%' . $schoolClassPeriod->getName() . '%');
        }

        return $qb->getQuery()->getResult();
    }
}
