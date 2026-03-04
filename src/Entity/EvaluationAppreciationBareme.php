<?php

namespace App\Entity;

use App\Repository\EvaluationAppreciationBaremeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EvaluationAppreciationBaremeRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_value_maxnote', columns: ["evaluation_appreciation_value", "evaluation_appreciation_max_note"])]
#[ORM\UniqueConstraint(name: 'unique_value_template', columns: ["evaluation_appreciation_value", "evaluation_appreciation_template_id"])]
class EvaluationAppreciationBareme
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: EvaluationAppreciationTemplate::class, inversedBy: 'baremes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?EvaluationAppreciationTemplate $evaluationAppreciationTemplate = null;

    #[ORM\Column(type: 'bigint')]
    private int $evaluationAppreciationMaxNote;

    #[ORM\Column(length: 255)]
    private string $evaluationAppreciationValue;

    #[ORM\Column(length: 255)]
    private string $evaluationAppreciationFullValue;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEvaluationAppreciationTemplate(): ?EvaluationAppreciationTemplate
    {
        return $this->evaluationAppreciationTemplate;
    }

    public function setEvaluationAppreciationTemplate(?EvaluationAppreciationTemplate $template): self
    {
        $this->evaluationAppreciationTemplate = $template;
        return $this;
    }

    public function getEvaluationAppreciationMaxNote(): int
    {
        return $this->evaluationAppreciationMaxNote;
    }

    public function setEvaluationAppreciationMaxNote(int $maxNote): self
    {
        $this->evaluationAppreciationMaxNote = $maxNote;
        return $this;
    }

    public function getEvaluationAppreciationValue(): string
    {
        return $this->evaluationAppreciationValue;
    }

    public function setEvaluationAppreciationValue(string $value): self
    {
        $this->evaluationAppreciationValue = $value;
        return $this;
    }

    public function getEvaluationAppreciationFullValue(): string
    {
        return $this->evaluationAppreciationFullValue;
    }

    public function setEvaluationAppreciationFullValue(string $fullValue): self
    {
        $this->evaluationAppreciationFullValue = $fullValue;
        return $this;
    }
}