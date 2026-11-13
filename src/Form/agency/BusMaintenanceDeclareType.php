<?php

declare(strict_types=1);

namespace App\Form\agency;

use App\Dto\admin\agency\BusMaintenanceDeclareData;
use App\Entity\Bus;
use App\Enum\MaintenanceSeverity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class BusMaintenanceDeclareType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<Bus> $activeBuses */
        $activeBuses = $options['active_buses'];

        $choices = [];
        foreach ($activeBuses as $bus) {
            $label = sprintf(
                '%s %s — %s (%s km)',
                $bus->getBrand(),
                $bus->getModel(),
                $bus->getPlateNumber(),
                number_format($bus->getMileage(), 0, ',', ' ')
            );
            $choices[$label] = $bus->getCode();
        }

        $builder
            ->add('busCode', ChoiceType::class, [
                'label'       => 'Véhicule concerné *',
                'placeholder' => '-- Sélectionner un véhicule en service --',
                'choices'     => $choices,
            ])
            ->add('severity', EnumType::class, [
                'label'        => 'Niveau de gravité *',
                'class'        => MaintenanceSeverity::class,
                'choice_label' => static fn(MaintenanceSeverity $s): string => match ($s) {
                    MaintenanceSeverity::Low      => 'Faible (Low) — Entretien mineur',
                    MaintenanceSeverity::Medium   => 'Moyenne (Medium) — Immobilisation standard',
                    MaintenanceSeverity::Critical => 'Critique (Critical) — Panne lourde / Urgence',
                },
            ])
            ->add('issuedAt', DateTimeType::class, [
                'label'  => 'Date et heure de constatation *',
                'widget' => 'single_text',
                'input'  => 'datetime_immutable',
            ])
            ->add('mileage', IntegerType::class, [
                'label'    => 'Kilométrage au compteur (km)',
                'required' => false,
                'attr'     => [
                    'placeholder' => 'Laisser vide pour conserver l\'actuel',
                    'min'         => 0,
                ],
            ])
            ->add('issueDescription', TextareaType::class, [
                'label' => 'Description détaillée de l\'avarie *',
                'attr'  => [
                    'rows'        => 4,
                    'placeholder' => 'Décrivez les symptômes, pièces défectueuses ou circonstances de la panne...',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'   => BusMaintenanceDeclareData::class,
            'active_buses' => [],
        ]);

        $resolver->setAllowedTypes('active_buses', 'array');
    }
}
