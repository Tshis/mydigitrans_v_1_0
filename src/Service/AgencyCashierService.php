<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\admin\agency\CashierPaymentData;
use App\Dto\admin\agency\CashRegisterCreateData;
use App\Dto\admin\agency\CashSessionCloseData;
use App\Dto\admin\agency\CashSessionInitData;
use App\Dto\admin\agency\CashSessionOpenData;
use App\Dto\admin\agency\CashSessionValidateData;
use App\Entity\Agency;
use App\Entity\AgencyPayment;
use App\Entity\CashRegister;
use App\Entity\CashSession;
use App\Entity\CashSessionBalance;
use App\Entity\Currency;
use App\Entity\CurrencyAgency;
use App\Entity\Expense;
use App\Entity\FinancialOperation;
use App\Entity\Reservation;
use App\Entity\Shipment;
use App\Entity\User;
use App\Enum\ActiveStatus;
use App\Enum\AuditAction;
use App\Enum\CashSessionStatus;
use App\Enum\ExpenseStatus;
use App\Enum\FinancialReferenceType;
use App\Enum\FlowType;
use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Enum\ReservationStatus;
use App\Enum\ShipmentStatus;
use App\Enum\TicketStatus;
use Doctrine\ORM\EntityManagerInterface;

final class AgencyCashierService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Immatricule un nouveau guichet de caisse (CashRegister).
     *
     * @throws \DomainException
     */
    public function createRegister(CashRegisterCreateData $data, User $actor): CashRegister
    {
        $agency = $actor->getAgency() ?? throw new \DomainException('Agence introuvable.');
        $branch = $data->branch;
        if (null === $branch || $branch->getAgency()?->getId() !== $agency->getId()) {
            throw new \DomainException('Succursale invalide.');
        }

        $code = strtoupper(trim((string) $data->code));
        $existing = $this->em->getRepository(CashRegister::class)->findOneBy([
            'agency' => $agency,
            'code'   => $code,
        ]);
        if (null !== $existing) {
            throw new \DomainException(sprintf('Le code guichet [%s] existe déjà dans votre agence.', $code));
        }

        $register = (new CashRegister())
            ->setAgency($agency)
            ->setBranch($branch)
            ->setName(trim((string) $data->name))
            ->setCode($code)
            ->setStatus(ActiveStatus::Active)
            ->setHardware(null !== $data->hardwareNote && '' !== trim($data->hardwareNote) ? ['note' => trim($data->hardwareNote)] : null)
            ->setCreatedBy($actor);

        $this->em->persist($register);

        $this->auditLogger->log(
            action: AuditAction::Create,
            entityName: 'CashRegister',
            entityId: $register->getId(),
            oldValues: null,
            newValues: ['name' => $register->getName(), 'code' => $code, 'branch' => $branch->getName()],
            actor: $actor,
            flush: false
        );

        $this->em->flush();

        return $register;
    }

    /**
     * Trouve la session active (Pending ou Opened) d'un caissier.
     */
    public function findCurrentSessionForCashier(User $cashier): ?CashSession
    {
        return $this->em->createQueryBuilder()
            ->select('cs', 'cr', 'br', 'bal', 'cur')
            ->from(CashSession::class, 'cs')
            ->join('cs.cashRegister', 'cr')
            ->join('cr.branch', 'br')
            ->leftJoin('cs.balances', 'bal')
            ->leftJoin('bal.currency', 'cur')
            ->andWhere('cs.openedBy = :cashier')
            ->andWhere('cs.status IN (:statuses)')
            ->setParameter('cashier', $cashier)
            ->setParameter('statuses', [CashSessionStatus::Pending->value, CashSessionStatus::Opened->value])
            ->orderBy('cs.openedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Prépare une session de caisse Top-Down (statut Pending) avec les dotations par devise.
     *
     * @throws \DomainException
     */
    public function initSession(CashSessionInitData $data, User $manager): CashSession
    {
        $agency = $manager->getAgency() ?? throw new \DomainException('Agence introuvable.');
        $register = $data->cashRegister;
        $cashier = $data->cashier;

        if (null === $register || $register->getAgency()?->getId() !== $agency->getId()) {
            throw new \DomainException('Guichet de caisse invalide.');
        }
        if (null === $cashier || $cashier->getAgency()?->getId() !== $agency->getId()) {
            throw new \DomainException('Caissier assigné invalide.');
        }

        // Vérifier qu'aucune session n'est déjà ouverte/en attente sur ce guichet ou pour ce caissier
        $busyRegister = $this->em->createQueryBuilder()
            ->select('COUNT(cs.id)')
            ->from(CashSession::class, 'cs')
            ->andWhere('cs.cashRegister = :reg')
            ->andWhere('cs.status IN (:st)')
            ->setParameter('reg', $register)
            ->setParameter('st', [CashSessionStatus::Pending->value, CashSessionStatus::Opened->value])
            ->getQuery()
            ->getSingleScalarResult();

        if ((int) $busyRegister > 0) {
            throw new \DomainException(sprintf('Le guichet [%s] possède déjà une session ouverte ou en attente.', $register->getCode()));
        }

        $existingCashierSession = $this->findCurrentSessionForCashier($cashier);
        if (null !== $existingCashierSession) {
            throw new \DomainException(sprintf('%s possède déjà une session active (%s).', $cashier->getFullName(), $existingCashierSession->getCashRegister()?->getName()));
        }

        $session = (new CashSession())
            ->setCashRegister($register)
            ->setOpenedBy($cashier)
            ->setStatus(CashSessionStatus::Pending);

        $this->em->persist($session);

        $activeCurrencies = $this->getAgencyActiveCurrencies($agency);
        if ([] === $activeCurrencies) {
            throw new \DomainException('Aucune devise active configurée pour cette compagnie.');
        }

        foreach ($activeCurrencies as $currency) {
            $code = (string) $currency->getCode();
            $expected = max(0.0, (float) ($data->amounts[$code] ?? 0));

            $balance = (new CashSessionBalance())
                ->setCashSession($session)
                ->setCurrency($currency)
                ->setOpeningBalanceExpected(number_format($expected, 2, '.', ''))
                ->setOpeningBalanceDeclared('0.00')
                ->setOpenBalanceDifference(number_format(-$expected, 2, '.', ''))
                ->setOpeningNote($data->openingNote);

            $session->addBalance($balance);
            $this->em->persist($balance);
        }

        $this->auditLogger->log(
            action: AuditAction::Create,
            entityName: 'CashSession',
            entityId: $session->getId(),
            oldValues: null,
            newValues: [
                'register' => $register->getCode(),
                'cashier'  => $cashier->getFullName(),
                'status'   => CashSessionStatus::Pending->value,
            ],
            actor: $manager,
            flush: false
        );

        $this->em->flush();

        return $session;
    }

    /**
     * Ouverture physique de la session par le caissier après comptage des espèces.
     *
     * @throws \DomainException
     */
    public function openSession(CashSession $session, CashSessionOpenData $data, User $cashier): void
    {
        if (CashSessionStatus::Pending !== $session->getStatus()) {
            throw new \DomainException('Cette session n\'est pas en attente d\'ouverture.');
        }

        foreach ($session->getBalances() as $balance) {
            $code = (string) $balance->getCurrency()?->getCode();
            $expected = (float) $balance->getOpeningBalanceExpected();
            $declared = max(0.0, (float) ($data->openingBalances[$code] ?? 0));
            $diff = round($declared - $expected, 2);

            if (abs($diff) > 0.009) {
                throw new \DomainException(sprintf(
                    'Litige de dotation initiale en %s : attendu %s %s, compté %s %s. Contactez le gérant.',
                    $code,
                    number_format($expected, 2, ',', ' '),
                    $code,
                    number_format($declared, 2, ',', ' '),
                    $code
                ));
            }

            $balance->setOpeningBalanceDeclared(number_format($declared, 2, '.', ''))
                ->setOpenBalanceDifference('0.00')
                ->setOpeningObservation($data->openingObservation);
        }

        $session->setStatus(CashSessionStatus::Opened);

        $this->auditLogger->log(
            action: AuditAction::Update,
            entityName: 'CashSession',
            entityId: $session->getId(),
            oldValues: ['status' => CashSessionStatus::Pending->value],
            newValues: ['status' => CashSessionStatus::Opened->value],
            actor: $cashier,
            flush: false
        );

        $this->em->flush();
    }

    /**
     * Calcule les soldes théoriques actuels en tiroir pour une session (dotation + IN - OUT).
     *
     * @return array<string, array{code: string, name: string, expected: float, formatted: string, opening: float}>
     */
    public function computeSessionTheoreticalBalances(CashSession $session): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('IDENTITY(fo.currency) AS curId', 'fo.type AS flowType', 'COALESCE(SUM(fo.amount), 0) AS total')
            ->from(FinancialOperation::class, 'fo')
            ->andWhere('fo.cashSession = :session')
            ->setParameter('session', $session)
            ->groupBy('curId', 'flowType')
            ->getQuery()
            ->getArrayResult();

        $netMovements = [];
        foreach ($rows as $row) {
            $cid = (int) $row['curId'];
            $ft = $row['flowType'] instanceof \BackedEnum ? $row['flowType']->value : (string) $row['flowType'];
            $netMovements[$cid] ??= 0.0;
            if (FlowType::In->value === $ft) {
                $netMovements[$cid] += (float) $row['total'];
            } else {
                $netMovements[$cid] -= (float) $row['total'];
            }
        }
        $result = [];
        foreach ($session->getBalances() as $bal) {
            $cur = $bal->getCurrency();
            if (null === $cur) {
                continue;
            }
            $code = (string) $cur->getCode();
            $opening = (float) $bal->getOpeningBalanceDeclared();
            if ($opening <= 0 && CashSessionStatus::Pending === $session->getStatus()) {
                $opening = (float) $bal->getOpeningBalanceExpected();
            }
            $expected = round($opening + ($netMovements[(int) $cur->getId()] ?? 0.0), 2);
            $result[$code] = [
                'code'      => $code,
                'name'      => (string) $cur->getName(),
                'opening'   => $opening,
                'expected'  => $expected,
                'formatted' => number_format($expected, 2, ',', ' '),
            ];
        }
        return $result;
    }
    /**
     * Clôture la session par le caissier et enregistre le comptage physique de fin de journée.
     *
     * @throws \DomainException
     */
    public function closeSession(CashSession $session, CashSessionCloseData $data, User $cashier): void
    {
        if (CashSessionStatus::Opened !== $session->getStatus()) {
            throw new \DomainException('Seule une session ouverte peut être clôturée.');
        }
        $theoretical = $this->computeSessionTheoreticalBalances($session);
        foreach ($session->getBalances() as $bal) {
            $code = (string) $bal->getCurrency()?->getCode();
            $expected = $theoretical[$code]['expected'] ?? 0.0;
            $declared = max(0.0, (float) ($data->physicalBalances[$code] ?? 0));
            $diff = round($declared - $expected, 2);
            $bal->setClosingBalanceExpected(number_format($expected, 2, '.', ''))
                ->setClosingBalanceDeclared(number_format($declared, 2, '.', ''))
                ->setClosingBalanceDifference(number_format($diff, 2, '.', ''))
                ->setClosingObservation($data->closingObservation);
        }
        $session->setStatus(CashSessionStatus::Closed)
            ->setClosedAt(new \DateTimeImmutable());
        $this->auditLogger->log(
            action: AuditAction::Update,
            entityName: 'CashSession',
            entityId: $session->getId(),
            oldValues: ['status' => CashSessionStatus::Opened->value],
            newValues: ['status' => CashSessionStatus::Closed->value],
            actor: $cashier,
            flush: false
        );
        $this->em->flush();
    }
    /**
     * Arbitrage final du gérant sur une session clôturée (enregistre closedBy et managerConclusion).
     *
     * @throws \DomainException
     */
    public function validateClosedSession(CashSession $session, CashSessionValidateData $data, User $manager): void
    {
        if (CashSessionStatus::Closed !== $session->getStatus()) {
            throw new \DomainException('Cette session n\'est pas en attente d\'arbitrage.');
        }
        foreach ($session->getBalances() as $bal) {
            $bal->setManagerConclusion(trim((string) $data->managerConclusion));
        }
        $session->setClosedBy($manager);
        $this->auditLogger->log(
            action: AuditAction::Update,
            entityName: 'CashSession',
            entityId: $session->getId(),
            oldValues: ['sessionId' => $session->getId()],
            newValues: ['arbitratedBy' => $manager->getFullName(), 'conclusion' => $data->managerConclusion],
            actor: $manager,
            flush: false
        );
        $this->em->flush();
    }
    /**
     * @return list
     */
    public function getAgencyActiveCurrencies(Agency $agency): array
    {
        /* @var list $rows */
        $rows = $this->em->createQueryBuilder()
            ->select('ca', 'c')
            ->from(CurrencyAgency::class, 'ca')
            ->join('ca.currency', 'c')
            ->andWhere('ca.agency = :agency')
            ->andWhere('ca.isActive = :yes')
            ->setParameter('agency', $agency)
            ->setParameter('yes', true)
            ->orderBy('ca.isBase', 'DESC')
            ->addOrderBy('c.code', 'ASC')
            ->getQuery()
            ->getResult();
        return array_values(array_filter(array_map(static fn(CurrencyAgency $ca): ?Currency => $ca->getCurrency(), $rows)));
    }


    /**
     * Recherche un bon client (Réservation billet ou Colis fret) par sa référence pour le guichet d'encaissement.
     *
     * @return array<string, mixed>|null
     */
    public function lookupVoucher(Agency $agency, string $rawReference): ?array
    {
        $ref = strtoupper(trim($rawReference));
        if ('' === $ref) {
            return null;
        }

        // 1. Recherche dans Reservation
        $reservation = $this->em->createQueryBuilder()
            ->select('r', 't', 'rt', 'b', 'u', 'depSt', 'arrSt', 'cur', 'tk', 'ts', 'st')
            ->from(Reservation::class, 'r')
            ->join('r.trip', 't')
            ->join('t.route', 'rt')
            ->join('t.bus', 'b')
            ->join('r.user', 'u')
            ->join('r.departureStop', 'depSt')
            ->join('r.arrivalStop', 'arrSt')
            ->join('r.currency', 'cur')
            ->leftJoin('r.tickets', 'tk')
            ->leftJoin('tk.tripSeat', 'ts')
            ->leftJoin('ts.seat', 'st')
            ->andWhere('r.agency = :agency')
            ->andWhere('UPPER(r.reference) = :ref')
            ->setParameter('agency', $agency)
            ->setParameter('ref', $ref)
            ->getQuery()
            ->getOneOrNullResult();


        if ($reservation instanceof Reservation) {
            if (ReservationStatus::Cancelled === $reservation->getStatus()) {
                return ['error' => 'Cette réservation a été annulée et ne peut plus être encaissée.'];
            }

            $alreadyPaid = $this->getConfirmedPaidTotal($agency, PaymentType::Reservation, (string) $reservation->getReference());
            $totalAmount = (float) $reservation->getTotalAmount();
            $remaining = max(0.0, round($totalAmount - $alreadyPaid, 2));

            if ($remaining <= 0.0) {
                return ['error' => sprintf('La réservation [%s] est déjà intégralement acquittée.', $reservation->getReference())];
            }

            // 1. Dans lookupVoucher() :
            $seats = [];
            foreach ($reservation->getTickets() as $ticket) {
                $seats[] = $ticket->getTripSeat()?->getSeat()?->getSeatLabel() ?? '-';
            }


            return [
                'docType'       => 'reservation',
                'natureLabel'   => 'BON DE RÉSERVATION PASSAGER',
                'reference'     => (string) $reservation->getReference(),
                'amountRaw'     => $remaining,
                'currencyId'    => $reservation->getCurrency()?->getId(),
                'currencyCode'  => (string) $reservation->getCurrency()?->getCode(),
                'actor1Label'   => 'Bénéficiaire / Voyageur',
                'actor1Name'    => (string) $reservation->getUser()?->getFullName(),
                'actor1Phone'   => (string) $reservation->getUser()?->getPhone(),
                'actor2Label'   => 'Logistique Associée',
                'actor2Name'    => sprintf('%s → %s', $reservation->getDepartureStop()?->getCity(), $reservation->getArrivalStop()?->getCity()),
                'actor2Phone'   => sprintf('%s %s | Siège(s) : %s', $reservation->getTrip()?->getBus()?->getBrand(), $reservation->getTrip()?->getBus()?->getModel(), implode(', ', $seats)),
            ];
        }

        // 2. Recherche dans Shipment (Colis / Fret)
        $shipment = $this->em->createQueryBuilder()
            ->select('s', 't', 'rt', 'snd', 'rcv', 'depSt', 'arrSt', 'cur')
            ->from(Shipment::class, 's')
            ->join('s.trip', 't')
            ->join('t.route', 'rt')
            ->join('s.sender', 'snd')
            ->join('s.receiver', 'rcv')
            ->join('s.departureStop', 'depSt')
            ->join('s.arrivalStop', 'arrSt')
            ->join('s.currency', 'cur')
            ->andWhere('s.agency = :agency')
            ->andWhere('UPPER(s.reference) = :ref')
            ->setParameter('agency', $agency)
            ->setParameter('ref', $ref)
            ->getQuery()
            ->getOneOrNullResult();

        if ($shipment instanceof Shipment) {
            if (ShipmentStatus::Cancelled === $shipment->getStatus()) {
                return ['error' => 'Ce bordereau de fret a été annulé.'];
            }

            $alreadyPaid = $this->getConfirmedPaidTotal($agency, PaymentType::Shipment, (string) $shipment->getReference());
            $totalAmount = (float) $shipment->getPrice();
            $remaining = max(0.0, round($totalAmount - $alreadyPaid, 2));

            if ($remaining <= 0.0) {
                return ['error' => sprintf('Le colis [%s] est déjà intégralement acquitté.', $shipment->getReference())];
            }

            return [
                'docType'       => 'shipment',
                'natureLabel'   => 'BORDEREAU DE FRET & COLIS',
                'reference'     => (string) $shipment->getReference(),
                'amountRaw'     => $remaining,
                'currencyId'    => $shipment->getCurrency()?->getId(),
                'currencyCode'  => (string) $shipment->getCurrency()?->getCode(),
                'actor1Label'   => 'Expéditeur & Destinataire',
                'actor1Name'    => sprintf('%s → %s', $shipment->getSender()?->getFullName(), $shipment->getReceiver()?->getFullName()),
                'actor1Phone'   => sprintf('Tél Dest: %s', $shipment->getReceiver()?->getPhone()),
                'actor2Label'   => 'Marchandise & Axe',
                'actor2Name'    => sprintf('%s (%s Kg)', $shipment->getDescription(), $shipment->getWeight()),
                'actor2Phone'   => sprintf('%s → %s', $shipment->getDepartureStop()?->getCity(), $shipment->getArrivalStop()?->getCity()),
            ];
        }

        return null;
    }

    /**
     * Encaisse un bon (Réservation ou Colis) dans la session de caisse active.
     *
     * @throws \DomainException
     */
    public function processPayment(CashierPaymentData $data, CashSession $session, User $cashier): AgencyPayment
    {
        if (CashSessionStatus::Opened !== $session->getStatus()) {
            throw new \DomainException('Votre session de caisse n\'est pas ouverte.');
        }

        $agency = $cashier->getAgency() ?? throw new \DomainException('Agence introuvable.');
        $voucher = $this->lookupVoucher($agency, (string) $data->voucherReference);

        if (null === $voucher) {
            throw new \DomainException('Aucun bon client trouvé avec cette référence.');
        }
        if (isset($voucher['error'])) {
            throw new \DomainException((string) $voucher['error']);
        }

        $capturedCurrency = $data->capturedCurrency ?? throw new \DomainException('Devise d\'encaissement requise.');
        $capturedAmount = (float) $data->capturedAmount;
        if ($capturedAmount <= 0) {
            throw new \DomainException('Le montant encaissé doit être supérieur à 0.');
        }

        $discount = max(0.0, (float) ($data->discountAmount ?? 0.0));
        if ($discount > 0 && (null === $data->discountObservation || '' === trim($data->discountObservation))) {
            throw new \DomainException('Une observation justificative est obligatoire lorsqu\'une remise est appliquée.');
        }

        $isReservation = 'reservation' === $voucher['docType'];
        $paymentType = $isReservation ? PaymentType::Reservation : PaymentType::Shipment;
        $ref = (string) $voucher['reference'];

        $payment = (new AgencyPayment())
            ->setType($paymentType)
            ->setReference($ref)
            ->setAmount(number_format($capturedAmount, 2, '.', ''))
            ->setCurrency($capturedCurrency)
            ->setPaymentMethod($data->paymentMethod)
            ->setStatus(PaymentStatus::Confirmed)
            ->setCashSession($session)
            ->setCreatedBy($cashier)
            ->setConfirmedBy($cashier)
            ->setConfirmedAt(new \DateTimeImmutable())
            ->setAgency($agency)
            ->setBranch($session->getCashRegister()?->getBranch());

        $this->em->persist($payment);
        $this->em->flush();

        // Écriture comptable entrante dans le journal de la session (FinancialOperation)
        $desc = sprintf(
            'Encaissement %s [%s] (%s)',
            $isReservation ? 'Réservation' : 'Colis Fret',
            $ref,
            $voucher['actor1Name']
        );
        if ($discount > 0) {
            $desc .= sprintf(' | Remise: %s (%s) — Motif: %s', $discount, $data->discountType, trim((string) $data->discountObservation));
        }

        $operation = (new FinancialOperation())
            ->setCashSession($session)
            ->setType(FlowType::In)
            ->setReferenceType(FinancialReferenceType::AgencyPayment)
            ->setReferenceId((int) $payment->getId())
            ->setAmount(number_format($capturedAmount, 2, '.', ''))
            ->setCurrency($capturedCurrency)
            ->setCreatedBy($cashier)
            ->setDescription($desc);

        $this->em->persist($operation);

        if ($isReservation) {
            $reservation = $this->em->getRepository(Reservation::class)->findOneBy(['agency' => $agency, 'reference' => $ref]);
            if ($reservation instanceof Reservation) {
                $reservation->setStatus(ReservationStatus::Paid);
                foreach ($reservation->getTickets() as $ticket) {
                    $ticket->setStatus(TicketStatus::Paid);
                }
            }
        } else {
            $shipment = $this->em->getRepository(Shipment::class)->findOneBy(['agency' => $agency, 'reference' => $ref]);
            if ($shipment instanceof Shipment && ShipmentStatus::Pending === $shipment->getStatus()) {
                $shipment->setStatus(ShipmentStatus::InTransit);
            }
        }

        $this->auditLogger->log(
            action: AuditAction::Create,
            entityName: 'AgencyPayment',
            entityId: $payment->getId(),
            oldValues: null,
            newValues: [
                'type'     => $paymentType->value,

                'ref'      => $ref,
                'amount'   => $payment->getAmount(),
                'currency' => $capturedCurrency->getCode(),
                'session'  => $session->getId(),
            ],
            actor: $cashier,
            flush: false
        );
        $this->em->flush();
        return $payment;
    }
    /**
     * Exécute physiquement un bon de décaissement approuvé depuis le tiroir de la session active.
     *
     * @throws \DomainException
     */
    public function executeExpense(Expense $expense, CashSession $session, User $cashier): void
    {
        if (CashSessionStatus::Opened !== $session->getStatus()) {
            throw new \DomainException('Votre session de caisse n\'est pas ouverte.');
        }
        if (ExpenseStatus::Pending !== $expense->getStatus()) {
            throw new \DomainException('Ce bon de décaissement a déjà été traité.');
        }
        $currency = $expense->getCurrency() ?? throw new \DomainException('Devise de la dépense invalide.');
        $amount = (float) $expense->getAmount();
        $balances = $this->computeSessionTheoreticalBalances($session);
        $available = $balances[(string) $currency->getCode()]['expected'] ?? 0.0;
        if ($available < $amount) {
            throw new \DomainException(sprintf(
                'Solde insuffisant en %s dans votre tiroir (%s %s disponibles pour %s %s demandés).',
                $currency->getCode(),
                number_format($available, 2, ',', ' '),
                $currency->getCode(),
                number_format($amount, 2, ',', ' '),
                $currency->getCode()
            ));
        }
        $expense->setStatus(ExpenseStatus::Paid)
            ->setCashRegister($session->getCashRegister())
            ->setConfirmedBy($cashier);
        $operation = (new FinancialOperation())
            ->setCashSession($session)
            ->setType(FlowType::Out)
            ->setReferenceType(FinancialReferenceType::Expense)
            ->setReferenceId((int) $expense->getId())
            ->setAmount(number_format($amount, 2, '.', ''))
            ->setCurrency($currency)
            ->setCreatedBy($cashier)
            ->setDescription(sprintf('Décaissement : %s (%s)', $expense->getName(), $expense->getDescription() ?? 'Bon validé'));
        $this->em->persist($operation);
        $this->auditLogger->log(
            action: AuditAction::Update,
            entityName: 'Expense',
            entityId: $expense->getId(),
            oldValues: ['status' => ExpenseStatus::Pending->value],
            newValues: ['status' => ExpenseStatus::Paid->value, 'amount' => $expense->getAmount(), 'currency' => $currency->getCode()],
            actor: $cashier,
            flush: false
        );
        $this->em->flush();
    }
    private function getConfirmedPaidTotal(Agency $agency, PaymentType $type, string $reference): float
    {
        $total = $this->em->createQueryBuilder()
            ->select('COALESCE(SUM(p.amount), 0)')
            ->from(AgencyPayment::class, 'p')
            ->andWhere('p.agency = :agency')
            ->andWhere('p.type = :type')
            ->andWhere('p.reference = :ref')
            ->andWhere('p.status = :confirmed')
            ->setParameter('agency', $agency)
            ->setParameter('type', $type)
            ->setParameter('ref', $reference)
            ->setParameter('confirmed', PaymentStatus::Confirmed)
            ->getQuery()
            ->getSingleScalarResult();
        return (float) $total;
    }

    /**
     * Liste les bons de décaissement en attente (Pending) pour la succursale du caissier.
     *
     * @return list<Expense>
     */
    public function getPendingExpensesForCashier(User $cashier): array
    {
        $agency = $cashier->getAgency();
        $branch = $cashier->getBranch();

        $qb = $this->em->createQueryBuilder()
            ->select('e', 'cur', 'cb', 'br')
            ->from(Expense::class, 'e')
            ->join('e.currency', 'cur')
            ->join('e.createdBy', 'cb')
            ->join('e.branch', 'br')
            ->andWhere('e.agency = :agency')
            ->andWhere('e.status = :pending')
            ->setParameter('agency', $agency)
            ->setParameter('pending', ExpenseStatus::Pending)
            ->orderBy('e.createdAt', 'DESC');

        if (null !== $branch) {
            $qb->andWhere('e.branch = :branch')->setParameter('branch', $branch);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Liste les mouvements financiers (FinancialOperation) d'une session donnée.
     *
     * @return list<FinancialOperation>
     */
    public function getSessionOperations(CashSession $session): array
    {
        return $this->em->createQueryBuilder()
            ->select('fo', 'cur', 'cb')
            ->from(FinancialOperation::class, 'fo')
            ->join('fo.currency', 'cur')
            ->join('fo.createdBy', 'cb')
            ->andWhere('fo.cashSession = :session')
            ->setParameter('session', $session)
            ->orderBy('fo.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
