<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\public\line\BookingData; // ◄ Synchro sur ton namespace exact
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class BookingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullName', TextType::class, [
                'label' => "Noms complet",
                'required' => true,

                // 1. Ajoute la classe CSS UNIQUEMENT sur le <label>
                'label_attr' => [
                    'class' => 'admin-bus-layout-add-label'
                ],

                // 2. Ajoute le placeholder (et optionnellement une classe) sur l' <input>
                'attr' => [
                    'placeholder' => 'Ex: Daniel L. Tshis\'.',
                    //'class' => 'votre-classe-input-ici' // Optionnel : retirez cette ligne si inutile
                ],
            ])
            ->add('phone', TextType::class, [
                'label' => "Téléphone",
                'required' => true,

                // 1. Ajoute la classe CSS UNIQUEMENT sur le <label>
                'label_attr' => [
                    'class' => 'admin-bus-layout-add-label'
                ],

                // 2. Ajoute le placeholder (et optionnellement une classe) sur l' <input>
                'attr' => [
                    'placeholder' => '+243810000000',
                    //'class' => 'votre-classe-input-ici' // Optionnel : retirez cette ligne si inutile
                ],
            ])
            ->add('departureStop', HiddenType::class, [
                'required' => true,
            ])
            ->add('arrivalStop', HiddenType::class, [
                'required' => true,
            ])
            // Collection dynamique mappée sur la propriété de liste : public array $seats = []
            ->add('seats', CollectionType::class, [
                'entry_type' => HiddenType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BookingData::class,
            'csrf_protection' => true,
        ]);
    }
}
