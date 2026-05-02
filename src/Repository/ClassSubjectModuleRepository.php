<?php

namespace App\Repository;

use App\Entity\ClassSubjectModule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClassSubjectModules>
 *
 * @method ClassSubjectModules|null find($id, $lockMode = null, $lockVersion = null)
 * @method ClassSubjectModules|null findOneBy(array $criteria, array $orderBy = null)
 * @method ClassSubjectModules[]    findAll()
 * @method ClassSubjectModules[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClassSubjectModuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClassSubjectModule::class);
    }

    public function save(ClassSubjectModule $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ClassSubjectModule $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}