<?php

declare(strict_types=1);

namespace App\Form\agency;

use App\Dto\admin\agency\FareMatrixData;
use App\Enum\FareType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FareMatrixType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, string> $currencyChoices */
        $currencyChoices = $options['currency_choices'];

        $builder
            ->add('fareType', EnumType::class, [
                'label'        => 'Catégorie Tarifaire',
                'class'        => FareType::class,
                'choice_label' => static fn(FareType $type): string => ucfirst($type->value),
                'attr'         => [
                    'class' => 'matrix-select',
                ],
            ])
            ->add('directAmount', NumberType::class, [
                'scale' => 2,
                'attr'  => [
                    'class'       => 'matrix-input main-price',
                    'step'        => '0.01',
                    'placeholder' => '0',
                ],
            ])
            ->add('directFret', NumberType::class, [
                'required' => false,
                'scale'    => 2,
                'attr'     => [
                    'class'       => 'matrix-input main-price',
                    'step'        => '0.01',
                    'placeholder' => '0',
                ],
            ])
            ->add('currencyCode', ChoiceType::class, [
                'choices' => $currencyChoices,
                'attr'    => [
                    'class' => 'matrix-select',
                ],
            ])
            ->add('segments', CollectionType::class, [
                'entry_type'   => FareSegmentType::class,
                'allow_add'    => false,
                'allow_delete' => false,
                'by_reference' => false,
                'label'        => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'       => FareMatrixData::class,
            'currency_choices' => [],
        ]);

        $resolver->setAllowedTypes('currency_choices', 'array');
    }
}
