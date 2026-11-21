<?php

declare(strict_types=1);

namespace App\Form\agency;

use App\Dto\admin\agency\CashRegisterCreateData;
use App\Entity\Agency;
use App\Entity\Branch;
use App\Enum\BranchStatus;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CashRegisterCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Agency $agency */
        $agency = $options['agency'];
        /** @var ?Branch $restrictedBranch */
        $restrictedBranch = $options['restricted_branch'];

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom / Libellé du Guichet *',
                'attr'  => ['id' => 'register-name-input', 'placeholder' => 'Ex : Guichet Principal 01, Caisse Fret...'],
            ])
            ->add('code', TextType::class, [
                'label'    => 'Code Unique (Optionnel)',
                'required' => false,
                'attr'     => ['id' => 'register-code-input', 'class' => 'text-uppercase', 'placeholder' => 'Ex : CSH-KIN-01'],
            ])
            ->add('branch', EntityType::class, [
                'class'              => Branch::class,
                'label'              => 'Succursale d\'immatriculation *',
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

            ->add('hardwareNote', TextareaType::class, [
                'label'    => 'Note d\'inventaire matériel (Optionnel)',
                'required' => false,
                'attr'     => ['class' => 'text-area-hardware', 'placeholder' => 'Ex : PC HP ProDesk, Imprimante thermique Epson...'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired('agency')
            ->setAllowedTypes('agency', Agency::class)
            ->setDefaults([
                'data_class'        => CashRegisterCreateData::class,
                'csrf_token_id'     => 'agency_cash_register_create',
                'restricted_branch' => null,
            ]);
    }
}
