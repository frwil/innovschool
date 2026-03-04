<?php

namespace App\Service;

use App\Entity\School;
use App\Entity\SchoolPeriod;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Repository\SchoolRepository;
use App\Repository\SchoolPeriodRepository;

class BulletinContextService
{
    private ?School $currentSchool = null;
    private ?SchoolPeriod $currentPeriod = null;

    public function __construct(
    private EntityManagerInterface $entityManager,
    private RequestStack $requestStack, // Utiliser RequestStack au lieu de Session
    private SchoolRepository $schoolRepo,
    private SchoolPeriodRepository $periodRepo
) {}

    public function initializeFromSession(): void
    {
        $schoolId = $this->requestStack->getSession()->get('school_id');
        $periodId = $this->requestStack->getSession()->get('period_id');

        if ($schoolId) {
            $this->currentSchool = $this->entityManager->getRepository(School::class)->find($schoolId);
        }

        if ($periodId) {
            $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($periodId);
        }
    }

    public function getCurrentSchool(): School
    {
        if (!$this->currentSchool) {
            throw new \RuntimeException('Aucune école définie dans le contexte');
        }
        return $this->currentSchool;
    }

    public function getCurrentPeriod(): SchoolPeriod
    {
        if (!$this->currentPeriod) {
            throw new \RuntimeException('Aucune période définie dans le contexte');
        }
        return $this->currentPeriod;
    }

    public function getCurrentUser()
    {
        // À adapter selon votre système d'authentification
        return null;
    }
}