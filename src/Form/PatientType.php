<?php

namespace App\Form;

use App\Entity\Patient;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PatientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('identityDocument')
            ->add('firstName')
            ->add('lastName')
            ->add('ssNumber')
            ->add('phone')
            ->add('email')
            ->add('address')
            ->add('consultationReason')
            ->add('familyHistory')
            ->add('healthStatus')
            ->add('lifestyleHabits')
            ->add('registrationDate', null, [
                'widget' => 'single_text',
            ])
            ->add('medicationAllergies')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Patient::class,
        ]);
    }
}
