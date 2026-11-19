<?php

declare(strict_types=1);

namespace App\Dto\admin\agency;

use App\Entity\Branch;
use App\Entity\Currency;
use App\Entity\RouteStop;
use App\Entity\Trip;
use App\Enum\BillingType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class ShipmentCreateData
{
    #[Assert\NotNull(message: 'Veuillez sélectionner le voyage de transport.')]
    public ?Trip $trip = null;

    #[Assert\NotNull(message: 'Veuillez sélectionner la succursale de départ.')]
    public ?Branch $departureAgencyBranch = null;

    #[Assert\NotNull(message: 'Veuillez sélectionner la succursale d\'arrivée.')]
    public ?Branch $arrivalAgencyBranch = null;

    #[Assert\NotNull(message: 'Veuillez sélectionner l\'arrêt de chargement.')]
    public ?RouteStop $departureStop = null;

    #[Assert\NotNull(message: 'Veuillez sélectionner l\'arrêt de retrait.')]
    public ?RouteStop $arrivalStop = null;

    #[Assert\NotBlank(message: 'Le nom de l\'expéditeur est obligatoire.')]
    #[Assert\Length(max: 120)]
    public ?string $senderName = null;

    #[Assert\NotBlank(message: 'Le téléphone de l\'expéditeur est obligatoire.')]
    #[Assert\Length(max: 30)]
    public ?string $senderPhone = null;

    #[Assert\NotBlank(message: 'Le nom du destinataire est obligatoire.')]
    #[Assert\Length(max: 120)]
    public ?string $receiverName = null;

    #[Assert\NotBlank(message: 'Le téléphone du destinataire est obligatoire.')]
    #[Assert\Length(max: 30)]
    public ?string $receiverPhone = null;

    #[Assert\NotBlank(message: 'Veuillez décrire le contenu du colis.')]
    #[Assert\Length(max: 500)]
    public ?string $description = null;

    #[Assert\NotBlank(message: 'Veuillez choisir la nature de la marchandise.')]
    #[Assert\Length(max: 100)]
    public string $nature = 'Standard';

    #[Assert\NotNull]
    public BillingType $billingType = BillingType::Weight;

    #[Assert\Positive(message: 'Le poids doit être supérieur à 0 kg.')]
    public ?float $weight = 1.0;

    public ?float $priceManual = null;

    public ?Currency $currencyManual = null;

    #[Assert\Callback]
    public function validateBusinessRules(ExecutionContextInterface $context): void
    {
        if (
            null !== $this->departureStop
            && null !== $this->arrivalStop
            && $this->departureStop->getStopOrder() >= $this->arrivalStop->getStopOrder()
        ) {
            $context->buildViolation('L\'arrêt de retrait doit être situé après l\'arrêt de chargement.')
                ->atPath('arrivalStop')
                ->addViolation();
        }

        if (BillingType::Unit === $this->billingType) {
            if (null === $this->priceManual || $this->priceManual <= 0.0) {
                $context->buildViolation('Veuillez saisir un prix forfaitaire supérieur à 0.')
                    ->atPath('priceManual')
                    ->addViolation();
            }
            if (null === $this->currencyManual) {
                $context->buildViolation('Veuillez choisir une devise.')
                    ->atPath('currencyManual')
                    ->addViolation();
            }
        }
    }
}
