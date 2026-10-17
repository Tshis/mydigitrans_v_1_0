<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\LineSearchData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LineSearchType extends AbstractType
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('departure', TextType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'Ville de départ',
                    'autocomplete' => 'off',
                    'maxlength' => 100,
                ],
            ])
            ->add('arrival', TextType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'Destination',
                    'autocomplete' => 'off',
                    'maxlength' => 100,
                ],
            ])
            ->add('date', DateType::class, [
                'label' => false,
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => [
                    'min' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
                ],
            ])
            ->add('passengers', IntegerType::class, [
                'label' => false,
                'required' => false,
                // Le formulaire de l'accueil n'a pas ce champ : valeur 1 par défaut.
                'empty_data' => '1',
                'attr' => [
                    'placeholder' => 'Nombre de passagers',
                    'min' => 1,
                    'max' => LineSearchData::MAX_PASSENGERS,
                    'style' => "width:20rem",
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LineSearchData::class,
            // Le formulaire envoie toujours vers la page de résultats.
            'action' => $this->urlGenerator->generate('public_lines_results'),
            // Formulaire de recherche : en GET, URL partageable, pas de CSRF.
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }

    /**
     * Préfixe vide : l'URL devient ?departure=Lubumbashi&arrival=Kolwezi&date=2026-10-20
     * au lieu de ?trip_search[departure]=...
     */
    public function getBlockPrefix(): string
    {
        return '';
    }
}
