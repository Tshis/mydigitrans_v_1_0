<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\admin\agency\ExpenseCreateData;
use App\Entity\Agency;
use App\Entity\AgencyPayment;
use App\Entity\Branch;
use App\Entity\CashSession;
use App\Entity\Expense;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\BranchStatus;
use App\Enum\CashSessionStatus;
use App\Enum\ExpenseStatus;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use Doctrine\ORM\EntityManagerInterface;

use App\Entity\Currency;
use App\Entity\CurrencyAgency;
use App\Entity\ExchangeRate;
use App\Entity\FundTransfer;
use App\Enum\FundTransferDestinationType;
use App\Enum\FundTransferStatus;


final class AgencyFinanceService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuditLogger $auditLogger,
        private readonly AgencyCashierService $cashierService,
    ) {}

    /**
     * Enregistre une dépense opérationnelle ou un abonnement SaaS.
     *
     * @throws \DomainException
     */
    public function createExpense(ExpenseCreateData $data, User $actor): Expense
    {
        $agency = $actor->getAgency() ?? throw new \DomainException('Agence introuvable.');
        $branch = $data->branch ?? throw new \DomainException('Succursale requise.');

        if ($branch->getAgency()?->getId() !== $agency->getId()) {
            throw new \DomainException('Succursale invalide pour cette agence.');
        }

        $currency = $data->currency ?? throw new \DomainException('Devise requise.');
        $amount = round((float) $data->amount, 2);
        if ($amount <= 0) {
            throw new \DomainException('Le montant de la dépense doit être supérieur à 0.');
        }

        $isFromCashRegister = 'cash_register' === $data->fundingSource;
        if ($isFromCashRegister && null === $data->cashRegister) {
            throw new \DomainException('Veuillez sélectionner le guichet de caisse émetteur.');
        }

        $expense = (new Expense())
            ->setType($data->type)
            ->setName(trim((string) $data->name))
            ->setAmount(number_format($amount, 2, '.', ''))
            ->setCurrency($currency)
            ->setCashRegister($isFromCashRegister ? $data->cashRegister : null)
            ->setAgency($agency)
            ->setBranch($branch)
            ->setDescription($data->description)
            ->setValidFrom($data->validFrom)
            ->setValidUntil($data->validUntil)
            ->setCreatedBy($actor)
            ->setStatus($isFromCashRegister ? ExpenseStatus::Pending : ExpenseStatus::Paid)
            ->setConfirmedBy($isFromCashRegister ? null : $actor);

        $this->em->persist($expense);

        $this->auditLogger->log(
            action: AuditAction::Create,
            entityName: 'Expense',
            entityId: $expense->getId(),
            oldValues: null,
            newValues: [
                'name'     => $expense->getName(),
                'amount'   => $expense->getAmount(),
                'currency' => $currency->getCode(),
                'source'   => $data->fundingSource,
                'status'   => $expense->getStatus()->value,
            ],
            actor: $actor,
            flush: false
        );

        $this->em->flush();

        return $expense;
    }

    /**
     * Compile les indicateurs multi-devises et le grand livre unifié (Recettes + Dépenses).
     *
     * @param array{date: ?string, branchId: ?int, type: ?string} $filters
     * @return array<string, mixed>
     */
    public function getFinanceOverview(Agency $agency, array $filters): array
    {
        $branches = $this->em->getRepository(Branch::class)->findBy(
            ['agency' => $agency, 'status' => BranchStatus::Opened],
            ['city' => 'ASC', 'name' => 'ASC']
        );

        $targetDate = null;
        if (null !== $filters['date'] && '' !== $filters['date']) {
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $filters['date']);
            if (false !== $parsed) {
                $targetDate = $parsed->setTime(0, 0);
            }
        }

        $statsDate = $targetDate ?? (new \DateTimeImmutable())->setTime(0, 0);
        $nextDay = $statsDate->modify('+1 day');

        // 1. Recettes du jour (ou de la date filtrée) par devise
        $revQb = $this->em->createQueryBuilder()
            ->select('c.code AS code', 'COALESCE(SUM(p.amount), 0) AS total')
            ->from(AgencyPayment::class, 'p')
            ->join('p.currency', 'c')
            ->andWhere('p.agency = :agency')
            ->andWhere('p.status = :confirmed')
            ->andWhere('p.createdAt >= :start')
            ->andWhere('p.createdAt < :end')
            ->setParameter('agency', $agency)
            ->setParameter('confirmed', PaymentStatus::Confirmed)
            ->setParameter('start', $statsDate)
            ->setParameter('end', $nextDay)
            ->groupBy('c.code');

        if (null !== $filters['branchId']) {
            $revQb->andWhere('IDENTITY(p.branch) = :branchId')->setParameter('branchId', $filters['branchId']);
        }

        $revRows = $revQb->getQuery()->getArrayResult();
        $revenuesByCode = [];
        foreach ($revRows as $r) {
            $revenuesByCode[(string) $r['code']] = (float) $r['total'];
        }

        // 2. Dépenses payées du jour (ou de la date filtrée) par devise
        $expQb = $this->em->createQueryBuilder()
            ->select('c.code AS code', 'COALESCE(SUM(e.amount), 0) AS total')
            ->from(Expense::class, 'e')
            ->join('e.currency', 'c')
            ->andWhere('e.agency = :agency')
            ->andWhere('e.status = :paid')
            ->andWhere('e.createdAt >= :start')
            ->andWhere('e.createdAt < :end')
            ->setParameter('agency', $agency)
            ->setParameter('paid', ExpenseStatus::Paid)
            ->setParameter('start', $statsDate)
            ->setParameter('end', $nextDay)
            ->groupBy('c.code');

        if (null !== $filters['branchId']) {
            $expQb->andWhere('IDENTITY(e.branch) = :branchId')->setParameter('branchId', $filters['branchId']);
        }

        $expRows = $expQb->getQuery()->getArrayResult();
        $expensesByCode = [];
        foreach ($expRows as $r) {
            $expensesByCode[(string) $r['code']] = (float) $r['total'];
        }

        $allCodes = array_unique(array_merge(array_keys($revenuesByCode), array_keys($expensesByCode)));
        $dailyRevenues = [];
        $dailyExpenses = [];
        $dailyNets = [];

        foreach ($allCodes as $code) {
            $rev = $revenuesByCode[$code] ?? 0.0;
            $exp = $expensesByCode[$code] ?? 0.0;
            $net = $rev - $exp;

            if ($rev > 0) {
                $dailyRevenues[] = ['code' => $code, 'amount' => number_format($rev, 2, ',', ' ')];
            }
            if ($exp > 0) {
                $dailyExpenses[] = ['code' => $code, 'amount' => number_format($exp, 2, ',', ' ')];
            }
            $dailyNets[] = ['code' => $code, 'amount' => number_format($net, 2, ',', ' ')];
        }

        // 3. Grand livre unifié (Paiements + Dépenses)
        $operations = [];

        if (null === $filters['type'] || '' === $filters['type'] || 'payment' === $filters['type']) {
            $pQb = $this->em->createQueryBuilder()
                ->select('p', 'c', 'br')
                ->from(AgencyPayment::class, 'p')
                ->join('p.currency', 'c')
                ->leftJoin('p.branch', 'br')
                ->andWhere('p.agency = :agency')
                ->setParameter('agency', $agency)
                ->orderBy('p.createdAt', 'DESC')
                ->setMaxResults(150);

            if (null !== $targetDate) {
                $pQb->andWhere('p.createdAt >= :start')->andWhere('p.createdAt < :end')
                    ->setParameter('start', $targetDate)->setParameter('end', $nextDay);
            }
            if (null !== $filters['branchId']) {
                $pQb->andWhere('IDENTITY(p.branch) = :branchId')->setParameter('branchId', $filters['branchId']);
            }

            /** @var list<AgencyPayment> $payments */
            $payments = $pQb->getQuery()->getResult();
            foreach ($payments as $pay) {
                $dt = $pay->getCreatedAt();
                $isRes = 'reservation' === $pay->getType()->value;
                $operations[] = [
                    'id'        => (int) $pay->getId(),
                    'timestamp' => $dt?->getTimestamp() ?? 0,
                    'date'      => $dt?->format('d/m/Y') ?? '-',
                    'time'      => $dt?->format('H\hi') ?? '-',
                    'reference' => sprintf('PAY-%04d', (int) $pay->getId()),
                    'type'      => 'payment',
                    'label'     => $isRes ? 'Encaissement Billet Passager' : 'Encaissement Colis Fret',
                    'sub_label' => sprintf('Bon #%s', $pay->getReference()),
                    'mode'      => $this->formatPaymentMethod($pay->getPaymentMethod()),
                    'origin'    => $pay->getBranch()?->getName() ?? 'Siège',
                    'amount'    => number_format((float) $pay->getAmount(), 2, ',', ' '),
                    'currency'  => (string) $pay->getCurrency()?->getCode(),
                    'status'    => PaymentStatus::Confirmed === $pay->getStatus() ? 'Confirmé' : ucfirst($pay->getStatus()->value),
                ];
            }
        }

        if (null === $filters['type'] || '' === $filters['type'] || 'expense' === $filters['type']) {
            $eQb = $this->em->createQueryBuilder()
                ->select('e', 'c', 'br', 'cr')
                ->from(Expense::class, 'e')
                ->join('e.currency', 'c')
                ->join('e.branch', 'br')
                ->leftJoin('e.cashRegister', 'cr')
                ->andWhere('e.agency = :agency')
                ->setParameter('agency', $agency)
                ->orderBy('e.createdAt', 'DESC')
                ->setMaxResults(150);

            if (null !== $targetDate) {

                $eQb->andWhere('e.createdAt >= :start')->andWhere('e.createdAt < :end')
                    ->setParameter('start', $targetDate)->setParameter('end', $nextDay);
            }
            if (null !== $filters['branchId']) {
                $eQb->andWhere('IDENTITY(e.branch) = :branchId')->setParameter('branchId', $filters['branchId']);
            }
            /** @var list $expenses */
            $expenses = $eQb->getQuery()->getResult();
            foreach ($expenses as $exp) {
                $dt = $exp->getCreatedAt();
                $operations[] = [
                    'id'        => (int) $exp->getId(),
                    'timestamp' => $dt?->getTimestamp() ?? 0,
                    'date'      => $dt?->format('d/m/Y') ?? '-',
                    'time'      => $dt?->format('H\hi') ?? '-',
                    'reference' => sprintf('EXP-%04d', (int) $exp->getId()),
                    'type'      => 'expense',
                    'label'     => (string) $exp->getName(),
                    'sub_label' => 'subscription' === $exp->getType()->value ? 'Abonnement SaaS' : 'Charge d\'exploitation',
                    'mode'      => null !== $exp->getCashRegister() ? 'Espèces (Guichet)' : 'Hors Caisse / Virement',
                    'origin'    => $exp->getBranch()?->getName() ?? '-',
                    'amount'    => number_format((float) $exp->getAmount(), 2, ',', ' '),
                    'currency'  => (string) $exp->getCurrency()?->getCode(),
                    'status'    => ExpenseStatus::Paid === $exp->getStatus() ? 'Décaissé' : 'En attente',
                ];
            }
        }
        usort($operations, static fn(array $a, array $b): int => $b['timestamp'] <=> $a['timestamp']);
        return [
            'branches'             => $branches,
            'daily_revenues'       => $dailyRevenues,
            'daily_expenses'       => $dailyExpenses,
            'daily_nets'           => $dailyNets,
            'financial_operations' => $operations,
        ];
    }

    /**
     * Charge le détail complet d'une opération financière (payment ou expense) pour la fiche d'audit show.html.twig.
     *
     * @return array<string, mixed>|null
     */
    public function getOperationAuditDetails(Agency $agency, int $id, string $type): ?array
    {
        if ('payment' === $type) {
            $pay = $this->em->getRepository(AgencyPayment::class)->find($id);
            if (!$pay instanceof AgencyPayment || $pay->getAgency()?->getId() !== $agency->getId()) {
                return null;
            }

            $dt = $pay->getCreatedAt();
            $isRes = 'reservation' === $pay->getType()->value;

            $tickets = [];
            if ($isRes) {
                $resEntity = $this->em->getRepository(\App\Entity\Reservation::class)->findOneBy([
                    'agency'    => $agency,
                    'reference' => $pay->getReference(),
                ]);
                if (null !== $resEntity) {
                    foreach ($resEntity->getTickets() as $tk) {
                        $tickets[] = (string) $tk->getTicketNumber();
                    }
                }
            }


            return [
                'id'             => $pay->getId(),
                'type'           => 'payment',
                'reference'      => sprintf('PAY-%04d', (int) $pay->getId()),
                'amount'         => number_format((float) $pay->getAmount(), 2, ',', ' '),
                'amount_raw'     => (float) $pay->getAmount(),
                'currency'       => (string) $pay->getCurrency()?->getCode(),
                'date'           => $dt?->format('d/m/Y') ?? '-',
                'time'           => $dt?->format('H:i') ?? '-',
                'label'          => $isRes ? 'Encaissement Billet Passager' : 'Encaissement Bordereau Fret',
                'branch_name'    => $pay->getBranch()?->getName() ?? 'Siège Central',
                'actor'          => sprintf('Document lié : %s', $pay->getReference()),
                'funding_source' => sprintf('%s %s', $this->formatPaymentMethod($pay->getPaymentMethod()), $pay->getCashSession() ? '— Session #' . $pay->getCashSession()->getId() : ''),
                'created_by'     => $pay->getCreatedBy()?->getFullName() ?? 'Système',
                'confirmed_by'   => $pay->getConfirmedBy()?->getFullName() ?? 'En attente',
                'description'    => sprintf('Règlement enregistré sur la référence %s (%s).', $pay->getReference(), $this->formatPaymentMethod($pay->getPaymentMethod())),
                'tickets' => $tickets,
            ];
        }

        $exp = $this->em->getRepository(Expense::class)->find($id);
        if (!$exp instanceof Expense || $exp->getAgency()?->getId() !== $agency->getId()) {
            return null;
        }

        $dt = $exp->getCreatedAt();

        return [
            'id'             => $exp->getId(),
            'type'           => 'expense',
            'reference'      => sprintf('EXP-%04d', (int) $exp->getId()),
            'amount'         => number_format((float) $exp->getAmount(), 2, ',', ' '),
            'amount_raw'     => (float) $exp->getAmount(),
            'currency'       => (string) $exp->getCurrency()?->getCode(),
            'date'           => $dt?->format('d/m/Y') ?? '-',
            'time'           => $dt?->format('H:i') ?? '-',
            'label'          => (string) $exp->getName(),
            'branch_name'    => $exp->getBranch()?->getName() ?? '-',
            'actor'          => 'subscription' === $exp->getType()->value ? 'Plateforme SaaS Mydigitrans' : 'Exploitation Succursale',
            'funding_source' => null !== $exp->getCashRegister() ? sprintf('%s [%s]', $exp->getCashRegister()->getName(), $exp->getCashRegister()->getCode()) : 'Trésorerie Hors Caisse',
            'created_by'     => $exp->getCreatedBy()?->getFullName() ?? '-',
            'confirmed_by'   => $exp->getConfirmedBy()?->getFullName() ?? 'En attente d\'exécution caisse',
            'description'    => $exp->getDescription() ?? 'Aucune note complémentaire.',
        ];
    }

    /**
     * Données de supervision des sessions de caisses (Succursale ou Réseau Global).
     *
     * @param array{branchId?: ?int, status?: ?string, query?: ?string} $filters
     * @return array<string, mixed>
     */
    public function getSessionsMonitoring(Agency $agency, array $filters = []): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('cs', 'cr', 'br', 'ob', 'cb', 'bal', 'cur')
            ->from(CashSession::class, 'cs')
            ->join('cs.cashRegister', 'cr')
            ->join('cr.branch', 'br')
            ->join('cs.openedBy', 'ob')
            ->leftJoin('cs.closedBy', 'cb')
            ->leftJoin('cs.balances', 'bal')
            ->leftJoin('bal.currency', 'cur')
            ->andWhere('cr.agency = :agency')
            ->setParameter('agency', $agency)
            ->orderBy('cs.openedAt', 'DESC')
            ->addOrderBy('cs.id', 'DESC')
            ->setMaxResults(150);

        if (!empty($filters['branchId'])) {
            $qb->andWhere('br.id = :branchId')->setParameter('branchId', (int) $filters['branchId']);
        }
        if (!empty($filters['status'])) {
            $qb->andWhere('cs.status = :status')->setParameter('status', $filters['status']);
        }
        if (!empty($filters['query'])) {
            $qb->andWhere('(cr.code LIKE :q OR cr.name LIKE :q OR ob.firstname LIKE :q OR ob.lastname LIKE :q)')
                ->setParameter('q', '%' . trim((string) $filters['query']) . '%');
        }

        /** @var list<CashSession> $sessions */
        $sessions = $qb->getQuery()->getResult();

        $activeCount = 0;
        $pendingArbitrationCount = 0;
        $totalsByCurrency = [];
        $rows = [];

        foreach ($sessions as $s) {
            $st = $s->getStatus()->value;

            if (CashSessionStatus::Opened === $s->getStatus()) {
                ++$activeCount;
            }
            if (CashSessionStatus::ClosingPending === $s->getStatus()) {
                ++$pendingArbitrationCount;
            }

            $theoretical = $this->cashierService->computeSessionTheoreticalBalances($s);
            $balancesView = [];

            foreach ($s->getBalances() as $bal) {
                $code = (string) $bal->getCurrency()?->getCode();
                $isEnded = \in_array($s->getStatus(), [CashSessionStatus::ClosingPending, CashSessionStatus::Closed], true);

                $liveAmount = $isEnded
                    ? (float) ($bal->getClosingBalanceDeclared() ?? $bal->getClosingBalanceExpected() ?? 0)
                    : ($theoretical['byCurrency'][$code]['expected'] ?? 0.0);

                $gap = (float) ($bal->getClosingBalanceDifference() ?? 0);

                if (\in_array($s->getStatus(), [CashSessionStatus::Opened, CashSessionStatus::ClosingPending], true) && $liveAmount > 0) {
                    $totalsByCurrency[$code] = ($totalsByCurrency[$code] ?? 0.0) + $liveAmount;
                }

                if ($liveAmount > 0 || CashSessionStatus::Opened === $s->getStatus()) {
                    $balancesView[] = [
                        'code'         => $code,
                        'amount'       => number_format($liveAmount, 2, ',', ' '),
                        'gap'          => $gap,
                        'gapFormatted' => number_format($gap, 2, ',', ' '),
                    ];
                }
            }

            $rows[] = [
                'id'           => $s->getId(),
                'code'         => sprintf('CS-%s-%04d', $s->getOpenedAt()?->format('Y') ?? date('Y'), (int) $s->getId()),
                'branchName'   => (string) $s->getCashRegister()?->getBranch()?->getName(),
                'registerName' => (string) $s->getCashRegister()?->getName(),
                'registerCode' => (string) $s->getCashRegister()?->getCode(),
                'cashierName'     => (string) $s->getOpenedBy()?->getFullName(),
                'validatedByName' => $s->getClosedBy()?->getFullName(),
                'openedTime'      => $s->getOpenedAt()?->format('d/m H\hi') ?? '-',
                'status'       => $st,
                'balances'     => $balancesView,
            ];
        }

        $branches = $this->em->getRepository(Branch::class)->findBy(
            ['agency' => $agency, 'status' => BranchStatus::Opened],
            ['city' => 'ASC']
        );

        $regQb = $this->em->createQueryBuilder()
            ->select('cr', 'br')
            ->from(\App\Entity\CashRegister::class, 'cr')
            ->join('cr.branch', 'br')
            ->andWhere('cr.agency = :agency')
            ->setParameter('agency', $agency)
            ->orderBy('cr.name', 'ASC');

        if (!empty($filters['branchId'])) {
            $regQb->andWhere('br.id = :branchId')->setParameter('branchId', (int) $filters['branchId']);
        }

        /** @var list<\App\Entity\CashRegister> $allRegisters */
        $allRegisters = $regQb->getQuery()->getResult();

        $activeByRegisterId = [];
        foreach ($sessions as $s) {
            $regId = $s->getCashRegister()?->getId();
            if (null === $regId || isset($activeByRegisterId[$regId])) {
                continue;
            }
            if (\in_array($s->getStatus(), [CashSessionStatus::Pending, CashSessionStatus::Opened, CashSessionStatus::ClosingPending], true)) {
                $activeByRegisterId[$regId] = $s;
            }
        }

        $registersView = [];
        foreach ($allRegisters as $reg) {
            $currentSession = $activeByRegisterId[(int) $reg->getId()] ?? null;
            $registersView[] = [
                'id'           => $reg->getId(),
                'name'         => (string) $reg->getName(),
                'code'         => (string) $reg->getCode(),
                'branchName'   => (string) $reg->getBranch()?->getName(),
                'isActive'     => \App\Enum\ActiveStatus::Active === $reg->getStatus(),
                'sessionId'    => $currentSession?->getId(),
                'sessionState' => $currentSession?->getStatus()->value ?? 'free',
                'cashierName'  => $currentSession?->getOpenedBy()?->getFullName(),
                'openedAt'     => $currentSession?->getOpenedAt()?->format('d/m H\hi'),
            ];
        }

        return [
            'branches'                  => $branches,
            'active_sessions_count'     => $activeCount,
            'pending_arbitration_count' => $pendingArbitrationCount,
            'totals_by_currency'        => $totalsByCurrency,
            'treasury'                  => $this->getTreasurySummary($agency, $filters['branchId'] ?? null),
            'sessions'                  => $rows,
            'registers'                 => $registersView,
        ];
    }


    /**
     * Charge le journal en direct d'une session spécifique (show_journal.html.twig).
     *
     * @return array<string, mixed>|null
     */
    public function getSessionJournalDetails(Agency $agency, int $sessionId): ?array
    {
        $session = $this->em->getRepository(CashSession::class)->find($sessionId);
        if (!$session instanceof CashSession || $session->getCashRegister()?->getAgency()?->getId() !== $agency->getId()) {
            return null;
        }

        $theoretical = $this->cashierService->computeSessionTheoreticalBalances($session);
        $operations = $this->cashierService->getSessionOperations($session);

        $opRows = [];
        foreach ($operations as $op) {
            $dt = $op->getCreatedAt();
            $isIn = 'in' === $op->getType()->value;
            $opRows[] = [
                'time'      => $dt?->format('H\hi') ?? '-',
                'reference' => sprintf('FLX-%04d', (int) $op->getId()),
                'direction' => $isIn ? 'IN' : 'OUT',
                'isIn'      => $isIn,
                'label'     => (string) $op->getDescription(),
                'subLabel'  => sprintf('Opérateur : %s', $op->getCreatedBy()?->getFullName()),
                'amount'    => number_format((float) $op->getAmount(), 2, ',', ' '),
                'currency'  => (string) $op->getCurrency()?->getCode(),
            ];
        }

        return [
            'session'     => $session,
            'sessionCode' => sprintf('CS-%s-%04d', $session->getOpenedAt()?->format('Y') ?? date('Y'), (int) $session->getId()),
            'theoretical' => $theoretical,
            'operations'  => $opRows,
        ];
    }


    private function getRateToBase(Agency $agency, Currency $from, Currency $base, \DateTimeImmutable $now): float
    {
        if ($from->getId() === $base->getId()) {
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
            ->setParameter('base', $base)
            ->setParameter('target', $from)
            ->setParameter('now', $now)
            ->orderBy('er.effectiveFrom', 'DESC')
            ->addOrderBy('er.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return (null !== $row && (float) $row['rate'] > 0.0) ? (float) $row['rate'] : 1.0;
    }



    /**
     * Calcule les indicateurs de trésorerie consolidés en devise de base (6.A & 6.B) :
     * 1. openSessionsTotal        : Fonds actuellement dans les tiroirs ouverts (Opened)
     * 2. closedVaultGross         : Cumul des sessions clôturées (ClosingPending + Closed) - dépenses hors tiroir payées
     * 3. outgoingTransfersTotal   : Cumul historique des transferts sortants (utilisé pour le solde réel disponible)
     *    monthOutgoingTotal       : Transferts sortants du mois en cours (affiché sur la carte)
     *    pendingOutgoingTotal     : Transferts sortants encore en transit (Pending)
     * 4. realAvailableBalance     : Solde réel disponible en succursale (Coffre clôturé + transferts reçus - transferts sortants)
     *
     * @return array{
     *   baseCode: string,
     *   openSessionsTotal: float,
     *   openSessionsFormatted: string,
     *   closedVaultGross: float,
     *   closedVaultFormatted: string,
     *   outgoingTransfersTotal: float,
     *   outgoingTransfersFormatted: string,
     *   monthOutgoingTotal: float,
     *   monthOutgoingFormatted: string,
     *   pendingOutgoingTotal: float,
     *   pendingOutgoingFormatted: string,
     *   incomingTransfersTotal: float,
     *   realAvailableBalance: float,
     *   realAvailableFormatted: string
     * }
     */
    public function getTreasurySummary(Agency $agency, ?int $branchId = null): array
    {
        $now = new \DateTimeImmutable();
        $baseRel = $this->em->getRepository(CurrencyAgency::class)->findOneBy([
            'agency'   => $agency,
            'isBase'   => true,
            'isActive' => true,
        ]);
        $baseCurrency = $baseRel?->getCurrency() ?? $agency->getDefaultCurrency();
        $baseCode = (string) ($baseCurrency?->getCode() ?? 'CDF');

        // 1. Sessions en cours (Opened)
        $openQb = $this->em->createQueryBuilder()
            ->select('cs', 'cr', 'br')
            ->from(CashSession::class, 'cs')
            ->join('cs.cashRegister', 'cr')
            ->join('cr.branch', 'br')
            ->andWhere('cr.agency = :agency')
            ->andWhere('cs.status = :opened')
            ->setParameter('agency', $agency)
            ->setParameter('opened', CashSessionStatus::Opened);

        if (null !== $branchId) {
            $openQb->andWhere('br.id = :branchId')->setParameter('branchId', $branchId);
        }

        $openSessionsTotal = 0.0;
        foreach ($openQb->getQuery()->getResult() as $openSession) {
            if ($openSession instanceof CashSession) {
                $summary = $this->cashierService->computeSessionTheoreticalBalances($openSession);
                $openSessionsTotal += (float) $summary['consolidatedExpected'];
            }
        }

        // 2. Coffre des sessions clôturées (ClosingPending + Closed)
        $closedQb = $this->em->createQueryBuilder()
            ->select('bal', 'cur', 'cs', 'cr', 'br')
            ->from(\App\Entity\CashSessionBalance::class, 'bal')
            ->join('bal.currency', 'cur')
            ->join('bal.cashSession', 'cs')
            ->join('cs.cashRegister', 'cr')
            ->join('cr.branch', 'br')
            ->andWhere('cr.agency = :agency')
            ->andWhere('cs.status IN (:ended)')
            ->setParameter('agency', $agency)
            ->setParameter('ended', [CashSessionStatus::ClosingPending->value, CashSessionStatus::Closed->value]);

        if (null !== $branchId) {
            $closedQb->andWhere('br.id = :branchId')->setParameter('branchId', $branchId);
        }

        $closedSessionsGross = 0.0;
        foreach ($closedQb->getQuery()->getResult() as $bal) {
            if (!$bal instanceof \App\Entity\CashSessionBalance) {
                continue;
            }
            $amt = (float) ($bal->getClosingBalanceDeclared() ?? $bal->getClosingBalanceExpected() ?? 0.0);
            if ($amt <= 0.0) {
                continue;
            }
            $cur = $bal->getCurrency();
            $rate = (null !== $cur && null !== $baseCurrency) ? $this->getRateToBase($agency, $cur, $baseCurrency, $now) : 1.0;
            $closedSessionsGross += $amt * $rate;
        }

        // Soustraire les dépenses payées directement depuis le coffre (hors guichet)
        $expQb = $this->em->createQueryBuilder()
            ->select('e', 'cur')
            ->from(Expense::class, 'e')
            ->join('e.currency', 'cur')
            ->andWhere('e.agency = :agency')
            ->andWhere('e.status = :paid')
            ->andWhere('e.cashRegister IS NULL')
            ->setParameter('agency', $agency)
            ->setParameter('paid', ExpenseStatus::Paid);

        if (null !== $branchId) {
            $expQb->andWhere('IDENTITY(e.branch) = :branchId')->setParameter('branchId', $branchId);
        }

        $vaultExpenses = 0.0;
        foreach ($expQb->getQuery()->getResult() as $exp) {
            if ($exp instanceof Expense && null !== $exp->getCurrency() && null !== $baseCurrency) {
                $rate = $this->getRateToBase($agency, $exp->getCurrency(), $baseCurrency, $now);
                $vaultExpenses += (float) $exp->getAmount() * $rate;
            }
        }

        $closedVaultGross = round(max(0.0, $closedSessionsGross - $vaultExpenses), 2);

        // 3. Transferts sortants (cumul intégral pour le solde réel + vue mensuelle & en transit)
        $outQb = $this->em->createQueryBuilder()
            ->select('ft', 'cur')
            ->from(FundTransfer::class, 'ft')
            ->join('ft.currency', 'cur')
            ->andWhere('ft.agency = :agency')
            ->andWhere('ft.status IN (:activeStatuses)')
            ->setParameter('agency', $agency)
            ->setParameter('activeStatuses', [FundTransferStatus::Pending->value, FundTransferStatus::Recieved->value]);

        if (null !== $branchId) {
            $outQb->andWhere('IDENTITY(ft.branch_source) = :branchId')->setParameter('branchId', $branchId);
        }

        $monthStart = $now->modify('first day of this month')->setTime(0, 0);
        $outgoingTransfersTotal = 0.0;
        $monthOutgoingTotal = 0.0;
        $pendingOutgoingTotal = 0.0;

        foreach ($outQb->getQuery()->getResult() as $ft) {
            if ($ft instanceof FundTransfer && null !== $ft->getCurrency() && null !== $baseCurrency) {
                $rate = $this->getRateToBase($agency, $ft->getCurrency(), $baseCurrency, $now);
                $inBase = (float) $ft->getAmount() * $rate;
                $outgoingTransfersTotal += $inBase;

                if (null !== $ft->getCreatedAt() && $ft->getCreatedAt() >= $monthStart) {
                    $monthOutgoingTotal += $inBase;
                }
                if (FundTransferStatus::Pending === $ft->getStatus()) {
                    $pendingOutgoingTotal += $inBase;
                }
            }
        }

        // 4. Transferts entrants reçus (si filtré sur une succursale ou le Siège)
        $incomingTransfersTotal = 0.0;
        if (null !== $branchId) {
            $inQb = $this->em->createQueryBuilder()
                ->select('ft', 'cur')
                ->from(FundTransfer::class, 'ft')
                ->join('ft.currency', 'cur')
                ->andWhere('ft.agency = :agency')
                ->andWhere('ft.destinationType = :branchType')
                ->andWhere('IDENTITY(ft.branch_destination) = :branchId')
                ->andWhere('ft.status = :recieved')
                ->setParameter('agency', $agency)
                ->setParameter('branchType', FundTransferDestinationType::Branch->value)
                ->setParameter('branchId', $branchId)
                ->setParameter('recieved', FundTransferStatus::Recieved->value);

            foreach ($inQb->getQuery()->getResult() as $ft) {
                if ($ft instanceof FundTransfer && null !== $ft->getCurrency() && null !== $baseCurrency) {
                    $rate = $this->getRateToBase($agency, $ft->getCurrency(), $baseCurrency, $now);
                    $incomingTransfersTotal += (float) $ft->getAmount() * $rate;
                }
            }
        }

        $openSessionsTotal = round($openSessionsTotal, 2);
        $outgoingTransfersTotal = round($outgoingTransfersTotal, 2);
        $monthOutgoingTotal = round($monthOutgoingTotal, 2);
        $pendingOutgoingTotal = round($pendingOutgoingTotal, 2);
        $incomingTransfersTotal = round($incomingTransfersTotal, 2);
        $realAvailableBalance = round($closedVaultGross + $incomingTransfersTotal - $outgoingTransfersTotal, 2);

        return [
            'baseCode'                   => $baseCode,
            'openSessionsTotal'          => $openSessionsTotal,
            'openSessionsFormatted'      => number_format($openSessionsTotal, 2, ',', ' '),
            'closedVaultGross'           => $closedVaultGross,
            'closedVaultFormatted'       => number_format($closedVaultGross, 2, ',', ' '),
            'outgoingTransfersTotal'     => $outgoingTransfersTotal,
            'outgoingTransfersFormatted' => number_format($outgoingTransfersTotal, 2, ',', ' '),
            'monthOutgoingTotal'         => $monthOutgoingTotal,
            'monthOutgoingFormatted'     => number_format($monthOutgoingTotal, 2, ',', ' '),
            'pendingOutgoingTotal'       => $pendingOutgoingTotal,
            'pendingOutgoingFormatted'   => number_format($pendingOutgoingTotal, 2, ',', ' '),
            'incomingTransfersTotal'     => $incomingTransfersTotal,
            'realAvailableBalance'       => $realAvailableBalance,
            'realAvailableFormatted'     => number_format($realAvailableBalance, 2, ',', ' '),
        ];
    }


    /**
     * Liste les transferts de fonds de l'agence (filtrés par succursale source ou destination).
     *
     * @return list<FundTransfer>
     */
    public function listFundTransfers(Agency $agency, ?int $branchId = null): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('ft', 'bs', 'bd', 'cur', 'cb', 'rb')
            ->from(FundTransfer::class, 'ft')
            ->leftJoin('ft.branch_source', 'bs')
            ->leftJoin('ft.branch_destination', 'bd')
            ->join('ft.currency', 'cur')
            ->leftJoin('ft.createdBy', 'cb')
            ->leftJoin('ft.recievedBy', 'rb')
            ->andWhere('ft.agency = :agency')
            ->setParameter('agency', $agency)
            ->orderBy('ft.createdAt', 'DESC')
            ->setMaxResults(150);

        if (null !== $branchId) {
            $qb->andWhere('(bs.id = :branchId OR bd.id = :branchId)')
                ->setParameter('branchId', $branchId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Crée un transfert / versement de fonds depuis le coffre de la succursale vers la Banque ou une autre Succursale (Siège).
     *
     * @param array{
     *   branchSourceId: ?int,
     *   destinationType: string,
     *   branchDestinationId: ?int,
     *   bankName: ?string,
     *   bankReference: ?string,
     *   amount: float,
     *   currencyId: int
     * } $payload
     *
     * @throws \DomainException
     */
    public function createFundTransfer(array $payload, User $actor): FundTransfer
    {
        $agency = $actor->getAgency() ?? throw new \DomainException('Agence introuvable.');
        $sourceId = $actor->getBranch()?->getId() ?? ($payload['branchSourceId'] ?? null);
        $sourceBranch = null !== $sourceId ? $this->em->getRepository(Branch::class)->find((int) $sourceId) : null;

        if (!$sourceBranch instanceof Branch || $sourceBranch->getAgency()?->getId() !== $agency->getId()) {
            throw new \DomainException('Succursale émettrice invalide.');
        }

        $currency = $this->em->getRepository(Currency::class)->find((int) ($payload['currencyId'] ?? 0));
        if (!$currency instanceof Currency) {
            throw new \DomainException('Devise de transfert invalide.');
        }

        $amount = round((float) ($payload['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw new \DomainException('Le montant du transfert doit être supérieur à 0.');
        }

        $destType = FundTransferDestinationType::tryFrom((string) ($payload['destinationType'] ?? ''));
        if (null === $destType) {
            throw new \DomainException('Type de destination invalide.');
        }

        // Vérification que le solde réel disponible en succursale couvre ce transfert
        $treasury = $this->getTreasurySummary($agency, (int) $sourceBranch->getId());
        $baseRel = $this->em->getRepository(CurrencyAgency::class)->findOneBy([
            'agency'   => $agency,
            'isBase'   => true,
            'isActive' => true,
        ]);
        $baseCurrency = $baseRel?->getCurrency() ?? $currency;
        $rate = $this->getRateToBase($agency, $currency, $baseCurrency, new \DateTimeImmutable());
        $amountInBase = round($amount * $rate, 2);

        if ($amountInBase > ($treasury['realAvailableBalance'] + 0.01)) {
            throw new \DomainException(sprintf(
                'Solde réel insuffisant en coffre succursale (%s %s disponibles, transfert demandé : %s %s).',
                $treasury['realAvailableFormatted'],
                $treasury['baseCode'],
                number_format($amountInBase, 2, ',', ' '),
                $treasury['baseCode']
            ));
        }

        $destBranch = null;
        $bankName = null;
        $bankRef = null;

        if (FundTransferDestinationType::Branch === $destType) {
            $destId = (int) ($payload['branchDestinationId'] ?? 0);
            $destBranch = $this->em->getRepository(Branch::class)->find($destId);
            if (!$destBranch instanceof Branch || $destBranch->getAgency()?->getId() !== $agency->getId()) {
                throw new \DomainException('Veuillez sélectionner la succursale ou le Siège destinataire.');
            }
            if ($destBranch->getId() === $sourceBranch->getId()) {
                throw new \DomainException('La succursale destinataire doit être différente de la succursale émettrice.');
            }
        } else {
            $bankName = trim((string) ($payload['bankName'] ?? ''));
            $bankRef = trim((string) ($payload['bankReference'] ?? ''));
            if ('' === $bankName || '' === $bankRef) {
                throw new \DomainException('Le nom de la banque et la référence du bordereau de versement sont obligatoires.');
            }
        }

        $ref = sprintf('TRF-%s-%04d', date('ym'), random_int(1000, 9999));

        $transfer = (new FundTransfer())
            ->setAgency($agency)
            ->setReference($ref)
            ->setBranchSource($sourceBranch)
            ->setDestinationType($destType)
            ->setBranchDestination($destBranch)
            ->setBankName($bankName)
            ->setBankReference($bankRef)
            ->setAmount(number_format($amount, 2, '.', ''))
            ->setCurrency($currency)
            ->setCreatedBy($actor)
            ->setStatus(FundTransferStatus::Pending);

        $this->em->persist($transfer);

        $this->auditLogger->log(
            action: AuditAction::Create,
            entityName: 'FundTransfer',
            entityId: $transfer->getId(),
            oldValues: null,
            newValues: [
                'reference' => $ref,
                'source'    => $sourceBranch->getName(),
                'type'      => $destType->value,
                'amount'    => $transfer->getAmount(),
                'currency'  => $currency->getCode(),
            ],
            actor: $actor,
            flush: false
        );

        $this->em->flush();

        return $transfer;
    }


    /**
     * Confirme la réception physique des fonds ou la validation du bordereau bancaire.
     * Autorisé uniquement pour :
     * - ROLE_AGENCY_ADMIN ou ROLE_GENERAL_MANAGER (Siège / réseau)
     * - ROLE_MANAGER de la succursale destinataire (pour un transfert inter-succursales)
     *
     * @throws \DomainException
     */
    public function confirmFundTransferReceipt(FundTransfer $transfer, User $receiver): void
    {
        if (FundTransferStatus::Pending !== $transfer->getStatus()) {
            throw new \DomainException('Ce transfert n\'est plus en attente de réception.');
        }

        if ($transfer->getCreatedBy()?->getId() === $receiver->getId()) {
            throw new \DomainException('Vous ne pouvez pas valider la réception d\'un transfert que vous avez vous-même émis.');
        }

        $roles = $receiver->getRoles();
        $isTopAdmin = \in_array('ROLE_AGENCY_ADMIN', $roles, true) || \in_array('ROLE_GENERAL_MANAGER', $roles, true);
        $isDestinationBranchManager = \in_array('ROLE_MANAGER', $roles, true)
            && FundTransferDestinationType::Branch === $transfer->getDestinationType()
            && null !== $receiver->getBranch()
            && null !== $transfer->getBranchDestination()
            && $receiver->getBranch()->getId() === $transfer->getBranchDestination()->getId();

        if (!$isTopAdmin && !$isDestinationBranchManager) {
            throw new \DomainException('Accès refusé : seul un administrateur, un directeur général ou le manager de la succursale destinataire peut confirmer ce transfert.');
        }

        $transfer->setStatus(FundTransferStatus::Recieved)
            ->setRecievedBy($receiver)
            ->setRecievedAt(new \DateTimeImmutable());

        $this->auditLogger->log(
            action: AuditAction::Update,
            entityName: 'FundTransfer',
            entityId: $transfer->getId(),
            oldValues: ['status' => FundTransferStatus::Pending->value],
            newValues: ['status' => FundTransferStatus::Recieved->value, 'receivedBy' => $receiver->getFullName()],
            actor: $receiver,
            flush: false
        );

        $this->em->flush();
    }



    private function formatPaymentMethod(PaymentMethod $method): string
    {
        return match ($method) {

            PaymentMethod::Cash         => 'Espèces (Cash)',
            PaymentMethod::MobileMoney  => 'Mobile Money',
            PaymentMethod::Card         => 'Carte Bancaire',
            PaymentMethod::BankTransfer => 'Virement Bancaire',
        };
    } //formatPaymentMethod
}
