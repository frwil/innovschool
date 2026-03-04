<?php

namespace App\Form;

use App\Entity\SchoolClassPeriod;
use App\Entity\SchoolEvaluation;
use App\Entity\SchoolSection;
use App\Entity\StudyLevel;
use App\Entity\Classe;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfonycasts\DynamicForms\DependentField;
use Symfonycasts\DynamicForms\DynamicFormBuilder;

class SchoolClassSearchType extends AbstractType
{
    public function __construct(
        private Security $security,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder = new DynamicFormBuilder($builder);
        /** @var \App\Entity\User */
        $user = $this->security->getUser();
        $school = $this->currentSchool;
        $ids = array_map(function (Classe $schoolSection) {
            return $schoolSection->getSection()->getId();
        }, $school->getSchoolSections()->toArray());


        $builder
            ->add('evaluation', EntityType::class, [
                'class' => SchoolEvaluation::class,
                'label' => false,
                'placeholder' => 'Selectionner une evaluation',
                'required' => true,
            ])

            ->add('schoolSection', EntityType::class, [
                'class' => StudyLevel::class,
                'choice_label' => 'name',
                'label' => false,
                'placeholder' => 'Selectionner une section',
                'required' => true,
                'query_builder' => function (EntityRepository $er) use ($ids) {
                    return $er->createQueryBuilder('ss')
                        ->andWhere('ss.id IN (:ids)')
                        ->setParameter('ids', $ids);
                },
                'attr' => ['onchange' => 'this.form.requestSubmit()'],
            ]);

        if (!$options['hide_classe']) {
            $builder
                ->addDependent('schoolClassPeriod', 'schoolSection', function (DependentField $field, ?StudyLevel $section) use ($school) {
                    if (null === $section) {
                        return;
                    }

                    $field->add(EntityType::class, [
                        'class' => SchoolClassPeriod::class,
                        'choice_label' => 'name',
                        'label' => false,
                        'placeholder' => 'Selectionner une classe',
                        'required' => true,
                        'query_builder' => function (EntityRepository $er) use ($section, $school) {
                            // Extraire les IDs des catégories de section
                            $sectionCategoryIds = $section->getSectionCategories()
                                ->map(fn($category) => $category->getId())
                                ->toArray();

                            return $er->createQueryBuilder('sc')
                                ->where('sc.sectionCategory IN (:sectionCategory)')
                                ->setParameter('sectionCategory', $sectionCategoryIds) // Passer les IDs comme tableau
                                ->andWhere('sc.school = :school')
                                ->setParameter('school', $school);
                        },
                    ]);
                });
        }

        $builder
            ->add('submit', SubmitType::class, [
                'label' => 'Chercher',
                'attr' => [
                    'value' => 'submit',
                    'data-turbo' => "false",
                    'class' => 'btn-fill-lg btn-gradient-yellow btn-hover-bluedark'
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
            'hide_classe' => false,
        ]);
    }
}
