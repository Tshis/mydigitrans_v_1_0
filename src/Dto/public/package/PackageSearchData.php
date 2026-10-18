<?php

declare(strict_types=1);

namespace App\Dto\public\package;

use App\Enum\PackageWeightRange;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class PackageSearchData
{
    #[Assert\NotBlank(message: 'Indiquez la ville de départ.')]
    #[Assert\Length(max: 100, maxMessage: 'Le nom de la ville est trop long.')]
    public ?string $departure = null;

    #[Assert\NotBlank(message: 'Indiquez la ville d\'arrivée.')]
    #[Assert\Length(max: 100, maxMessage: 'Le nom de la ville est trop long.')]
    public ?string $arrival = null;

    /**
     * Facultative : sans date, la recherche porte sur les prochains départs.
     */
    #[Assert\GreaterThanOrEqual('today', message: 'La date ne peut pas être dans le passé.')]
    #[Assert\LessThanOrEqual('+90 days', message: 'Vous ne pouvez rechercher que sur les 90 prochains jours.')]
    public ?\DateTimeImmutable $date = null;

    /** Facultatif : nature du colis, conservée pour l'étape d'expédition. */
    #[Assert\Length(max: 100, maxMessage: 'Le type de colis ne peut pas dépasser {{ limit }} caractères.')]
    public ?string $packageType = null;

    #[Assert\NotNull(message: 'Choisissez une tranche de poids.')]
    public ?PackageWeightRange $weightRange = PackageWeightRange::UpTo5;

    #[Assert\Callback]
    public function validateCities(ExecutionContextInterface $context): void
    {
        if (null === $this->departure || null === $this->arrival) {
            return;
        }

        if (mb_strtolower(trim($this->departure)) === mb_strtolower(trim($this->arrival))) {
            $context->buildViolation('La ville d\'arrivée doit être différente de la ville de départ.')
                ->atPath('arrival')
                ->addViolation();
        }
    }
}
