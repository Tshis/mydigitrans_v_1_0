<?php

declare(strict_types=1);

namespace App\Dto\admin\agency;

use App\Entity\Currency;
use App\Enum\PaymentMethod;
use Symfony\Component\Validator\Constraints as Assert;

final class CashierPaymentData
{
    #[Assert\NotBlank(message: 'Veuillez saisir la référence du bon client (Réservation ou Colis).')]
    #[Assert\Length(max: 50)]
    public ?string $voucherReference = null;

    #[Assert\GreaterThanOrEqual(value: 0, message: 'La remise ne peut pas être négative.')]
    public ?float $discountAmount = 0.0;

    #[Assert\Choice(choices: ['percent', 'value'])]
    public string $discountType = 'percent';

    #[Assert\NotNull(message: 'Veuillez saisir le montant réellement encaissé.')]
    #[Assert\Positive(message: 'Le montant encaissé doit être supérieur à 0.')]
    public ?float $capturedAmount = null;

    #[Assert\NotNull(message: 'Veuillez sélectionner la devise reçue.')]
    public ?Currency $capturedCurrency = null;

    #[Assert\NotNull]
    public PaymentMethod $paymentMethod = PaymentMethod::Cash;

    #[Assert\Length(max: 1000)]
    public ?string $discountObservation = null;
}
