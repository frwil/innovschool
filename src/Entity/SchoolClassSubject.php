<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use App\Repository\SchoolClassSubjectRepository;
use Doctrine\Common\Collections\ArrayCollection;

#[ORM\Entity(repositoryClass: SchoolClassSubjectRepository::class)]
class SchoolClassSubject
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SchoolClassPeriod::class, inversedBy: 'schoolClassSubjects')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SchoolClassPeriod $schoolClassPeriod = null;

    #[ORM\ManyToOne(targetEntity: StudySubject::class, inversedBy: 'schoolClassSubjects')]
    #[ORM\JoinColumn(nullable: false)]
    private ?StudySubject $studySubject = null;

    #[ORM\ManyToOne(targetEntity: SubjectGroup::class, inversedBy: 'schoolClassSubjects')]
    #[ORM\JoinColumn(nullable: true)]
    private ?SubjectGroup $group = null;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $coefficient = 1;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'teacherSchoolClassSubjects')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $teacher = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $awaitedSkills = null;

    #[ORM\OneToMany(mappedBy: 'schoolClassSubject', targetEntity: SchoolClassSubjectEvaluationTimeNotApplicable::class)]
    private Collection $evaluationTimeNotApplicables;

    public function __construct()
    {
        $this->evaluationTimeNotApplicables = new ArrayCollection();
    }

    public function getEvaluationTimeNotApplicables(): Collection
    {
        return $this->evaluationTimeNotApplicables;
    }

    public function addEvaluationTimeNotApplicable(SchoolClassSubjectEvaluationTimeNotApplicable $evaluationTimeNotApplicable): self
    {
        if (!$this->evaluationTimeNotApplicables->contains($evaluationTimeNotApplicable)) {
            $this->evaluationTimeNotApplicables->add($evaluationTimeNotApplicable);
            $evaluationTimeNotApplicable->setSchoolClassSubject($this);
        }
        return $this;
    }

    public function removeEvaluationTimeNotApplicable(SchoolClassSubjectEvaluationTimeNotApplicable $evaluationTimeNotApplicable): self
    {
        if ($this->evaluationTimeNotApplicables->removeElement($evaluationTimeNotApplicable)) {
            if ($evaluationTimeNotApplicable->getSchoolClassSubject() === $this) {
                $evaluationTimeNotApplicable->setSchoolClassSubject(null);
            }
        }
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSchoolClassPeriod(): ?SchoolClassPeriod
    {
        return $this->schoolClassPeriod;
    }

    public function setSchoolClassPeriod(?SchoolClassPeriod $schoolClassPeriod): self
    {
        $this->schoolClassPeriod = $schoolClassPeriod;
        return $this;
    }

    public function getStudySubject(): ?StudySubject
    {
        return $this->studySubject;
    }

    public function setStudySubject(?StudySubject $studySubject): self
    {
        $this->studySubject = $studySubject;
        return $this;
    }

    public function getCoefficient(): int
    {
        return $this->coefficient;
    }

    public function setCoefficient(int $coefficient): self
    {
        $this->coefficient = $coefficient;
        return $this;
    }

    public function getGroup(): ?SubjectGroup
    {
        return $this->group;
    }

    public function setGroup(?SubjectGroup $group): self
    {
        $this->group = $group;
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

    public function getAwaitedSkills(): ?string
    {
        return $this->awaitedSkills;
    }

    public function setAwaitedSkills(?string $awaitedSkills): self
    {
        $this->awaitedSkills = $awaitedSkills;
        return $this;
    }
}
