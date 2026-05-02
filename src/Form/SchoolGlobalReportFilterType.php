<?php

namespace App\Form;

use App\Contract\UserRoleEnum;
use App\Entity\SchoolClassPeriod;
use App\Entity\SchoolSection;
use App\Entity\Classe;
use App\Entity\User;
use App\Repository\SchoolPeriodRepository;
use App\Service\SchoolGlobalReportFilterDto;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfonycasts\DynamicForms\DependentField;
use Symfonycasts\DynamicForms\DynamicFormBuilder;

class SchoolGlobalReportFilterType extends AbstractType
{
    public function __construct(
        private SchoolPeriodRepository $schoolPeriodRepository,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var SchoolGlobalReportFilterDto */
        $filter = $builder->getData();
        $period = $this->schoolPeriod;

        $builder = new DynamicFormBuilder($builder);

        $builder
            ->add('section', EntityType::class, [
                'required' => false,
                'label' => 'StudyLevel',
                'class' => SchoolSection::class,
                'query_builder' => function (EntityRepository $entityRepository) use ($filter) {
                    return $entityRepository->createQueryBuilder('s')
                        ->andWhere('s.school = :school')
                        ->setParameter('school', $filter->school);
                },
                'attr' => ['onchange' => 'this.form.requestSubmit()'],
            ])
            ->addDependent('level', 'section', function (DependentField $field, ?SchoolSection $schoolSection) {
                $field->add(EntityType::class, [
                    'class' => Classe::class,
                    'query_builder' => function (EntityRepository $entityRepository) use ($schoolSection) {
                        return $entityRepository->createQueryBuilder('s')
                            ->orWhere('s.section = :school')
                            ->setParameter('school', $schoolSection?->getSection());
                        // $qb = $entityRepository->createQueryBuilder('s');
                        // if ($schoolSection && $schoolSection->getSection()) {
                        //     $qb->where('s.section = :school')
                        //         ->setParameter('school', $schoolSection->getSection());
                        // }
                        // return $qb;
                    },
                    'required' => false,
                    'attr' => ['onchange' => 'this.form.requestSubmit()'],
                ]);
            })

            ->add('minClassCount', IntegerType::class, [
                'required' => false,
                'label' => 'Nombre de classes min.',
            ])
            ->add('maxClassCount', IntegerType::class, [
                'required' => false,
                'label' => 'Nombre de classes max.',
            ])
            ->addDependent('subClass', ['section', 'level'], function (DependentField $field, ?SchoolSection $schoolSection, ?Classe $sectionCategory) use ($period, $filter) {
                $field->add(EntityType::class, [
                    'class' => SchoolClassPeriod::class,
                    'query_builder' => function (EntityRepository $entityRepository) use ($schoolSection, $sectionCategory, $period, $filter) {
                        $qb = $entityRepository->createQueryBuilder('s');
                        $qb->andWhere('s.school = :school')
                            ->setParameter('school', $filter->school);

                        $qb->andWhere('s.period = :period')
                            ->setParameter('period', $period);
                        if ($schoolSection) {
                            $qb->join('s.sectionCategory', 'ss')
                                ->andWhere('ss.section = :section')
                                ->setParameter('section', $schoolSection->getSection());
                        }

                        if ($sectionCategory) {
                            $qb->andWhere('s.sectionCategory = :sectionCategory')
                                ->setParameter('sectionCategory', $sectionCategory);
                        }

                        return $qb;
                        // return $entityRepository->createQueryBuilder('s')
                        //     ->andWhere('s.period = :period')
                        //     ->setParameter('period', $period)
                        //     ->andWhere('s.sectionCategory = :sectionCategory')
                        //     ->setParameter('sectionCategory', $sectionCategory)
                        //     ->andWhere('s.school = :school')
                        //     ->setParameter('school', $filter->school)
                        // ;
                    },
                    'required' => false,
                    'attr' => ['onchange' => 'this.form.requestSubmit()'],
                ]);
            })
            ->addDependent('teacher', ['subClass', 'section'], function (DependentField $fiel, ?SchoolClassPeriod $schoolClassPeriod, ?SchoolSection $schoolSection) use ($period, $filter) {
                $fiel->add(EntityType::class, [
                    'class' => User::class,
                    'query_builder' => function (EntityRepository $entityRepository) use ($schoolClassPeriod, $period, $filter, $schoolSection) {
                        $qb = $entityRepository->createQueryBuilder('u')
                            ->andWhere('u.school = :school')
                            ->setParameter('school', $filter->school);
                        if ($schoolSection) {

                            $qb->join('u.school', 'us')
                                ->innerJoin('us.schoolSections', 'ss')
                                ->andWhere('ss.section = :section')
                                ->setParameter('section', $schoolSection->getSection());
                        }
                        $qb->andWhere('ut.period = :period')
                            ->setParameter('period', $period);
                        $qb->andWhere('u.roles LIKE :roles')
                            ->setParameter('roles', '%' . UserRoleEnum::TEACHER->value . '%')
                            ->join('u.teacherClasses', 'ut');

                        if ($schoolClassPeriod) {
                            $qb->andWhere('ut.schoolClassPeriod = :schoolClassPeriod')
                                ->setParameter('schoolClassPeriod', $schoolClassPeriod);
                        }


                        return $qb;
                        // return $entityRepository->createQueryBuilder('u')
                        //     ->andWhere('u.roles LIKE :roles')
                        //     ->setParameter('roles', '%' . UserRoleEnum::TEACHER->value . '%')
                        //     ->join('u.teacherClasses', 'ut')
                        //     ->orWhere('ut.schoolClassPeriod = :schoolClassPeriod')
                        //     ->setParameter('schoolClassPeriod', $schoolClassPeriod)
                        //     ->orWhere('ut.period = :period')
                        //     ->setParameter('period', $period)
                        // ;
                    },
                    'required' => false,
                    'choice_label' => 'fullName',
                    'label' => 'Enseignant',
                ]);
            })
            ->add('minTeacher', IntegerType::class, [
                'required' => false,
                'label' => 'Enseignant min.',
            ])
            ->add('maxTeacher', IntegerType::class, [
                'required' => false,
                'label' => 'Enseignant max.',
            ])
            ->add('minCapacity', IntegerType::class, [
                'required' => false,
                'label' => 'Capacité min.',
            ])
            ->add('maxCapacity', IntegerType::class, [
                'required' => false,
                'label' => 'Capacité max.',
            ])
            ->add('minCurrentEnrollment', IntegerType::class, [
                'required' => false,
                'label' => 'Effectif actuel min.',
            ])
            ->add('maxCurrentEnrollment', IntegerType::class, [
                'required' => false,
                'label' => 'Effectif actuel max.',
            ])
            ->add('minRepeaters', IntegerType::class, [
                'required' => false,
                'label' => 'Redoublants min.',
            ])
            ->add('maxRepeaters', IntegerType::class, [
                'required' => false,
                'label' => 'Redoublants max.',
            ])
            ->add('minBoys', IntegerType::class, [
                'required' => false,
                'label' => 'Garçons min.',
            ])
            ->add('maxBoys', IntegerType::class, [
                'required' => false,
                'label' => 'Garçons max.',
            ])
            ->add('minGirls', IntegerType::class, [
                'required' => false,
                'label' => 'Filles min.',
            ])
            ->add('maxGirls', IntegerType::class, [
                'required' => false,
                'label' => 'Filles max.',
            ])
            ->add('minParents', IntegerType::class, [
                'required' => false,
                'label' => 'Parents min.',
            ])
            ->add('maxParents', IntegerType::class, [
                'required' => false,
                'label' => 'Parents max.',
            ])
            ->add('minPaid', IntegerType::class, [
                'required' => false,
                'label' => 'Payé min.',
            ])
            ->add('maxPaid', IntegerType::class, [
                'required' => false,
                'label' => 'Payé max.',
            ])
            ->add('minUnpaid', IntegerType::class, [
                'required' => false,
                'label' => 'Impayé min.',
            ])
            ->add('maxUnpaid', IntegerType::class, [
                'required' => false,
                'label' => 'Impayé max.',
            ])
            
            ->add('reportType', ChoiceType::class, [
                'required' => false,
                'label' => 'Type de rapport',
                'choices' => [
                    'PDF' => 'pdf',
                    'HTML' => 'html',
                ]
            ])

            ->add('submit', SubmitType::class, [
                'label' => 'Continuer',
                'attr' => [
                    'value' => 'submit',
                    'data-turbo' => "false",
                    'class' => 'btn btn-primary btn-lg'
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SchoolGlobalReportFilterDto::class,
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }
}
