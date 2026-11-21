<?php

declare(strict_types=1);

namespace App\Form\agency;

use App\Dto\admin\agency\BranchData;
use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class BranchType extends AbstractType
{
    private const PREFERRED_COUNTRIES = ['CD', 'CG', 'AO', 'ZM', 'UG', 'RW', 'BI', 'TZ'];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<User> $agents */
        $agents = $options['agents'];

        $managerChoices = [];
        foreach ($agents as $agent) {
            $label = sprintf('%s (%s)', $agent->getFullName() ?? $agent->getEmail(), $agent->getEmail());
            $managerChoices[$label] = $agent->getId();
        }

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la succursale *',
                'attr'  => [
                    'placeholder' => 'Ex: Agence de Limete',
                ],
            ])
            ->add('code', TextType::class, [
                'label'    => 'Code de la succursale (Facultatif)',
                'required' => false,
                'attr'     => [
                    'placeholder' => 'Généré automatiquement si vide (Ex: SUC-KIN-01)',
                ],
            ])
            ->add('city', TextType::class, [
                'label' => 'Ville de la succursale *',
                'attr'  => [
                    'placeholder' => 'Ex: Kinshasa',
                ],
            ])
            ->add('country', CountryType::class, [
                'label'              => 'Pays de la succursale *',
                'preferred_choices'  => self::PREFERRED_COUNTRIES,
                'placeholder'        => '-- Choisir un pays --',
                'autocomplete'       => true,
                'tom_select_options' => [
                    'plugins'     => ['dropdown_input'],
                    'placeholder' => 'Rechercher un pays...',
                ],
            ])
            ->add('managerId', ChoiceType::class, [
                'label'       => 'Gérant (Directeur de succursale)',
                'placeholder' => '-- Aucun gérant désigné pour l\'instant --',
                'required'    => false,
                'choices'     => $managerChoices,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'    => BranchData::class,
            'csrf_token_id' => 'agency_branch_form',
            'agents'        => [],

        ]);

        $resolver->setAllowedTypes('agents', 'array');
    }
}
