<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\public\home\ContactData;
use App\Enum\ContactSubject;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire de contact : protégé par CSRF et par un champ piège à robots.
 */
class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullName', TextType::class, [
                'label' => 'Nom complet',
                'attr' => ['maxlength' => 100, 'autocomplete' => 'name'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => ['maxlength' => 180, 'autocomplete' => 'email'],
            ])
            ->add('subject', EnumType::class, [
                'label' => 'Objet',
                'class' => ContactSubject::class,
                'choice_label' => static fn (ContactSubject $subject): string => $subject->label(),
                'placeholder' => '-- Sélectionner --',
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Message',
                'attr' => ['rows' => 5, 'maxlength' => 5000],
            ])
            ->add('website', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['tabindex' => '-1', 'autocomplete' => 'off', 'aria-hidden' => 'true'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ContactData::class]);
    }
}
