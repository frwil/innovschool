<?php

namespace App\Form;

use App\Contract\GenderEnum;
use App\Contract\UserRoleEnum;
use App\Entity\User;
use App\Repository\SchoolClassPeriodRepository;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserType extends AbstractType
{
    public function __construct(
        private SchoolClassPeriodRepository $schoolClassRepository,
        private Security $security,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var \App\Entity\User */
        $user = $builder->getData();
        $prevData = $options['prev_data'] ?? [];

        $data = [];
        foreach ($user->getTeacherClasses() as $key => $tc) {
            # code...
            foreach ($tc->getSchoolClass() as $classes) {
                $data[$classes->getName()] = $classes->getId();
            }
        }

        $builder
            ->add('fullName', null, [
                'label' => 'Nom complet',
            ])
            ->add('address', null, [
                'label' => 'Adresse',
                'required' => false,
            ])
           
            ->add('gender', ChoiceType::class, [
                'label' => 'Sexe',
                'choices' => [
                    'Masculin' => GenderEnum::MALE,
                    'Féminin' => GenderEnum::FEMALE,
                ],
            ])
           
            ->add('dateOfBirth', null, [
                'widget' => 'single_text',
                'label' => 'Date de naissance',
                'required' => false,
            ])
            ->add('religion', null, [
                'label' => 'Religion',
                'required' => false,
            ])
            // ->add('plainPassword', PasswordType::class, [
            //     'mapped' => false,
            //     'required' => false,
            //     'attr' => ['autocomplete' => 'new-password'],
            //     'constraints' => [
            //         new NotBlank([
            //             'message' => 'Please enter a password',
            //         ]),
            //         new Length([
            //             'min' => 6,
            //             'minMessage' => 'Your password should be at least {{ limit }} characters',
            //             // max length allowed by Symfony for security reasons
            //             'max' => 4096,
            //         ]),
            //     ],
            //     'label' => 'Mot de passe',
            // ])
            ;
        if (in_array(UserRoleEnum::TEACHER->value, $user->getRoles())) {

            $builder
                ->add('classes', ChoiceType::class, [
                    'label' => 'Classes',
                    'multiple' => true, // Permet la sélection multiple
                    // 'expanded' => true, // Affiche les choix sous forme de cases à cocher (optionnel)
                    'choices' => $this->getClassesChoices(), // Charge la liste des sections
                    'mapped' => false,  // Ne lie pas directement ce champ à l'entité School
                    // 'data' => $data,
                ])
                 ->add('phone', null, [
                    'label' => 'Téléphone',
                ]);
        }

        if (in_array(UserRoleEnum::STUDENT->value, $user->getRoles())) {

            $builder
                ->add('studentClasses', ChoiceType::class, [
                    'label' => 'Classe',
                    // 'multiple' => true, // Permet la sélection multiple
                    // 'expanded' => true, // Affiche les choix sous forme de cases à cocher (optionnel)
                    'choices' => $this->getClassesChoices(), // Charge la liste des sections
                    'mapped' => false,  // Ne lie pas directement ce champ à l'entité School
                    'data' => !empty($prevData['schoolClassPeriod']) ? array_values($prevData['schoolClassPeriod'])[0] : null,
                ])
                ->add('repeated', ChoiceType::class, [
                    'label' => 'Redoublant',
                    'choices' => [
                        'Non' => false,
                        'Oui' => true,
                    ],
                ])
                ->add('tutor', TutorType::class, [
                    'label' => false,
                    'data' => $user->getTutor(),
                ])
                
                ;
        }

        // add submit button
        $builder->add('submit', SubmitType::class, [
            'label' => 'Enregistrer',
            'attr' => ['class' => 'btn-fill-lg btn-gradient-yellow btn-hover-bluedark', 'value' => 'submit',
                'data-turbo' => 'false'],
            'block_prefix' => 'custom_submit',
        ]);
    }

    private function getClassesChoices()
    {
        /** @var \App\Entity\User */
        $user = $this->security->getUser();
        $school = $this->currentSchool;
        // Tu récupères toutes les sections disponibles dans la base de données
        $classes = $this->schoolClassRepository->findBy(['school' => $school]);

        $choices = [];
        foreach ($classes as $class) {
            $choices[$class->getName()] = $class->getId();  // Utiliser le nom de la section et l'ID
        }

        return $choices;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'prev_data' => [],
        ]);
    }
}
