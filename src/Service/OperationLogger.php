<?php

namespace App\Service;

use App\Entity\OperationLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class OperationLogger
{
    private EntityManagerInterface $entityManager;
    private Security $security;

    public function __construct(EntityManagerInterface $entityManager, Security $security)
    {
        $this->entityManager = $entityManager;
        $this->security = $security;
    }

    public function log(
        string $operationType,
        string $status,
        ?string $entityName = null,
        ?int $entityId = null,
        ?string $errorMessage = null,
        ?array $additionalData = null
    ): void {
        $log = new OperationLog();
        $log->setOperationType($operationType);
        $log->setStatus($status);
        $log->setEntityName($entityName);
        $log->setEntityId($entityId);
        $log->setErrorMessage($errorMessage);
        $log->setTimestamp(new \DateTime());
        $log->setPerformedBy($this->security->getUser()->getUsername() ?? 'Anonymous');
        $log->setAdditionalData($additionalData ? json_encode($additionalData) : null);

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }
}
