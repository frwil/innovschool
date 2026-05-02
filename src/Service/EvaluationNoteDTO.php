<?php

namespace App\Service;

use App\Entity\SchoolClassPeriod;
use App\Entity\SectionCategorySubject;

class EvaluationNoteDTO
{
    public function __construct(
        /** @var SectionCategorySubject[] */
        public readonly array $subjects,
        public readonly SchoolClassPeriod $sClass,
    ) {}
}
