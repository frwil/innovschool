<<<<<<< HEAD
<?php

namespace App\Repository;

use App\Entity\ReportCardTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ReportCardTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReportCardTemplate::class);
    }
=======
<?php

namespace App\Repository;

use App\Entity\ReportCardTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ReportCardTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReportCardTemplate::class);
    }
>>>>>>> claude/naughty-rubin-200ad9
}