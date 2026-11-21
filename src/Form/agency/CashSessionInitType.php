<?php

declare(strict_types=1);

namespace App\Form\agency;

use App\Dto\admin\agency\CashSessionInitData;
use App\Entity\Agency;
use App\Entity\Branch;
use App\Entity\CashRegister;
use App\Entity\User;
use App\Enum\ActiveStatus;
use App\Enum\UserType;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CashSessionInitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Agency $agency */
        $agency = $options['agency'];
        /** @var ?Branch $branch */
        $branch = $options['branch'];
        /** @var ?CashRegister $lockedRegister */
        $lockedRegister = $options['locked_register'];

        $effectiveBranch = $lockedRegister?->getBranch() ?? $branch;

        $builder
            ->add('cashRegister', EntityType::class, [
                'class'              => CashRegister::class,
                'label'              => 'Guichet physique de destination *',
                'disabled'           => null !== $lockedRegister,
                'autocomplete'       => null === $lockedRegister,
                'tom_select_options' => ['plugins' => ['dropdown_input']],
                'choice_label'       => static fn(CashRegister $cr): string => sprintf('%s [%s] — %s', $cr->getName(), $cr->getCode(), $cr->getBranch()?->getName()),
                'query_builder'      => static function (EntityRepository $er) use ($agency, $effectiveBranch, $lockedRegister) {
                    $qb = $er->createQueryBuilder('cr')
                        ->andWhere('cr.agency = :agency')
                        ->andWhere('cr.status = :active')
                        ->setParameter('agency', $agency)
                        ->setParameter('active', ActiveStatus::Active)
                        ->orderBy('cr.name', 'ASC');

                    if (null !== $lockedRegister) {
                        $qb->andWhere('cr = :locked')->setParameter('locked', $lockedRegister);
                    } elseif (null !== $effectiveBranch) {
                        $qb->andWhere('cr.branch = :branch')->setParameter('branch', $effectiveBranch);
                    }

                    return $qb;
                },
            ])
            ->add('cashier', EntityType::class, [
                'class'              => User::class,
                'label'              => 'Guichetier / Caissier assigné *',
                'autocomplete'       => true,
                'tom_select_options' => ['plugins' => ['dropdown_input']],
                'choice_label'       => static fn(User $u): string => sprintf('%s (%s)', $u->getFullName(), $u->getEmail()),
                'query_builder'      => static function (EntityRepository $er) use ($agency, $effectiveBranch) {
                    $qb = $er->createQueryBuilder('u')
                        ->andWhere('u.agency = :agency')
                        ->andWhere('u.userType = :type')
                        ->andWhere('u.isActive = :yes')
                        ->setParameter('agency', $agency)
                        ->setParameter('type', UserType::Agency)
                        ->setParameter('yes', true)
                        ->orderBy('u.firstname', 'ASC');

                    if (null !== $effectiveBranch) {
                        $qb->andWhere('u.branch = :branch')->setParameter('branch', $effectiveBranch);
                    }

                    return $qb;
                },
            ])
            ->add('openingNote', TextareaType::class, [
                'label'    => 'Consignes de service / Notes pour le guichetier',
                'required' => false,
                'attr'     => ['class' => 'input-session-field text-area-fixed', 'placeholder' => 'Ex: Prioriser le rendu de monnaie en petites coupures...'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired('agency')
            ->setAllowedTypes('agency', Agency::class)
            ->setDefaults([
                'data_class'         => CashSessionInitData::class,
                'branch'             => null,
                'locked_register'    => null,
                'allow_extra_fields' => true,
            ]);
    }
}
