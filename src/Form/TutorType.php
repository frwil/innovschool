<?php

namespace App\Form;

use App\Contract\GenderEnum;
use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfonycasts\DynamicForms\DependentField;
use Symfonycasts\DynamicForms\DynamicFormBuilder;

class TutorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
         /** @var \App\Entity\User */
         $user = $builder->getData();

        $builder = new DynamicFormBuilder($builder);

        $builder
            ->add('phone', null, [
                'label' => 'Téléphone',
            ])
            ->addDependent('fullName', 'phone', function (DependentField $field, $phone) use ($user) {
                if (null === $phone) return;
                $field->add(null, [
                    'label' => 'Nom complet',
                    'data' => $user ? $user->getFullName() : null,
                    'required' => false,
                ]);
            })
            ->addDependent('address', 'phone', function (DependentField $field, $phone) use ($user) {
                if (null === $phone) return;
                $field->add(null, [
                    'label' => 'Adresse',
                    'data' => $user ? $user->getAddress() : null,
                    'required' => false,
                ]);
            })
            ->addDependent('gender', 'phone', function (DependentField $field, $phone) use ($user) {
                if (null === $phone) return;
                $field->add(ChoiceType::class, [
                    'label' => 'Sexe',
                    'choices' => [
                        'Masculin' => GenderEnum::MALE,
                        'Féminin' => GenderEnum::FEMALE,
                    ],
                    'data' => $user ? $user->getGender() : null,
                ]);
            })
            ->addDependent('dateOfBirth', 'phone', function (DependentField $field, $phone) use ($user) {
                if (null === $phone) return;
                $field->add(null, [
                    'label' => 'Date de naissance',
                    'widget' => 'single_text',
                    'data' => $user ? $user->getDateOfBirth() : null,
                    'required' => false,
                ]);
            })
            ->addDependent('religion', 'phone', function (DependentField $field, $phone) use ($user) {
                if (null === $phone) return;
                $field->add(null, [
                    'label' => 'Religion',
                    'data' => $user ? $user->getReligion() : null,
                    'required' => false,
                ]);
            })
            ->addDependent('infos', 'phone', function (DependentField $field, $phone) use ($user) {
                if (null === $phone) return;
                $field->add(null, [
                    'label' => 'Informations complémentaires (noms + téléphones)',
                    'data' => $user ? $user->getInfos() : null,
                    'required' => false,
                ]);
            })
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
