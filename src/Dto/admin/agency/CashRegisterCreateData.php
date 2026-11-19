<?php

declare(strict_types=1);

namespace App\Dto\admin\agency;

use App\Entity\Branch;
use Symfony\Component\Validator\Constraints as Assert;

final class CashRegisterCreateData
{
    #[Assert\NotBlank(message: 'Le libellé du guichet est obligatoire.')]
    #[Assert\Length(max: 100)]
    public ?string $name = null;

    #[Assert\NotBlank(message: 'Le code d\'identification unique est obligatoire.')]
    #[Assert\Length(max: 50)]
    public ?string $code = null;

    #[Assert\NotNull(message: 'Veuillez sélectionner la succursale d\'affectation.')]
    public ?Branch $branch = null;

    #[Assert\Length(max: 500)]
    public ?string $hardwareNote = null;
}
