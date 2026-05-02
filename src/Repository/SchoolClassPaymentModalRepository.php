<?php

namespace App\Repository;

use App\Entity\SchoolClassPaymentModal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SchoolClassPaymentModal>
 *
 * @method SchoolClassPaymentModal|null find($id, $lockMode = null, $lockVersion = null)
 * @method SchoolClassPaymentModal|null findOneBy(array $criteria, array $orderBy = null)
 * @method SchoolClassPaymentModal[]    findAll()
 * @method SchoolClassPaymentModal[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SchoolClassPaymentModalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SchoolClassPaymentModal::class);
    }

    public function findByFiltersWithRelations(?int $sectionId, ?int $classId): array
    {
        $qb = $this->createQueryBuilder('pm')
            ->join('pm.schoolClassPeriod', 'sc')
            ->join('sc.classOccurence', 'co')
            ->join('co.classe', 'c')
            ->join('c.studyLevel', 'sl');

        if ($sectionId) {
            $qb->andWhere('c.id = :sectionId')
               ->setParameter('sectionId', $sectionId);
        }

        if ($classId) {
            $qb->andWhere('sc.id = :classId')
               ->setParameter('classId', $classId);
        }

        $query = $qb->getQuery();
        
        return $query->getResult();
    }

    public function findUsedPriorities(int $schoolId, int $classId, int $schoolPeriodId): array
    {
        $qb = $this->createQueryBuilder('m')
            ->select('m.modalPriority')
            ->where('m.school = :schoolId') // Utilisation de la relation "school"
            ->andWhere('m.schoolClassPeriod = :classId') // Utilisation de la relation "schoolClass"
            ->andWhere('m.schoolPeriod = :schoolPeriodId') // Utilisation de la relation "schoolPeriod"
            ->setParameter('schoolId', $schoolId)
            ->setParameter('classId', $classId)
            ->setParameter('schoolPeriodId', $schoolPeriodId);

        $results = $qb->getQuery()->getResult();

        // Extraire les priorités dans un tableau simple
        return array_column($results, 'modalPriority');
    }

    public function findBatchPaymentModalities(int $classId): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.schoolClassPeriod = :classId')
            ->andWhere('m.modalType = 0') // Filtrer par modalType = 0
            ->andWhere('m.modalPriority > 0') // Filtrer les priorités supérieures à 0
            ->setParameter('classId', $classId)
            ->orderBy('m.modalPriority', 'ASC') // Trier par priorité croissante
            ->getQuery()
            ->getResult();
    }
}
