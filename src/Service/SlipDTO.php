<?php

namespace App\Service;

use App\Contract\SlipTypeEnum;
use App\Entity\SchoolClassPeriod;

class SlipDTO
{
    public function __construct(
        public SchoolClassPeriod $schoolClasse,
        public ?int $assessmentNumber = 6,
        public ?SlipTypeEnum $type = null,
    ) {}

    public function getStudents(): array
    {
        return $this->schoolClasse->getStudents();
    }

    public function getClassName(): string
    {
        return $this->schoolClasse->getName();
    }

    public function getSubjects(): array
    {
        return $this->schoolClasse->getSchoolClassSubjects()->toArray();
    }

    public function getSubjectGroups(): array
    {
        return $this->schoolClasse->getSchoolClassSubjectGroups()->toArray();
    }
}
