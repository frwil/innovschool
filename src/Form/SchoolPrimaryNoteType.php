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

class SchoolPrimaryNoteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('studentNotes', CollectionType::class, [
                'entry_type' => PrimaryGradeType::class,
                'entry_options' => ['label' => false],
                'by_reference' => false,
                'mapped' => false,
                'data' => $options['studentNotes'],
                'entry_options' => [
                    'subject' => $options['subject'], // <-- on passe ici
                ],
            ]);;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SchoolClassPeriod::class,
            'studentNotes' => [],
            'subject' => null,
        ]);
    }
}
