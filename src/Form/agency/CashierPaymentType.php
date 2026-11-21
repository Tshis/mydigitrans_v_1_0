<?php

declare(strict_types=1);

namespace App\Form\agency;

use App\Dto\admin\agency\CashierPaymentData;
use App\Entity\Agency;
use App\Entity\Currency;
use App\Entity\CurrencyAgency;
use App\Enum\PaymentMethod;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CashierPaymentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Agency $agency */
        $agency = $options['agency'];

        $builder
            ->add('voucherReference', HiddenType::class, [
                'attr' => ['id' => 'hidden-voucher-reference'],
            ])
            ->add('discountAmount', NumberType::class, [
                'label'    => 'Appliquer une remise',
                'required' => false,
                'scale'    => 2,
                'attr'     => ['id' => 'discount-input', 'class' => 'input-search-voucher', 'min' => 0, 'step' => '0.01', 'placeholder' => '0'],
            ])
            ->add('discountType', ChoiceType::class, [
                'label'   => 'Type de remise',
                'choices' => [
                    'Pourcentage (%)' => 'percent',
                    'Valeur Fixe'     => 'value',
                ],
                'attr'    => ['id' => 'discount-type', 'class' => 'input-search-voucher'],
            ])
            ->add('paymentMethod', EnumType::class, [
                'class'        => PaymentMethod::class,
                'label'        => 'Mode de règlement *',
                'choice_label' => static fn(PaymentMethod $m): string => match ($m) {
                    PaymentMethod::Cash         => 'Espèces (Cash)',
                    PaymentMethod::MobileMoney  => 'Mobile Money (M-Pesa / Airtel / Orange)',
                    PaymentMethod::Card         => 'Carte Bancaire (TPE)',
                    PaymentMethod::BankTransfer => 'Virement Bancaire',
                },
                'attr' => ['class' => 'input-search-voucher font-bold'],
            ])
            ->add('capturedAmount', NumberType::class, [
                'label' => 'Montant réellement encaissé *',
                'scale' => 2,
                'attr'  => ['id' => 'captured-amount', 'class' => 'input-search-voucher font-bold', 'step' => '0.01', 'placeholder' => 'Saisir la somme reçue'],
            ])
            ->add('capturedCurrency', EntityType::class, [
                'class'         => Currency::class,
                'label'         => 'Devise physiquement reçue *',
                'choice_label'  => static fn(Currency $c): string => sprintf('%s (%s)', $c->getCode(), $c->getName()),
                'query_builder' => static fn(EntityRepository $er) => $er->createQueryBuilder('c')
                    ->innerJoin(CurrencyAgency::class, 'ca', 'WITH', 'ca.currency = c')
                    ->andWhere('ca.agency = :agency')
                    ->andWhere('ca.isActive = :yes')
                    ->setParameter('agency', $agency)
                    ->setParameter('yes', true)
                    ->orderBy('c.code', 'ASC'),
                'attr' => ['id' => 'captured-currency-select', 'class' => 'input-search-voucher font-bold'],
            ])
            ->add('discountObservation', TextareaType::class, [
                'label'    => 'Observation / Justificatif de la remise',
                'required' => false,
                'attr'     => ['id' => 'discount-observation', 'class' => 'input-search-voucher text-area-fixed', 'placeholder' => 'Obligatoire si une remise est appliquée...'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired('agency')
            ->setAllowedTypes('agency', Agency::class)
            ->setDefaults([
                'data_class'    => CashierPaymentData::class,
                'csrf_token_id' => 'agency_cashier_payment',
            ]);
    }
}
