<?php

namespace App\Form;

use App\Entity\SchoolClassSubject;
use App\Entity\SectionCategorySubject;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SectionCategoriesType extends AbstractType
{

    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
        
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var SchoolClassSubject */
        $schoolClassSubject = $builder->getData();

         /** @var \App\Entity\User */
        $currentUser = $this->security->getUser();
        $school = $this->currentSchool;
        $sectionCategorySubjects = $this->entityManager->getRepository(SectionCategorySubject::class)
            ->findBy(['sectionCategory' => $schoolClassSubject->getSchoolClass()->getSectionCategory()]);
        $builder
            ->add('sectionCategorySubjects', ChoiceType::class, [
                'label' => 'Sélectionner les matières',
                'choices' => $sectionCategorySubjects,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true,
                'mapped' => false,
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
