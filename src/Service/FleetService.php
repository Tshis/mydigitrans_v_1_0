<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\admin\agency\BusData;
use App\Entity\Agency;
use App\Entity\Bus;
use App\Entity\BusDocument;
use App\Entity\BusLayout;
use App\Entity\BusMaintenance;
use App\Entity\Seat;
use App\Entity\User;
use App\Enum\BusStatus;
use App\Enum\MaintenanceSeverity;
use App\Enum\MaintenanceStatus;
use App\Enum\SeatType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service central de gestion de la flotte de véhicules d'une agence (Bus, Sièges, Maintenance).
 */
final class FleetService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BusLayoutGridBuilder $gridBuilder,
    ) {}

    /**
     * Hydrate un DTO BusData à partir d'une entité Bus existante pour l'édition.
     */
    public function createDtoFromBus(Bus $bus): BusData
    {
        $dto = new BusData();
        $dto->code = $bus->getCode();
        $dto->name = $bus->getName();
        $dto->plateNumber = $bus->getPlateNumber();
        $dto->brand = $bus->getBrand();
        $dto->model = $bus->getModel();
        $dto->year = $bus->getYear();
        $dto->mileage = $bus->getMileage();
        $dto->busLayoutId = $bus->getBusLayout()?->getId();

        return $dto;
    }

    /**
     * Retourne la liste formatée des bus de l'agence ainsi que les statistiques globales.
     *
     * @return array{fleet: list<array<string, mixed>>, stats: array{total: int, active: int, maintenance: int, expiredDocs: int}}
     */
    public function listAgencyFleet(Agency $agency): array
    {
        $buses = $this->em->getRepository(Bus::class)->findBy(
            ['agency' => $agency],
            ['createdAt' => 'DESC']
        );

        $today = new \DateTimeImmutable('today');

        $expiredBusRows = $this->em->createQueryBuilder()
            ->select('DISTINCT IDENTITY(d.bus) AS busId')
            ->from(BusDocument::class, 'd')
            ->join('d.bus', 'b')
            ->andWhere('b.agency = :agency')
            ->andWhere('d.expiredAt < :today')
            ->setParameter('agency', $agency)
            ->setParameter('today', $today)
            ->getQuery()
            ->getArrayResult();

        $expiredBusIds = [];
        foreach ($expiredBusRows as $row) {
            $expiredBusIds[(int) $row['busId']] = true;
        }

        $stats = [
            'total'       => \count($buses),
            'active'      => 0,
            'maintenance' => 0,
            'expiredDocs' => 0,
        ];

        $fleet = [];
        foreach ($buses as $bus) {
            $status = $bus->getStatus();
            if (BusStatus::Active === $status) {
                ++$stats['active'];
            } elseif (BusStatus::Maintenance === $status) {
                ++$stats['maintenance'];
            }

            $hasExpiredDoc = isset($expiredBusIds[(int) $bus->getId()]);
            if ($hasExpiredDoc) {
                ++$stats['expiredDocs'];
            }

            $statusLabel = match ($status) {
                BusStatus::Active      => 'En Service',
                BusStatus::Maintenance => 'Au Garage',
                BusStatus::Inactive    => 'Déclassé',
            };

            $fleet[] = [
                'code'        => (string) $bus->getCode(),
                'name'        => $bus->getName(),
                'brand'       => (string) $bus->getBrand(),
                'model'       => (string) $bus->getModel(),
                'layoutName'  => (string) $bus->getBusLayout()?->getName(),
                'plateNumber' => (string) $bus->getPlateNumber(),
                'capacity'    => $bus->getSeats()->count(),
                'mileage'     => $bus->getMileage(),
                'docStatus'   => $hasExpiredDoc ? 'expired' : 'up-to-date',
                'status'      => $status->value,
                'statusLabel' => $statusLabel,
            ];
        }

        return [
            'fleet' => $fleet,
            'stats' => $stats,
        ];
    }

    /**
     * Retourne les gabarits d'usine validés disponibles pour les agences.
     *
     * @return list<BusLayout>
     */
    public function availableFactoryLayouts(): array
    {
        return $this->em->getRepository(BusLayout::class)->findBy(
            ['agency' => null, 'isValidated' => true],
            ['name' => 'ASC']
        );
    }

    public function findValidatedFactoryLayout(int $id): ?BusLayout
    {
        return $this->em->getRepository(BusLayout::class)->findOneBy([
            'id'          => $id,
            'agency'      => null,
            'isValidated' => true,
        ]);
    }

    public function findBusByCode(Agency $agency, string $code): ?Bus
    {
        return $this->em->getRepository(Bus::class)->findOneBy([
            'agency' => $agency,
            'code'   => $code,
        ]);
    }

    /**
     * Crée un véhicule à partir d'un DTO BusData validé et génère ses entités Seat.
     *
     * @throws \DomainException
     */
    public function createBus(User $actor, BusData $data): Bus
    {
        $agency = $actor->getAgency() ?? throw new \LogicException('Agence introuvable.');

        $plateNumber = strtoupper(trim((string) $data->plateNumber));
        $brand       = trim((string) $data->brand);
        $model       = trim((string) $data->model);
        $year        = (int) $data->year;
        $mileage     = max(0, (int) $data->mileage);
        $layoutId    = (int) $data->busLayoutId;
        $rawCode     = strtoupper(trim((string) $data->code));
        $rawName     = trim((string) $data->name);

        if (null !== $this->em->getRepository(Bus::class)->findOneBy(['plateNumber' => $plateNumber])) {
            throw new \DomainException(sprintf('La plaque d\'immatriculation [%s] est déjà enregistrée.', $plateNumber));
        }

        $layout = $this->findValidatedFactoryLayout($layoutId);
        if (!$layout instanceof BusLayout) {
            throw new \DomainException('Le gabarit sélectionné est introuvable ou désactivé.');
        }

        $code = '' !== $rawCode ? $rawCode : $this->generateUniqueBusCode($agency);
        if (null !== $this->findBusByCode($agency, $code)) {
            throw new \DomainException(sprintf('Le code interne [%s] est déjà attribué dans votre agence.', $code));
        }

        $bus = new Bus();

        $this->em->wrapInTransaction(function () use ($bus, $agency, $actor, $layout, $code, $rawName, $plateNumber, $brand, $model, $year, $mileage): void {
            $bus->setAgency($agency)
                ->setBusLayout($layout)
                ->setCode($code)
                ->setName('' !== $rawName ? $rawName : null)
                ->setPlateNumber($plateNumber)
                ->setBrand($brand)
                ->setModel($model)
                ->setYear($year)
                ->setMileage($mileage)
                ->setStatus(BusStatus::Active)
                ->setCreatedBy($actor);

            $this->em->persist($bus);

            $builtGrid = $this->gridBuilder->build($layout->toArray());
            foreach ($builtGrid['cells'] as $cell) {
                if (!\in_array($cell['type'], ['seat', 'vip', 'normal'], true) || null === $cell['label']) {
                    continue;
                }

                $seat = new Seat();
                $seat->setBus($bus)
                    ->setSeatLabel((string) $cell['label'])
                    ->setType('vip' === $cell['type'] ? SeatType::Vip : SeatType::Normal)
                    ->setRow((int) $cell['row'])
                    ->setColumn((int) $cell['col']);

                $this->em->persist($seat);
                $bus->addSeat($seat);
            }
        });

        return $bus;
    }

    /**
     * Rassemble toutes les données nécessaires à la fiche technique d'un bus.
     *
     * @return array<string, mixed>
     */
    public function getBusProfile(Bus $bus): array
    {
        $today = new \DateTimeImmutable('today');

        $rawDocs = $this->em->getRepository(BusDocument::class)->findBy(
            ['bus' => $bus],
            ['expiredAt' => 'ASC']
        );

        $documents = [];
        foreach ($rawDocs as $doc) {
            $isExpired = null !== $doc->getExpiredAt() && $doc->getExpiredAt() < $today;
            $documents[] = [
                'code'            => (string) $doc->getCode(),
                'type'            => (string) $doc->getType(),
                'referenceNumber' => $doc->getReferenceNumber(),
                'issuedAt'        => $doc->getIssuedAt(),
                'expiredAt'       => $doc->getExpiredAt(),
                'status'          => $isExpired ? 'expired' : 'valid',
                'url'             => $doc->getUrl(),
            ];
        }

        $maintenanceHistory = $this->em->getRepository(BusMaintenance::class)->findBy(
            ['bus' => $bus],
            ['reportedAt' => 'DESC']
        );

        $activeMaintenance = $this->em->getRepository(BusMaintenance::class)->findOneBy(
            ['bus' => $bus, 'status' => MaintenanceStatus::Broken],
            ['reportedAt' => 'DESC']
        );

        return [
            'bus'                => $bus,
            'bus_code'           => $bus->getCode(),
            'seatmap'            => $this->gridBuilder->build($bus->getBusLayout()->toArray()),
            'documents'          => $documents,
            'maintenanceHistory' => $maintenanceHistory,
            'activeMaintenance'  => $activeMaintenance,
        ];
    }

    /**
     * Met à jour les informations administratives d'un bus à partir d'un DTO BusData validé.
     *
     * @throws \DomainException
     */
    public function updateBus(Bus $bus, BusData $data): void
    {
        if (BusStatus::Inactive === $bus->getStatus()) {
            throw new \DomainException('Un véhicule déclassé ne peut plus être modifié.');
        }

        $plateNumber = strtoupper(trim((string) $data->plateNumber));
        $brand       = trim((string) $data->brand);
        $model       = trim((string) $data->model);
        $name        = trim((string) $data->name);
        $year        = (int) $data->year;
        $mileage     = (int) $data->mileage;
        if ($mileage < $bus->getMileage()) {
            throw new \DomainException(sprintf('Le nouveau kilométrage ne peut pas être inférieur à l\'actuel (%d km).', $bus->getMileage()));
        }
        $existingPlate = $this->em->getRepository(Bus::class)->findOneBy(['plateNumber' => $plateNumber]);
        if ($existingPlate instanceof Bus && $existingPlate->getId() !== $bus->getId()) {
            throw new \DomainException(sprintf('La plaque [%s] est déjà attribuée à un autre véhicule.', $plateNumber));
        }
        $bus->setPlateNumber($plateNumber)
            ->setBrand($brand)
            ->setModel($model)
            ->setName('' !== $name ? $name : null)
            ->setYear($year)
            ->setMileage($mileage);
        $this->em->flush();
    }
    /**
     * Déclasse définitivement un véhicule.
     *
     * @throws \DomainException
     */
    public function decommissionBus(Bus $bus, string $reason): void
    {
        $cleanReason = trim($reason);
        if ('' === $cleanReason) {
            throw new \DomainException('Le motif de déclassement est obligatoire.');
        }
        $bus->setStatus(BusStatus::Inactive)
            ->setInactiveReason($cleanReason);
        $this->em->flush();
    }
    /**
     * Déclare une panne et bascule le bus au garage.
     *
     * @throws \DomainException
     */
    public function reportFault(Bus $bus, User $actor, string $issue, string $severityValue): void
    {
        $cleanIssue = trim($issue);
        if ('' === $cleanIssue) {
            throw new \DomainException('Veuillez décrire la nature de la panne.');
        }
        $severity = MaintenanceSeverity::tryFrom($severityValue) ?? MaintenanceSeverity::Medium;
        $now = new \DateTimeImmutable();
        $maintenance = new BusMaintenance();
        $maintenance->setBus($bus)
            ->setIssuedAt($now)
            ->setReportedAt($now)
            ->setReportedBy($actor)
            ->setSeverity($severity)
            ->setMileage($bus->getMileage())
            ->setSolution($cleanIssue)
            ->setStatus(MaintenanceStatus::Broken);
        $bus->setStatus(BusStatus::Maintenance);
        $this->em->persist($maintenance);
        $this->em->flush();
    }
    /**
     * Clôture la panne en cours et remet le bus en service.
     *
     * @throws \DomainException
     */
    public function resolveFault(Bus $bus, User $actor, string $resolution): void
    {
        $cleanResolution = trim($resolution);
        if ('' === $cleanResolution) {
            throw new \DomainException('Veuillez saisir le rapport de réparation.');
        }
        $maintenance = $this->em->getRepository(BusMaintenance::class)->findOneBy(
            ['bus' => $bus, 'status' => MaintenanceStatus::Broken],
            ['reportedAt' => 'DESC']
        );
        if ($maintenance instanceof BusMaintenance) {
            $previousNote = $maintenance->getSolution();
            $fullReport = $previousNote ? $previousNote . "\n--- Réparation ---\n" . $cleanResolution : $cleanResolution;
            $maintenance->setSolution($fullReport)
                ->setStatus(MaintenanceStatus::Resolved)
                ->setResolvedAt(new \DateTimeImmutable())
                ->setUpdatedBy($actor);
        }
        $bus->setStatus(BusStatus::Active);
        $this->em->flush();
    }
    public function buildSeatmap(BusLayout $layout): array
    {
        return $this->gridBuilder->build($layout->toArray());
    }
    private function generateUniqueBusCode(Agency $agency): string
    {
        $repo = $this->em->getRepository(Bus::class);
        $count = (int) $this->em->createQueryBuilder()
            ->select('COUNT(b.id)')
            ->from(Bus::class, 'b')
            ->andWhere('b.agency = :agency')
            ->setParameter('agency', $agency)
            ->getQuery()
            ->getSingleScalarResult();
        for ($i = $count + 1; $i < $count + 500; ++$i) {
            $candidate = sprintf('BUS-%03d', $i);
            if (null === $repo->findOneBy(['agency' => $agency, 'code' => $candidate])) {
                return $candidate;
            }
        }
        return 'BUS-' . strtoupper(bin2hex(random_bytes(3)));
    }
}
