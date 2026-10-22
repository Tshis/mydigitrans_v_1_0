<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\public\business\PartnerRegistrationData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PartnerRegistrationType extends AbstractType
{
    private const LABEL = ['class' => 'admin-bus-layout-add-label'];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('lastname', TextType::class, [
                'label' => 'Nom *',
                'label_attr' => self::LABEL,
                'attr' => ['class' => 'admin-bus-layout-add-input', 'placeholder' => 'Ex: Ilunga', 'maxlength' => 100, 'autocomplete' => 'family-name'],
            ])
            ->add('firstname', TextType::class, [
                'label' => 'Prénom *',
                'label_attr' => self::LABEL,
                'attr' => ['class' => 'admin-bus-layout-add-input', 'placeholder' => 'Ex: Christian', 'maxlength' => 100, 'autocomplete' => 'given-name'],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Numéro de Téléphone *',
                'label_attr' => self::LABEL,
                'attr' => ['class' => 'admin-bus-layout-add-input', 'placeholder' => 'Ex: +243 812 345 678', 'maxlength' => 20, 'autocomplete' => 'tel'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse Email *',
                'label_attr' => self::LABEL,
                'attr' => ['class' => 'admin-bus-layout-add-input', 'placeholder' => 'Ex: christian.ilunga@mail.com', 'maxlength' => 180, 'autocomplete' => 'email'],
            ])
            ->add('sponsorToken', TextType::class, [
                'label' => 'Code de Parrainage / Sponsor Accroché',
                'label_attr' => self::LABEL,
                'required' => false,
                'attr' => ['class' => 'admin-bus-layout-add-input font-monospace-code', 'placeholder' => 'Aucun (Racine Système)', 'maxlength' => 64, 'autocomplete' => 'off'],
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Mot de passe *',
                'label_attr' => self::LABEL,
                'attr' => ['class' => 'admin-bus-layout-add-input', 'placeholder' => '••••••••', 'autocomplete' => 'new-password'],
            ])
            ->add('confirmPassword', PasswordType::class, [
                'label' => 'Confirmer le mot de passe *',
                'label_attr' => self::LABEL,
                'attr' => ['class' => 'admin-bus-layout-add-input', 'placeholder' => '••••••••', 'autocomplete' => 'new-password'],
            ])
            ->add('acceptLegal', CheckboxType::class, ['label' => false])
            // Mémorise que le parrain vient du lien d'affiliation (champ verrouillé à l'écran, pour l'affichage seulement).
            ->add('sponsorLocked', HiddenType::class, ['mapped' => false, 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => PartnerRegistrationData::class]);
    }
}
