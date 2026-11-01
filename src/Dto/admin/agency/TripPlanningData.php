<?php

declare(strict_types=1);

namespace App\Dto\admin\agency;

use App\Entity\Bus;
use App\Entity\Route;
use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Planification d'un voyage : ligne, bus, équipage et horaires.
 */
final class TripPlanningData
{
    #[Assert\NotNull(message: 'Choisissez la ligne.')]
    public ?Route $route = null;

    #[Assert\NotNull(message: 'Choisissez un bus.')]
    public ?Bus $bus = null;

    #[Assert\NotNull(message: 'Choisissez le chauffeur titulaire.')]
    public ?User $driver = null;

    public ?User $coDriver = null;

    #[Assert\NotNull(message: 'Indiquez la date de départ.')]
    public ?\DateTimeImmutable $departureDate = null;

    #[Assert\NotNull(message: 'Indiquez l\'heure de départ.')]
    public ?\DateTimeImmutable $departureTime = null;

    #[Assert\NotNull(message: 'Indiquez la date d\'arrivée.')]
    public ?\DateTimeImmutable $arrivalDate = null;

    #[Assert\NotNull(message: 'Indiquez l\'heure d\'arrivée.')]
    public ?\DateTimeImmutable $arrivalTime = null;

    /** Départ complet (date + heure), null tant que l'un des deux manque. */
    public function departureAt(): ?\DateTimeImmutable
    {
        return $this->combine($this->departureDate, $this->departureTime);
    }

    /** Arrivée estimée complète (date + heure). */
    public function arrivalAt(): ?\DateTimeImmutable
    {
        return $this->combine($this->arrivalDate, $this->arrivalTime);
    }

    #[Assert\Callback]
    public function validateSchedule(ExecutionContextInterface $context): void
    {
        $departure = $this->departureAt();
        $arrival = $this->arrivalAt();

        if (null !== $departure && $departure < new \DateTimeImmutable()) {
            $context->buildViolation('Le départ ne peut pas être dans le passé.')
                ->atPath('departureDate')
                ->addViolation();
        }

        if (null !== $departure && null !== $arrival && $arrival <= $departure) {
            $context->buildViolation('L\'arrivée doit être après le départ.')
                ->atPath('arrivalDate')
                ->addViolation();
        }

        if (null !== $this->driver && null !== $this->coDriver && $this->driver === $this->coDriver) {
            $context->buildViolation('Le convoyeur doit être différent du chauffeur titulaire.')
                ->atPath('coDriver')
                ->addViolation();
        }
    }

    private function combine(?\DateTimeImmutable $date, ?\DateTimeImmutable $time): ?\DateTimeImmutable
    {
        if (null === $date || null === $time) {
            return null;
        }

        return $date->setTime((int) $time->format('H'), (int) $time->format('i'));
    }
}
