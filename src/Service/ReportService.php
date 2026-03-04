<?php

namespace App\Service;

use App\Contract\UserRoleEnum;
use App\Entity\SchoolPeriod;
use App\Entity\User;
use App\Repository\SchoolClassRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class ReportService
{


    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private SchoolClassRepository $schoolClassRepository,
        private Security $security,
    ) {}

    public function teacherCount(): int
    {
        /** @var \App\Entity\User */
        $user = $this->security->getUser();
        // TODO créer une method cout by role & school
        $teachers = $this->userRepository->findByRoleAndSchool(UserRoleEnum::TEACHER->value, $this->currentSchool);
        return sizeof($teachers);
    }

    public function studentCount(): int
    {
        /** @var \App\Entity\User */
        $user = $this->security->getUser();
        // TODO créer une method cout by role & school
        $teachers = $this->userRepository->findByRoleAndSchool(UserRoleEnum::STUDENT->value, $this->currentSchool);
        return sizeof($teachers);
    }

    public function parentCount(): int
    {
        /** @var \App\Entity\User */
        $user = $this->security->getUser();
        // TODO créer une method cout by role & school
        $teachers = $this->userRepository->findByRoleAndSchool(UserRoleEnum::TUTOR->value, $this->currentSchool);
        return sizeof($teachers);
    }

    public function schoolClassCount(): int
    {
        /** @var \App\Entity\User */
        $user = $this->security->getUser();
        $period = $this->entityManager->getRepository(SchoolPeriod::class)->findOneBy(['enabled' => true]);
        $schoolClassPeriod = $this->schoolClassRepository->findBy([
            'period' => $period, 'school' => $this->currentSchool
        ]);
        return sizeof($schoolClassPeriod);
    }
}
