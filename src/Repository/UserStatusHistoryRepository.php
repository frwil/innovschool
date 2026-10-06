<?php

namespace App\Repository;

use App\Entity\UserStatusHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserStatusHistory>
 *
 * @method UserStatusHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserStatusHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserStatusHistory[]    findAll()
 * @method UserStatusHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserStatusHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserStatusHistory::class);
    }
}
