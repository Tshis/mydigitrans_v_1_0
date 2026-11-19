<?php

declare(strict_types=1);

namespace App\Dto\admin\agency;

use Symfony\Component\Validator\Constraints as Assert;

final class CashSessionOpenData
{
    /** @var array<string, float|string|null> code devise => montant compté par le caissier */
    public array $openingBalances = [];

    #[Assert\Length(max: 1000)]
    public ?string $openingObservation = null;
}
