<?php

declare(strict_types=1);

namespace App\Form\agency;

use App\Dto\admin\agency\RouteStopData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class RouteStopType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('city', TextType::class, [
                'label' => 'Ville d\'Escale',
                'attr'  => [
                    'placeholder' => 'Ex: Mbanza-Ngungu',
                ],
            ])
            ->add('country', TextType::class, [
                'label' => 'Pays',
                'attr'  => [
                    'placeholder' => 'RDC',
                ],
            ])
            ->add('distance', NumberType::class, [
                'label' => 'Distance (Km)',
                'scale' => 2,
                'attr'  => [
                    'placeholder' => '150',
                    'step'        => '0.01',
                ],
            ])
            ->add('duration', TextType::class, [
                'label' => 'Durée',
                'attr'  => [
                    'placeholder' => '2h15',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RouteStopData::class,
        ]);
    }
}
