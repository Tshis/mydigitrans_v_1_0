<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Agency;
use App\Entity\Ticket;
use App\Entity\TicketPrintLog;
use App\Entity\TicketScanLog;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\PrintReason;
use App\Enum\ScanResult;
use App\Enum\SeatType;
use App\Enum\TicketStatus;
use App\Enum\TripSeatStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

final class AgencyTicketService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Liste les billets de l'agence (par défaut : départs du jour et à venir ; historique complet via filtres).
     *
     * @param array{status: ?string, date: ?string, query: ?string} $filters
     * @return list<array<string, mixed>>
     */
    public function listAgencyTickets(User $actor, array $filters): array
    {
        $agency = $actor->getAgency();
        $branch = $actor->getBranch();
        $now = new \DateTimeImmutable();

        $qb = $this->em->createQueryBuilder()
            ->select('tk', 'res', 't', 'r', 'dep', 'arr', 'ts', 's', 'cur', 'cb')
            ->from(Ticket::class, 'tk')
            ->join('tk.reservation', 'res')
            ->join('res.trip', 't')
            ->join('t.route', 'r')
            ->join('res.departureStop', 'dep')
            ->join('res.arrivalStop', 'arr')
            ->join('tk.tripSeat', 'ts')
            ->join('ts.seat', 's')
            ->join('tk.currency', 'cur')
            ->leftJoin('res.createdBy', 'cb')
            ->andWhere('tk.agency = :agency')
            ->setParameter('agency', $agency)
            ->orderBy('tk.createdAt', 'DESC')
            ->setMaxResults(300);

        if (null !== $branch && 'SIEGE' !== $branch->getCode()) {
            $qb->andWhere('(dep.city = :branchCity OR arr.city = :branchCity OR cb.branch = :branch)')
                ->setParameter('branchCity', $branch->getCity())
                ->setParameter('branch', $branch);
        }

        $hasActiveFilter = !empty($filters['status']) || !empty($filters['date']) || !empty($filters['query']);

        if (!$hasActiveFilter) {
            $qb->andWhere('t.departureDate >= :today')
                ->setParameter('today', $now->setTime(0, 0), Types::DATE_IMMUTABLE);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('tk.status = :status')->setParameter('status', $filters['status']);
        }

        if (!empty($filters['date'])) {
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $filters['date']);
            if (false !== $parsed) {
                $qb->andWhere('t.departureDate = :depDate')
                    ->setParameter('depDate', $parsed->setTime(0, 0), Types::DATE_IMMUTABLE);
            }
        }

        if (!empty($filters['query'])) {
            $q = '%' . trim((string) $filters['query']) . '%';
            $qb->andWhere('(tk.ticketNumber LIKE :q OR res.reference LIKE :q OR tk.travelerName LIKE :q OR tk.travelerPhone LIKE :q)')
                ->setParameter('q', $q);
        }

        /** @var list<Ticket> $tickets */
        $tickets = $qb->getQuery()->getResult();

        // Récupération groupée des logs de scan valides pour les billets affichés (évite le N+1)
        $ticketNumbers = array_values(array_unique(array_filter(array_map(
            static fn(Ticket $tk): ?string => $tk->getTicketNumber(),
            $tickets
        ))));

        $scansByNumber = [];
        if ([] !== $ticketNumbers) {
            /** @var list<TicketScanLog> $scanLogs */
            $scanLogs = $this->em->createQueryBuilder()
                ->select('sl', 'u')
                ->from(TicketScanLog::class, 'sl')
                ->join('sl.scannedBy', 'u')
                ->andWhere('sl.ticketNumber IN (:nums)')
                ->andWhere('sl.result = :valid')
                ->setParameter('nums', $ticketNumbers)
                ->setParameter('valid', ScanResult::Valid)
                ->orderBy('sl.scannedAt', 'DESC')
                ->getQuery()
                ->getResult();

            foreach ($scanLogs as $sl) {
                $num = (string) $sl->getTicketNumber();
                if (!isset($scansByNumber[$num])) {
                    $scansByNumber[$num] = [
                        'scannedBy' => (string) ($sl->getScannedBy()?->getFullName() ?? 'Contrôleur'),
                        'scannedAt' => $sl->getScannedAt(),
                    ];
                }
            }
        }

        $rows = [];
        foreach ($tickets as $tk) {
            $res = $tk->getReservation();
            $trip = $res?->getTrip();
            $num = (string) $tk->getTicketNumber();
            $scanInfo = $scansByNumber[$num] ?? null;

            $rows[] = [
                'id'             => $tk->getId(),
                'reference'      => $num,
                'reservationRef' => (string) $res?->getReference(),
                'passengerName'  => (string) $tk->getTravelerName(),
                'passengerPhone' => (string) $tk->getTravelerPhone(),
                'tripCode'       => (string) $trip?->getCode(),
                'segment'        => sprintf('%s → %s', $res?->getDepartureStop()?->getCity(), $res?->getArrivalStop()?->getCity()),
                'seatNumber'     => (string) ($tk->getTripSeat()?->getSeat()?->getSeatLabel() ?? '-'),
                'status'         => $tk->getStatus()->value,
                'createdAt'      => $tk->getCreatedAt(),
                'scannedBy'      => $scanInfo['scannedBy'] ?? null,
                'scannedAt'      => $scanInfo['scannedAt'] ?? null,
            ];
        }

        return $rows;
    }


    public function findAgencyTicketByNumber(Agency $agency, string $ticketNumber): ?Ticket
    {
        return $this->em->createQueryBuilder()
            ->select('tk', 'res', 't', 'r', 'b', 'dep', 'arr', 'ts', 's', 'cur')
            ->from(Ticket::class, 'tk')
            ->join('tk.reservation', 'res')
            ->join('res.trip', 't')
            ->join('t.route', 'r')
            ->join('t.bus', 'b')
            ->join('res.departureStop', 'dep')
            ->join('res.arrivalStop', 'arr')
            ->join('tk.tripSeat', 'ts')
            ->join('ts.seat', 's')
            ->join('tk.currency', 'cur')
            ->andWhere('tk.agency = :agency')
            ->andWhere('UPPER(tk.ticketNumber) = :num')
            ->setParameter('agency', $agency)
            ->setParameter('num', strtoupper(trim($ticketNumber)))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Prépare les données d'impression PDF thermique et trace l'impression dans TicketPrintLog.
     *
     * @return array<string, mixed>
     */
    public function prepareTicketPdfPayload(Ticket $ticket, User $actor): array
    {
        $previousPrints = (int) $this->em->createQueryBuilder()
            ->select('COUNT(pl.id)')
            ->from(TicketPrintLog::class, 'pl')
            ->andWhere('pl.ticket = :ticket')
            ->setParameter('ticket', $ticket)
            ->getQuery()
            ->getSingleScalarResult();

        $printCount = $previousPrints + 1;
        $reason = 1 === $printCount ? PrintReason::Original : PrintReason::Duplicate;

        $log = (new TicketPrintLog())
            ->setTicket($ticket)
            ->setPrintedBy($actor)
            ->setReason($reason)
            ->setPrintCount($printCount);

        $this->em->persist($log);
        $this->em->flush();

        $res = $ticket->getReservation();
        $trip = $res?->getTrip();
        $bus = $trip?->getBus();
        $seat = $ticket->getTripSeat()?->getSeat();
        $num = (string) $ticket->getTicketNumber();

        return [
            'number'          => $num,
            'passenger_name'  => (string) $ticket->getTravelerName(),
            'passenger_phone' => (string) $ticket->getTravelerPhone(),
            'route'           => (string) ($trip?->getRoute()?->getName() ?? '-'),
            'departure_stop'  => (string) ($res?->getDepartureStop()?->getCity() ?? '-'),
            'arrival_stop'    => (string) ($res?->getArrivalStop()?->getCity() ?? '-'),
            'departure_date'  => $trip?->getDepartureDate(),
            'departure_time'  => $trip?->getDepartureTime()?->format('H\hi') ?? '-',
            'bus_plate'       => trim(sprintf('%s %s', $bus?->getCode() ?? '', $bus?->getPlateNumber() ?? '')),
            'seat_number'     => (string) ($seat?->getSeatLabel() ?? '-'),
            'fare_category'   => SeatType::Vip === $seat?->getType() ? 'Classe VIP' : 'Standard',
            'amount'          => (float) $ticket->getPrice(),
            'currency_code'   => (string) ($ticket->getCurrency()?->getCode() ?? 'CDF'),
            'security_hash'   => strtoupper(substr(hash('sha256', $num . '|' . $ticket->getId()), 0, 12)) . ($printCount > 1 ? ' (DUPLICATA #' . $printCount . ')' : ''),
        ];
    }

    /**
     * Annule un billet payé et libère le siège.
     *
     * @throws \DomainException
     */
    public function cancelTicket(Ticket $ticket, User $actor): void
    {
        if (TicketStatus::Paid !== $ticket->getStatus()) {
            throw new \DomainException('Seul un billet valide (payé) peut être annulé.');
        }

        $ticket->setStatus(TicketStatus::Cancelled);
        $ts = $ticket->getTripSeat();
        if (null !== $ts) {
            $ts->setSeatStatus(TripSeatStatus::Available)
                ->setReservation(null)
                ->setHeldUntil(null);
        }

        $this->auditLogger->log(
            action: AuditAction::Update,
            entityName: 'Ticket',
            entityId: $ticket->getId(),
            oldValues: ['status' => TicketStatus::Paid->value],
            newValues: ['status' => TicketStatus::Cancelled->value, 'ticket' => $ticket->getTicketNumber()],
            actor: $actor,
            flush: false
        );

        $this->em->flush();
    }

    /**
     * Contrôle et valide un billet lors de l'embarquement (QR Code ou saisie manuelle).
     * Vérifie que la date/heure du voyage correspond à la fenêtre d'embarquement avant de passer le billet à Used.
     *
     * @return array<string, mixed>
     */
    public function verifyAndBoardTicket(Agency $agency, string $rawCode, User $scanner): array
    {
        $code = strtoupper(trim($rawCode));
        $ticket = $this->findAgencyTicketByNumber($agency, $code);
        $now = new \DateTimeImmutable();

        if (!$ticket instanceof Ticket) {
            $scanLog = (new TicketScanLog())
                ->setTicketNumber('' !== $code ? $code : 'INCONNU')
                ->setScannedBy($scanner)
                ->setResult(ScanResult::Invalid);
            $this->em->persist($scanLog);
            $this->em->flush();

            return [
                'valid'     => false,
                'status'    => ScanResult::Invalid->value,
                'scannedBy' => $scanner->getFullName(),
                'scannedAt' => $now->format('H:i:s'),
                'message'   => 'Billet introuvable ou invalide dans cette agence.',
            ];
        }

        $res = $ticket->getReservation();
        $trip = $res?->getTrip();
        $depStop = $res?->getDepartureStop();

        $departureAt = null;
        if (null !== $trip?->getDepartureDate() && null !== $trip?->getDepartureTime()) {
            $base = \DateTimeImmutable::createFromInterface($trip->getDepartureDate())->setTime(
                (int) $trip->getDepartureTime()->format('H'),
                (int) $trip->getDepartureTime()->format('i'),
                0
            );
            $offsetMinutes = (int) ($depStop?->getDurationFromDeparture() ?? 0);
            $departureAt = $base->add(new \DateInterval(sprintf('PT%dM', max(0, $offsetMinutes))));
        }

        $payload = [
            'ticketNumber'  => (string) $ticket->getTicketNumber(),
            'passengerName' => (string) $ticket->getTravelerName(),
            'seatNumber'    => (string) ($ticket->getTripSeat()?->getSeat()?->getSeatLabel() ?? '-'),
            'destination'   => sprintf('%s → %s', $depStop?->getCity(), $res?->getArrivalStop()?->getCity()),
            'tripCode'      => (string) $trip?->getCode(),
            'departureAt'   => $departureAt?->format('d/m/Y à H\hi') ?? '-',
            'scannedBy'     => $scanner->getFullName(),
            'scannedAt'     => $now->format('H:i:s'),
        ];

        // 1. Billet déjà utilisé : retrouver qui l'a scanné précédemment
        if (TicketStatus::Used === $ticket->getStatus()) {
            $previousScan = $this->em->createQueryBuilder()
                ->select('sl', 'u')
                ->from(TicketScanLog::class, 'sl')
                ->join('sl.scannedBy', 'u')
                ->andWhere('sl.ticketNumber = :num')
                ->andWhere('sl.result = :valid')
                ->setParameter('num', (string) $ticket->getTicketNumber())
                ->setParameter('valid', ScanResult::Valid)
                ->orderBy('sl.scannedAt', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            $prevInfo = $previousScan instanceof TicketScanLog
                ? sprintf(' par %s le %s', $previousScan->getScannedBy()?->getFullName(), $previousScan->getScannedAt()?->format('d/m à H:i'))
                : '';

            $scanLog = (new TicketScanLog())
                ->setTicketNumber((string) $ticket->getTicketNumber())
                ->setScannedBy($scanner)
                ->setResult(ScanResult::AlreadyUsed);
            $this->em->persist($scanLog);
            $this->em->flush();

            return $payload + [
                'valid'   => false,
                'status'  => ScanResult::AlreadyUsed->value,
                'message' => sprintf('ALERTE : Billet déjà validé%s !', $prevInfo),
            ];
        }

        // 2. Statut non valide (annulé / remboursé)
        if (TicketStatus::Paid !== $ticket->getStatus()) {
            $scanLog = (new TicketScanLog())
                ->setTicketNumber((string) $ticket->getTicketNumber())
                ->setScannedBy($scanner)
                ->setResult(ScanResult::Invalid);
            $this->em->persist($scanLog);
            $this->em->flush();

            return $payload + [
                'valid'   => false,
                'status'  => ScanResult::Invalid->value,
                'message' => sprintf('Billet refusé (statut : %s).', $ticket->getStatus()->value),
            ];
        }

        // 3. Vérification de la fenêtre horaire d'embarquement (sans brûler le billet si hors créneau !)
        $tripStatus = $trip?->getStatus();
        if (\App\Enum\TripStatus::Cancelled === $tripStatus) {
            return $payload + [
                'valid'   => false,
                'status'  => ScanResult::Invalid->value,
                'message' => 'Ce voyage a été annulé.',
            ];
        }

        if (null !== $departureAt && \App\Enum\TripStatus::Boarding !== $tripStatus) {
            $windowOpen = $departureAt->modify('-3 hours');
            $windowClose = $departureAt->modify('+4 hours');

            if ($now < $windowOpen) {
                return $payload + [
                    'valid'   => false,
                    'status'  => 'too_early',
                    'message' => sprintf(
                        'EMBARQUEMENT NON OUVERT : Ce billet est prévu pour le %s (ouverture 3h avant le départ). Le billet reste valide.',
                        $departureAt->format('d/m/Y à H\hi')
                    ),
                ];
            }

            if ($now > $windowClose || \App\Enum\TripStatus::Arrived === $tripStatus) {
                return $payload + [
                    'valid'   => false,
                    'status'  => 'expired_trip',
                    'message' => sprintf(
                        'VOYAGE DÉJÀ PARTI / CLÔTURÉ : Départ prévu le %s.',
                        $departureAt->format('d/m/Y à H\hi')
                    ),
                ];
            }
        }

        // 4. Fenêtre OK -> Passage du billet à Used et journalisation avec l'agent contrôleur
        $ticket->setStatus(TicketStatus::Used);
        $scanLog = (new TicketScanLog())
            ->setTicketNumber((string) $ticket->getTicketNumber())
            ->setScannedBy($scanner)
            ->setResult(ScanResult::Valid);
        $this->em->persist($scanLog);
        $this->em->flush();

        return $payload + [
            'valid'   => true,
            'status'  => ScanResult::Valid->value,
            'message' => sprintf('EMBARQUEMENT AUTORISÉ — Validé par %s.', $scanner->getFullName()),
        ];
    }

    /**
     * Retourne les 15 derniers scans enregistrés aujourd'hui dans l'agence (avec le nom de l'agent contrôleur).
     *
     * @return list<array<string, mixed>>
     */
    public function getRecentScanLogs(Agency $agency): array
    {
        $today = (new \DateTimeImmutable())->setTime(0, 0);

        $rows = $this->em->createQueryBuilder()
            ->select('sl', 'u', 'tk')
            ->from(TicketScanLog::class, 'sl')
            ->join('sl.scannedBy', 'u')
            ->leftJoin(Ticket::class, 'tk', 'WITH', 'tk.ticketNumber = sl.ticketNumber AND tk.agency = :agency')
            ->andWhere('u.agency = :agency')
            ->andWhere('sl.scannedAt >= :today')
            ->setParameter('agency', $agency)
            ->setParameter('today', $today)
            ->orderBy('sl.scannedAt', 'DESC')
            ->setMaxResults(15)
            ->getQuery()
            ->getResult();

        $logs = [];
        foreach ($rows as $row) {
            if (!$row instanceof TicketScanLog) {
                continue;
            }
            $logs[] = [
                'ticketNumber' => (string) $row->getTicketNumber(),
                'scannedBy'    => (string) ($row->getScannedBy()?->getFullName() ?? 'Agent'),
                'scannedAt'    => $row->getScannedAt()?->format('H:i:s') ?? '-',
                'isValid'      => ScanResult::Valid === $row->getResult(),
                'resultLabel'  => match ($row->getResult()) {
                    ScanResult::Valid       => 'VALIDÉ',
                    ScanResult::AlreadyUsed => 'DÉJÀ SCANNÉ',
                    ScanResult::Invalid     => 'REFUSÉ',
                },
            ];
        }

        return $logs;
    }
}
