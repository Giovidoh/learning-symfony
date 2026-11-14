<?php

namespace App\Form;

use App\DTO\ContactDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'empty_data' => '',
                'label' => 'contactForm.name'
            ])
            ->add('email', EmailType::class, [
                'empty_data' => '',
                'label' => 'contactForm.email'
            ])
            ->add('message', TextareaType::class, [
                'empty_data' => '',
                'label' => 'contactForm.message'
            ])
            ->add('service', ChoiceType::class, [
                'choices' => [
                    'Compta' => 'compta@demo.fr',
                    'Support' => 'support@demo.fr',
                    'Marketing' => 'marketing@demo.fr',
                ],
                'label' => 'contactForm.service'
            ])
            ->add('save', SubmitType::class, [
                'label' => 'contactForm.submit'
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContactDTO::class,
        ]);
    }
}
