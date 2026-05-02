<?php

namespace App\Repository;

use App\Entity\TimetableDay;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TimetableDay>
 *
 * @method TimetableDay|null find($id, $lockMode = null, $lockVersion = null)
 * @method TimetableDay|null findOneBy(array $criteria, array $orderBy = null)
 * @method TimetableDay[]    findAll()
 * @method TimetableDay[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TimetableDayRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TimetableDay::class);
    }

    // Ajoute ici tes méthodes personnalisées si besoin
}