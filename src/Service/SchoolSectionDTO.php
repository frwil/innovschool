<?php

namespace App\Service;

use App\Entity\SchoolSection;

class SchoolSectionDTO
{
    public function __construct(
        private SchoolSection $schoolSection,
    ) {}

    public function getSchoolSection(): SchoolSection
    {
        return $this->schoolSection;
    }

    
}