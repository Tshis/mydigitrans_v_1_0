<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\public\package\PackageSearchData;
use App\Enum\PackageWeightRange;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PackageSearchType extends AbstractType
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('departure', TextType::class, [
                'label' => 'Ville de départ',
                'attr' => ['placeholder' => 'Ex : Kinshasa', 'autocomplete' => 'off', 'maxlength' => 100],
            ])
            ->add('arrival', TextType::class, [
                'label' => 'Ville d\'arrivée',
                'attr' => ['placeholder' => 'Ex : Matadi', 'autocomplete' => 'off', 'maxlength' => 100],
            ])
            ->add('date', DateType::class, [
                'label' => 'Date souhaitée d\'envoi (optionnel)',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => [
                    'min' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
                    'max' => (new \DateTimeImmutable('today'))->modify('+90 days')->format('Y-m-d'),
                ],
            ])
            ->add('packageType', TextType::class, [
                'label' => 'Type de colis (optionnel)',
                'required' => false,
                'attr' => ['placeholder' => 'Documents, marchandises, effets personnels', 'maxlength' => 100],
            ])
            ->add('weightRange', EnumType::class, [
                'label' => 'Poids approximatif',
                'class' => PackageWeightRange::class,
                'choice_label' => static fn(PackageWeightRange $range): string => $range->label(),
                'placeholder' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PackageSearchData::class,
            // Le formulaire envoie toujours vers la page de résultats.
            'action' => $this->urlGenerator->generate('public_package_results'),
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }

    /**
     * Préfixe vide : l'URL est ?departure=Kinshasa&arrival=Matadi&weightRange=5_to_10
     */
    public function getBlockPrefix(): string
    {
        return '';
    }
}
