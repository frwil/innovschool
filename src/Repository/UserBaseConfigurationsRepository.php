<?php

namespace App\Repository;

use App\Entity\UserBaseConfiguration; // Ensure this class exists in the App\Entity namespace
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserBaseConfiguration>
 */
class UserBaseConfigurationsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserBaseConfiguration::class);
    }

    /**
     * Ajouter une nouvelle configuration
     */
    public function add(UserBaseConfiguration $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprimer une configuration
     */
    public function remove(UserBaseConfiguration $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouver les configurations par utilisateur
     */
    public function findByUser(int $userId): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.user = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les configurations par école
     */
    public function findBySchool(int $schoolId): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.school = :schoolId')
            ->setParameter('schoolId', $schoolId)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver une configuration spécifique par utilisateur et école
     */
    public function findOneByUserAndSchool(int $userId, int $schoolId): ?UserBaseConfiguration
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.user = :userId')
            ->andWhere('u.school = :schoolId')
            ->setParameter('userId', $userId)
            ->setParameter('schoolId', $schoolId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}