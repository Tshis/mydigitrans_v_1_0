<?php

declare(strict_types=1);

namespace App\Dto\admin\agency;

use Symfony\Component\Validator\Constraints as Assert;

final class CashSessionCloseData
{
    /** @var array<string, float|string|null> code devise => montant physique recompté */
    public array $physicalBalances = [];

    #[Assert\Length(max: 1000)]
    public ?string $closingObservation = null;
}
