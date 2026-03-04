<?php

namespace App\Entity;

use App\Repository\SchoolEvaluationTimeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\SchoolEvaluationFrame;

#[ORM\Entity(repositoryClass: SchoolEvaluationTimeRepository::class)]
class SchoolEvaluationTime
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    #[ORM\Column(length: 20)]
    private ?string $shortName = null;

    /**
     * @var Collection<int, SchoolEvaluation>
     */
    #[ORM\OneToMany(targetEntity: SchoolEvaluation::class, mappedBy: 'time')]
    private Collection $schoolEvaluations;

    #[ORM\OneToMany(mappedBy: 'time', targetEntity: Evaluation::class, cascade: ['persist', 'remove'])]
    private Collection $evaluations;

    #[ORM\ManyToOne(targetEntity: SchoolEvaluationFrame::class, inversedBy: 'evaluationTimes')]
    #[ORM\JoinColumn(nullable: true)]
    private ?SchoolEvaluationFrame $evaluationFrame = null;

    #[ORM\ManyToOne(targetEntity: SchoolEvaluationTimeType::class, inversedBy: 'evaluationTimes')]
    #[ORM\JoinColumn(nullable: true)]
    private ?SchoolEvaluationTimeType $type = null;

    #[ORM\OneToMany(mappedBy: 'time', targetEntity: StudentClassAttendance::class)]
    private Collection $attendances;

    #[ORM\OneToMany(mappedBy: 'schoolEvaluationTime', targetEntity: StudentClassTimetablePresence::class)]
    private Collection $presences;

    #[ORM\OneToMany(mappedBy: 'schoolEvaluationTime', targetEntity: SchoolClassSubjectEvaluationTimeNotApplicable::class)]
    private Collection $subjectNotApplicables;

    public function __construct()
    {
        $this->schoolEvaluations = new ArrayCollection();
        $this->evaluations = new ArrayCollection();
        $this->attendances = new ArrayCollection();
        $this->presences = new ArrayCollection();
        $this->subjectNotApplicables = new ArrayCollection();

        if ($this->shortName === null && $this->name !== null) {
            $this->shortName = $this->generateInitials($this->name);
        }
    }

    public function getSubjectNotApplicables(): Collection
    {
        return $this->subjectNotApplicables;
    }

    public function addSubjectNotApplicable(SchoolClassSubjectEvaluationTimeNotApplicable $subjectNotApplicable): self
    {
        if (!$this->subjectNotApplicables->contains($subjectNotApplicable)) {
            $this->subjectNotApplicables->add($subjectNotApplicable);
            $subjectNotApplicable->setSchoolEvaluationTime($this);
        }
        return $this;
    }

    public function removeSubjectNotApplicable(SchoolClassSubjectEvaluationTimeNotApplicable $subjectNotApplicable): self
    {
        if ($this->subjectNotApplicables->removeElement($subjectNotApplicable)) {
            if ($subjectNotApplicable->getSchoolEvaluationTime() === $this) {
                $subjectNotApplicable->setSchoolEvaluationTime(null);
            }
        }
        return $this;
    }

    /**
     * Récupère la collection des évaluations non applicables filtrées par le time_id
     * 
     * @param int $timeId L'ID de la sous-période d'évaluation (SchoolEvaluationTime)
     * @return Collection<int, SchoolClassSubjectEvaluationTimeNotApplicable>
     */
    public function getEvaluationTimeNotApplicablesByTime(int $timeId): Collection
    {
        return $this->subjectNotApplicables->filter(
            function (SchoolClassSubjectEvaluationTimeNotApplicable $notApplicable) use ($timeId) {
                return $notApplicable->getSchoolEvaluationTime() &&
                    $notApplicable->getSchoolEvaluationTime()->getId() === $timeId;
            }
        );
    }

    /**
     * Vérifie si la matière est non applicable pour une sous-période spécifique
     * 
     * @param int $timeId L'ID de la sous-période d'évaluation
     * @return bool True si la matière est marquée comme non applicable, false sinon
     */
    public function isNotApplicableForTime(int $timeId): bool
    {
        $notApplicables = $this->getEvaluationTimeNotApplicablesByTime($timeId);

        if ($notApplicables->isEmpty()) {
            return false; // Par défaut, la matière est applicable si aucune entrée n'existe
        }

        // Retourne la valeur de notApplicable du premier élément trouvé
        return $notApplicables->first()->isNotApplicable();
    }

    /**
     * Vérifie si la matière est applicable pour une sous-période spécifique
     * 
     * @param int $timeId L'ID de la sous-période d'évaluation
     * @return bool True si la matière est applicable, false sinon
     */
    public function isApplicableForTime(int $timeId): bool
    {
        return !$this->isNotApplicableForTime($timeId);
    }

    /**
     * Récupère tous les IDs des périodes d'évaluation où la matière est marquée comme non applicable
     * 
     * @return array<int> Tableau d'IDs des SchoolEvaluationTime où la matière n'est pas applicable
     */
    public function getNotApplicableTimeIds(): array
    {
        $timeIds = [];

        foreach ($this->subjectNotApplicables as $notApplicable) {
            if ($notApplicable->isNotApplicable() && $notApplicable->getSchoolEvaluationTime()) {
                $timeIds[] = $notApplicable->getSchoolEvaluationTime()->getId();
            }
        }

        return $timeIds;
    }

    /**
     * Récupère la collection des matières non applicables filtrées par le school_class_subject_id
     * 
     * @param int $schoolClassSubjectId L'ID de la matière dans une classe (SchoolClassSubject)
     * @return Collection<int, SchoolClassSubjectEvaluationTimeNotApplicable>
     */
    public function getSubjectNotApplicablesBySchoolClassSubject(int $schoolClassSubjectId): Collection
    {
        return $this->subjectNotApplicables->filter(
            function (SchoolClassSubjectEvaluationTimeNotApplicable $notApplicable) use ($schoolClassSubjectId) {
                return $notApplicable->getSchoolClassSubject() &&
                    $notApplicable->getSchoolClassSubject()->getId() === $schoolClassSubjectId;
            }
        );
    }

    /**
     * Vérifie si une matière spécifique est non applicable pour cette période
     * 
     * @param int $schoolClassSubjectId L'ID de la matière dans une classe
     * @return bool True si la matière est marquée comme non applicable, false sinon
     */
    public function isSubjectNotApplicable(int $schoolClassSubjectId): bool
    {
        $notApplicables = $this->getSubjectNotApplicablesBySchoolClassSubject($schoolClassSubjectId);

        if ($notApplicables->isEmpty()) {
            return false; // Par défaut, la matière est applicable si aucune entrée n'existe
        }

        // Retourne la valeur de notApplicable du premier élément trouvé
        return $notApplicables->first()->isNotApplicable();
    }

    /**
     * Vérifie si une matière spécifique est applicable pour cette période
     * 
     * @param int $schoolClassSubjectId L'ID de la matière dans une classe
     * @return bool True si la matière est applicable, false sinon
     */
    public function isSubjectApplicable(int $schoolClassSubjectId): bool
    {
        return !$this->isSubjectNotApplicable($schoolClassSubjectId);
    }

    /**
     * Vérifie si une matière (objet) est non applicable pour cette période
     * 
     * @param SchoolClassSubject $schoolClassSubject L'objet matière-classe
     * @return bool True si la matière est marquée comme non applicable, false sinon
     */
    public function isSchoolClassSubjectNotApplicable(SchoolClassSubject $schoolClassSubject): bool
    {
        return $this->isSubjectNotApplicable($schoolClassSubject->getId());
    }

    /**
     * Récupère tous les IDs des matières-classe qui sont marquées comme non applicables pour cette période
     * 
     * @return array<int> Tableau d'IDs des SchoolClassSubject qui ne sont pas applicables
     */
    public function getNotApplicableSchoolClassSubjectIds(): array
    {
        $subjectIds = [];

        foreach ($this->subjectNotApplicables as $notApplicable) {
            if ($notApplicable->isNotApplicable() && $notApplicable->getSchoolClassSubject()) {
                $subjectIds[] = $notApplicable->getSchoolClassSubject()->getId();
            }
        }

        return $subjectIds;
    }

    /**
     * Récupère toutes les matières-classe qui sont marquées comme non applicables pour cette période
     * 
     * @return array<SchoolClassSubject> Tableau des SchoolClassSubject qui ne sont pas applicables
     */
    public function getNotApplicableSchoolClassSubjects(): array
    {
        $subjects = [];

        foreach ($this->subjectNotApplicables as $notApplicable) {
            if ($notApplicable->isNotApplicable() && $notApplicable->getSchoolClassSubject()) {
                $subjects[] = $notApplicable->getSchoolClassSubject();
            }
        }

        return $subjects;
    }

    /**
     * Récupère toutes les matières-classe qui sont applicables pour cette période (à partir d'une liste donnée)
     * 
     * @param array<SchoolClassSubject> $allSubjects Toutes les matières-classe possibles
     * @return array<SchoolClassSubject> Tableau des SchoolClassSubject qui sont applicables
     */
    public function getApplicableSchoolClassSubjects(array $allSubjects): array
    {
        $applicableSubjects = [];
        $notApplicableSubjectIds = $this->getNotApplicableSchoolClassSubjectIds();

        foreach ($allSubjects as $subject) {
            if (!in_array($subject->getId(), $notApplicableSubjectIds)) {
                $applicableSubjects[] = $subject;
            }
        }

        return $applicableSubjects;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        // Générer les initiales si shortName n'est pas déjà défini
        if ($this->shortName === null) {
            $this->shortName = $this->generateInitials($name);
        }
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    public function getShortName(): ?string
    {
        return $this->shortName;
    }

    public function setShortName(string $shortName): self
    {
        $this->shortName = $shortName;
        return $this;
    }

    /**
     * @return Collection<int, SchoolEvaluation>
     */
    public function getSchoolEvaluations(): Collection
    {
        return $this->schoolEvaluations;
    }

    public function addSchoolEvaluation(SchoolEvaluation $schoolEvaluation): self
    {
        if (!$this->schoolEvaluations->contains($schoolEvaluation)) {
            $this->schoolEvaluations->add($schoolEvaluation);
            $schoolEvaluation->setTime($this);
        }

        return $this;
    }

    public function removeSchoolEvaluation(SchoolEvaluation $schoolEvaluation): self
    {
        if ($this->schoolEvaluations->removeElement($schoolEvaluation)) {
            // set the owning side to null (unless already changed)
            if ($schoolEvaluation->getTime() === $this) {
                $schoolEvaluation->setTime(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Evaluation>
     */
    public function getEvaluations(): Collection
    {
        return $this->evaluations;
    }

    public function addEvaluation(Evaluation $evaluation): self
    {
        if (!$this->evaluations->contains($evaluation)) {
            $this->evaluations[] = $evaluation;
            $evaluation->setTime($this);
        }

        return $this;
    }

    public function removeEvaluation(Evaluation $evaluation): self
    {
        if ($this->evaluations->removeElement($evaluation)) {
            // Set the owning side to null (unless already changed)
            if ($evaluation->getTime() === $this) {
                $evaluation->setTime(null);
            }
        }

        return $this;
    }

    public function getEvaluationFrame(): ?SchoolEvaluationFrame
    {
        return $this->evaluationFrame;
    }

    public function setEvaluationFrame(?SchoolEvaluationFrame $evaluationFrame): self
    {
        $this->evaluationFrame = $evaluationFrame;
        return $this;
    }

    public function getType(): ?SchoolEvaluationTimeType
    {
        return $this->type;
    }

    public function setType(?SchoolEvaluationTimeType $type): self
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return Collection<int, StudentClassAttendance>
     */
    public function getAttendances(): Collection
    {
        return $this->attendances;
    }

    public function addAttendance(StudentClassAttendance $attendance): self
    {
        if (!$this->attendances->contains($attendance)) {
            $this->attendances[] = $attendance;
            $attendance->setTime($this);
        }
        return $this;
    }

    public function removeAttendance(StudentClassAttendance $attendance): self
    {
        if ($this->attendances->removeElement($attendance)) {
            // set the owning side to null (unless already changed)
            if ($attendance->getTime() === $this) {
                $attendance->setTime(null);
            }
        }
        return $this;
    }


    /**
     * @return Collection<int, StudentClassTimetablePresence>
     */
    public function getPresences(): Collection
    {
        return $this->presences;
    }

    public function addPresence(StudentClassTimetablePresence $presence): self
    {
        if (!$this->presences->contains($presence)) {
            $this->presences[] = $presence;
            $presence->setSchoolEvaluationTime($this);
        }
        return $this;
    }

    public function removePresence(StudentClassTimetablePresence $presence): self
    {
        if ($this->presences->removeElement($presence)) {
            // set the owning side to null (unless already changed)
            if ($presence->getSchoolEvaluationTime() === $this) {
                $presence->setSchoolEvaluationTime(null);
            }
        }
        return $this;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }

    private function generateInitials(string $name): string
    {
        $words = preg_split('/\s+/', trim($name));
        $initials = '';
        foreach ($words as $word) {
            if (isset($word[0])) {
                $initials .= strtoupper($word[0]);
            }
        }
        return $initials;
    }

    // Méthodes utilitaires supplémentaires

    public function getEvaluationCount(): int
    {
        return $this->evaluations->count();
    }

    public function getAttendanceCount(): int
    {
        return $this->attendances->count();
    }

    public function getPresenceCount(): int
    {
        return $this->presences->count();
    }

    public function isActive(): bool
    {
        // Implémentez votre logique pour déterminer si cette période d'évaluation est active
        // Par exemple, basé sur des dates ou un statut
        return true; // À adapter selon vos besoins
    }

    public function getFullName(): string
    {
        $frameName = $this->evaluationFrame ? $this->evaluationFrame->getName() : '';
        $typeName = $this->type ? $this->type->getName() : '';

        return trim("{$frameName} - {$typeName} - {$this->name}");
    }
}
