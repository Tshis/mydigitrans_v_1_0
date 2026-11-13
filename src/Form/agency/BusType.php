<?php

declare(strict_types=1);

namespace App\Form\agency;

use App\Dto\admin\agency\BusData;
use App\Entity\BusLayout;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class BusType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var bool $isEdit */
        $isEdit = $options['is_edit'];

        /** @var list<BusLayout> $layouts */
        $layouts = $options['layouts'];

        if (!$isEdit) {
            $builder->add('code', TextType::class, [
                'label'    => 'Code Interne du Bus (Optionnel)',
                'required' => false,
                'attr'     => [
                    'placeholder' => 'Ex: BUS-012 (généré automatiquement si vide)',
                ],
            ]);
        }

        $builder
            ->add('name', TextType::class, [
                'label'    => 'Nom commercial (Optionnel)',
                'required' => false,
                'attr'     => [
                    'placeholder' => 'Ex: Marcopolo VIP',
                ],
            ])
            ->add('plateNumber', TextType::class, [
                'label' => 'Numéro de Plaque d\'Immatriculation *',
                'attr'  => [
                    'placeholder' => 'Ex: 5412AB/01',
                ],
            ])
            ->add('brand', TextType::class, [
                'label' => 'Marque *',
                'attr'  => [
                    'placeholder' => 'Ex: Toyota',
                ],
            ])
            ->add('model', TextType::class, [
                'label' => 'Modèle *',
                'attr'  => [
                    'placeholder' => 'Ex: Coaster',
                ],
            ])
            ->add('year', IntegerType::class, [
                'label' => 'Année de fabrication *',
                'attr'  => [
                    'placeholder' => 'Ex: 2022',
                    'min'         => 1990,
                    'max'         => 2027,
                ],
            ])
            ->add('mileage', IntegerType::class, [
                'label' => 'Kilométrage *',
                'attr'  => [
                    'placeholder' => '0',
                    'min'         => $options['min_mileage'],
                ],
            ]);

        if (!$isEdit) {
            $choices = [];
            foreach ($layouts as $layout) {
                $choices[$layout->getName()] = $layout->getId();
            }

            $builder->add('busLayoutId', ChoiceType::class, [
                'label'       => 'Modèle de configuration (Gabarit d\'Usine) *',
                'placeholder' => 'Choisir la disposition d\'usine...',
                'choices'     => $choices,
                'attr'        => [
                    'id' => 'bus-layout-selector',
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'        => BusData::class,
            'is_edit'           => false,
            'layouts'           => [],
            'min_mileage'       => 0,
            'validation_groups' => static function (FormInterface $form): array {
                $isEdit = (bool) $form->getConfig()->getOption('is_edit');

                return $isEdit ? ['Default'] : ['Default', 'create'];
            },
        ]);

        $resolver->setAllowedTypes('is_edit', 'bool');
        $resolver->setAllowedTypes('layouts', 'array');
        $resolver->setAllowedTypes('min_mileage', 'int');
    }
}
