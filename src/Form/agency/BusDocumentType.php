<?php

declare(strict_types=1);

namespace App\Form\admin\agency;

use App\Dto\admin\agency\BusDocumentData;
use App\Entity\Bus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class BusDocumentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var string $mode */
        $mode = $options['mode']; // 'create' | 'edit' | 'renew'

        /** @var list<Bus> $fleet */
        $fleet = $options['fleet'];

        if ('create' === $mode) {
            $choices = [];
            foreach ($fleet as $bus) {
                $label = sprintf('%s %s (%s)', $bus->getBrand(), $bus->getModel(), $bus->getPlateNumber());
                $choices[$label] = $bus->getCode();
            }

            $builder->add('busCode', ChoiceType::class, [
                'label'       => 'Associer à un véhicule *',
                'placeholder' => '-- Choisir le bus concerné --',
                'choices'     => $choices,
            ]);
        }

        if ('renew' !== $mode) {
            $builder->add('type', TextType::class, [
                'label' => 'Type de pièce légale *',
                'attr'  => [
                    'placeholder'  => 'Ex: Assurance, Contrôle technique, Carte rose...',
                    'autocomplete' => 'off',
                ],
            ]);
        }

        $builder
            ->add('referenceNumber', TextType::class, [
                'label'    => 'renew' === $mode
                    ? 'Nouveau numéro de document / Référence de série'
                    : 'Numéro du document / Référence de série',
                'required' => false,
                'attr'     => [
                    'placeholder'  => 'Ex: POL-SON-99824...',
                    'autocomplete' => 'off',
                ],
            ])
            ->add('issuedAt', DateType::class, [
                'label'  => 'renew' === $mode ? 'Nouvelle Date d\'Émission *' : 'Date d\'Émission *',
                'widget' => 'single_text',
                'input'  => 'datetime_immutable',
            ])
            ->add('expiredAt', DateType::class, [
                'label'  => 'renew' === $mode ? 'Nouvelle Date d\'Expiration *' : 'Date d\'Expiration *',
                'widget' => 'single_text',
                'input'  => 'datetime_immutable',
            ])
            ->add('documentFile', FileType::class, [
                'label'    => 'edit' === $mode
                    ? 'Remplacer le fichier (optionnel)'
                    : 'Fichier numérique (PDF, JPG, PNG — max 5 Mo)',
                'required' => false,
                'attr'     => [
                    'accept' => '.pdf,.jpg,.jpeg,.png,.webp',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'        => BusDocumentData::class,
            'mode'              => 'create',
            'fleet'             => [],
            'validation_groups' => static function (FormInterface $form): array {
                $mode = (string) $form->getConfig()->getOption('mode');

                return match ($mode) {
                    'edit'  => ['Default', 'edit'],
                    'renew' => ['Default', 'renew'],
                    default => ['Default', 'create'],
                };
            },
        ]);

        $resolver->setAllowedValues('mode', ['create', 'edit', 'renew']);
        $resolver->setAllowedTypes('fleet', 'array');
    }
}
