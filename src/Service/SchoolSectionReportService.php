<?php

namespace App\Service;

use App\Entity\School;
use App\Entity\SchoolSection;
use App\Repository\SchoolSectionRepository;

class SchoolSectionReportService
{
    private School $school;

    public function __construct(
        private SchoolSectionRepository $schoolSectionRepository,
    ) {}

    public function build(School $school): static
    {
        $this->school = $school;
        return $this;
    }

    public function getSections(SchoolSectionDTO $filter): array
    {
        /** @var \App\Entity\SchoolSection[] */
        $sections = $this->schoolSectionRepository->filter($filter);

        $sections = array_map(function (SchoolSection $schoolSection) {
            return new SchoolSectionDTO($schoolSection);
        }, $sections);
        return $sections;
    }
}
