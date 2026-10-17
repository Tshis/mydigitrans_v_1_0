<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class LineSearchData
{
    public const MAX_PASSENGERS = 10;

    #[Assert\NotBlank(message: 'Indiquez la ville de départ.')]
    #[Assert\Length(max: 100, maxMessage: 'Le nom de la ville est trop long.')]
    public ?string $departure = null;

    #[Assert\NotBlank(message: 'Indiquez la destination.')]
    #[Assert\Length(max: 100, maxMessage: 'Le nom de la ville est trop long.')]
    public ?string $arrival = null;

    ##[Assert\NotNull(message: 'Choisissez une date de voyage.')]
    #[Assert\GreaterThanOrEqual('today', message: 'La date ne peut pas être dans le passé.')]
    public ?\DateTimeImmutable $date = null;

    ##[Assert\NotNull(message: 'Indiquez le nombre de passagers.')]
    #[Assert\Range(
        min: 1,
        max: self::MAX_PASSENGERS,
        notInRangeMessage: 'Le nombre de passagers doit être compris entre {{ min }} et {{ max }}.',
    )]
    public ?int $passengers = 1;

    #[Assert\Callback]
    public function validateCities(ExecutionContextInterface $context): void
    {
        if (null === $this->departure || null === $this->arrival) {
            return;
        }

        if (mb_strtolower(trim($this->departure)) === mb_strtolower(trim($this->arrival))) {
            $context->buildViolation('La destination doit être différente de la ville de départ.')
                ->atPath('arrival')
                ->addViolation();
        }
    }
}
