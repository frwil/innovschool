<?php

namespace App\Form;

use App\Entity\SchoolEvaluation;
use App\Entity\SchoolEvaluationFrame;
use App\Entity\SchoolEvaluationTime;
use App\Entity\SchoolPeriod;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SchoolEvaluationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('frame', null, [
                'label' => 'Semestre',
                'required' => true,
            ])
            ->add('time', null, [
                'label' => 'Séquence',
                'required' => true,
            ])
            ->add('period', null, [
                'label' => 'Année scolaire',
                'required' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SchoolEvaluation::class,
        ]);
    }
}
