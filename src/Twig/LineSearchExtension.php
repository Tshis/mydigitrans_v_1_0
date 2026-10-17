<?php

declare(strict_types=1);

namespace App\Twig;


use App\Form\LineSearchType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class LineSearchExtension extends AbstractExtension
{
    public function __construct(private readonly FormFactoryInterface $formFactory) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('line_search_form', $this->createSearchForm(...)),
        ];
    }

    /**
     * Formulaire de recherche vierge, utilisable dans n'importe quel template.
     */
    public function createSearchForm(): FormView
    {
        return $this->formFactory->create(LineSearchType::class)->createView();
    }
}
