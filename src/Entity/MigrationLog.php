<?php

namespace App\Entity;

use App\Repository\MigrationLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MigrationLogRepository::class)]
#[ORM\Table(name: 'migration_log')]
class MigrationLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?School $school = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?SchoolPeriod $sourcePeriod = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?SchoolPeriod $targetPeriod = null;

    #[ORM\Column]
    private float $passingGrade = 10.0;

    #[ORM\Column(type: 'json')]
    private array $options = [];

    /** IDs de tout ce qui a été créé : subjectGroups, schoolClassPeriods, schoolClassSubjects, classSubjectModules, paymentModals, studentClasses */
    #[ORM\Column(type: 'json')]
    private array $createdIds = [];

    /** executed | cancelled | corrected */
    #[ORM\Column(length: 20)]
    private string $status = 'executed';

    #[ORM\Column]
    private \DateTimeImmutable $executedAt;

    #[ORM\Column(length: 255)]
    private string $executedBy = '';

    /** Résumé de config (classes, matières…) */
    #[ORM\Column(type: 'json')]
    private array $configSummary = [];

    /** Résumé élèves (promus, redoublants, ignorés) */
    #[ORM\Column(type: 'json')]
    private array $studentStats = [];

    public function __construct()
    {
        $this->executedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getSchool(): ?School { return $this->school; }
    public function setSchool(?School $school): static { $this->school = $school; return $this; }

    public function getSourcePeriod(): ?SchoolPeriod { return $this->sourcePeriod; }
    public function setSourcePeriod(?SchoolPeriod $p): static { $this->sourcePeriod = $p; return $this; }

    public function getTargetPeriod(): ?SchoolPeriod { return $this->targetPeriod; }
    public function setTargetPeriod(?SchoolPeriod $p): static { $this->targetPeriod = $p; return $this; }

    public function getPassingGrade(): float { return $this->passingGrade; }
    public function setPassingGrade(float $g): static { $this->passingGrade = $g; return $this; }

    public function getOptions(): array { return $this->options; }
    public function setOptions(array $o): static { $this->options = $o; return $this; }

    public function getCreatedIds(): array { return $this->createdIds; }
    public function setCreatedIds(array $ids): static { $this->createdIds = $ids; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $s): static { $this->status = $s; return $this; }

    public function getExecutedAt(): \DateTimeImmutable { return $this->executedAt; }
    public function setExecutedAt(\DateTimeImmutable $d): static { $this->executedAt = $d; return $this; }

    public function getExecutedBy(): string { return $this->executedBy; }
    public function setExecutedBy(string $s): static { $this->executedBy = $s; return $this; }

    public function getConfigSummary(): array { return $this->configSummary; }
    public function setConfigSummary(array $s): static { $this->configSummary = $s; return $this; }

    public function getStudentStats(): array { return $this->studentStats; }
    public function setStudentStats(array $s): static { $this->studentStats = $s; return $this; }
}
