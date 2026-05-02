<?php

namespace App\Repository;

use App\Entity\SchoolSection;
use App\Service\SchoolGlobalReportFilterDto;
use App\Service\SchoolSectionDTO;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SchoolSection>
 */
class SchoolSectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SchoolSection::class);
    }

    public function filter(SchoolSectionDTO $filter): array
    {
        $qb = $this->createQueryBuilder('s');



        return $qb->getQuery()->getResult();
    }

    public function filterDTO(SchoolGlobalReportFilterDto $filter): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.school = :school')
            ->setParameter('school', $filter->school);

        if ($filter->section) {
            $qb->andWhere('s.id = :section')
            ->setParameter('section', $filter->section->getId());
        }

        return $qb->getQuery()->getResult();
    }

    //    /**
    //     * @return SchoolSection[] Returns an array of SchoolSection objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('s.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?SchoolSection
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
