<?php
namespace App\Repository;

use App\Entity\AdmissionReductions;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\User;
use App\Entity\School;
use App\Entity\SchoolPeriod;

/**
 * @extends ServiceEntityRepository<AdmissionReductions>
 *
 * @method AdmissionReductions|null find($id, $lockMode = null, $lockVersion = null)
 * @method AdmissionReductions|null findOneBy(array $criteria, array $orderBy = null)
 * @method AdmissionReductions[]    findAll()
 * @method AdmissionReductions[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AdmissionReductionsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdmissionReductions::class);
    }

    /**
     * Ajouter une réduction
     */
    public function add(AdmissionReductions $entity, bool $flush = false): void
    {
        $this->_em->persist($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * Supprimer une réduction
     */
    public function remove(AdmissionReductions $entity, bool $flush = false): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * Retourne le montant total des réductions pour une modalité et un élève donné
     */
    public function getTotalReductionForModal($modal, $student, $classPeriod, $school, $schoolPeriod, $onlyApproved = false): float
    {
        $qb = $this->createQueryBuilder('r')
            ->select('COALESCE(SUM(r.reductionAmount), 0) as total')
            ->where('r.reductionModal = :modal')
            ->andWhere('r.student = :student')
            ->andWhere('r.schoolClassPeriod = :classPeriod')
            ->andWhere('r.school = :school')
            ->andWhere('r.schoolPeriod = :schoolPeriod');

        if ($onlyApproved) {
            $qb->andWhere('r.approved = true');
        }

        $qb->setParameter('modal', $modal)
           ->setParameter('student', $student)
           ->setParameter('classPeriod', $classPeriod)
           ->setParameter('school', $school)
           ->setParameter('schoolPeriod', $schoolPeriod);

        return (float) $qb->getQuery()->getSingleScalarResult();
    }
    /**
     * Retourne les réductions en attente d'approbation pour lesquelles l'utilisateur est approvalOwner
     */
    public function findPendingForApprovalOwner(User $user, School $school, SchoolPeriod $period): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $platform = $conn->getDatabasePlatform()->getName();

        if ($platform === 'mysql') {
            $sql = 'SELECT * FROM admission_reductions WHERE pending_approval = 1 AND approval_owners IS NOT NULL AND approval_owners != "" AND JSON_CONTAINS(approval_owners, :userIdJson) and school_id = :schoolId and school_period_id = :periodId';
            $stmt = $conn->prepare($sql);
            $stmt->bindValue('userIdJson', '"' . $user->getId() . '"');
            $stmt->bindValue('schoolId',$school->getId());
            $stmt->bindValue('periodId',$period->getId());
            $result = $stmt->executeQuery();
            $rows = $result->fetchAllAssociative();
            return $rows ? $this->hydrateEntities($rows) : [];
        } else {
            // Fallback pour SQLite/PostgreSQL : LIKE (moins précis)
            $qb = $this->createQueryBuilder('r')
                ->where('r.pendingApproval = true')
                ->andWhere('r.approvalOwners LIKE :userId')
                ->andWhere('r.school = :schoolId')
                ->andWhere('r.schoolPeriod = :periodId')
                ->setParameter('schoolId',$school->getId())
                ->setParameter('periodId',$period->getId())
                ->setParameter('userId', '%"' . $user->getId() . '"%');
            return $qb->getQuery()->getResult();
        }
    }

    /**
     * Hydrate les entités à partir d'un tableau associatif (pour MySQL natif)
     */
    private function hydrateEntities(array $rows): array
    {
        $entities = [];
        foreach ($rows as $row) {
            $entities[] = $this->find($row['id']);
        }
        return $entities;
    }
}