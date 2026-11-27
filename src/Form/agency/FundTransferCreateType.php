<?php

declare(strict_types=1);

namespace App\Form\agency;

use App\Entity\Agency;
use App\Entity\Branch;
use App\Entity\Currency;
use App\Entity\CurrencyAgency;
use App\Enum\FundTransferDestinationType;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class FundTransferCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Agency $agency */
        $agency = $options['agency'];
        /** @var ?Branch $restrictedBranch */
        $restrictedBranch = $options['restricted_branch'];

        if (null === $restrictedBranch) {
            $builder->add('branchSource', EntityType::class, [
                'class'              => Branch::class,
                'label'              => 'Succursale émettrice *',
                'required'           => false,
                'autocomplete'       => true,
                'tom_select_options' => [
                    'plugins' => ['dropdown_input'],
                ],
                'attr'               => [
                    'placeholder' => '-- Sélectionner la succursale --',
                ],
                'choice_label'       => static fn(Branch $b): string => sprintf('%s (%s)', $b->getName(), $b->getCity()),
                'query_builder'      => static fn(EntityRepository $er): QueryBuilder => $er->createQueryBuilder('b')
                    ->andWhere('b.agency = :agency')
                    ->setParameter('agency', $agency)
                    ->orderBy('b.city', 'ASC')
                    ->addOrderBy('b.name', 'ASC'),
                'constraints'        => [
                    new Assert\NotNull(message: 'La succursale émettrice est obligatoire.'),
                ],
            ]);
        }

        $builder
            ->add('destinationType', EnumType::class, [
                'class'        => FundTransferDestinationType::class,
                'label'        => 'Destination des fonds *',
                'choice_label' => static fn(FundTransferDestinationType $t): string => match ($t) {
                    FundTransferDestinationType::Bank   => 'Versement Bancaire (Banque)',
                    FundTransferDestinationType::Branch => 'Remise vers le Siège Central / Succursale',
                },
                'attr'         => ['id' => 'js-transfer-dest-type'],
            ])
            ->add('branchDestination', EntityType::class, [
                'class'              => Branch::class,
                'label'              => 'Succursale / Siège destinataire *',
                'required'           => false,
                'autocomplete'       => true,
                'tom_select_options' => [
                    'plugins' => ['dropdown_input'],
                ],
                'attr'               => [
                    'placeholder' => '-- Sélectionner la destination --',
                ],
                'choice_label'       => static fn(Branch $b): string => sprintf('%s (%s)', $b->getName(), $b->getCity()),
                'query_builder'      => static function (EntityRepository $er) use ($agency, $restrictedBranch): QueryBuilder {
                    $qb = $er->createQueryBuilder('b')
                        ->andWhere('b.agency = :agency')
                        ->setParameter('agency', $agency)
                        ->orderBy('b.city', 'ASC')
                        ->addOrderBy('b.name', 'ASC');

                    if (null !== $restrictedBranch) {
                        $qb->andWhere('b.id != :selfId')->setParameter('selfId', $restrictedBranch->getId());
                    }

                    return $qb;
                },
            ])
            ->add('currency', EntityType::class, [
                'class'              => Currency::class,
                'label'              => 'Devise remise *',
                'autocomplete'       => true,
                'tom_select_options' => [
                    'plugins' => ['dropdown_input'],
                ],
                'choice_label'       => static fn(Currency $c): string => sprintf('%s — %s', $c->getCode(), $c->getName()),
                'query_builder'      => static fn(EntityRepository $er): QueryBuilder => $er->createQueryBuilder('c')
                    ->innerJoin(CurrencyAgency::class, 'ca', 'WITH', 'ca.currency = c')
                    ->andWhere('ca.agency = :agency')
                    ->andWhere('ca.isActive = :yes')
                    ->setParameter('agency', $agency)
                    ->setParameter('yes', true)
                    ->orderBy('ca.isBase', 'DESC')
                    ->addOrderBy('c.code', 'ASC'),
                'constraints'        => [
                    new Assert\NotNull(message: 'La devise est obligatoire.'),
                ],
            ])
            ->add('amount', NumberType::class, [
                'label'       => 'Montant transféré *',
                'scale'       => 2,
                'html5'       => true,
                'attr'        => [
                    'step'        => '0.01',
                    'min'         => '0.01',
                    'placeholder' => '0.00',
                ],
                'constraints' => [
                    new Assert\NotNull(message: 'Le montant est obligatoire.'),
                    new Assert\Positive(message: 'Le montant doit être supérieur à 0.'),
                ],
            ])
            ->add('bankName', TextType::class, [
                'label'    => 'Nom de la Banque *',
                'required' => false,
                'attr'     => ['placeholder' => 'Ex: Rawbank, Equity BCDC, TMB...'],
            ])
            ->add('bankReference', TextType::class, [
                'label'    => 'N° Bordereau de versement *',
                'required' => false,
                'attr'     => ['placeholder' => 'Ex: BDR-2026-88412'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'agency'            => null,
            'restricted_branch' => null,
        ]);

        $resolver->setRequired(['agency']);
        $resolver->setAllowedTypes('agency', [Agency::class]);
        $resolver->setAllowedTypes('restricted_branch', ['null', Branch::class]);
    }
}
