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

    private function formatPaymentMethod(PaymentMethod $method): string
    {
        return match ($method) {

            PaymentMethod::Cash         => 'Espèces (Cash)',
            PaymentMethod::MobileMoney  => 'Mobile Money',
            PaymentMethod::Card         => 'Carte Bancaire',
            PaymentMethod::BankTransfer => 'Virement Bancaire',
        };
    }
}
