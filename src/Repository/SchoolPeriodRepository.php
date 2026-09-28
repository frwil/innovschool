<?php

namespace App\Repository;

use App\Entity\SchoolPeriod;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SchoolPeriod>
 */
class SchoolPeriodRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SchoolPeriod::class);
    }

    /**
     * Dernière période chronologiquement (null si aucune).
     * Le format \d{4}/\d{4} garantit que l'ordre lexicographique = ordre chronologique.
     */
    public function findLatest(): ?SchoolPeriod
    {
        return $this->findOneBy([], ['name' => 'DESC']);
    }

    /**
     * Nom que DOIT porter la prochaine période (successeure directe de la dernière),
     * ou null si aucune période n'existe encore (première création libre).
     */
    public function getExpectedNextName(): ?string
    {
        $latest = $this->findLatest();
        if ($latest === null) {
            return null;
        }

        $start = $latest->getStartYear();
        if ($start === null) {
            return null; // dernière période au nom invalide : pas de contrainte de chaînage
        }

        return sprintf('%04d/%04d', $start + 1, $start + 2);
    }

    /**
     * Nom proposé par défaut à la création :
     * dernière période + 1, sinon année scolaire courante
     * (à partir de septembre → YYYY/YYYY+1, sinon YYYY-1/YYYY).
     */
    public function suggestNextName(): string
    {
        $expected = $this->getExpectedNextName();
        if ($expected !== null) {
            return $expected;
        }

        $year  = (int) date('Y');
        $start = ((int) date('n') >= 9) ? $year : $year - 1;

        return sprintf('%04d/%04d', $start, $start + 1);
    }

    /**
     * Vrai si une période avec une année de début strictement supérieure existe
     * (comparaison par nom, indépendante de previous_period_id pour les lignes legacy).
     */
    public function hasLaterThan(SchoolPeriod $period): bool
    {
        $year = $period->getStartYear();
        if ($year === null) {
            return true; // nom non parsable : suppression bloquée par prudence
        }

        foreach ($this->findAll() as $other) {
            if ($other->getId() === $period->getId()) {
                continue;
            }
            $otherYear = $other->getStartYear();
            if ($otherYear !== null && $otherYear > $year) {
                return true;
            }
        }

        return false;
    }

    //    /**
    //     * @return SchoolPeriod[] Returns an array of SchoolPeriod objects
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

    //    public function findOneBySomeField($value): ?SchoolPeriod
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
