<?php

declare(strict_types=1);

namespace App\Dto\admin\agency;

use App\Enum\MaintenanceSeverity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Déclaration d'une panne technique et mise au garage d'un bus.
 */
final class BusMaintenanceDeclareData
{
    #[Assert\NotBlank(message: 'Sélectionnez le véhicule concerné.')]
    public ?string $busCode = null;

    #[Assert\NotNull(message: 'Choisissez le niveau de gravité.')]
    public MaintenanceSeverity $severity = MaintenanceSeverity::Medium;

    #[Assert\NotNull(message: 'Indiquez la date et l\'heure de constatation.')]
    public ?\DateTimeImmutable $issuedAt = null;

    #[Assert\PositiveOrZero(message: 'Le kilométrage ne peut pas être négatif.')]
    public ?int $mileage = null;

    #[Assert\NotBlank(message: 'Décrivez la nature de l\'avarie.')]
    #[Assert\Length(
        min: 5,
        max: 2000,
        minMessage: 'La description de la panne est trop courte (minimum {{ limit }} caractères).',
        maxMessage: 'La description est trop longue.'
    )]
    public ?string $issueDescription = null;

    public function __construct()
    {
        $this->issuedAt = new \DateTimeImmutable();
    }
}
