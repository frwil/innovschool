<?php

namespace App\Repository;

use App\Entity\TimetableSlot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TimetableSlot>
 *
 * @method TimetableSlot|null find($id, $lockMode = null, $lockVersion = null)
 * @method TimetableSlot|null findOneBy(array $criteria, array $orderBy = null)
 * @method TimetableSlot[]    findAll()
 * @method TimetableSlot[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TimetableSlotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TimetableSlot::class);
    }

    // Ajoute ici tes méthodes personnalisées si besoin
}