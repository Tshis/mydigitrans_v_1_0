<?php

declare(strict_types=1);

namespace App\Dto\admin\agency;

use Symfony\Component\Validator\Constraints as Assert;

final class CashSessionValidateData
{
    #[Assert\NotBlank(message: 'Veuillez rédiger votre note d\'arbitrage et de décharge.')]
    #[Assert\Length(max: 1500)]
    public ?string $managerConclusion = null;
}
