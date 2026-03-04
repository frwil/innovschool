<?php

namespace App\Repository;

use App\Entity\School;
use App\Entity\SchoolClassPeriod;
use App\Entity\SchoolClassAdmissionPayment;
use App\Entity\SchoolPeriod;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SchoolClassAdmissionPayment>
 */
class SchoolClassAdmissionPaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SchoolClassAdmissionPayment::class);
    }

    public function getTotalPaidByStudentAndClassAndSchoolAndPeriod(
        User $studentId,
        SchoolClassPeriod $classId,
        School $schoolId,
        SchoolPeriod $schoolPeriodId
    ): float {
        $qb = $this->createQueryBuilder('p')
            ->select('SUM(p.paymentAmount) as totalPaid')
            ->where('p.student = :studentId')
            ->andWhere('p.schoolClassPeriod = :classId')
            ->andWhere('p.school = :schoolId')
            ->andWhere('p.schoolPeriod = :schoolPeriodId')
            ->setParameter('studentId', $studentId)
            ->setParameter('classId', $classId)
            ->setParameter('schoolId', $schoolId)
            ->setParameter('schoolPeriodId', $schoolPeriodId);

        $result = $qb->getQuery()->getSingleScalarResult();

        return $result ? (float) $result : 0.0;
    }

    public function getTotalPaidForModal($modalId, $studentId, $classId, $schoolId, $schoolPeriodId): float
    {
        return (float) $this->createQueryBuilder('p')
            ->select('SUM(p.paymentAmount)')
            ->where('p.paymentModal = :modalId')
            ->andWhere('p.student = :studentId')
            ->andWhere('p.schoolClassPeriod = :classId')
            ->andWhere('p.school = :schoolId')
            ->andWhere('p.schoolPeriod = :schoolPeriodId')
            ->setParameter('modalId', $modalId)
            ->setParameter('studentId', $studentId)
            ->setParameter('classId', $classId)
            ->setParameter('schoolId', $schoolId)
            ->setParameter('schoolPeriodId', $schoolPeriodId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getTotalPaidByStudentForModalType(
        User $studentId,
        SchoolClassPeriod $classId,
        School $schoolId,
        SchoolPeriod $schoolPeriodId,
        String $modalType
    ): float {
        $qb = $this->createQueryBuilder('p')
            ->select('SUM(p.paymentAmount) as totalPaid')
            ->join('p.paymentModal', 'm')
            ->where('p.student = :studentId')
            ->andWhere('p.schoolClassPeriod = :classId')
            ->andWhere('p.school = :schoolId')
            ->andWhere('p.schoolPeriod = :schoolPeriodId')
            ->andWhere('m.modalType = :modalType')
            ->setParameter('studentId', $studentId)
            ->setParameter('classId', $classId)
            ->setParameter('schoolId', $schoolId)
            ->setParameter('schoolPeriodId', $schoolPeriodId)
            ->setParameter('modalType', $modalType);

        $result = $qb->getQuery()->getSingleScalarResult();

        return $result ? (float) $result : 0.0;
    }

    public function getTotalToPayForModalType(
        SchoolClassPeriod $classId,
        School $schoolId,
        SchoolPeriod $schoolPeriodId,
        String $modalType
    ): float {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('SUM(m.amount) as totalToPay')
            ->from('App\\Entity\\SchoolClassPaymentModal', 'm') // Utiliser l'entité SchoolClassPaymentModal
            ->where('m.schoolClassPeriod = :classId')
            ->andWhere('m.school = :schoolId')
            ->andWhere('m.schoolPeriod = :schoolPeriodId')
            ->andWhere('m.modalType = :modalType')
            ->setParameter('classId', $classId)
            ->setParameter('schoolId', $schoolId)
            ->setParameter('schoolPeriodId', $schoolPeriodId)
            ->setParameter('modalType', $modalType);
    
        $result = $qb->getQuery()->getSingleScalarResult();
    
        return $result ? (float) $result : 0.0;
    }

    public function findByModalTypeAndStudentAndClass(string $modalType, int $studentId, int $classId)
    {
        return $this->createQueryBuilder('p')
            ->join('p.paymentModal', 'm')
            ->where('m.modalType = :modalType')
            ->andWhere('p.student = :studentId')
            ->andWhere('p.schoolClassPeriod = :classId')
            ->setParameter('modalType', $modalType)
            ->setParameter('studentId', $studentId)
            ->setParameter('classId', $classId)
            ->orderBy('p.paymentDate', 'DESC') // Trier par date décroissante
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return SchoolClassAdmissionPayment[] Returns an array of SchoolClassAdmissionPayment objects
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

    //    public function findOneBySomeField($value): ?SchoolClassAdmissionPayment
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
