<?php

declare(strict_types=1);

namespace App\Dto\admin\agency;

use App\Enum\FareType;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Configuration complète de la matrice tarifaire d'une ligne de transport.
 */
final class FareMatrixData
{
    #[Assert\NotNull(message: 'Choisissez la catégorie tarifaire.')]
    public FareType $fareType = FareType::Standard;

    #[Assert\NotNull(message: 'Indiquez le prix du billet direct.')]
    #[Assert\Positive(message: 'Le prix du billet direct doit être supérieur à 0.')]
    public ?float $directAmount = null;

    #[Assert\PositiveOrZero(message: 'Le prix du fret direct ne peut pas être négatif.')]
    public ?float $directFret = null;

    #[Assert\NotBlank(message: 'Choisissez la devise de la grille.')]
    public ?string $currencyCode = null;

    /** @var list<FareSegmentData> */
    #[Assert\Valid]
    public array $segments = [];
}
