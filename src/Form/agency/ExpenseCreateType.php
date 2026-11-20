<?php

declare(strict_types=1);

namespace App\Form\agency;

use App\Dto\admin\agency\ExpenseCreateData;
use App\Entity\Agency;
use App\Entity\Branch;
use App\Entity\CashRegister;
use App\Entity\Currency;
use App\Entity\CurrencyAgency;
use App\Enum\ActiveStatus;
use App\Enum\BranchStatus;
use App\Enum\ExpenseType;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ExpenseCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Agency $agency */
        $agency = $options['agency'];
        /** @var ?Branch $restrictedBranch */
        $restrictedBranch = $options['restricted_branch'];

        $builder
            ->add('type', EnumType::class, [
                'class'        => ExpenseType::class,
                'label'        => 'Type de dépense *',
                'choice_label' => static fn(ExpenseType $t): string => match ($t) {
                    ExpenseType::Other        => 'Frais opérationnels généraux (Carburant, Bureau, Maintenance)',
                    ExpenseType::Subscription => 'Abonnement Plateforme SaaS (Mydigitrans)',
                },
                'attr' => ['id' => 'expense-type-select', 'class' => 'admin-bus-layout-add-select'],
            ])
            ->add('branch', EntityType::class, [
                'class'              => Branch::class,
                'label'              => 'Succursale bénéficiaire *',
                'disabled'           => null !== $restrictedBranch,
                'autocomplete'       => null === $restrictedBranch,
                'tom_select_options' => ['plugins' => ['dropdown_input']],
                'choice_label'       => static fn(Branch $b): string => sprintf('%s (%s)', $b->getName(), $b->getCity()),
                'query_builder'      => static function (EntityRepository $er) use ($agency, $restrictedBranch) {
                    $qb = $er->createQueryBuilder('b')
                        ->andWhere('b.agency = :agency')
                        ->andWhere('b.status = :opened')
                        ->setParameter('agency', $agency)
                        ->setParameter('opened', BranchStatus::Opened)
                        ->orderBy('b.city', 'ASC');
                    if (null !== $restrictedBranch) {
                        $qb->andWhere('b = :branch')->setParameter('branch', $restrictedBranch);
                    }
                    return $qb;
                },
            ])

            ->add('name', TextType::class, [
                'label' => 'Nom / Libellé de la dépense *',
                'attr'  => ['class' => 'admin-bus-layout-add-input', 'placeholder' => 'Ex: Achat carburant Bus #01, Abonnement SaaS...'],
            ])
            ->add('amount', NumberType::class, [
                'label' => 'Montant *',
                'scale' => 2,
                'attr'  => ['class' => 'admin-bus-layout-add-input', 'min' => 0.01, 'step' => '0.01', 'placeholder' => '0.00'],
            ])
            ->add('currency', EntityType::class, [
                'class'         => Currency::class,
                'label'         => 'Devise *',
                'choice_label'  => static fn(Currency $c): string => sprintf('%s', $c->getCode()),
                'query_builder' => static fn(EntityRepository $er) => $er->createQueryBuilder('c')
                    ->innerJoin(CurrencyAgency::class, 'ca', 'WITH', 'ca.currency = c')
                    ->andWhere('ca.agency = :agency')
                    ->andWhere('ca.isActive = :yes')
                    ->setParameter('agency', $agency)
                    ->setParameter('yes', true)
                    ->orderBy('c.code', 'ASC'),
                'attr' => ['class' => 'admin-bus-layout-add-select'],
            ])
            ->add('validFrom', DateType::class, [
                'label'    => 'Valide du (validFrom) *',
                'required' => false,
                'widget'   => 'single_text',
                'input'    => 'datetime_immutable',
                'attr'     => ['id' => 'valid-from-input', 'class' => 'admin-bus-layout-add-input'],
            ])
            ->add('validUntil', DateType::class, [
                'label'    => 'Valide jusqu\'au (validUntil) *',
                'required' => false,
                'widget'   => 'single_text',
                'input'    => 'datetime_immutable',
                'attr'     => ['id' => 'valid-until-input', 'class' => 'admin-bus-layout-add-input'],
            ])
            ->add('fundingSource', ChoiceType::class, [
                'label'   => 'Source du règlement / Financement *',
                'choices' => [
                    'Hors Caisse (Virement bancaire, Chèque, Mobile Money Global)' => 'bank',
                    'Depuis une Caisse de guichet (Espèces physiques)'             => 'cash_register',
                ],
                'attr' => ['id' => 'expense-funding-source', 'class' => 'admin-bus-layout-add-select'],
            ])
            ->add('cashRegister', EntityType::class, [
                'class'              => CashRegister::class,
                'label'              => 'Sélectionner la Caisse émettrice *',
                'required'           => false,
                'autocomplete'       => true,
                'tom_select_options' => ['plugins' => ['dropdown_input']],
                'choice_label'       => static fn(CashRegister $cr): string => sprintf('%s [%s] — %s', $cr->getName(), $cr->getCode(), $cr->getBranch()?->getName()),
                'query_builder'      => static function (EntityRepository $er) use ($agency, $restrictedBranch) {
                    $qb = $er->createQueryBuilder('cr')
                        ->andWhere('cr.agency = :agency')
                        ->andWhere('cr.status = :active')
                        ->setParameter('agency', $agency)
                        ->setParameter('active', ActiveStatus::Active)
                        ->orderBy('cr.name', 'ASC');
                    if (null !== $restrictedBranch) {
                        $qb->andWhere('cr.branch = :branch')->setParameter('branch', $restrictedBranch);
                    }
                    return $qb;
                },
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Description / Notes complémentaires',
                'required' => false,
                'attr'     => ['class' => 'admin-bus-layout-add-input text-area-fixed', 'placeholder' => 'Ajoutez des précisions (N° facture fournisseur, bus concerné...)'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired('agency')
            ->setAllowedTypes('agency', Agency::class)
            ->setDefaults([
                'data_class'        => ExpenseCreateData::class,
                'restricted_branch' => null,
            ]);
    }
}
