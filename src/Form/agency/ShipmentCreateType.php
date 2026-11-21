<?php

declare(strict_types=1);

namespace App\Form\agency;

use App\Dto\admin\agency\ShipmentCreateData;
use App\Entity\Agency;
use App\Entity\Branch;
use App\Entity\Currency;
use App\Entity\CurrencyAgency;
use App\Entity\RouteStop;
use App\Entity\Trip;
use App\Enum\BillingType;
use App\Enum\BranchStatus;
use App\Enum\TripStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ShipmentCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Agency $agency */
        $agency = $options['agency'];
        $today  = (new \DateTimeImmutable())->setTime(0, 0);

        $builder
            ->add('trip', EntityType::class, [
                'class' => Trip::class,
                'label' => 'Voyage / Soute d\'affectation *',
                'autocomplete' => true,
                'tom_select_options' => ['plugins' => ['dropdown_input']],
                'placeholder' => '-- Choisir un voyage --',
                'choice_label' => static fn(Trip $t): string => sprintf(
                    '%s — %s (%s à %s)',
                    strtoupper((string) $t->getCode()),
                    (string) $t->getRoute()?->getName(),
                    $t->getDepartureDate()?->format('d/m/Y') ?? '-',
                    $t->getDepartureTime()?->format('H\hi') ?? '-'
                ),
                'query_builder' => static fn(EntityRepository $er) => $er->createQueryBuilder('t')
                    ->join('t.route', 'r')
                    ->andWhere('r.agency = :agency')
                    ->andWhere('t.departureDate >= :today')
                    ->andWhere('t.status IN (:statuses)')
                    ->setParameter('agency', $agency)
                    ->setParameter('today', $today, Types::DATE_IMMUTABLE)
                    ->setParameter('statuses', [TripStatus::Scheduled->value, TripStatus::Boarding->value])
                    ->orderBy('t.departureDate', 'ASC')
                    ->addOrderBy('t.departureTime', 'ASC'),
            ])
            ->add('departureAgencyBranch', EntityType::class, [
                'class' => Branch::class,
                'label' => 'Succursale de départ *',
                'autocomplete' => true,
                'tom_select_options' => ['plugins' => ['dropdown_input']],
                'choice_label' => static fn(Branch $b): string => sprintf('%s (%s)', $b->getName(), $b->getCity()),
                'query_builder' => static fn(EntityRepository $er) => $er->createQueryBuilder('b')
                    ->andWhere('b.agency = :agency')
                    ->andWhere('b.status = :opened')
                    ->setParameter('agency', $agency)
                    ->setParameter('opened', BranchStatus::Opened)
                    ->orderBy('b.city', 'ASC'),
            ])
            ->add('arrivalAgencyBranch', EntityType::class, [
                'class' => Branch::class,
                'label' => 'Succursale d\'arrivée *',
                'autocomplete' => true,
                'tom_select_options' => ['plugins' => ['dropdown_input']],
                'choice_label' => static fn(Branch $b): string => sprintf('%s (%s)', $b->getName(), $b->getCity()),
                'query_builder' => static fn(EntityRepository $er) => $er->createQueryBuilder('b')
                    ->andWhere('b.agency = :agency')
                    ->andWhere('b.status = :opened')
                    ->setParameter('agency', $agency)
                    ->setParameter('opened', BranchStatus::Opened)
                    ->orderBy('b.city', 'ASC'),
            ])
            ->add('departureStop', EntityType::class, [
                'class' => RouteStop::class,
                'label' => 'Arrêt de chargement *',
                'autocomplete' => true,
                'tom_select_options' => ['plugins' => ['dropdown_input']],
                'choice_label' => static fn(RouteStop $s): string => sprintf('%s (%s)', $s->getCity(), $s->getRoute()?->getName()),
                'query_builder' => static fn(EntityRepository $er) => $er->createQueryBuilder('s')
                    ->join('s.route', 'r')
                    ->andWhere('r.agency = :agency')
                    ->andWhere('s.isActive = :yes')
                    ->setParameter('agency', $agency)
                    ->setParameter('yes', true)
                    ->orderBy('s.stopOrder', 'ASC'),
            ])
            ->add('arrivalStop', EntityType::class, [
                'class' => RouteStop::class,
                'label' => 'Arrêt de retrait *',
                'autocomplete' => true,
                'tom_select_options' => ['plugins' => ['dropdown_input']],
                'choice_label' => static fn(RouteStop $s): string => sprintf('%s (%s)', $s->getCity(), $s->getRoute()?->getName()),
                'query_builder' => static fn(EntityRepository $er) => $er->createQueryBuilder('s')
                    ->join('s.route', 'r')
                    ->andWhere('r.agency = :agency')
                    ->andWhere('s.isActive = :yes')
                    ->setParameter('agency', $agency)
                    ->setParameter('yes', true)
                    ->orderBy('s.stopOrder', 'ASC'),
            ])
            ->add('senderName', TextType::class, [
                'label' => 'Nom de l\'Expéditeur *',
                'attr' => ['placeholder' => 'Ex : Mbala Jean'],
            ])
            ->add('senderPhone', TelType::class, [
                'label' => 'Téléphone Expéditeur *',
                'attr' => ['placeholder' => 'Ex : +243...'],
            ])
            ->add('receiverName', TextType::class, [
                'label' => 'Nom du Destinataire *',
                'attr' => ['placeholder' => 'Ex : Toko Dieudonné'],
            ])
            ->add('receiverPhone', TelType::class, [
                'label' => 'Téléphone Destinataire *',
                'attr' => ['placeholder' => 'Ex : +243...'],
            ])
            ->add('description', TextType::class, [
                'label' => 'Description / Contenu déclaré *',
                'attr' => ['placeholder' => 'Ex : Carton pièces de rechange, sac...'],
            ])
            ->add('nature', ChoiceType::class, [
                'label' => 'Nature Marchandise *',
                'choices' => [
                    'Standard (Cartons ordinaires)' => 'Standard',
                    'Fragile / Électronique'         => 'Fragile',
                    'Périssable / Vivres'            => 'Perishable',
                ],
            ])
            ->add('billingType', EnumType::class, [
                'class' => BillingType::class,
                'label' => 'Mode de Facturation *',
                'choice_label' => static fn(BillingType $type): string => match ($type) {
                    BillingType::Weight => 'Facturation au Poids (Par Kg)',
                    BillingType::Unit   => 'Facturation Forfaitaire (Par Pièce)',
                },
            ])
            ->add('weight', NumberType::class, [
                'label' => 'Poids du colis (En Kg) *',
                'scale' => 2,
                'attr' => ['min' => 0.1, 'step' => '0.1'],
            ])
            ->add('priceManual', NumberType::class, [
                'label' => 'Prix forfaitaire manuel *',
                'required' => false,
                'scale' => 2,
                'attr' => ['min' => 0.01, 'step' => '0.01'],
            ])
            ->add('currencyManual', EntityType::class, [
                'class' => Currency::class,
                'label' => 'Devise *',
                'required' => false,
                'choice_label' => 'code',
                'query_builder' => static fn(EntityRepository $er) => $er->createQueryBuilder('c')
                    ->innerJoin(CurrencyAgency::class, 'ca', 'WITH', 'ca.currency = c')
                    ->andWhere('ca.agency = :agency')
                    ->andWhere('ca.isActive = :yes')
                    ->setParameter('agency', $agency)
                    ->setParameter('yes', true)
                    ->orderBy('c.code', 'ASC'),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired('agency')
            ->setAllowedTypes('agency', Agency::class)
            ->setDefaults([
                'data_class'    => ShipmentCreateData::class,
                'csrf_token_id' => 'agency_shipment_create',
            ]);
    }
}
