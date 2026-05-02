<?php

namespace App\Contract;

enum UserRoleEnum: string
{
    case ADMIN = 'ROLE_ADMIN';
    case USER = 'ROLE_USER';
    case STUDENT = 'ROLE_STUDENT';
    case TEACHER = 'ROLE_TEACHER';
    case TUTOR = 'ROLE_TUTOR';
    case FATHER = 'ROLE_FATHER';
    case MOTHER = 'ROLE_MOTHER';
    case EMPLOYEE = 'ROLE_EMPLOYEE';
    case GUEST = 'ROLE_GUEST';
    case SUPER_ADMIN = 'ROLE_SUPER_ADMIN';

    public static function getTitle(): string
    {
        return match (self::class) {
            self::ADMIN => 'Administrateur',
            self::USER => 'user',
            self::STUDENT => 'Etudiant',
            self::TEACHER => 'Enseignant',
            self::TUTOR => 'Tuteur',
            self::FATHER => 'Père',
            self::MOTHER => 'Mère',
            self::EMPLOYEE => 'Employé',
            self::GUEST => 'visiteur',
            self::SUPER_ADMIN => 'Super-admin',
        };
    }

    public static function getTitleFrom($role): ?string
    {
        
        return match ($role) {
            self::ADMIN->value => 'Administrateur',
            self::USER->value => null,
            self::STUDENT->value => 'Etudiant',
            self::TEACHER->value => 'Enseignant',
            self::TUTOR->value => 'Tuteur',
            self::FATHER->value => 'Père',
            self::MOTHER->value => 'Mère',
            self::EMPLOYEE->value => 'Employé',
            self::GUEST->value => 'visiteur',
            self::SUPER_ADMIN->value => 'Super-admin',
            default => '',
        };
    }

    public static function getRole(string $name): self
    {
        return match ($name) {
            'admin' => self::ADMIN,
            'user' => self::USER,
            'student' => self::STUDENT,
            'teacher' => self::TEACHER,
            'tutor' => self::TUTOR,
            'father' => self::FATHER,
            'mother' => self::MOTHER,
            'employee' => self::EMPLOYEE,
            'guest' => self::GUEST,
            'super-admin' => self::SUPER_ADMIN,
        };
    }
}