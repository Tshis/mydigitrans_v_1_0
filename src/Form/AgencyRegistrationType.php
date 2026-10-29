<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\public\agency\AgencyRegistrationData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AgencyRegistrationType extends AbstractType
{
    private const LABEL = ['class' => 'admin-bus-layout-add-label'];
    private const INPUT = 'admin-bus-layout-add-input';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('agencyName', TextType::class, [
                'label' => 'Nom Commercial de la Compagnie *',
                'label_attr' => self::LABEL,
                'attr' => ['class' => self::INPUT, 'placeholder' => 'Ex: TransKin Express', 'maxlength' => 150, 'autocomplete' => 'off'],
            ])
            ->add('registrationNumber', TextType::class, [
                'label' => 'Numéro RCCM',
                'label_attr' => self::LABEL,
                'required' => false,
                'attr' => ['class' => self::INPUT . ' monospace', 'placeholder' => 'RCCM-KIN-...', 'maxlength' => 100],
            ])
            ->add('taxNumber', TextType::class, [
                'label' => 'Numéro d\'Identification Fiscale (NIF)',
                'label_attr' => self::LABEL,
                'required' => false,
                'attr' => ['class' => self::INPUT . ' monospace', 'placeholder' => 'NIF-...', 'maxlength' => 100],
            ])
            ->add('agencyPhone', TelType::class, [
                'label' => 'Téléphone agence *',
                'label_attr' => self::LABEL,
                'attr' => ['class' => self::INPUT . ' monospace', 'placeholder' => '+24381........', 'maxlength' => 20],
            ])
            ->add('agencyEmail', EmailType::class, [
                'label' => 'Email *',
                'label_attr' => self::LABEL,
                'attr' => ['class' => self::INPUT . ' monospace', 'placeholder' => 'agency@exemple.com', 'maxlength' => 180],
            ])
            ->add('mainCity', TextType::class, [
                'label' => 'Ville principale *',
                'label_attr' => self::LABEL,
                'attr' => ['class' => self::INPUT, 'placeholder' => 'Ex: Kinshasa', 'maxlength' => 100],
            ])
            ->add('country', TextType::class, [
                'label' => 'Pays *',
                'label_attr' => self::LABEL,
                'attr' => ['class' => self::INPUT, 'placeholder' => 'Ex: RDC', 'maxlength' => 100],
            ])
            ->add('address', TextType::class, [
                'label' => 'Adresse *',
                'label_attr' => self::LABEL,
                'attr' => ['class' => self::INPUT, 'placeholder' => 'Ex: N°45, Boulevard du 30 Juin, Gombe', 'maxlength' => 255],
            ])
            ->add('sponsorToken', TextType::class, [
                'label' => 'Code d\'Affiliation Apporteur / Sponsor Token',
                'label_attr' => self::LABEL,
                'required' => false,
                'attr' => ['class' => self::INPUT . ' font-monospace-code', 'placeholder' => 'Ex : MDTDAN718 (Laissez vide si enrôlement direct)', 'maxlength' => 64, 'autocomplete' => 'off'],
            ])
            ->add('ownerLastname', TextType::class, [
                'label' => 'Nom *',
                'label_attr' => self::LABEL,
                'attr' => ['class' => self::INPUT, 'placeholder' => 'Ex: Lukonu', 'maxlength' => 100, 'autocomplete' => 'off'],
            ])
            ->add('ownerFirstname', TextType::class, [
                'label' => 'Prénom *',
                'label_attr' => self::LABEL,
                'attr' => ['class' => self::INPUT, 'placeholder' => 'Ex: Daniel', 'maxlength' => 100, 'autocomplete' => 'off'],
            ])
            ->add('ownerPhone', TelType::class, [
                'label' => 'Téléphone *',
                'label_attr' => self::LABEL,
                'attr' => ['class' => self::INPUT, 'placeholder' => 'Ex: +243...', 'maxlength' => 20],
            ])
            ->add('ownerEmail', EmailType::class, [
                'label' => 'Email *',
                'label_attr' => self::LABEL,
                'attr' => ['class' => self::INPUT, 'placeholder' => 'Ex: admin@agence.com', 'maxlength' => 180, 'autocomplete' => 'email'],
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Mot de passe de sécurité *',
                'label_attr' => self::LABEL,
                'attr' => ['class' => self::INPUT, 'placeholder' => '••••••••', 'autocomplete' => 'new-password'],
            ])
            ->add('confirmPassword', PasswordType::class, [
                'label' => 'Confirmer le mot de passe *',
                'label_attr' => self::LABEL,
                'attr' => ['class' => self::INPUT, 'placeholder' => '••••••••', 'autocomplete' => 'new-password'],
            ])
            ->add('acceptLegal', CheckboxType::class, ['label' => false])
            // Mémorise que le parrain vient du lien d'invitation (champ verrouillé à l'écran, pour l'affichage seulement).
            ->add('sponsorLocked', HiddenType::class, ['mapped' => false, 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => AgencyRegistrationData::class]);
    }
}
