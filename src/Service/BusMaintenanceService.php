<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\admin\agency\BusMaintenanceDeclareData;
use App\Dto\admin\agency\BusMaintenanceResolveData;
use App\Entity\Agency;
use App\Entity\Bus;
use App\Entity\BusMaintenance;
use App\Entity\User;
use App\Enum\BusStatus;
use App\Enum\MaintenanceStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service de gestion de l'atelier central, des pannes et des interventions sur la flotte d'une agence.
 */
final class BusMaintenanceService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Liste l'historique complet des pannes de l'agence et calcule les statistiques de l'atelier.
     *
     * @return array{
     *     fleet: list<array<string, mixed>>,
     *     stats: array{total: int, broken: int, resolved: int, cancelled: int}
     * }
     */
    public function listAgencyMaintenances(Agency $agency): array
    {
        /** @var list<BusMaintenance> $logs */
        $logs = $this->em->createQueryBuilder()
            ->select('m', 'b', 'rb', 'ub')
            ->from(BusMaintenance::class, 'm')
            ->join('m.bus', 'b')
            ->join('m.reportedBy', 'rb')
            ->leftJoin('m.updatedBy', 'ub')
            ->andWhere('b.agency = :agency')
            ->setParameter('agency', $agency)
            ->orderBy('m.reportedAt', 'DESC')
            ->getQuery()
            ->getResult();

        $stats = [
            'total'     => \count($logs),
            'broken'    => 0,
            'resolved'  => 0,
            'cancelled' => 0,
        ];

        $fleetLogs = [];
        foreach ($logs as $log) {
            $status = $log->getStatus();
            if (MaintenanceStatus::Broken === $status) {
                ++$stats['broken'];
            } elseif (MaintenanceStatus::Resolved === $status) {
                ++$stats['resolved'];
            } elseif (MaintenanceStatus::Cancelled === $status) {
                ++$stats['cancelled'];
            }

            $statusLabel = match ($status) {
                MaintenanceStatus::Broken    => 'En Panne',
                MaintenanceStatus::Resolved  => 'Résolu',
                MaintenanceStatus::Cancelled => 'Annulé',
            };

            $bus = $log->getBus();
            $fleetLogs[] = [
                'id'           => $log->getId(),
                'busCode'      => (string) $bus?->getCode(),
                'brand'        => (string) $bus?->getBrand(),
                'model'        => (string) $bus?->getModel(),
                'plateNumber'  => (string) $bus?->getPlateNumber(),
                'reportedAt'   => $log->getReportedAt(),
                'issuedAt'     => $log->getIssuedAt(),
                'resolvedAt'   => $log->getResolvedAt(),
                'severity'     => $log->getSeverity()->value,
                'mileage'      => $log->getMileage(),
                'solution'     => $log->getSolution(),
                'reportedBy'   => $log->getReportedBy()?->getFullName() ?? 'Inconnu',
                'updatedBy'    => $log->getUpdatedBy()?->getFullName(),
                'status'       => $status->value,
                'statusLabel'  => $statusLabel,
            ];
        }

        return [
            'fleet' => $fleetLogs,
            'stats' => $stats,
        ];
    }

    /**
     * Retourne les véhicules actifs d'une agence disponibles pour une déclaration de panne.
     *
     * @return list<Bus>
     */
    public function operationalBuses(Agency $agency): array
    {
        return $this->em->getRepository(Bus::class)->findBy(
            ['agency' => $agency, 'status' => BusStatus::Active],
            ['plateNumber' => 'ASC']
        );
    }

    /**
     * Recherche un ticket de maintenance en vérifiant qu'il appartient bien à l'agence.
     */
    public function findAgencyMaintenance(Agency $agency, int $id): ?BusMaintenance
    {
        return $this->em->createQueryBuilder()
            ->select('m', 'b')
            ->from(BusMaintenance::class, 'm')
            ->join('m.bus', 'b')
            ->andWhere('b.agency = :agency')
            ->andWhere('m.id = :id')
            ->setParameter('agency', $agency)
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Déclare une panne depuis l'atelier central à partir d'un DTO validé et immobilise le bus.
     *
     * @throws \DomainException
     */
    public function declareFault(User $actor, BusMaintenanceDeclareData $data): BusMaintenance
    {
        $agency = $actor->getAgency() ?? throw new \LogicException('Agence introuvable.');

        $bus = $this->em->getRepository(Bus::class)->findOneBy([
            'agency' => $agency,
            'code'   => (string) $data->busCode,
        ]);

        if (!$bus instanceof Bus) {
            throw new \DomainException('Véhicule introuvable dans votre agence.');
        }

        if (BusStatus::Inactive === $bus->getStatus()) {
            throw new \DomainException('Impossible de déclarer une panne sur un véhicule déclassé.');
        }

        $now = new \DateTimeImmutable();
        $recordedMileage = max($bus->getMileage(), (int) ($data->mileage ?? 0));

        $maintenance = new BusMaintenance();
        $maintenance->setBus($bus)
            ->setIssuedAt($data->issuedAt ?? $now)
            ->setReportedAt($now)
            ->setReportedBy($actor)
            ->setSeverity($data->severity)
            ->setMileage($recordedMileage)
            ->setSolution(trim((string) $data->issueDescription))
            ->setStatus(MaintenanceStatus::Broken);

        $bus->setMileage($recordedMileage)
            ->setStatus(BusStatus::Maintenance);

        $this->em->persist($maintenance);
        $this->em->flush();

        return $maintenance;
    }

    /**
     * Clôture une panne en cours à partir d'un DTO validé et remet le bus en service.
     *
     * @throws \DomainException
     */
    public function resolveFault(BusMaintenance $maintenance, User $actor, BusMaintenanceResolveData $data): void
    {
        if (MaintenanceStatus::Broken !== $maintenance->getStatus()) {
            throw new \DomainException('Ce ticket de maintenance n\'est plus ouvert.');
        }

        $resolution = trim((string) $data->solutionDescription);
        $previousNote = $maintenance->getSolution();
        $fullReport = $previousNote ? $previousNote . "\n--- Réparation ---\n" . $resolution : $resolution;

        $maintenance->setSolution($fullReport)
            ->setStatus(MaintenanceStatus::Resolved)
            ->setResolvedAt($data->resolvedAt ?? new \DateTimeImmutable())
            ->setUpdatedBy($actor);

        $bus = $maintenance->getBus();
        if ($bus instanceof Bus && BusStatus::Maintenance === $bus->getStatus()) {
            $bus->setStatus(BusStatus::Active);
        }

        $this->em->flush();
    }

    /**
     * Annule un ticket de panne ouvert par erreur et remet le véhicule en service.
     *
     * @throws \DomainException
     */
    public function cancelFault(BusMaintenance $maintenance, User $actor): void
    {
        if (MaintenanceStatus::Broken !== $maintenance->getStatus()) {
            throw new \DomainException('Seul un ticket ouvert peut être annulé.');
        }

        $maintenance->setStatus(MaintenanceStatus::Cancelled)
            ->setResolvedAt(new \DateTimeImmutable())
            ->setUpdatedBy($actor);

        $bus = $maintenance->getBus();
        if ($bus instanceof Bus && BusStatus::Maintenance === $bus->getStatus()) {
            $bus->setStatus(BusStatus::Active);
        }

        $this->em->flush();
    }
}
