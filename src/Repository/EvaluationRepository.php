<?php

namespace App\Repository;

use App\Entity\Evaluation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\DBAL\Connection;
use App\Entity\ClassSubjectModule;

/**
 * @extends ServiceEntityRepository<Evaluation>
 *
 * @method Evaluation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Evaluation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Evaluation[]    findAll()
 * @method Evaluation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EvaluationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evaluation::class);
    }

    public function save(Evaluation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Evaluation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findEvaluationPeriods(int $schoolId, int $periodId): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT 
                co.name AS className,
                sct.name AS evaluationTimeName,
                sef.name AS evaluationFrameName,
                sct.id
            FROM 
                school_class_period sc
            LEFT JOIN 
                student_class stc ON stc.school_class_period_id = sc.id
            LEFT JOIN 
                evaluation ev ON ev.student_id = stc.id
            LEFT JOIN 
                school_evaluation_time sct ON sct.id = ev.time_id
            LEFT JOIN 
                school_evaluation_frame sef ON sef.id = sct.evaluation_frame_id
            LEFT JOIN 
                class_occurence co ON co.id = sc.class_occurence_id
            WHERE 
                sc.school_id = :schoolId
                AND sc.period_id = :periodId
                AND sct.id IS NOT NULL
            GROUP BY 
                co.name, sct.name, sef.name, sct.id
        ";

        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery([
            'schoolId' => $schoolId,
            'periodId' => $periodId,
        ]);

        return $resultSet->fetchAllAssociative();
    }

    public function findEvaluationPeriodsByClass(int $classId, int $schoolId, int $periodId): array
    {
        $conn = $this->getEntityManager()->getConnection();

        // Liste les périodes/sous-périodes d'évaluation de la période en cours :
        // 1. celles CONFIGURÉES pour la période (school_evaluation) — sinon une
        //    nouvelle année n'aurait rien à afficher avant la première note ;
        // 2. si la période n'a aucune configuration, retomber sur les
        //    sous-périodes réellement utilisées par l'école (au moins une note
        //    saisie, toutes années confondues) — cas des écoles qui ne
        //    re-créent pas la config chaque année.
        // Les notes elles-mêmes sont créées à la volée à l'ouverture de la grille.
        $sql = "
            SELECT
                NULL AS className,
                sct.name AS evaluationTimeName,
                sef.name AS evaluationFrameName,
                sct.id AS evaluationTimeId,
                sef.id AS evaluationFrameId,
                NULL AS classId
            FROM
                school_evaluation_time sct
            LEFT JOIN
                school_evaluation_frame sef ON sef.id = sct.evaluation_frame_id
            WHERE
                EXISTS (
                    SELECT 1 FROM school_evaluation se
                    WHERE se.time_id = sct.id AND se.period_id = :periodId
                )
                OR (
                    NOT EXISTS (SELECT 1 FROM school_evaluation se2 WHERE se2.period_id = :periodId)
                    AND EXISTS (SELECT 1 FROM evaluation ev WHERE ev.time_id = sct.id)
                )
            GROUP BY
                sct.id
            ORDER BY
                sef.id ASC, sct.id ASC
        ";

        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery([
            'periodId' => $periodId,
        ]);

        return $resultSet->fetchAllAssociative();
    }

    public function countClassesWithEvaluationsForModule(ClassSubjectModule $module): int
    {
        return $this->createQueryBuilder('e')
            ->select('COUNT(DISTINCT sc.id)')
            ->join('e.student', 's') // Joindre la relation avec Student
            ->join('s.schoolClassPeriod', 'sc') // Joindre la relation avec SchoolClassPeriod
            ->where('e.classSubjectModule = :module')
            ->setParameter('module', $module)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findEvaluationsByClassAndPeriods($class, array $periods): array
    {
        $qb = $this->createQueryBuilder('e')
            ->select('e', 'csm', 'subject', 'module', 'time', 'scs', 'subjectGroup')
            ->join('e.classSubjectModule', 'csm')
            ->join('csm.subject', 'subject')
            ->join('csm.module', 'module')
            ->join('subject.schoolClassSubjects', 'scs')
            ->join('scs.group', 'subjectGroup') // Assurez-vous que cette relation existe
            ->join('e.time', 'time')
            ->join('e.student', 'student')
            ->join('student.schoolClassPeriod', 'classPeriod')
            ->where('classPeriod = :class')
            ->andWhere('time IN (:periods)')
            ->andWhere('student.enabled = 1') // Comptes désactivés exclus
            ->setParameter('class', $class)
            ->setParameter('periods', $periods);

        return $qb->getQuery()->getResult();
    }

    public function findEvaluationsByClassAndPeriodsStructured($class, array $periods): array
    {
        $qb = $this->createQueryBuilder('e')
            ->select(
                'e.evaluationNote',
                'sg.id as subjectGroupId',
                's.id as subjectId',
                'm.id as moduleId'
            )
            ->join('e.classSubjectModule', 'csm')
            ->join('csm.subject', 's')
            ->join('csm.module', 'm')
            ->join('s.schoolClassSubjects', 'scs')
            ->join('scs.group', 'sg')
            ->join('e.time', 't')
            ->join('e.student', 'st')
            ->join('st.schoolClassPeriod', 'scp')
            ->where('scp = :class')
            ->andWhere('t IN (:periods)')
            ->andWhere('st.enabled = 1') // Comptes désactivés exclus
            ->setParameter('class', $class)
            ->setParameter('periods', $periods);

        return $qb->getQuery()->getResult();
    }
}
