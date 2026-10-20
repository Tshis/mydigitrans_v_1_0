<?php

declare(strict_types=1);

namespace App\Dto\public\line;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Étape 1 de la réservation : identité de la personne qui réserve et sièges choisis.
 * Conformément au MCD, la réservation porte l'utilisateur (client) et les sièges ; le billet n'existe qu'au paiement.
 */
final class BookingData
{
    public const MAX_SEATS = 10;

    /** Identifiants des arrêts de montée et de descente (vérifiés côté serveur). */
    public ?string $departureStop = null;

    public ?string $arrivalStop = null;

    #[Assert\NotBlank(message: 'Indiquez votre nom complet.')]
    #[Assert\Length(max: 150, maxMessage: 'Le nom est trop long.')]
    #[Assert\Regex(pattern: '/^\S+(\s+\S+)+$/u', message: 'Indiquez votre nom et votre prénom.')]
    public ?string $fullName = null;

    #[Assert\NotBlank(message: 'Indiquez votre numéro de téléphone.')]
    #[Assert\Regex(pattern: '/^\+?[0-9][0-9 ().-]{7,19}$/', message: 'Numéro de téléphone invalide.')]
    public ?string $phone = null;

    /**
     * Libellés des sièges choisis (Seat::seatLabel), remplis par la carte des sièges.
     *
     * @var list<string|null>
     */
    #[Assert\Count(
        min: 1,
        max: self::MAX_SEATS,
        minMessage: 'Choisissez au moins un siège.',
        maxMessage: 'Vous ne pouvez pas réserver plus de {{ limit }} places à la fois.',
    )]
    #[Assert\All([
        new Assert\NotBlank(message: 'Choisissez tous vos sièges sur le plan du bus.'),
        new Assert\Length(max: 20),
    ])]
    public array $seats = [];

    #[Assert\Callback]
    public function validateSeats(ExecutionContextInterface $context): void
    {
        $seen = [];

        foreach ($this->seats as $index => $seat) {
            $label = null === $seat ? '' : mb_strtolower(trim($seat));

            if ('' === $label) {
                continue;
            }

            if (isset($seen[$label])) {
                $context->buildViolation('Ce siège est choisi deux fois.')
                    ->atPath(sprintf('seats[%d]', $index))
                    ->addViolation();
            }

            $seen[$label] = true;
        }
    }
}
