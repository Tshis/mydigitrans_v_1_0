<?php

declare(strict_types=1);

namespace App\Form\agency;

use App\Dto\admin\agency\ShipmentPickupData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ShipmentPickupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('pickupCode', TextType::class, [
                'label' => 'Saisir le Code de Retrait Secret *',
                'attr' => [
                    'class' => 'admin-bus-layout-add-input font-bold monospace text-center text-size-18 uppercase',
                    'placeholder' => 'Ex: 591A',
                    'autocomplete' => 'off',
                    'maxlength' => 10,
                ],
            ])
            ->add('receiverIdType', ChoiceType::class, [
                'label' => 'Type de pièce d\'identité *',
                'choices' => [
                    'Carte d\'Électeur (RDC)' => 'carte_electeur',
                    'Passeport National'      => 'passeport',
                    'Permis de Conduire'      => 'permis',
                    'Carte d\'Identité'       => 'carte_identite',
                ],
                'attr' => ['class' => 'admin-bus-layout-add-select'],
            ])
            ->add('receiverIdNumber', TextType::class, [
                'label' => 'Numéro de la pièce présentée *',
                'attr' => [
                    'class' => 'admin-bus-layout-add-input',
                    'placeholder' => 'Ex: 123-4567-XYZ',
                    'autocomplete' => 'off',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => ShipmentPickupData::class,
            'csrf_protection'    => false,
            'allow_extra_fields' => true,
        ]);
    }
}
