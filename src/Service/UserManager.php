<?php

namespace App\Service;

use App\Contract\UserRoleEnum;
use App\Entity\SchoolClassPeriod;
use App\Entity\SchoolPeriod;
use App\Entity\StudentClass;
use App\Entity\TeacherClass;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserManager
{
    private SchoolPeriod $schoolPeriod;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $userPasswordHasher,
        private EmailGeneratorService $emailGenerator,
        private RegistrationNumberGenerator $registrationNumberGenerator,
    ) {

        $this->schoolPeriod = $entityManager->getRepository(SchoolPeriod::class)->findOneBy(['enabled' => true]);
    }

    public function saveUser(User $user, UserRoleEnum $role, FormInterface $form): void
    {
        $user->setPassword($this->userPasswordHasher->hashPassword($user, Utils::getDefaultPassword()));

        if (null === $user->getEmail()) {
            $user->setEmail(
                $this->emailGenerator->generateEmail($user->getFullName())
            );
        }

        $user->setUsername($user->getEmail());

        if ($role === UserRoleEnum::TEACHER) {
            $this->saveTeacher($user, $form);
            return;
        }

        if ($role === UserRoleEnum::STUDENT) {
            $this->saveStudent($user, $form);
            return;
        }
    }

    private function saveTeacher(User $user, FormInterface $form): void
    {
        $this->setRegistractionNumber($user);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        /** @var array>int> */
        $classesIds = $form->get('classes')->getData();
        $classes = $this->entityManager->getRepository(SchoolClassPeriod::class)->findBy(['id' => $classesIds]);
        foreach ($classes as $key => $class) {

            $teacherClass = (new TeacherClass())
                ->setSchoolClass($class)
                ->setTeacher($user)
                ->setPeriod($this->schoolPeriod);
            $this->entityManager->persist($teacherClass);
        }
        $this->entityManager->flush();
    }

    private function saveStudent(User $user, FormInterface $form): void
    {

        $tutor = $user->getTutor();
        $tutor = $this->entityManager->getRepository(User::class)->findByRoleAndSchoolAnsPhone(UserRoleEnum::TUTOR->value, $this->currentSchool, $tutor->getPhone());
        
        if (null === $tutor) {
            $user->getTutor()->addRole(UserRoleEnum::TUTOR->value)
                ->setEmail($this->emailGenerator->generateEmail($user->getTutor()->getFullName()))
                ->setSchool($this->currentSchool)
                ->setUsername($user->getTutor()->getEmail())
                ->setPassword($this->userPasswordHasher->hashPassword($user->getTutor(), Utils::getDefaultPassword()))
                ;
            $this->setRegistractionNumber($user->getTutor());
            $this->entityManager->persist($user->getTutor());
            $this->entityManager->flush();
        }

        if(null !== $tutor){
            $user->setTutor($tutor);
        }
        
        $this->setRegistractionNumber($user);
            
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $classesId = $form->get('studentClasses')->getData();
        $classe = $this->entityManager->getRepository(SchoolClassPeriod::class)->findOneBy(['id' => $classesId]);
        $studentClass = (new StudentClass())
            ->setSchoolClass($classe)
            ->setStudent($user)
            ->setPeriod($this->schoolPeriod);
        $this->entityManager->persist($studentClass);
        $this->entityManager->flush();
    }

    public function setRegistractionNumber(User $user): void
    {
        /** @var \App\Entity\User | null */
        $lastUser = $this->entityManager->getRepository(User::class)->findOneBy([], ['id' => 'DESC']);
        $number = 1;
        if(null == $lastUser){
            $number = rand(1, 100);
        }else {
            $number = $lastUser->getId() + 1;
        }
        $user->setRegistrationNumber(
            $this->registrationNumberGenerator->generate(
                $this->currentSchool->getAcronym(),
                $number
            )
        );
    }
}
