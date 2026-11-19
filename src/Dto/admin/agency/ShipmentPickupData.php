<?php

declare(strict_types=1);

namespace App\Dto\admin\agency;

use Symfony\Component\Validator\Constraints as Assert;

final class ShipmentPickupData
{
    #[Assert\NotBlank(message: 'Veuillez saisir le code secret de retrait à 4 caractères.')]
    #[Assert\Length(min: 4, max: 10)]
    public ?string $pickupCode = null;

    #[Assert\NotBlank(message: 'Veuillez sélectionner le type de pièce d\'identité.')]
    public string $receiverIdType = 'carte_electeur';

    #[Assert\NotBlank(message: 'Veuillez saisir le numéro de la pièce d\'identité présentée.')]
    #[Assert\Length(max: 80)]
    public ?string $receiverIdNumber = null;
}
