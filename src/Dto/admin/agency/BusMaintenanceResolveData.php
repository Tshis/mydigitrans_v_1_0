<?php

declare(strict_types=1);

namespace App\Dto\admin\agency;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Clôture d'une intervention technique et remise en service d'un bus.
 */
final class BusMaintenanceResolveData
{
    #[Assert\NotNull(message: 'Indiquez la date et l\'heure de fin des travaux.')]
    public ?\DateTimeImmutable $resolvedAt = null;

    #[Assert\NotBlank(message: 'Saisissez le rapport de réparation avant de valider.')]
    #[Assert\Length(
        min: 5,
        max: 2000,
        minMessage: 'Le rapport de réparation est trop court (minimum {{ limit }} caractères).',
        maxMessage: 'Le rapport est trop long.'
    )]
    public ?string $solutionDescription = null;

    public function __construct()
    {
        $this->resolvedAt = new \DateTimeImmutable();
    }
}
