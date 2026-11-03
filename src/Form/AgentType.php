<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\admin\AgentData;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire utilisateur commun aux agences et à la plateforme.
 */
class AgentType extends AbstractType
{
    private const LABEL = ['class' => 'admin-bus-layout-add-label'];
    private const INPUT = 'admin-bus-layout-add-input';
    private const SELECT = 'admin-bus-layout-add-select';

    public function __construct(
        private readonly Security $security
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstname', TextType::class, [
                'label' => 'Prénom (firstname) *',
                'label_attr' => self::LABEL,
                'attr' => ['class' => self::INPUT, 'placeholder' => 'Ex: Jean', 'maxlength' => 100, 'autocomplete' => 'off'],
            ])
            ->add('lastname', TextType::class, [
                'label' => 'Nom (lastname) *',
                'label_attr' => self::LABEL,
                'attr' => ['class' => self::INPUT, 'placeholder' => 'Ex: Mukendi', 'maxlength' => 100, 'autocomplete' => 'off'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse Email (Identifiant de connexion) *',
                'label_attr' => self::LABEL,
                'attr' => ['class' => self::INPUT, 'placeholder' => 'j.mukendi@exemple.com', 'maxlength' => 180, 'autocomplete' => 'off'],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone mobile *',
                'label_attr' => self::LABEL,
                'attr' => ['class' => self::INPUT . ' font-bold', 'placeholder' => '+243...', 'maxlength' => 20],
            ]);

        if (!$options['is_edit']) {
            $builder->add('password', PasswordType::class, [
                'label' => 'Mot de passe initial *',
                'label_attr' => self::LABEL,
                'attr' => ['class' => self::INPUT, 'placeholder' => '••••••••', 'autocomplete' => 'new-password'],
            ]);
        }

        if ($options['with_branch']) {
            $branches = [];
            /** @var \App\Entity\Branch $branch */
            foreach ($options['branches'] as $branch) {
                // Utilisation des getters de l'objet Branch issu de la BDD
                $branches[sprintf('%s (#%s)', $branch->getName(), $branch->getCode())] = $branch->getCode();
            }

            $builder->add('branch_code', ChoiceType::class, [
                'property_path' => 'branchCode',
                'label' => 'Succursale d\'affectation',
                'label_attr' => self::LABEL,
                'choices' => $branches,
                'required' => !$options['can_choose_general'],
                'placeholder' => $options['can_choose_general'] ? '-- Siège Central (Accès Général) --' : false,
                'attr' => ['class' => self::SELECT],
            ]);
        }

        $rolesChoices = [];
        $descriptions = [];
        /** @var \App\Entity\Role $role */
        foreach ($options['roles'] as $role) {
            // Utilisation des getters de l'objet Role issu de la BDD
            $rolesChoices[$role->getName()] = $role->getCode();
            $descriptions[$role->getCode()] = $role->getDescription();
        }

        $builder->add('role_code', ChoiceType::class, [
            'property_path' => 'roleCode',
            'label' => 'Fonction (rôle système) *',
            'label_attr' => self::LABEL,
            'choices' => $rolesChoices,
            'choice_attr' => static fn(string $code): array => ['title' => $descriptions[$code] ?? ''],
            'placeholder' => '-- Choisir une fonction --',
            'attr' => ['class' => self::SELECT],
        ]);

        if ($this->security->isGranted('ROLE_AGENCY_ADMIN')) {
            $builder->add('is_active', ChoiceType::class, [
                'property_path' => 'isActive',
                'label' => 'État du compte',
                'label_attr' => self::LABEL,
                'choices' => ['Compte actif (accès autorisé)' => '1', 'Compte bloqué (connexion interdite)' => '0'],
                'placeholder' => false,
                'attr' => ['class' => self::SELECT],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AgentData::class,
            'is_edit' => false,
            'with_branch' => true,
            'can_choose_general' => true,
            'branches' => [],
            'roles' => [],
            'validation_groups' => static fn(FormInterface $form): array => $form->getConfig()->getOption('is_edit') ? ['Default'] : ['Default', 'create'],
        ]);

        $resolver
            ->setAllowedTypes('is_edit', 'bool')
            ->setAllowedTypes('with_branch', 'bool')
            ->setAllowedTypes('can_choose_general', 'bool')
            ->setAllowedTypes('branches', 'array')
            ->setAllowedTypes('roles', 'array');
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
