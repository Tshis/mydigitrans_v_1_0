<?php

declare(strict_types=1);

namespace App\Form\agency;

use App\Dto\admin\agency\RouteData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class RouteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, string> $currencyChoices */
        $currencyChoices = $options['currency_choices'];

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom Commercial de la Ligne *',
                'attr'  => [
                    'id'          => 'route-name-input',
                    'placeholder' => 'Ex : Kinshasa - Muanda',
                ],
            ])
            ->add('code', TextType::class, [
                'label' => 'Code Ligne (Unique) *',
                'attr'  => [
                    'placeholder' => 'Ex : KIN-MUA',
                ],
            ])
            ->add('departureCity', TextType::class, [
                'label' => 'Ville d\'Origine *',
                'attr'  => [
                    'placeholder' => 'Ex : Kinshasa',
                ],
            ])
            ->add('departureCountry', TextType::class, [
                'label' => 'Pays *',
                'attr'  => [
                    'placeholder' => 'RDC',
                ],
            ])
            ->add('terminusCity', TextType::class, [
                'label' => 'Ville Terminus *',
                'attr'  => [
                    'placeholder' => 'Ex : Muanda',
                ],
            ])
            ->add('terminusCountry', TextType::class, [
                'label' => 'Pays *',
                'attr'  => [
                    'placeholder' => 'RDC',
                ],
            ])
            ->add('distance', NumberType::class, [
                'label' => 'Distance Totale (Km) *',
                'scale' => 2,
                'attr'  => [
                    'placeholder' => '650',
                    'step'        => '0.01',
                ],
            ])
            ->add('duration', TextType::class, [
                'label' => 'Durée Totale *',
                'attr'  => [
                    'placeholder' => '11h00',
                ],
            ])
            ->add('pricePerKg', NumberType::class, [
                'label'    => 'Prix par Kilo *',
                'scale'    => 2,
                'required' => false,
                'attr'     => [
                    'step'        => '0.01',
                    'placeholder' => '500',
                ],
            ])
            ->add('currencyCode', ChoiceType::class, [
                'label'   => 'Devise *',
                'choices' => $currencyChoices,
            ])
            ->add('reservationExpirationDelay', IntegerType::class, [
                'label'    => 'Délai expiration réservation (minutes)',
                'required' => false,
                'attr'     => [
                    'min'         => 5,
                    'placeholder' => '120',
                ],
            ])
            ->add('isAvailable', ChoiceType::class, [
                'label'   => 'Statut de la ligne',
                'choices' => [
                    'Disponible / Actif' => true,
                    'Désactivé'          => false,
                ],
            ])
            ->add('stops', CollectionType::class, [
                'entry_type'   => RouteStopType::class,
                'allow_add'    => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype'    => true,
                'required'     => false,
                'label'        => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'       => RouteData::class,
            'currency_choices' => [],
        ]);

        $resolver->setAllowedTypes('currency_choices', 'array');
    }
}
