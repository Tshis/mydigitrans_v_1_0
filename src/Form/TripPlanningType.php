<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\admin\agency\TripPlanningData;
use App\Entity\Agency;
use App\Entity\Bus;
use App\Entity\BusMaintenance;
use App\Entity\Route;
use App\Entity\User;
use App\Entity\UserRole;
use App\Enum\BusStatus;
use App\Enum\MaintenanceStatus;
use App\Enum\UserType;
use App\Service\TripPlanningService;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Planification d'un voyage. Les listes ne proposent que ce qui appartient à l'agence : un identifiant
 * d'une autre agence envoyé à la main est refusé par le formulaire.
 *
 * Les noms des champs (route_id, bus_id, driver_id, codriver_id, departureDate…) sont ceux du template d'origine,
 * pour que trip-add.js continue de fonctionner ; property_path les relie aux propriétés du DTO.
 */
class TripPlanningType extends AbstractType
{
    private const LABEL = ['class' => 'admin-bus-layout-add-label'];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Agency $agency */
        $agency = $options['agency'];

        $drivers = static fn (EntityRepository $er): QueryBuilder => $er->createQueryBuilder('u')
            ->andWhere('u.agency = :agency')
            ->andWhere('u.userType = :type')
            ->andWhere('u.isActive = :yes')
            ->andWhere(sprintf(
                'EXISTS (SELECT ur.id FROM %s ur JOIN ur.role dr WHERE ur.user = u AND dr.code = :driverRole AND ur.revokedAt IS NULL)',
                UserRole::class,
            ))
            ->setParameter('agency', $agency)
            ->setParameter('type', UserType::Agency->value)
            ->setParameter('yes', true)
            ->setParameter('driverRole', TripPlanningService::DRIVER_ROLE_CODE)
            ->orderBy('u.lastname', 'ASC')
            ->addOrderBy('u.firstname', 'ASC');

        $builder
            ->add('route_id', EntityType::class, [
                'property_path' => 'route',
                'class' => Route::class,
                'label' => 'Ligne / Route Réseau',
                'label_attr' => self::LABEL,
                'placeholder' => '-- Choisir la ligne --',
                'choice_label' => static fn (Route $r): string => $r->getCode().' : '.$r->getName(),
                'query_builder' => static fn (EntityRepository $er): QueryBuilder => $er->createQueryBuilder('r')
                    ->andWhere('r.agency = :agency')
                    ->andWhere('r.isAvailable = :yes')
                    ->setParameter('agency', $agency)
                    ->setParameter('yes', true)
                    ->orderBy('r.name', 'ASC'),
                'attr' => ['class' => 'admin-bus-layout-add-select'],
            ])
            ->add('bus_id', EntityType::class, [
                'property_path' => 'bus',
                'class' => Bus::class,
                'label' => 'Bus Assigné',
                'label_attr' => self::LABEL,
                'placeholder' => '-- Choisir un véhicule --',
                'choice_label' => static fn (Bus $b): string => $b->getPlateNumber().' — '.trim($b->getBrand().' '.$b->getModel())
                    .($b->getBusLayout() ? ' · '.$b->getBusLayout()->getName() : ''),
                // Bus actifs de l'agence, sans panne ouverte (statut « broken »).
                'query_builder' => static fn (EntityRepository $er): QueryBuilder => $er->createQueryBuilder('b')
                    ->andWhere('b.agency = :agency')
                    ->andWhere('b.status = :active')
                    ->andWhere(sprintf('NOT EXISTS (SELECT m.id FROM %s m WHERE m.bus = b AND m.status = :broken)', BusMaintenance::class))
                    ->setParameter('agency', $agency)
                    ->setParameter('active', BusStatus::Active->value)
                    ->setParameter('broken', MaintenanceStatus::Broken->value)
                    ->orderBy('b.plateNumber', 'ASC'),
                'attr' => ['class' => 'admin-bus-layout-add-select'],
            ])
            ->add('driver_id', EntityType::class, [
                'property_path' => 'driver',
                'class' => User::class,
                'label' => 'Chauffeur Titulaire',
                'label_attr' => self::LABEL,
                'placeholder' => '-- Sélectionner le conducteur --',
                'choice_label' => static fn (User $u): string => $u->getFullName(),
                'query_builder' => $drivers,
                'attr' => ['class' => 'admin-bus-layout-add-select'],
            ])
            ->add('codriver_id', EntityType::class, [
                'property_path' => 'coDriver',
                'class' => User::class,
                'label' => 'Convoyeur / Co-Chauffeur',
                'label_attr' => self::LABEL,
                'required' => false,
                'placeholder' => '-- Aucun convoyeur --',
                'choice_label' => static fn (User $u): string => $u->getFullName(),
                'query_builder' => $drivers,
                'attr' => ['class' => 'admin-bus-layout-add-select'],
            ])
            ->add('departureDate', DateType::class, [
                'label' => 'Date de Départ',
                'label_attr' => self::LABEL,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'matrix-input', 'min' => (new \DateTimeImmutable('today'))->format('Y-m-d')],
            ])
            ->add('departureTime', TimeType::class, [
                'label' => 'Heure de Départ',
                'label_attr' => self::LABEL,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'matrix-input'],
            ])
            ->add('arrivalDate', DateType::class, [
                'label' => 'Date d\'Arrivée Estimée',
                'label_attr' => self::LABEL,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'matrix-input', 'min' => (new \DateTimeImmutable('today'))->format('Y-m-d')],
            ])
            ->add('arrivalTime', TimeType::class, [
                'label' => 'Heure d\'Arrivée Estimée',
                'label_attr' => self::LABEL,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'matrix-input'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired('agency')
            ->setAllowedTypes('agency', Agency::class)
            ->setDefaults(['data_class' => TripPlanningData::class]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
