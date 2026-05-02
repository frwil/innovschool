<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\ReportCardTemplateRepository;
use App\Entity\School;
use App\Entity\SchoolClassPeriod;
use App\Entity\EvaluationAppreciationTemplate;

#[ORM\Entity(repositoryClass: ReportCardTemplateRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_report_card_template_name', columns: ['name'])]
class ReportCardTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $name;

    #[ORM\Column(type: 'string', length: 1024, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $headerLeft = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $headerRight = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $nationalMottoLeft = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $nationalMottoRight = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $headerTitle = null;

    #[ORM\Column(type: 'text', nullable: true, options: ["default" => null])]
    private ?string $additionalHeaderLeft = null;

    #[ORM\Column(type: 'text', nullable: true, options: ["default" => null])]
    private ?string $additionalHeaderRight = null;

    #[ORM\Column(type: 'text', nullable: true, options: ["default" => null])]
    private ?string $schoolValuesLeft = null;

    #[ORM\Column(type: 'text', nullable: true, options: ["default" => null])]
    private ?string $schoolValuesRight = null;

    #[ORM\OneToMany(mappedBy: 'reportCardTemplate', targetEntity: School::class)]
    private Collection $schools;

    #[ORM\OneToMany(mappedBy: 'reportCardTemplate', targetEntity: SchoolClassPeriod::class)]
    private Collection $schoolClassPeriods;

    #[ORM\ManyToOne(targetEntity: EvaluationAppreciationTemplate::class)]
    #[ORM\JoinColumn(name: "evaluation_appreciation_template_id", referencedColumnName: "id", nullable: true)]
    private ?EvaluationAppreciationTemplate $evaluationAppreciationTemplate = null;

    public function __construct()
    {
        $this->schools = new ArrayCollection();
        $this->schoolClassPeriods = new ArrayCollection();
    }

    // Getters & Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getHeaderLeft(): ?string
    {
        return $this->headerLeft;
    }

    public function setHeaderLeft(?string $headerLeft): self
    {
        $this->headerLeft = $headerLeft;
        return $this;
    }

    public function getHeaderRight(): ?string
    {
        return $this->headerRight;
    }

    public function setHeaderRight(?string $headerRight): self
    {
        $this->headerRight = $headerRight;
        return $this;
    }

    public function getNationalMottoLeft(): ?string
    {
        return $this->nationalMottoLeft;
    }

    public function setNationalMottoLeft(?string $nationalMottoLeft): self
    {
        $this->nationalMottoLeft = $nationalMottoLeft;
        return $this;
    }

    public function getNationalMottoRight(): ?string
    {
        return $this->nationalMottoRight;
    }

    public function setNationalMottoRight(?string $nationalMottoRight): self
    {
        $this->nationalMottoRight = $nationalMottoRight;
        return $this;
    }

    public function getHeaderTitle(): ?string
    {
        return $this->headerTitle;
    }

    public function setHeaderTitle(?string $headerTitle): self
    {
        $this->headerTitle = $headerTitle;
        return $this;
    }

    public function getAdditionalHeaderLeft(): ?string
    {
        return $this->additionalHeaderLeft;
    }

    public function setAdditionalHeaderLeft(?string $additionalHeaderLeft): self
    {
        $this->additionalHeaderLeft = $additionalHeaderLeft;
        return $this;
    }

    public function getAdditionalHeaderRight(): ?string
    {
        return $this->additionalHeaderRight;
    }

    public function setAdditionalHeaderRight(?string $additionalHeaderRight): self
    {
        $this->additionalHeaderRight = $additionalHeaderRight;
        return $this;
    }

    public function getSchoolValuesLeft(): ?string
    {
        return $this->schoolValuesLeft;
    }

    public function setSchoolValuesLeft(?string $schoolValuesLeft): self
    {
        $this->schoolValuesLeft = $schoolValuesLeft;
        return $this;
    }

    public function getSchoolValuesRight(): ?string
    {
        return $this->schoolValuesRight;
    }

    public function setSchoolValuesRight(?string $schoolValuesRight): self
    {
        $this->schoolValuesRight = $schoolValuesRight;
        return $this;
    }

    /**
     * @return Collection<int, School>
     */
    public function getSchools(): Collection
    {
        return $this->schools;
    }

    public function addSchool(School $school): self
    {
        if (!$this->schools->contains($school)) {
            $this->schools[] = $school;
            $school->setReportCardTemplate($this);
        }
        return $this;
    }

    public function removeSchool(School $school): self
    {
        if ($this->schools->removeElement($school)) {
            if ($school->getReportCardTemplate() === $this) {
                $school->setReportCardTemplate(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, SchoolClassPeriod>
     */
    public function getSchoolClassPeriods(): Collection
    {
        return $this->schoolClassPeriods;
    }

    public function addSchoolClassPerod(SchoolClassPeriod $schoolClassPeriod): self
    {
        if (!$this->schoolClassPeriods->contains($schoolClassPeriod)) {
            $this->schoolClassPeriods[] = $schoolClassPeriod;
            $schoolClassPeriod->setReportCardTemplate($this);
        }
        return $this;
    }

    public function removeSchoolClassPeriod(SchoolClassPeriod $schoolClassPeriod): self
    {
        if ($this->schoolClassPeriods->removeElement($schoolClassPeriod)) {
            if ($schoolClassPeriod->getReportCardTemplate() === $this) {
                $schoolClassPeriod->setReportCardTemplate(null);
            }
        }
        return $this;
    }

    public function getEvaluationAppreciationTemplate(): ?EvaluationAppreciationTemplate
    {
        return $this->evaluationAppreciationTemplate;
    }

    public function setEvaluationAppreciationTemplate(?EvaluationAppreciationTemplate $evaluationAppreciationTemplate): self
    {
        $this->evaluationAppreciationTemplate = $evaluationAppreciationTemplate;
        return $this;
    }
}
