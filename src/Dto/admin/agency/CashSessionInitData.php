<?php

declare(strict_types=1);

namespace App\Dto\admin\agency;

use App\Entity\CashRegister;
use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

final class CashSessionInitData
{
    #[Assert\NotNull(message: 'Veuillez sélectionner le guichet physique.')]
    public ?CashRegister $cashRegister = null;

    #[Assert\NotNull(message: 'Veuillez sélectionner le caissier assigné.')]
    public ?User $cashier = null;

    /** @var array<string, float|string|null> code devise => montant alloué */
    public array $amounts = [];

    #[Assert\Length(max: 1000)]
    public ?string $openingNote = null;
}
