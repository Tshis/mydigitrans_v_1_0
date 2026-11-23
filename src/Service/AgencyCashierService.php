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
use App\Entity\Branch;
use App\Entity\CashRegister;
use App\Entity\CashSession;
use App\Entity\CashSessionBalance;
use App\Entity\Currency;
use App\Entity\CurrencyAgency;
use App\Entity\ExchangeRate;
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

        $rawCode = strtoupper(trim((string) ($data->code ?? '')));
        $code = '' !== $rawCode ? $rawCode : $this->generateRegisterCode($agency, $branch);

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
     * Prépare une session de caisse Top-Down (statut Pending) ou ouvre directement la session si l'utilisateur connecté s'auto-attribue le guichet.
     *
     * @throws \DomainException
     */
    public function initSession(CashSessionInitData $data, User $manager): CashSession
    {
        $agency = $manager->getAgency() ?? throw new \DomainException('Agence introuvable.');
        $register = $data->cashRegister;
        $cashier = $data->cashier ?? $manager;

        if (null === $register || $register->getAgency()?->getId() !== $agency->getId()) {
            throw new \DomainException('Guichet de caisse invalide.');
        }
        if ($cashier->getAgency()?->getId() !== $agency->getId()) {
            throw new \DomainException('Caissier assigné invalide.');
        }

        // Vérifier qu'aucune session n'est déjà ouverte/en attente sur ce guichet
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

        // Vérifier que le caissier n'a pas déjà une session active ailleurs
        $existingCashierSession = $this->findCurrentSessionForCashier($cashier);
        if (null !== $existingCashierSession) {
            throw new \DomainException(sprintf('%s possède déjà une session active (%s).', $cashier->getFullName(), $existingCashierSession->getCashRegister()?->getName()));
        }

        $isSelfOpening = $cashier->getId() === $manager->getId();

        $session = (new CashSession())
            ->setCashRegister($register)
            ->setOpenedBy($cashier)
            ->setStatus($isSelfOpening ? CashSessionStatus::Opened : CashSessionStatus::Pending);

        $this->em->persist($session);

        $activeCurrencies = $this->getAgencyActiveCurrencies($agency);
        if ([] === $activeCurrencies) {
            throw new \DomainException('Aucune devise active configurée pour cette compagnie.');
        }

        foreach ($activeCurrencies as $currency) {
            $code = (string) $currency->getCode();
            $expected = max(0.0, (float) ($data->amounts[$code] ?? 0));
            $formatted = number_format($expected, 2, '.', '');

            $balance = (new CashSessionBalance())
                ->setCashSession($session)
                ->setCurrency($currency)
                ->setOpeningBalanceExpected($formatted)
                ->setOpeningBalanceDeclared($isSelfOpening ? $formatted : '0.00')
                ->setOpenBalanceDifference($isSelfOpening ? '0.00' : number_format(-$expected, 2, '.', ''))
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
                'register'    => $register->getCode(),
                'cashier'     => $cashier->getFullName(),
                'selfOpening' => $isSelfOpening,
                'status'      => $session->getStatus()->value,
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

        $balances = $this->em->getRepository(CashSessionBalance::class)->findBy([
            'cashSession' => $session,
        ]);

        if ([] === $balances) {
            throw new \DomainException('Aucune ligne de dotation trouvée pour cette session.');
        }

        foreach ($balances as $balance) {
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
     * Calcule le solde global théorique de la session consolidé en devise de base (dotation + IN - OUT),
     * ainsi que les soldes détaillés par devise pour la validation physique.
     *
     * @return array{
     *   baseCode: string,
     *   consolidatedExpected: float,
     *   consolidatedFormatted: string,
     *   consolidatedOut: float,
     *   consolidatedOutFormatted: string,
     *   byCurrency: array<string, array{code: string, name: string, expected: float, formatted: string, opening: float, rateToBase: float}>
     * }
     */
    public function computeSessionTheoreticalBalances(CashSession $session): array
    {
        $this->ensureSessionBalances($session);

        $agency = $session->getCashRegister()?->getAgency();
        $baseCurrency = null !== $agency ? $this->getBaseCurrency($agency) : null;
        $baseCode = (string) ($baseCurrency?->getCode() ?? 'CDF');

        $rows = $this->em->createQueryBuilder()
            ->select('IDENTITY(fo.currency) AS curId', 'fo.type AS flowType', 'COALESCE(SUM(fo.amount), 0) AS total')
            ->from(FinancialOperation::class, 'fo')
            ->andWhere('fo.cashSession = :session')
            ->setParameter('session', $session)
            ->groupBy('curId', 'flowType')
            ->getQuery()
            ->getArrayResult();

        $netMovements = [];
        $outMovements = [];
        foreach ($rows as $row) {
            $cid = (int) $row['curId'];
            $ft = $row['flowType'] instanceof \BackedEnum ? $row['flowType']->value : (string) $row['flowType'];
            $netMovements[$cid] ??= 0.0;
            $outMovements[$cid] ??= 0.0;
            if (FlowType::In->value === $ft) {
                $netMovements[$cid] += (float) $row['total'];
            } else {
                $netMovements[$cid] -= (float) $row['total'];
                $outMovements[$cid] += (float) $row['total'];
            }
        }

        $balances = $this->em->getRepository(CashSessionBalance::class)->findBy([
            'cashSession' => $session,
        ]);

        $orderedCurrencies = null !== $agency ? $this->getAgencyActiveCurrencies($agency) : [];
        $orderMap = [];
        foreach ($orderedCurrencies as $idx => $c) {
            $orderMap[(int) $c->getId()] = $idx;
        }
        usort($balances, static fn(CashSessionBalance $a, CashSessionBalance $b): int => ($orderMap[(int) $a->getCurrency()?->getId()] ?? 99) <=> ($orderMap[(int) $b->getCurrency()?->getId()] ?? 99));

        $byCurrency = [];
        $consolidatedExpected = 0.0;
        $consolidatedOut = 0.0;

        foreach ($balances as $bal) {
            $cur = $bal->getCurrency();
            if (null === $cur) {
                continue;
            }
            $code = (string) $cur->getCode();
            $opening = (float) $bal->getOpeningBalanceDeclared();
            if ($opening <= 0 && CashSessionStatus::Pending === $session->getStatus()) {
                $opening = (float) $bal->getOpeningBalanceExpected();
            }

            $cid = (int) $cur->getId();
            $expected = round($opening + ($netMovements[$cid] ?? 0.0), 2);
            $rate = null !== $agency ? $this->getCurrencyRateToBase($agency, $cur) : 1.0;
            if ($rate <= 0) {
                $rate = 1.0;
            }

            $consolidatedExpected += $expected * $rate;
            $consolidatedOut += ($outMovements[$cid] ?? 0.0) * $rate;

            $byCurrency[$code] = [
                'code'       => $code,
                'name'       => (string) $cur->getName(),
                'opening'    => $opening,
                'expected'   => $expected,
                'formatted'  => number_format($expected, 2, ',', ' '),
                'rateToBase' => $rate,
            ];
        }

        $consolidatedExpected = round($consolidatedExpected, 2);
        $consolidatedOut = round($consolidatedOut, 2);

        return [
            'baseCode'                 => $baseCode,
            'consolidatedExpected'     => $consolidatedExpected,
            'consolidatedFormatted'    => number_format($consolidatedExpected, 2, ',', ' '),
            'consolidatedOut'          => $consolidatedOut,
            'consolidatedOutFormatted' => number_format($consolidatedOut, 2, ',', ' '),
            'byCurrency'               => $byCurrency,
        ];
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
            $expected = $theoretical['byCurrency'][$code]['expected'] ?? 0.0;
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
     * @return list<Currency>
     */
    public function getAgencyActiveCurrencies(Agency $agency): array
    {
        /** @var list<CurrencyAgency> $rows */
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

        return array_values(array_filter(array_map(
            static fn(CurrencyAgency $ca): ?Currency => $ca->getCurrency(),
            $rows
        )));
    }

    /**
     * 
     * Retourne la valeur  devise de base de l'agence .
     */
    private function getBaseCurrency(Agency $agency): ?Currency
    {
        $baseRel = $this->em->getRepository(CurrencyAgency::class)->findOneBy([
            'agency'   => $agency,
            'isBase'   => true,
            'isActive' => true,
        ]);

        return $baseRel?->getCurrency() ?? $agency->getDefaultCurrency();
    }//getBaseCurrency




    /**
     * Retourne la valeur de 1 unité de $currency dans la devise de base de l'agence (ex: 1 USD = 2300 CDF -> 2300.0).
     */
    private function getCurrencyRateToBase(Agency $agency, Currency $currency): float
    {
        $baseRel = $this->em->getRepository(CurrencyAgency::class)->findOneBy([
            'agency'   => $agency,
            'isBase'   => true,
            'isActive' => true,
        ]);

        $baseCurrency = $baseRel?->getCurrency();
        if (null === $baseCurrency || $baseCurrency->getId() === $currency->getId()) {
            return 1.0;
        }

        $row = $this->em->createQueryBuilder()
            ->select('er.rate')
            ->from(ExchangeRate::class, 'er')
            ->andWhere('er.agency = :agency')
            ->andWhere('er.baseCurrency = :base')
            ->andWhere('er.targetCurrency = :target')
            ->andWhere('er.effectiveFrom <= :now')
            ->setParameter('agency', $agency)
            ->setParameter('base', $baseCurrency)
            ->setParameter('target', $currency)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('er.effectiveFrom', 'DESC')
            ->addOrderBy('er.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return null !== $row ? (float) $row['rate'] : 0.0;
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

            if (
                ReservationStatus::Expired === $reservation->getStatus()
                || (null !== $reservation->getExpiresAt() && $reservation->getExpiresAt() <= new \DateTimeImmutable())
            ) {
                return ['error' => 'Cette réservation a expiré et ses sièges ont été libérés.'];
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
     * Gère le rendu de monnaie : si le client remet un montant supérieur (ex: 15 USD = 34 500 CDF pour un bon de 30 000 CDF),
     * la différence (4 500 CDF) est rendue au client et le tiroir est crédité de la recette nette exacte.
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
        $tenderedAmount = round((float) $data->capturedAmount, 2);
        if ($tenderedAmount <= 0) {
            throw new \DomainException('Le montant remis par le client doit être supérieur à 0.');
        }

        $discount = max(0.0, (float) ($data->discountAmount ?? 0.0));
        if ($discount > 0 && (null === $data->discountObservation || '' === trim($data->discountObservation))) {
            throw new \DomainException('Une observation justificative est obligatoire lorsqu\'une remise est appliquée.');
        }

        $baseAmount = (float) $voucher['amountRaw'];
        $netInVoucherCurrency = 'percent' === $data->discountType
            ? round($baseAmount - ($baseAmount * min(100.0, $discount) / 100.0), 2)
            : max(0.0, round($baseAmount - $discount, 2));

        $voucherCurrency = $this->em->getRepository(Currency::class)->find((int) $voucher['currencyId']);
        if (!$voucherCurrency instanceof Currency) {
            throw new \DomainException('Devise du document introuvable.');
        }

        $baseCurrency = $this->getBaseCurrency($agency) ?? $voucherCurrency;
        $rateVoucherToBase = $this->getCurrencyRateToBase($agency, $voucherCurrency);
        $rateCapturedToBase = $this->getCurrencyRateToBase($agency, $capturedCurrency);

        if ($rateVoucherToBase <= 0 || $rateCapturedToBase <= 0) {
            throw new \DomainException(sprintf(
                'Aucun taux de change actif configuré pour convertir %s vers %s.',
                $voucher['currencyCode'],
                $capturedCurrency->getCode()
            ));
        }

        // Conversion du net dû et du montant remis en devise de base (ex: CDF)
        $dueInBase = round($netInVoucherCurrency * $rateVoucherToBase, 2);
        $tenderedInBase = round($tenderedAmount * $rateCapturedToBase, 2);
        $toleranceBase = max(1.0, round($dueInBase * 0.005, 2));

        if (($tenderedInBase + $toleranceBase) < $dueInBase) {
            $expectedInCaptured = round($dueInBase / $rateCapturedToBase, 2);
            throw new \DomainException(sprintf(
                'Montant remis insuffisant : %s %s attendus (équivalent de %s %s), remis %s %s.',
                number_format($expectedInCaptured, 2, ',', ' '),
                $capturedCurrency->getCode(),
                number_format($dueInBase, 2, ',', ' '),
                $baseCurrency->getCode(),
                number_format($tenderedAmount, 2, ',', ' '),
                $capturedCurrency->getCode()
            ));
        }

        // Monnaie rendue au client en devise de base (si le client a donné un billet supérieur)
        $changeInBase = max(0.0, round($tenderedInBase - $dueInBase, 2));

        // Recette nette comptabilisée dans la devise encaissée (sans le surplus rendu en monnaie)
        $netBookedInCaptured = ($capturedCurrency->getId() === $voucherCurrency->getId())
            ? $netInVoucherCurrency
            : round($dueInBase / $rateCapturedToBase, 2);

        $isReservation = 'reservation' === $voucher['docType'];
        $paymentType = $isReservation ? PaymentType::Reservation : PaymentType::Shipment;
        $ref = (string) $voucher['reference'];

        $payment = (new AgencyPayment())
            ->setType($paymentType)
            ->setReference($ref)
            ->setAmount(number_format($netBookedInCaptured, 2, '.', ''))
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

        $desc = sprintf(
            'Encaissement %s [%s] (%s)',
            $isReservation ? 'Réservation' : 'Colis Fret',
            $ref,
            $voucher['actor1Name']
        );
        if ($capturedCurrency->getId() !== $baseCurrency->getId()) {
            $desc .= sprintf(' — Équiv. %s %s', number_format($dueInBase, 2, ',', ' '), $baseCurrency->getCode());
        }
        if ($changeInBase > 0.01) {
            $desc .= sprintf(
                ' | Reçu: %s %s — Monnaie rendue: %s %s',
                number_format($tenderedAmount, 2, ',', ' '),
                $capturedCurrency->getCode(),
                number_format($changeInBase, 2, ',', ' '),
                $baseCurrency->getCode()
            );
        }
        if ($discount > 0) {
            $desc .= sprintf(' | Remise: %s (%s) — Motif: %s', $discount, $data->discountType, trim((string) $data->discountObservation));
        }

        $operation = (new FinancialOperation())
            ->setCashSession($session)
            ->setType(FlowType::In)
            ->setReferenceType(FinancialReferenceType::AgencyPayment)
            ->setReferenceId((int) $payment->getId())
            ->setAmount(number_format($netBookedInCaptured, 2, '.', ''))
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
                'type'         => $paymentType->value,
                'ref'          => $ref,
                'amount'       => $payment->getAmount(),
                'currency'     => $capturedCurrency->getCode(),
                'dueInBase'    => $dueInBase,
                'changeInBase' => $changeInBase,
                'session'      => $session->getId(),
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
        $available = $balances['byCurrency'][(string) $currency->getCode()]['expected'] ?? 0.0;
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

    private function generateRegisterCode(Agency $agency, Branch $branch): string
    {
        $cityPrefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) $branch->getCity()) ?: 'AGY', 0, 3));
        $repo = $this->em->getRepository(CashRegister::class);
        $index = 1;

        do {
            $candidate = sprintf('CSH-%s-%02d', $cityPrefix, $index);
            ++$index;
        } while (null !== $repo->findOneBy(['agency' => $agency, 'code' => $candidate]));

        return $candidate;
    }


    /**
     * Active ou désactive un guichet physique (réservé à l'Admin d'agence / General Manager).
     *
     * @throws \DomainException
     */
    public function toggleRegisterStatus(CashRegister $register, User $actor): void
    {
        if (ActiveStatus::Active === $register->getStatus()) {
            $busyCount = (int) $this->em->createQueryBuilder()
                ->select('COUNT(cs.id)')
                ->from(CashSession::class, 'cs')
                ->andWhere('cs.cashRegister = :reg')
                ->andWhere('cs.status IN (:st)')
                ->setParameter('reg', $register)
                ->setParameter('st', [CashSessionStatus::Pending->value, CashSessionStatus::Opened->value])
                ->getQuery()
                ->getSingleScalarResult();

            if ($busyCount > 0) {
                throw new \DomainException(sprintf('Impossible de désactiver le guichet [%s] tant qu\'une session est ouverte ou en attente.', $register->getCode()));
            }
            $register->setStatus(ActiveStatus::Inactive);
        } else {
            $register->setStatus(ActiveStatus::Active);
        }

        $this->auditLogger->log(
            action: AuditAction::Update,
            entityName: 'CashRegister',
            entityId: $register->getId(),
            oldValues: ['code' => $register->getCode()],
            newValues: ['code' => $register->getCode(), 'status' => $register->getStatus()->value],
            actor: $actor,
            flush: false
        );

        $this->em->flush();
    }

    /**
     * Garantit que chaque devise active de l'agence possède une ligne CashSessionBalance sur la session.
     */
    public function ensureSessionBalances(CashSession $session): void
    {
        $agency = $session->getCashRegister()?->getAgency();
        if (null === $agency) {
            return;
        }

        $existingBalances = $this->em->getRepository(CashSessionBalance::class)->findBy([
            'cashSession' => $session,
        ]);

        $existingCurrencyIds = [];
        foreach ($existingBalances as $bal) {
            if (null !== $bal->getCurrency()) {
                $existingCurrencyIds[(int) $bal->getCurrency()->getId()] = true;
            }
        }

        $added = false;
        foreach ($this->getAgencyActiveCurrencies($agency) as $currency) {
            if (isset($existingCurrencyIds[(int) $currency->getId()])) {
                continue;
            }

            $balance = (new CashSessionBalance())
                ->setCashSession($session)
                ->setCurrency($currency)
                ->setOpeningBalanceExpected('0.00')
                ->setOpeningBalanceDeclared('0.00')
                ->setOpenBalanceDifference('0.00');

            $session->addBalance($balance);
            $this->em->persist($balance);
            $added = true;
        }

        if ($added) {
            $this->em->flush();
        }
    }
}
