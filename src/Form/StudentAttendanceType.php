<?php

namespace App\Form;

use App\Entity\SchoolClassPeriod;
use App\Entity\StudentAttendance;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StudentAttendanceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('abscence', null, [
                'label' => false,
            ])
            ->add('student', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'fullName',
                'attr' => [
                    'style' => 'appearance: none; border: none; background: transparent; pointer-events: none; font-size: inherit; color: inherit;'
                ],
                'label' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => StudentAttendance::class,
        ]);
    }
}
