<?php

declare(strict_types=1);

namespace App\Form\agency;

use App\Dto\admin\agency\FareSegmentData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FareSegmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fromStopId', HiddenType::class)
            ->add('toStopId', HiddenType::class)
            ->add('amount', NumberType::class, [
                'required' => false,
                'scale'    => 2,
                'attr'     => [
                    'class'       => 'matrix-input',
                    'step'        => '0.01',
                    'placeholder' => '0',
                ],
            ])
            ->add('pricePerKg', NumberType::class, [
                'required' => false,
                'scale'    => 2,
                'attr'     => [
                    'class'       => 'matrix-input',
                    'step'        => '0.01',
                    'placeholder' => '0',
                ],
            ])
            ->add('isActive', CheckboxType::class, [
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FareSegmentData::class,
        ]);
    }
}
