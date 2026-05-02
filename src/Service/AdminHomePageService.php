<?php

namespace App\Service;

use App\Contract\UserRoleEnum;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class AdminHomePageService
{

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function getModel(): AdminHomePageDTO
    {
        $model = new AdminHomePageDTO();
        $countStudents = $this->entityManager->getRepository(User::class)->count(['roles' => [UserRoleEnum::STUDENT->value]]);
        $model->students = $countStudents;
        $countTeachers = $this->entityManager->getRepository(User::class)->count(['roles' => [UserRoleEnum::TEACHER->value]]);
        $model->teachers = $countTeachers;
        $countTutors = $this->entityManager->getRepository(User::class)->count(['roles' => [UserRoleEnum::TUTOR->value]]);
        $model->tutors = $countTutors;
        

        return $model;
    }
}
