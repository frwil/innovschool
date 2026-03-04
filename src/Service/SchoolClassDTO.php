<?php

namespace App\Service;

use App\Entity\SchoolClassPeriod;

class SchoolClassDTO
{

    public function __construct(
        private SchoolClassPeriod $schoolClassPeriod
    ) {}

    public function getSchoolClass(): SchoolClassPeriod
    {
        return $this->schoolClassPeriod;
    }
}
