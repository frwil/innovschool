<?php

namespace App\Repository;

use App\Entity\SubjectsModules;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SubjectsModules>
 *
 * @method SubjectsModules|null find($id, $lockMode = null, $lockVersion = null)
 * @method SubjectsModules|null findOneBy(array $criteria, array $orderBy = null)
 * @method SubjectsModules[]    findAll()
 * @method SubjectsModules[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SubjectsModulesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SubjectsModules::class);
    }

    public function save(SubjectsModules $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(SubjectsModules $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}