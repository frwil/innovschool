<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'operation_logs')]
class OperationLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $operationType; // Type d'opération (ajout, modification, suppression)

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $entityName = null; // Nom de l'entité concernée

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $entityId = null; // ID de l'entité concernée

    #[ORM\Column(type: 'string', length: 255)]
    private string $status; // Statut de l'opération (success, error)

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null; // Message d'erreur (si statut = error)

    #[ORM\Column(type: 'datetime')]
    private \DateTime $timestamp; // Date et heure de l'opération

    #[ORM\Column(type: 'string', length: 255)]
    private string $performedBy; // Utilisateur ayant effectué l'opération

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $additionalData = null; // Données supplémentaires (JSON ou texte)

    // Getters et setters...

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setOperationType(string $operationType): self
    {
        $this->operationType = $operationType;
        return $this;
    }

    public function getOperationType(): string
    {
        return $this->operationType;
    }

    public function setEntityName(?string $entityName): self
    {
        $this->entityName = $entityName;
        return $this;
    }

    public function getEntityName(): ?string
    {
        return $this->entityName;
    }

    public function setEntityId(?int $entityId): self
    {
        $this->entityId = $entityId;
        return $this;
    }

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setTimestamp(\DateTime $timestamp): self
    {
        $this->timestamp = $timestamp;
        return $this;
    }

    public function getTimestamp(): \DateTime
    {
        return $this->timestamp;
    }

    public function setPerformedBy(string $performedBy): self
    {
        $this->performedBy = $performedBy;
        return $this;
    }

    public function getPerformedBy(): string
    {
        return $this->performedBy;
    }

    public function setAdditionalData(?string $additionalData): self
    {
        $this->additionalData = $additionalData;
        return $this;
    }

    public function getAdditionalData(): ?string
    {
        return $this->additionalData;
    }
}
