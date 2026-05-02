<?php

namespace App\Form;

use App\Entity\School;
use App\Repository\StudyLevelRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SchoolType extends AbstractType
{
    public function __construct(
        private StudyLevelRepository $sectionRepository,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var \App\Entity\School */
        $school = $builder->getData();
        $data = [];
        foreach ($school->getSchoolSections() as $section) {
            $data[$section->getSection()->getName()] = $section->getSection()->getId();
        }

        $builder
            ->add('name', null, [
                'label' => 'Nom',
            ])
            ->add('address', null, [
                'label' => 'Adresse',
            ])
            ->add('contactName', null, [
                'label' => 'Nom du contact',
            ])
            ->add('contactPhone', null, [
                'label' => 'Téléphone du contact',
            ])
            // ->add('contactEmail', EmailType::class, [
            //     'label' => 'Email du contact',
            //     'required' => false,
            // ])
            ->add('trialStartAt', null, [
                'widget' => 'single_text',
                'label' => 'Début essai',
            ])
            ->add('trialDuration', null, [
                'label' => 'Durée éssai (en jour)',
            ])

            ->add('fee', FeesType::class, [
                'label' => false,
            ])

            ->add('section', ChoiceType::class, [
                'label' => 'Sections',
                'multiple' => true, // Permet la sélection multiple
                // 'expanded' => true, // Affiche les choix sous forme de cases à cocher (optionnel)
                'choices' => $this->getSectionsChoices(), // Charge la liste des sections
                'mapped' => false,  // Ne lie pas directement ce champ à l'entité School
                'data' => $data,
            ])
            ->add('acronym', null, [
                'label' => 'Sigle',
            ])
            ->add('schoolNumber', null, [
                'label' => 'Matricule',
            ])
            ->add('admin', SchoolAdminType::class, [
                'label' => false,
                'mapped' => false,
            ])
        ;;
    }

    private function getSectionsChoices()
    {
        // Tu récupères toutes les sections disponibles dans la base de données
        $sections = $this->sectionRepository->findAll();

        $choices = [];
        foreach ($sections as $section) {
            $choices[$section->getName()] = $section->getId();  // Utiliser le nom de la section et l'ID
        }

        return $choices;
    }


    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => School::class,
        ]);
    }
}
