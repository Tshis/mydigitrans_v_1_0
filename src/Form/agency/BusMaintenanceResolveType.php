<?php

declare(strict_types=1);

namespace App\Form\admin\agency;

use App\Dto\admin\agency\BusMaintenanceResolveData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class BusMaintenanceResolveType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('resolvedAt', DateTimeType::class, [
                'label'  => 'Date et heure de fin des travaux *',
                'widget' => 'single_text',
                'input'  => 'datetime_immutable',
            ])
            ->add('solutionDescription', TextareaType::class, [
                'label' => 'Détail des réparations effectuées / Pièces remplacées *',
                'attr'  => [
                    'rows'        => 4,
                    'placeholder' => 'Indiquez les travaux réalisés par l\'atelier avant la remise en circulation...',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BusMaintenanceResolveData::class,
        ]);
    }
}
