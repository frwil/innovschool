<?php

namespace App\Form;

use App\Entity\Role;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RoleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, [
                'label' => 'Nom technique (ROLE_…)',
                'disabled' => $options['lock_name'],
                'attr' => ['placeholder' => 'ROLE_CAISSIER'],
            ])
            ->add('label', null, [
                'label' => 'Libellé',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
            ])
            ->add('dataScope', ChoiceType::class, [
                'label' => 'Périmètre de données',
                'choices' => [
                    'Aucune restriction' => Role::SCOPE_NONE,
                    'Restreint à ses classes et matières' => Role::SCOPE_OWN,
                    'Ses classes + toutes les matières (lecture seule hors charge)' => Role::SCOPE_OWN_CLASSES,
                ],
                'expanded' => false,
                'multiple' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Role::class,
            'lock_name' => false,
            // La matrice (permissions[]/mandatory[]) est postée avec le formulaire
            // sans être un champ Symfony : traitée par le contrôleur.
            'allow_extra_fields' => true,
        ]);
    }
}
