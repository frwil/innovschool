<?php

namespace App\Service;

use App\Entity\SchoolClassPeriod;
use App\Repository\SchoolClassRepository;

class SchoolClassReportService
{

    public function __construct(
        private SchoolClassRepository $schoolClassRepository,
    ) {
    }

    public function getClasses(SchoolClassPeriod $schoolClassFilter): array
    {
        $classes = $this->schoolClassRepository->filter($schoolClassFilter);
        $classes = array_map(function(SchoolClassPeriod $schoolClassPeriod){
            return new SchoolClassDTO($schoolClassPeriod);
        }, $classes);
        return $classes;
    }

    
}