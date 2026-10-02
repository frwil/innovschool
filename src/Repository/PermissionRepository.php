<?php

namespace App\Repository;

use App\Entity\Permission;
use App\Entity\RolePermission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Permission>
 */
class PermissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Permission::class);
    }

    /**
     * Noms de permissions accordées à au moins un des rôles donnés (une requête).
     *
     * @param list<string> $roleNames
     * @return list<string>
     */
    public function findNamesByRoleNames(array $roleNames): array
    {
        if (!$roleNames) {
            return [];
        }

        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('DISTINCT p.name')
            ->from(RolePermission::class, 'rp')
            ->join('rp.permission', 'p')
            ->join('rp.role', 'r')
            ->andWhere('r.name IN (:names)')
            ->setParameter('names', $roleNames)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): string => (string) reset($row), $rows);
    }
}
