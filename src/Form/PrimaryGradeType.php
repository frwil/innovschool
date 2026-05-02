<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PrimaryGradeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {

        /** @var \App\Entity\SchoolClassSubject|null */
        $subject = $options['subject'];

        $builder
            // ->add('note', null, [
            //     'label' => false,
            // ])
            // ->add('pointKnowHomw', null, [
            //     'label' => false,
            // ])
            // ->add('pointOral', null, [
            //     'label' => false,
            // ])
            // ->add('pointWritten', null, [
            //     'label' => false,
            // ])
            // ->add('pointPractical', null, [
            //     'label' => false,
            // ])
            ->add('student', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'fullName',
                'attr' => [
                    'style' => 'appearance: none; border: none; background: transparent; pointer-events: none; font-size: inherit; color: inherit;'
                ],
                'label' => false,
            ])
            ->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($subject) {
                $form = $event->getForm();
                $data = $event->getData();

                if(null == $subject) {
                    return;
                }

                if ($subject->getPointKnowHomw() !== null) {
                    $form->add('pointKnowHomw', null, [
                        'label' => false,
                    ]);
                }
                if ($subject->getPointOral() !== null) {
                    $form->add('pointOral', null, [
                        'label' => false,
                    ]);
                }
                if ($subject->getPointWritten() !== null) {
                    $form->add('pointWritten', null, [
                        'label' => false,
                    ]);
                }
                if ($subject->getPointPractical() !== null) {
                    $form->add('pointPractical', null, [
                        'label' => false,
                    ]);
                }
            })
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
            'subject' => null,
        ]);
    }
}
