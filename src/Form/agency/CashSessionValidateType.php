<?php

declare(strict_types=1);

namespace App\Form\agency;

use App\Dto\admin\agency\CashSessionValidateData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CashSessionValidateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('managerConclusion', TextareaType::class, [
                'label' => 'Note explicative de décharge *',
                'attr'  => [
                    'class'       => 'text-area-arbitrage',
                    'placeholder' => 'Saisissez les conclusions de l\'inventaire physique avec l\'agent (ex: Erreur de rendu de monnaie, écart justifié ou retenu)...',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'    => CashSessionValidateData::class,
            'csrf_token_id' => 'agency_cash_session_validate',
        ]);
    }
}
