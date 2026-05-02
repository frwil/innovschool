<?php

namespace App\Repository;

use App\Entity\StudentClass;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StudentClass>
 */
class StudentClassRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StudentClass::class);
    }

    public function findStudentsByCriteria($classId, $modalities)
    {
        $qb = $this->createQueryBuilder('s')
            ->join('s.student', 'st') // Joindre l'entité User (étudiant)
            ->join('s.schoolClassPeriod', 'sc') // Joindre l'entité SchoolClassPeriod
            ->join('sc.period', 'sp') // Joindre l'entité SchoolPeriod
            ->leftJoin('st.admissionPayments', 'p') // Joindre les paiements d'admission de l'étudiant
            ->leftJoin('sc.paymentModals', 'm') // Joindre les modalités de paiement de la classe
            ->addSelect('st') // Ajouter les données de l'étudiant
            ->addSelect('sc') // Ajouter les données de la classe
            ->addSelect('sp') // Ajouter les données de la période scolaire
            ->addSelect('p') // Ajouter les données des paiements
            ->addSelect('m') // Ajouter les données des modalités
            ->where('s.schoolClassPeriod = :classId') // Filtrer par classe
            ->andWhere('m.id IN (:modalities)') // Filtrer par modalités
            ->orderBy('st.fullName','ASC')
            ->setParameter('classId', $classId)
            ->setParameter('modalities', $modalities);

        return $qb->getQuery()->getResult();
    }


    //    /**
    //     * @return StudentClass[] Returns an array of StudentClass objects
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

    //    public function findOneBySomeField($value): ?StudentClass
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
