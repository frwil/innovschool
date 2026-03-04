<?php

namespace App\Service;

use App\Entity\School;
use App\Entity\SchoolClassPeriod;
use App\Entity\SchoolSection;
use App\Entity\Classe;
use App\Entity\User;

class SchoolGlobalReportFilterDto
{
    public ?SchoolSection $section = null;          // Primaire, Secondaire...
    public ?Classe $level = null;            // CM2, 6ème...
    public ?int $minClassCount = null;       // nombre de classes (minimum)
    public ?int $maxClassCount = null;       // nombre de classes (maximum)
    public ?SchoolClassPeriod $subClass = null;         // CM2 A, CM2 B...
    public ?User $teacher = null;
    public ?int $minCapacity = null;         // capacité min
    public ?int $maxCapacity = null;         // capacité max
    public ?int $minCurrentEnrollment = null; // effectif actuel min
    public ?int $maxCurrentEnrollment = null; // effectif actuel max
    public ?int $minRepeaters = null;        // nombre de redoublants min
    public ?int $maxRepeaters = null;        // nombre de redoublants max
    public ?int $minBoys = null;             // garçons min
    public ?int $maxBoys = null;
    public ?int $minGirls = null;
    public ?int $maxGirls = null;
    public ?int $minParents = null;
    public ?int $maxParents = null;
    public ?int $minPaid = null;
    public ?int $maxPaid = null;
    public ?int $minUnpaid = null;
    public ?int $maxUnpaid = null;
    public ?School $school = null;

    public ?int $minTeacher = null;
    public ?int $maxTeacher = null;
    public ?string $reportType = null;

    public function getSection(): ?SchoolSection
    {
        return $this->section;
    }

    public function setSection(?SchoolSection $section): self
    {
        $this->section = $section;
        return $this;
    }

    public function getLevel(): ?Classe
    {
        return $this->level;
    }

    public function setLevel(?Classe $level): self
    {
        $this->level = $level;
        return $this;
    }

    public function getMinClassCount(): ?int
    {
        return $this->minClassCount;
    }

    public function setMinClassCount(?int $minClassCount): self
    {
        $this->minClassCount = $minClassCount;
        return $this;
    }

    public function getMaxClassCount(): ?int
    {
        return $this->maxClassCount;
    }

    public function setMaxClassCount(?int $maxClassCount): self
    {
        $this->maxClassCount = $maxClassCount;
        return $this;
    }

    public function getSubClass(): ?SchoolClassPeriod
    {
        return $this->subClass;
    }

    public function setSubClass(?SchoolClassPeriod $subClass): self
    {
        $this->subClass = $subClass;
        return $this;
    }

    public function getTeacher(): ?User
    {
        return $this->teacher;
    }

    public function setTeacher(?User $teacher): self
    {
        $this->teacher = $teacher;
        return $this;
    }

    public function getMinCapacity(): ?int
    {
        return $this->minCapacity;
    }

    public function setMinCapacity(?int $minCapacity): self
    {
        $this->minCapacity = $minCapacity;
        return $this;
    }

    public function getMaxCapacity(): ?int
    {
        return $this->maxCapacity;
    }

    public function setMaxCapacity(?int $maxCapacity): self
    {
        $this->maxCapacity = $maxCapacity;
        return $this;
    }

    public function getMinCurrentEnrollment(): ?int
    {
        return $this->minCurrentEnrollment;
    }

    public function setMinCurrentEnrollment(?int $minCurrentEnrollment): self
    {
        $this->minCurrentEnrollment = $minCurrentEnrollment;
        return $this;
    }

    public function getMaxCurrentEnrollment(): ?int
    {
        return $this->maxCurrentEnrollment;
    }

    public function setMaxCurrentEnrollment(?int $maxCurrentEnrollment): self
    {
        $this->maxCurrentEnrollment = $maxCurrentEnrollment;
        return $this;
    }

    public function getMinRepeaters(): ?int
    {
        return $this->minRepeaters;
    }

    public function setMinRepeaters(?int $minRepeaters): self
    {
        $this->minRepeaters = $minRepeaters;
        return $this;
    }

    public function getMaxRepeaters(): ?int
    {
        return $this->maxRepeaters;
    }

    public function setMaxRepeaters(?int $maxRepeaters): self
    {
        $this->maxRepeaters = $maxRepeaters;
        return $this;
    }

    public function getMinBoys(): ?int
    {
        return $this->minBoys;
    }

    public function setMinBoys(?int $minBoys): self
    {
        $this->minBoys = $minBoys;
        return $this;
    }

    public function getMaxBoys(): ?int
    {
        return $this->maxBoys;
    }

    public function setMaxBoys(?int $maxBoys): self
    {
        $this->maxBoys = $maxBoys;
        return $this;
    }

    public function getMinGirls(): ?int
    {
        return $this->minGirls;
    }

    public function setMinGirls(?int $minGirls): self
    {
        $this->minGirls = $minGirls;
        return $this;
    }

    public function getMaxGirls(): ?int
    {
        return $this->maxGirls;
    }

    public function setMaxGirls(?int $maxGirls): self
    {
        $this->maxGirls = $maxGirls;
        return $this;
    }

    public function getMinParents(): ?int
    {
        return $this->minParents;
    }

    public function setMinParents(?int $minParents): self
    {
        $this->minParents = $minParents;
        return $this;
    }

    public function getMaxParents(): ?int
    {
        return $this->maxParents;
    }

    public function setMaxParents(?int $maxParents): self
    {
        $this->maxParents = $maxParents;
        return $this;
    }

    public function getMinPaid(): ?int
    {
        return $this->minPaid;
    }

    public function setMinPaid(?int $minPaid): self
    {
        $this->minPaid = $minPaid;
        return $this;
    }

    public function getMaxPaid(): ?int
    {
        return $this->maxPaid;
    }

    public function setMaxPaid(?int $maxPaid): self
    {
        $this->maxPaid = $maxPaid;
        return $this;
    }

    public function getMinUnpaid(): ?int
    {
        return $this->minUnpaid;
    }

    public function setMinUnpaid(?int $minUnpaid): self
    {
        $this->minUnpaid = $minUnpaid;
        return $this;
    }

    public function getMaxUnpaid(): ?int
    {
        return $this->maxUnpaid;
    }

    public function setMaxUnpaid(?int $maxUnpaid): self
    {
        $this->maxUnpaid = $maxUnpaid;
        return $this;
    }

    public function getSchool(): ?School
    {
        return $this->school;
    }

    public function setSchool(?School $school): self
    {
        $this->school = $school;
        return $this;
    }

    public function getMinTeacher(): ?int
    {
        return $this->minTeacher;
    }

    public function setMinTeacher(?int $minTeacher): self
    {
        $this->minTeacher = $minTeacher;
        return $this;
    }

    public function getMaxTeacher(): ?int
    {
        return $this->maxTeacher;
    }

    public function setMaxTeacher(?int $maxTeacher): self
    {
        $this->maxTeacher = $maxTeacher;
        return $this;
    }

    public function getReportType(): ?string
    {
        return $this->reportType;
    }

    public function setReportType(?string $type): self
    {
        $this->reportType = $type;
        return $this;
    }
}
