<?php

namespace App\Repository;

use App\Entity\SchoolClassSubjectEvaluationTimeNotApplicable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SchoolClassSubjectEvaluationTimeNotApplicable>
 *
 * @method SchoolClassSubjectEvaluationTimeNotApplicable|null find($id, $lockMode = null, $lockVersion = null)
 * @method SchoolClassSubjectEvaluationTimeNotApplicable|null findOneBy(array $criteria, array $orderBy = null)
 * @method SchoolClassSubjectEvaluationTimeNotApplicable[]    findAll()
 * @method SchoolClassSubjectEvaluationTimeNotApplicable[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SchoolClassSubjectEvaluationTimeNotApplicableRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SchoolClassSubjectEvaluationTimeNotApplicable::class);
    }

    /**
     * Trouve un enregistrement par matière-classe et période d'évaluation
     */
    public function findBySchoolClassSubjectAndEvaluationTime(int $schoolClassSubjectId, int $evaluationTimeId): ?SchoolClassSubjectEvaluationTimeNotApplicable
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.schoolClassSubject = :schoolClassSubjectId')
            ->andWhere('s.schoolEvaluationTime = :evaluationTimeId')
            ->setParameter('schoolClassSubjectId', $schoolClassSubjectId)
            ->setParameter('evaluationTimeId', $evaluationTimeId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve tous les enregistrements pour une matière-classe spécifique
     * 
     * @return SchoolClassSubjectEvaluationTimeNotApplicable[]
     */
    public function findBySchoolClassSubject(int $schoolClassSubjectId): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.schoolClassSubject = :schoolClassSubjectId')
            ->setParameter('schoolClassSubjectId', $schoolClassSubjectId)
            ->orderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les enregistrements pour une période d'évaluation spécifique
     * 
     * @return SchoolClassSubjectEvaluationTimeNotApplicable[]
     */
    public function findByEvaluationTime(int $evaluationTimeId): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.schoolEvaluationTime = :evaluationTimeId')
            ->setParameter('evaluationTimeId', $evaluationTimeId)
            ->orderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les matières non applicables pour une période d'évaluation
     * 
     * @return SchoolClassSubjectEvaluationTimeNotApplicable[]
     */
    public function findNotApplicableByEvaluationTime(int $evaluationTimeId): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.schoolEvaluationTime = :evaluationTimeId')
            ->andWhere('s.notApplicable = :notApplicable')
            ->setParameter('evaluationTimeId', $evaluationTimeId)
            ->setParameter('notApplicable', true)
            ->orderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les périodes où une matière est non applicable
     * 
     * @return SchoolClassSubjectEvaluationTimeNotApplicable[]
     */
    public function findNotApplicableBySchoolClassSubject(int $schoolClassSubjectId): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.schoolClassSubject = :schoolClassSubjectId')
            ->andWhere('s.notApplicable = :notApplicable')
            ->setParameter('schoolClassSubjectId', $schoolClassSubjectId)
            ->setParameter('notApplicable', true)
            ->orderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les enregistrements pour une classe (via les matières de la classe)
     * 
     * @return SchoolClassSubjectEvaluationTimeNotApplicable[]
     */
    public function findBySchoolClass(int $schoolClassPeriodId): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.schoolClassSubject', 'scs')
            ->andWhere('scs.schoolClassPeriod = :schoolClassPeriodId')
            ->setParameter('schoolClassPeriodId', $schoolClassPeriodId)
            ->orderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Marque une matière comme non applicable pour une période
     */
    public function markAsNotApplicable(int $schoolClassSubjectId, int $evaluationTimeId, bool $notApplicable = true): void
    {
        $entity = $this->findBySchoolClassSubjectAndEvaluationTime($schoolClassSubjectId, $evaluationTimeId);
        
        if (!$entity) {
            // Créer un nouvel enregistrement
            $entity = new SchoolClassSubjectEvaluationTimeNotApplicable();
            $entity->setSchoolClassSubject($this->_em->getReference('App\Entity\SchoolClassSubject', $schoolClassSubjectId));
            $entity->setSchoolEvaluationTime($this->_em->getReference('App\Entity\SchoolEvaluationTime', $evaluationTimeId));
        }
        
        $entity->setNotApplicable($notApplicable);
        
        $this->_em->persist($entity);
        $this->_em->flush();
    }

    /**
     * Supprime l'enregistrement pour une matière et une période
     */
    public function removeBySchoolClassSubjectAndEvaluationTime(int $schoolClassSubjectId, int $evaluationTimeId): void
    {
        $entity = $this->findBySchoolClassSubjectAndEvaluationTime($schoolClassSubjectId, $evaluationTimeId);
        
        if ($entity) {
            $this->_em->remove($entity);
            $this->_em->flush();
        }
    }

    /**
     * Supprime tous les enregistrements pour une matière
     */
    public function removeBySchoolClassSubject(int $schoolClassSubjectId): void
    {
        $entities = $this->findBySchoolClassSubject($schoolClassSubjectId);
        
        foreach ($entities as $entity) {
            $this->_em->remove($entity);
        }
        
        $this->_em->flush();
    }

    /**
     * Supprime tous les enregistrements pour une période d'évaluation
     */
    public function removeByEvaluationTime(int $evaluationTimeId): void
    {
        $entities = $this->findByEvaluationTime($evaluationTimeId);
        
        foreach ($entities as $entity) {
            $this->_em->remove($entity);
        }
        
        $this->_em->flush();
    }

    /**
     * Vérifie si une matière est marquée comme non applicable pour une période
     */
    public function isNotApplicable(int $schoolClassSubjectId, int $evaluationTimeId): bool
    {
        $entity = $this->findBySchoolClassSubjectAndEvaluationTime($schoolClassSubjectId, $evaluationTimeId);
        
        if (!$entity) {
            return false;
        }
        
        return $entity->isNotApplicable();
    }

    /**
     * Récupère les IDs des périodes d'évaluation où une matière n'est pas applicable
     * 
     * @return int[]
     */
    public function getNotApplicableTimeIdsForSubject(int $schoolClassSubjectId): array
    {
        $result = $this->createQueryBuilder('s')
            ->select('IDENTITY(s.schoolEvaluationTime) as timeId')
            ->andWhere('s.schoolClassSubject = :schoolClassSubjectId')
            ->andWhere('s.notApplicable = :notApplicable')
            ->setParameter('schoolClassSubjectId', $schoolClassSubjectId)
            ->setParameter('notApplicable', true)
            ->getQuery()
            ->getArrayResult();
        
        return array_column($result, 'timeId');
    }

    /**
     * Récupère les IDs des matières qui ne sont pas applicables pour une période
     * 
     * @return int[]
     */
    public function getNotApplicableSubjectIdsForTime(int $evaluationTimeId): array
    {
        $result = $this->createQueryBuilder('s')
            ->select('IDENTITY(s.schoolClassSubject) as subjectId')
            ->andWhere('s.schoolEvaluationTime = :evaluationTimeId')
            ->andWhere('s.notApplicable = :notApplicable')
            ->setParameter('evaluationTimeId', $evaluationTimeId)
            ->setParameter('notApplicable', true)
            ->getQuery()
            ->getArrayResult();
        
        return array_column($result, 'subjectId');
    }

    /**
     * Récupère les enregistrements avec jointures pour optimisation
     * 
     * @return SchoolClassSubjectEvaluationTimeNotApplicable[]
     */
    public function findByEvaluationTimeWithJoins(int $evaluationTimeId): array
    {
        return $this->createQueryBuilder('s')
            ->addSelect('scs', 'ss', 'scp', 'set')
            ->join('s.schoolClassSubject', 'scs')
            ->join('scs.studySubject', 'ss')
            ->join('scs.schoolClassPeriod', 'scp')
            ->join('s.schoolEvaluationTime', 'set')
            ->andWhere('s.schoolEvaluationTime = :evaluationTimeId')
            ->setParameter('evaluationTimeId', $evaluationTimeId)
            ->orderBy('scp.id', 'ASC')
            ->addOrderBy('ss.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Sauvegarde une entité
     */
    public function save(SchoolClassSubjectEvaluationTimeNotApplicable $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une entité
     */
    public function remove(SchoolClassSubjectEvaluationTimeNotApplicable $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}