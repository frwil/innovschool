<?php

namespace App\Form;

use App\Entity\School;
use App\Entity\SchoolClassPeriod;
use App\Entity\SchoolPeriod;
use App\Entity\Classe;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SchoolAttendanceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('studentAttendances', CollectionType::class, [
                'entry_type' => StudentAttendanceType::class,
                'entry_options' => ['label' => false],
                'by_reference' => false,
                'mapped' => false,
                'data' => $options['studentAttendances'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SchoolClassPeriod::class,
            'studentAttendances' => [],
        ]);
    }
}
