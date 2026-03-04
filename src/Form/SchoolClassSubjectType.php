<?php

namespace App\Form;

use App\Entity\School;
use App\Entity\SchoolClassPeriod;
use App\Entity\SchoolClassSubject;
use App\Entity\SchoolPeriod;
use App\Entity\SectionCategorySubject;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\String\Slugger\SluggerInterface;

class SchoolClassSubjectType extends AbstractType
{

    public function __construct(
        private SluggerInterface $slugger
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var \App\Entity\SchoolClassSubject */
        $subject = $builder->getData();
        /** @var \App\Entity\School */
        $school = $subject->getSchool();

        $builder
            ->add('name', null, [
                'label' => 'Nom de la matière',
            ])
            ->add('coefficient', null, [
                'label' => 'Coefficient',
            ])
            ->add('teacher',  null, [
                'label' => 'Enseignant',
                'query_builder' => function (EntityRepository $repository) use ($school) {
                    return $repository->createQueryBuilder('u')
                        ->where('u.roles LIKE :role')
                        ->setParameter('role', '%ROLE_TEACHER%')
                        ->andWhere('u.school = :school')
                        ->setParameter('school', $school);
                },
            ]);

        if ($subject->getSchoolClass()->getSectionCategory()->getSection()->getSlug() == $this->slugger->slug("Primaire")) {

            $builder
                ->add('pointKnowHomw', null, [
                    'label' => 'Points savoir faire',
                ])
                ->add('pointOral', null, [
                    'label' => 'Points oral',
                ])
                ->add('pointWritten', null, [
                    'label' => 'Points écriture',
                ])
                ->add('pointPractical', null, [
                    'label' => 'Points pratique',
                ])
            ;
        }

        $builder
            ->add('teacher',  null, [
                'label' => 'Enseignant',
                'query_builder' => function (EntityRepository $repository) use ($subject) {
                    return $repository->createQueryBuilder('u')
                        ->where('u.roles LIKE :role')
                        ->setParameter('role', '%ROLE_TEACHER%')
                        ->join("u.teacherClasses", "tc")
                        ->andWhere("tc.schoolClassPeriod = :schoolClassPeriod")
                        ->setParameter('schoolClassPeriod', $subject->getSchoolClass());
                },
                'choice_label' => function (?User $user) {
                    return $user ? $user->getFullName() : '';
                },
            ])
            ->add('schoolClassSubjectGroup', null, [
                'label' => 'Groupe de matière',
            ])
            ->add('targetSkills', null, [
                'label' => 'Compétences Ciblées',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SchoolClassSubject::class,
        ]);
    }
}
