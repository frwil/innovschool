<?php

namespace App\Form;

use App\Entity\SchoolClassPeriod;
use App\Entity\SchoolEvaluation;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchSubjectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('evaluation', EntityType::class, [
                'class' => SchoolEvaluation::class,
                'label' => false,
                'placeholder' => 'Selectionner une evaluation',
                'required' => true,
            ])
            ->add('schoolClassPeriod', EntityType::class, [
                'class' => SchoolClassPeriod::class,
                'choice_label' => 'name',
                'label' => false,
                'placeholder' => 'Selectionner une classe',
                'required' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}
