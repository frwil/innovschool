<?php

namespace App\Form;

use App\Entity\School;
use App\Entity\SchoolClassPeriod;
use App\Entity\SchoolPeriod;
use App\Entity\Classe;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SchoolClassReportFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('slug')
            ->add('school', EntityType::class, [
                'class' => School::class,
                'choice_label' => 'id',
            ])
            ->add('period', EntityType::class, [
                'class' => SchoolPeriod::class,
                'choice_label' => 'id',
            ])
            ->add('sectionCategory', EntityType::class, [
                'class' => Classe::class,
                'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SchoolClassPeriod::class,
        ]);
    }
}
