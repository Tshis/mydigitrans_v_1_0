<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\admin\agency\ShipmentCreateData;
use App\Dto\admin\agency\ShipmentPickupData;
use App\Entity\Agency;
use App\Entity\Currency;
use App\Entity\CurrencyAgency;
use App\Entity\Fare;
use App\Entity\Shipment;
use App\Entity\Trip;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\BillingType;
use App\Enum\FareTargetType;
use App\Enum\ShipmentStatus;
use App\Enum\TripStatus;
use App\Enum\UserType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Gestion complète du fret et des colis côté backoffice d'agence.
 */
final class AgencyShipmentService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuditLogger $auditLogger,
        private readonly \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface $passwordHasher,
    ) {}


    public function findAgencyShipmentByReference(Agency $agency, string $reference): ?Shipment
    {
        return $this->em->createQueryBuilder()
            ->select('s', 't', 'r', 'b', 'snd', 'rcv', 'depBr', 'arrBr', 'depSt', 'arrSt', 'cur', 'cb')
            ->from(Shipment::class, 's')
            ->join('s.trip', 't')
            ->join('t.route', 'r')
            ->join('t.bus', 'b')
            ->join('s.sender', 'snd')
            ->join('s.receiver', 'rcv')
            ->join('s.departureAgencyBranch', 'depBr')
            ->join('s.arrivalAgencyBranch', 'arrBr')
            ->join('s.departureStop', 'depSt')
            ->join('s.arrivalStop', 'arrSt')
            ->join('s.currency', 'cur')
            ->leftJoin('s.createdBy', 'cb')
            ->andWhere('s.agency = :agency')
            ->andWhere('s.reference = :ref')
            ->setParameter('agency', $agency)
            ->setParameter('ref', $reference)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Liste les colis de l'agence avec statistiques globales et filtres.
     *
     * @param array{query: ?string, status: ?string} $filters
     * @return array<string, mixed>
     */
    public function listAgencyShipments(Agency $agency, array $filters): array
    {
        $statsRows = $this->em->createQueryBuilder()
            ->select('s.status AS st', 'COUNT(s.id) AS cnt')
            ->from(Shipment::class, 's')
            ->andWhere('s.agency = :agency')
            ->setParameter('agency', $agency)
            ->groupBy('s.status')
            ->getQuery()
            ->getArrayResult();

        $stats = ['warehouse' => 0, 'in_transit' => 0, 'delivered' => 0];
        foreach ($statsRows as $row) {
            $val = $row['st'] instanceof \BackedEnum ? $row['st']->value : (string) $row['st'];
            if (\in_array($val, [ShipmentStatus::Pending->value, ShipmentStatus::ReadyForPickup->value], true)) {
                $stats['warehouse'] += (int) $row['cnt'];
            } elseif (ShipmentStatus::InTransit->value === $val) {
                $stats['in_transit'] += (int) $row['cnt'];
            } elseif (ShipmentStatus::Delivered->value === $val) {
                $stats['delivered'] += (int) $row['cnt'];
            }
        }

        $qb = $this->em->createQueryBuilder()
            ->select('s', 't', 'r', 'snd', 'rcv', 'depBr', 'arrBr', 'cur')
            ->from(Shipment::class, 's')
            ->join('s.trip', 't')
            ->join('t.route', 'r')
            ->join('s.sender', 'snd')
            ->join('s.receiver', 'rcv')
            ->join('s.departureAgencyBranch', 'depBr')
            ->join('s.arrivalAgencyBranch', 'arrBr')
            ->join('s.currency', 'cur')
            ->andWhere('s.agency = :agency')
            ->setParameter('agency', $agency)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults(300);

        if (null !== $filters['query'] && '' !== $filters['query']) {
            $qb->andWhere('(s.reference LIKE :q OR rcv.phone LIKE :q OR snd.phone LIKE :q OR rcv.firstname LIKE :q OR rcv.lastname LIKE :q)')
                ->setParameter('q', '%' . $filters['query'] . '%');
        }

        if (null !== $filters['status'] && '' !== $filters['status']) {
            $qb->andWhere('s.status = :status')
                ->setParameter('status', $filters['status']);
        } elseif (null === $filters['query'] || '' === $filters['query']) {
            // Par défaut à l'accueil : uniquement les colis actifs à encaisser, en transit ou à livrer
            $qb->andWhere('s.status NOT IN (:archivedStatuses)')
                ->setParameter('archivedStatuses', [
                    ShipmentStatus::Delivered->value,
                    ShipmentStatus::Cancelled->value,
                ]);
        }


        /** @var list<Shipment> $shipments */
        $shipments = $qb->getQuery()->getResult();

        $rows = [];
        $paidRefs = $this->findPaidShipmentReferences($shipments);
        foreach ($shipments as $shp) {
            $st = $shp->getStatus()->value;
            $rows[] = [
                'reference'       => (string) $shp->getReference(),
                'departureBranch' => (string) $shp->getDepartureAgencyBranch()?->getName(),
                'arrivalBranch'   => (string) $shp->getArrivalAgencyBranch()?->getName(),
                'senderName'      => (string) $shp->getSender()?->getFullName(),
                'senderPhone'     => (string) $shp->getSender()?->getPhone(),
                'receiverName'    => (string) $shp->getReceiver()?->getFullName(),
                'receiverPhone'   => (string) $shp->getReceiver()?->getPhone(),
                'description'     => (string) $shp->getDescription(),
                'weight'          => (float) $shp->getWeight(),
                'nature'          => (string) $shp->getNature(),
                'amountFormatted' => sprintf('%s %s', number_format((float) $shp->getPrice(), 2, ',', ' '), $shp->getCurrency()?->getCode()),
                'isPaid' => isset($paidRefs[(string) $shp->getReference()]),
                'status'          => $st,
                'statusLabel'     => match ($st) {
                    'pending'          => 'En attente (Non payé)',
                    'in_transit'       => 'En Transit',
                    'ready_for_pickup' => 'Disponible Terminus',
                    'delivered'        => 'Livré / Retiré',
                    'cancelled'        => 'Annulé',
                    default            => $st,
                },
            ];
        }

        return [
            'stats'     => $stats,
            'shipments' => $rows,
        ];
    }


    /**
     * Réceptionne un colis en transit au magasin de destination après vérification du mot de passe de l'agent responsable.
     *
     * @throws \DomainException
     */
    public function markReadyForPickup(Shipment $shipment, string $plainPassword, ?string $observation, User $actor): void
    {
        if (!\in_array($shipment->getStatus(), [ShipmentStatus::Pending, ShipmentStatus::InTransit], true)) {
            throw new \DomainException('Seul un colis en attente ou en transit peut être réceptionné en magasin d\'arrivée.');
        }

        if ('' === trim($plainPassword) || !$this->passwordHasher->isPasswordValid($actor, $plainPassword)) {
            throw new \DomainException('Mot de passe incorrect : votre signature numérique est requise pour réceptionner ce colis en magasin.');
        }

        $oldStatus = $shipment->getStatus()->value;
        $shipment->setStatus(ShipmentStatus::ReadyForPickup);

        $newValues = [
            'reference'     => $shipment->getReference(),
            'status'        => ShipmentStatus::ReadyForPickup->value,
            'receivedBy'    => sprintf('%s (%s)', $actor->getFullName(), $actor->getEmail()),
            'arrivalBranch' => $shipment->getArrivalAgencyBranch()?->getName(),
        ];

        if (null !== $observation && '' !== trim($observation)) {
            $newValues['observation'] = trim($observation);
        }

        $this->auditLogger->log(
            action: AuditAction::Update,
            entityName: 'Shipment',
            entityId: $shipment->getId(),
            oldValues: [
                'reference' => $shipment->getReference(),
                'status'    => $oldStatus,
            ],
            newValues: $newValues,
            actor: $actor,
            flush: false
        );

        $this->em->flush();
    }




    /**
     * Fournit la matrice JSON des arrêts et tarifs segmentaires par voyage pour le calcul temps réel en JS.
     *
     * @return array{trips_meta: array<int, mixed>, base_currency: string}
     */
    public function buildTripsPricingMetadata(Agency $agency): array
    {
        $today = (new \DateTimeImmutable())->setTime(0, 0);

        /** @var list<Trip> $trips */
        $trips = $this->em->createQueryBuilder()
            ->select('t', 'r')
            ->from(Trip::class, 't')
            ->join('t.route', 'r')
            ->andWhere('r.agency = :agency')
            ->andWhere('t.departureDate >= :today')
            ->andWhere('t.status IN (:statuses)')
            ->setParameter('agency', $agency)
            ->setParameter('today', $today, Types::DATE_IMMUTABLE)
            ->setParameter('statuses', [TripStatus::Scheduled->value, TripStatus::Boarding->value])
            ->getQuery()
            ->getResult();

        $ca = $this->em->getRepository(CurrencyAgency::class)->findOneBy([
            'agency'   => $agency,
            'isBase'   => true,
            'isActive' => true,
        ]);
        $baseCurrency = $ca?->getCurrency()?->getCode() ?? 'CDF';

        $meta = [];
        foreach ($trips as $trip) {
            $route = $trip->getRoute();
            $stopIds = [];
            foreach ($route->getRouteStops() as $st) {
                if ($st->isActive()) {
                    $stopIds[] = $st->getId();
                }
            }

            /** @var list<Fare> $fares */
            $fares = $this->em->createQueryBuilder()
                ->select('f', 'c')
                ->from(Fare::class, 'f')
                ->leftJoin('f.currency', 'c')
                ->andWhere('f.targetType = :type')
                ->andWhere('f.targetId = :routeId')
                ->andWhere('f.isActive = :yes')
                ->andWhere('f.pricePerKg IS NOT NULL')
                ->setParameter('type', FareTargetType::Route)
                ->setParameter('routeId', (int) $route->getId())
                ->setParameter('yes', true)
                ->getQuery()
                ->getResult();

            $segmentRates = [];
            foreach ($fares as $fare) {
                $depId = $fare->getDepartureStop()?->getId();
                $arrId = $fare->getArrivalStop()?->getId();
                if (null !== $depId && null !== $arrId && null !== $fare->getPricePerKg()) {
                    $segmentRates[$depId . '-' . $arrId] = [
                        'rate'     => (float) $fare->getPricePerKg(),
                        'currency' => $fare->getCurrency()?->getCode() ?? $baseCurrency,
                    ];
                }
            }

            $meta[(int) $trip->getId()] = [
                'stopIds'      => $stopIds,
                'pricePerKg'   => (float) ($route->getPricePerKg() ?? 0),
                'segmentRates' => $segmentRates,
            ];
        }

        return [
            'trips_meta'    => $meta,
            'base_currency' => $baseCurrency,
        ];
    }

    /**
     * Enregistre un nouveau colis au guichet depuis le DTO validé (statut Pending, tarif réel par segment, génération pickupCode).
     *
     * @throws \DomainException
     */
    public function createShipment(ShipmentCreateData $data, User $actor): Shipment
    {
        $agency = $actor->getAgency() ?? throw new \DomainException('Agence introuvable pour cet agent.');

        $trip = $data->trip;
        $depBranch = $data->departureAgencyBranch;
        $arrBranch = $data->arrivalAgencyBranch;
        $depStop = $data->departureStop;
        $arrStop = $data->arrivalStop;

        if (!$trip instanceof Trip || $trip->getRoute()?->getAgency()?->getId() !== $agency->getId()) {
            throw new \DomainException('Veuillez sélectionner un voyage valide de votre agence.');
        }

        if (
            null === $depBranch || null === $arrBranch
            || $depBranch->getAgency()?->getId() !== $agency->getId()
            || $arrBranch->getAgency()?->getId() !== $agency->getId()
        ) {
            throw new \DomainException('Les succursales de départ et d\'arrivée sont invalides.');
        }

        if (
            null === $depStop || null === $arrStop
            || $depStop->getRoute()?->getId() !== $trip->getRoute()?->getId()
            || $arrStop->getRoute()?->getId() !== $trip->getRoute()?->getId()
            || $depStop->getStopOrder() >= $arrStop->getStopOrder()
        ) {
            throw new \DomainException('Les arrêts sélectionnés ne correspondent pas à l\'itinéraire de ce voyage.');
        }

        $operatingBranch = $actor->getBranch() ?? $depBranch;
        $weight = max(0.1, (float) $data->weight);

        if (BillingType::Unit === $data->billingType) {
            $price = (float) $data->priceManual;
            $currency = $data->currencyManual;
        } else {
            $fare = $this->em->getRepository(Fare::class)->findOneBy([
                'targetType'    => FareTargetType::Route,
                'targetId'      => (int) $trip->getRoute()?->getId(),
                'departureStop' => $depStop,
                'arrivalStop'   => $arrStop,
                'isActive'      => true,
            ]);

            if ($fare instanceof Fare && null !== $fare->getPricePerKg()) {
                $ratePerKg = (float) $fare->getPricePerKg();
                $currency = $fare->getCurrency();
            } else {
                $ratePerKg = (float) ($trip->getRoute()?->getPricePerKg() ?? 0);
                $ca = $this->em->getRepository(CurrencyAgency::class)->findOneBy([
                    'agency'   => $agency,
                    'isBase'   => true,
                    'isActive' => true,
                ]);
                $currency = $ca?->getCurrency();
            }

            if ($ratePerKg <= 0) {
                throw new \DomainException('Aucun tarif au kilo n\'est configuré sur cette ligne ou ce segment.');
            }
            $price = round($weight * $ratePerKg, 2);
        }

        if (!$currency instanceof Currency) {
            throw new \DomainException('Devise introuvable ou non configurée pour cette agence.');
        }

        $sender = $this->findOrCreateClient((string) $data->senderName, (string) $data->senderPhone);
        $receiver = $this->findOrCreateClient((string) $data->receiverName, (string) $data->receiverPhone);

        $reference = $this->generateReference($agency);
        $pickupCode = $this->generatePickupCode();

        $shipment = (new Shipment())
            ->setReference($reference)
            ->setTrip($trip)
            ->setSender($sender)
            ->setReceiver($receiver)
            ->setPickupCode($pickupCode)
            ->setDescription(trim((string) $data->description))
            ->setNature($data->nature)
            ->setDepartureAgencyBranch($depBranch)
            ->setArrivalAgencyBranch($arrBranch)
            ->setWeight(number_format($weight, 2, '.', ''))
            ->setBillingType($data->billingType)
            ->setPrice(number_format($price, 2, '.', ''))
            ->setCurrency($currency)
            ->setAgency($agency)
            ->setBranch($operatingBranch)
            ->setStatus(ShipmentStatus::Pending)
            ->setDepartureStop($depStop)
            ->setArrivalStop($arrStop)
            ->setCreatedBy($actor);

        $this->em->persist($shipment);

        $this->auditLogger->log(
            action: AuditAction::Create,
            entityName: 'Shipment',
            entityId: $shipment->getId(),
            oldValues: null,
            newValues: [
                'reference' => $reference,
                'trip'      => $trip->getCode(),
                'price'     => $shipment->getPrice(),
                'currency'  => $currency->getCode(),
                'status'    => ShipmentStatus::Pending->value,
            ],
            actor: $actor,
            flush: false
        );

        $this->em->flush();

        return $shipment;
    }


    /**
     * Compile les données d'affichage de la fiche détaillée, du bordereau PDF et de l'écran de retrait.
     *
     * @return array<string, mixed>
     */
    public function getShipmentDetails(Shipment $shipment): array
    {
        $trip = $shipment->getTrip();
        $bus = $trip?->getBus();
        $st = $shipment->getStatus()->value;

        return [
            'shipment' => [
                'id'                   => $shipment->getId(),
                'reference'            => (string) $shipment->getReference(),
                'waybill'              => (string) $shipment->getReference(),
                'pickupCode'           => (string) $shipment->getPickupCode(),
                'receiverIdCardNumber' => $shipment->getReceiverIdCardNumber(),
                'status'               => $st,
                'statusLabel'          => match ($st) {
                    'pending'          => 'En attente (Non payé)',
                    'in_transit'       => 'En Transit',
                    'ready_for_pickup' => 'Disponible Terminus',
                    'delivered'        => 'Livré / Retiré',
                    'cancelled'        => 'Annulé',
                    default            => $st,
                },
                'isPaid' => $this->isShipmentPaid($shipment),
                'createdAt'            => $shipment->getCreatedAt(),
                'issuerLabel'          => sprintf('Guichet %s (%s)', $shipment->getBranch()?->getName(), $shipment->getCreatedBy()?->getFullName()),
                'senderName'           => (string) $shipment->getSender()?->getFullName(),
                'senderPhone'          => (string) $shipment->getSender()?->getPhone(),
                'receiverName'         => (string) $shipment->getReceiver()?->getFullName(),
                'receiverPhone'        => (string) $shipment->getReceiver()?->getPhone(),
                'route'                => sprintf('%s → %s', $shipment->getDepartureStop()?->getCity(), $shipment->getArrivalStop()?->getCity()),
                'departureBranch'      => (string) $shipment->getDepartureAgencyBranch()?->getName(),
                'arrivalBranch'        => (string) $shipment->getArrivalAgencyBranch()?->getName(),
                'description'          => (string) $shipment->getDescription(),
                'nature'               => (string) $shipment->getNature(),
                'weight'               => (float) $shipment->getWeight(),
                'billingType'          => $shipment->getBillingType()->value,
                'price'                => (float) $shipment->getPrice(),
                'currency'             => (string) $shipment->getCurrency()?->getCode(),
                'tripLabel'            => sprintf('%s (%s)', trim(($bus?->getBrand() ?? '') . ' ' . ($bus?->getModel() ?? '')), strtoupper((string) $trip?->getCode())),
            ],
        ];
    }

    /**
     * Valide la livraison physique du colis après vérification du code secret et de la pièce d'identité (via DTO).
     *
     * @throws \DomainException
     */
    public function processPickup(Shipment $shipment, ShipmentPickupData $pickupData, User $actor): void
    {
        if (!$this->isShipmentPaid($shipment)) {
            throw new \DomainException('Ce colis n\'est pas encore acquitté en caisse : encaissez le fret avant de le livrer.');
        }

        if (\in_array($shipment->getStatus(), [ShipmentStatus::Delivered, ShipmentStatus::Cancelled], true)) {
            throw new \DomainException('Ce colis a déjà été retiré ou annulé.');
        }

        if (strtoupper(trim((string) $pickupData->pickupCode)) !== strtoupper((string) $shipment->getPickupCode())) {
            throw new \DomainException('Code secret incorrect : le code dicté par le destinataire ne correspond pas.');
        }

        $oldStatus = $shipment->getStatus()->value;
        $idRecord = sprintf('%s : %s', strtoupper(trim($pickupData->receiverIdType)), trim((string) $pickupData->receiverIdNumber));

        $shipment->setStatus(ShipmentStatus::Delivered)
            ->setReceiverIdCardNumber($idRecord);

        $this->auditLogger->log(
            action: AuditAction::Update,
            entityName: 'Shipment',
            entityId: $shipment->getId(),
            oldValues: ['reference' => $shipment->getReference(), 'status' => $oldStatus],
            newValues: ['reference' => $shipment->getReference(), 'status' => ShipmentStatus::Delivered->value, 'receiverId' => $idRecord],
            actor: $actor,
            flush: false
        );

        $this->em->flush();
    }

    /**
     * Annule un colis non encore payé et trace le motif dans audit_log.
     *
     * @throws \DomainException
     */
    public function cancelShipment(Shipment $shipment, ?string $reason, User $actor): void
    {
        if (ShipmentStatus::Pending !== $shipment->getStatus()) {
            throw new \DomainException('Seul un colis en attente de paiement peut être annulé directement.');
        }

        $cleanReason = null !== $reason && '' !== trim($reason) ? trim($reason) : null;
        if (null === $cleanReason) {
            throw new \DomainException('Veuillez indiquer le motif d\'annulation de l\'expédition.');
        }

        $shipment->setStatus(ShipmentStatus::Cancelled);

        $this->auditLogger->log(
            action: AuditAction::Cancel,
            entityName: 'Shipment',
            entityId: $shipment->getId(),
            oldValues: [
                'reference' => $shipment->getReference(),
                'status'    => ShipmentStatus::Pending->value,
            ],
            newValues: [
                'reference' => $shipment->getReference(),
                'status'    => ShipmentStatus::Cancelled->value,
                'reason'    => $cleanReason,
            ],
            actor: $actor,
            flush: false
        );

        $this->em->flush();
    }


    private function findOrCreateClient(string $fullName, string $phone): User
    {
        $parts = array_filter(explode(' ', trim($fullName)));
        $firstname = array_shift($parts) ?? 'Client';
        $lastname = [] !== $parts ? implode(' ', $parts) : 'Fret';
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        $email = sprintf('clt.%s@mydigitrans.local', $cleanPhone ?: bin2hex(random_bytes(4)));
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            $user = (new User())
                ->setEmail($email)
                ->setFirstname($firstname)
                ->setLastname($lastname)
                ->setPhone($phone)
                ->setUserType(UserType::Client)
                ->setIsActive(true)
                ->setPassword(bin2hex(random_bytes(16)))
                ->setCode(sprintf('CLT-%s', $cleanPhone ?: strtoupper(bin2hex(random_bytes(4)))));
            $this->em->persist($user);
        } else {
            $user->setFirstname($firstname)
                ->setLastname($lastname)
                ->setPhone($phone);
        }
        return $user;
    }
    private function generateReference(Agency $agency): string
    {
        $repo = $this->em->getRepository(Shipment::class);
        $prefix = sprintf('SHP-%s-%02d', (new \DateTimeImmutable())->format('ym'), (int) $agency->getId());
        do {
            $ref = sprintf('%s-%04d', $prefix, random_int(1000, 9999));
        } while (null !== $repo->findOneBy(['reference' => $ref]));
        return $ref;
    }
    private function generatePickupCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $repo = $this->em->getRepository(Shipment::class);
        do {
            $code = '';
            for ($i = 0; $i < 4; ++$i) {
                $code .= $alphabet[random_int(0, \strlen($alphabet) - 1)];
            }
        } while (null !== $repo->findOneBy(['pickupCode' => $code]));
        return $code;
    }

    /**
     * Renvoi un bool si shipment est payé
     */
    private function isShipmentPaid(Shipment $shipment): bool
    {
        $ref = (string) $shipment->getReference();
        if ('' === $ref) {
            return false;
        }

        $count = (int) $this->em->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(\App\Entity\AgencyPayment::class, 'p')
            ->andWhere('p.type = :type')
            ->andWhere('p.reference = :ref')
            ->andWhere('p.status = :confirmed')
            ->setParameter('type', \App\Enum\PaymentType::Shipment)
            ->setParameter('ref', $ref)
            ->setParameter('confirmed', \App\Enum\PaymentStatus::Confirmed)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * @param list<Shipment> $shipments
     * @return array<string, true>
     */
    private function findPaidShipmentReferences(array $shipments): array
    {
        $refs = array_values(array_filter(array_map(static fn(Shipment $s): ?string => $s->getReference(), $shipments)));
        if ([] === $refs) {
            return [];
        }

        $rows = $this->em->createQueryBuilder()
            ->select('DISTINCT p.reference AS ref')
            ->from(\App\Entity\AgencyPayment::class, 'p')
            ->andWhere('p.type = :type')
            ->andWhere('p.reference IN (:refs)')
            ->andWhere('p.status = :confirmed')
            ->setParameter('type', \App\Enum\PaymentType::Shipment)
            ->setParameter('refs', $refs)
            ->setParameter('confirmed', \App\Enum\PaymentStatus::Confirmed)
            ->getQuery()
            ->getArrayResult();

        $paidMap = [];
        foreach ($rows as $row) {
            $paidMap[(string) $row['ref']] = true;
        }

        return $paidMap;
    }
}
