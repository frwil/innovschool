<?php

namespace App\Form;

use App\Entity\SchoolClassPeriod;
use App\Entity\SchoolEvaluation;
use App\Entity\SchoolPeriod;
use App\Entity\SectionCategorySubject;
use App\Entity\SubjectGrade;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UploadGradeCSVType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
        ->add('csv_file', FileType::class, [
            'label' => 'CSV file',
            // 'constraints' => [
            //     new File([
            //         'maxSize' => '1024k',
            //         'mimeTypes' => [
            //             'text/csv',
            //         ],
            //         'mimeTypesMessage' => 'Please upload a valid CSV file',
            //     ])
            // ],
        ]);
        
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            
        ]);
    }
}
