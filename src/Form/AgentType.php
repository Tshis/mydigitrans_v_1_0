<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\admin\AgentData;
use App\Entity\Role; // 🟢 Importation de l'entité Role
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
                'label' => 'Prénom *',
                'label_attr' => self::LABEL,
                'attr' => ['class' => self::INPUT, 'placeholder' => 'Ex: Jean', 'maxlength' => 100, 'autocomplete' => 'off'],
            ])
            ->add('lastname', TextType::class, [
                'label' => 'Nom *',
                'label_attr' => self::LABEL,
                'attr' => ['class' => self::INPUT, 'placeholder' => 'Ex: Mukendi', 'maxlength' => 100, 'autocomplete' => 'off'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse Email *',
                'label_attr' => self::LABEL,
                'attr' => ['class' => self::INPUT, 'placeholder' => 'j.mukendi@exemple.com', 'maxlength' => 180, 'autocomplete' => 'off'],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone mobile *',
                'label_attr' => self::LABEL,
                'attr' => ['class' => self::INPUT . ' font-bold', 'placeholder' => '+243...', 'maxlength' => 20],
            ]);

        // Le champ est toujours présent, mais requis uniquement à la création
        $builder->add('password', PasswordType::class, [
            'label' => $options['is_edit'] ? 'Nouveau mot de passe (laisser vide pour inchangé)' : 'Mot de passe initial *',
            'label_attr' => self::LABEL,
            'required' => !$options['is_edit'],
            'attr' => [
                'class' => self::INPUT,
                'placeholder' => $options['is_edit'] ? 'Laisser vide si inchangé' : '••••••••',
                'autocomplete' => 'new-password'
            ],
        ]);

        // Si on est dans le Scope Agence, on affiche les succursales
        if ($options['with_branch']) {
            $branches = [];
            foreach ($options['branches'] as $branch) {
                // $options['branches'] reste pour l'instant un tableau associatif brut d'options de tri
                $branches[sprintf('%s (#%s)', $branch['name'], $branch['code'])] = $branch['code'];
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

        // Chargement des rôles
        $rolesChoices = [];
        $descriptions = [];

        /** @var Role $role */
        foreach ($options['roles'] as $role) {
            // 🟢 FIX : Utilisation des méthodes get de l'objet d'entité Role
            $rolesChoices[$role->getName()] = $role->getCode();
            $descriptions[$role->getCode()] = $role->getDescription();
        }

        $builder->add('role_code', ChoiceType::class, [
            'property_path' => 'roleCode',
            'label' => 'Fonction *',
            'label_attr' => self::LABEL,
            'choices' => $rolesChoices,
            'choice_attr' => static fn(?string $code): array => ['title' => $descriptions[$code] ?? ''],
            'placeholder' => '-- Choisir une fonction --',
            'attr' => ['class' => self::SELECT],
        ]);

        // Affichage garanti du statut si autorisé par le contrôleur ou hiérarchie d'agence
        $isAgencyManager = $this->security->isGranted('ROLE_AGENCY_ADMIN') || $this->security->isGranted('ROLE_GENERAL_MANAGER');

        if ($options['can_activate'] || $isAgencyManager) {
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
            'can_activate' => false,
            'identity_only' => false,
            'branches' => [],
            'roles' => [],
            'validation_groups' => static fn(FormInterface $form): array => $form->getConfig()->getOption('is_edit') ? ['Default'] : ['Default', 'create'],
        ]);

        $resolver
            ->setAllowedTypes('is_edit', 'bool')
            ->setAllowedTypes('with_branch', 'bool')
            ->setAllowedTypes('can_choose_general', 'bool')
            ->setAllowedTypes('can_activate', 'bool')
            ->setAllowedTypes('identity_only', 'bool')
            ->setAllowedTypes('branches', 'array')
            ->setAllowedTypes('roles', 'array');
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
