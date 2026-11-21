<?php

declare(strict_types=1);

namespace App\Controller\web\admin\agency;

use App\Controller\web\admin\AbstractAdminController;
use App\Dto\admin\agency\CashierPaymentData;
use App\Dto\admin\agency\CashRegisterCreateData;
use App\Dto\admin\agency\CashSessionCloseData;
use App\Dto\admin\agency\CashSessionInitData;
use App\Dto\admin\agency\CashSessionOpenData;
use App\Dto\admin\agency\CashSessionValidateData;
use App\Entity\CashSession;
use App\Entity\Expense;
use App\Enum\CashSessionStatus;
use App\Enum\Scope;
use App\Form\agency\CashierPaymentType;
use App\Form\agency\CashRegisterCreateType;
use App\Form\agency\CashSessionInitType;
use App\Form\agency\CashSessionValidateType;
use App\Service\AgencyCashierService;
use App\Service\AgencyFinanceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class CashierController extends AbstractAdminController
{
    public function __construct(
        private readonly AgencyCashierService $cashierService,
        private readonly AgencyFinanceService $financeService,
        private readonly EntityManagerInterface $em,
    ) {}

    protected function scope(): Scope
    {
        return Scope::Agency;
    }

    #[Route('/admin/agency/cash-register', name: 'admin_agency_cashier_index', methods: ['GET'])]
    public function index(): Response
    {
        $actor = $this->actor();

        // 1. Administrateur d'agence ou General Manager (réseau global, non rattaché à une succursale unique)
        if (
            null === $actor->getBranch()
            || $this->isGranted('ROLE_AGENCY_ADMIN')
            || $this->isGranted('ROLE_GENERAL_MANAGER')
        ) {
            return $this->redirectToRoute('admin_agency_cashier_dashboard_global');
        }

        // 2. Si l'agent rattaché à une succursale a une session personnelle (Pending ou Opened) -> Espace Caissier
        $session = $this->cashierService->findCurrentSessionForCashier($actor);
        if ($session instanceof CashSession) {
            if (CashSessionStatus::Pending === $session->getStatus()) {
                return $this->redirectToRoute('admin_agency_cashier_session_open');
            }

            return $this->redirectToRoute('admin_agency_cashier_dashboard');
        }

        // 3. Gestionnaire / Comptable de succursale (rattaché à une succursale) -> Dashboard de sa succursale uniquement
        if ($this->isGranted('ROLE_MANAGER') || $this->isGranted('ROLE_ACCOUNTANT')) {
            return $this->redirectToRoute('admin_agency_cashier_dashboard_branch');
        }

        // 4. Caissier sans session préparée -> Écran d'attente
        return $this->redirectToRoute('admin_agency_cashier_awaiting_init');
    }



    #[Route('/admin/agency/cash-register/create', name: 'admin_agency_cashier_add', methods: ['GET', 'POST'])]
    public function add(Request $request): Response
    {
        if (!$this->isGranted('ROLE_AGENCY_ADMIN') && !$this->isGranted('ROLE_GENERAL_MANAGER')) {
            throw $this->createAccessDeniedException('Seule la direction générale peut immatriculer un nouveau guichet de caisse.');
        }


        $actor            = $this->actor();
        $agency           = $actor->getAgency();
        $restrictedBranch = $actor->getBranch();

        $data = new CashRegisterCreateData();
        if (null !== $restrictedBranch) {
            $data->branch = $restrictedBranch;
        }

        $form = $this->createForm(CashRegisterCreateType::class, $data, [
            'agency'            => $agency,
            'restricted_branch' => $restrictedBranch,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                if (null !== $restrictedBranch) {
                    $data->branch = $restrictedBranch;
                }

                $register = $this->cashierService->createRegister($data, $actor);
                $this->addFlash('success', sprintf('Guichet [%s — %s] immatriculé et activé.', $register->getCode(), $register->getName()));

                return $this->redirectToRoute(
                    null === $restrictedBranch ? 'admin_agency_cashier_dashboard_global' : 'admin_agency_cashier_dashboard_branch'
                );
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('admin/agency/cashier/add.html.twig', [
            'page'   => 'cashier',
            'agency' => $agency,
            'form'   => $form->createView(),
        ]);
    }




    #[Route('/admin/agency/cash-register/await/init', name: 'admin_agency_cashier_awaiting_init', methods: ['GET'])]
    public function awaitingInit(): Response
    {
        $actor = $this->actor();
        $session = $this->cashierService->findCurrentSessionForCashier($actor);
        if ($session instanceof CashSession) {
            return $this->redirectToRoute('admin_agency_cashier_index');
        }

        return $this->render('admin/agency/cashier/awaiting_init.html.twig', [
            'page'  => 'cashier',
            'actor' => $actor,
        ]);
    }

    #[Route('/admin/agency/cash-register/open-session', name: 'admin_agency_cashier_session_open', methods: ['GET', 'POST'])]
    public function sessionOpening(Request $request): Response
    {
        $actor   = $this->actor();
        $session = $this->cashierService->findCurrentSessionForCashier($actor);

        if (!$session instanceof CashSession) {
            return $this->redirectToRoute('admin_agency_cashier_awaiting_init');
        }
        if (CashSessionStatus::Opened === $session->getStatus()) {
            return $this->redirectToRoute('admin_agency_cashier_dashboard');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('open_session_' . $session->getId(), (string) $request->request->get('_token'))) {
                throw new BadRequestHttpException('Jeton CSRF invalide.');
            }

            try {
                $openData = new CashSessionOpenData();
                $openData->openingBalances = (array) $request->request->all('opening_balances');
                $openData->openingObservation = trim((string) $request->request->get('opening_note', '')) ?: null;

                $this->cashierService->openSession($session, $openData, $actor);
                $this->addFlash('success', 'Session de caisse ouverte avec succès. Bon service !');

                return $this->redirectToRoute('admin_agency_cashier_dashboard');
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('admin/agency/cashier/session_open.html.twig', [
            'page'    => 'cashier',
            'actor'   => $actor,
            'session' => $session,
        ]);
    }

    #[Route('/admin/agency/cash-register/dashboard', name: 'admin_agency_cashier_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $actor   = $this->actor();
        $session = $this->cashierService->findCurrentSessionForCashier($actor);

        if (!$session instanceof CashSession) {
            return $this->redirectToRoute('admin_agency_cashier_index');
        }
        if (CashSessionStatus::Pending === $session->getStatus()) {
            return $this->redirectToRoute('admin_agency_cashier_session_open');
        }

        $balances = $this->cashierService->computeSessionTheoreticalBalances($session);
        $operations = $this->cashierService->getSessionOperations($session);
        $pendingExpenses = $this->cashierService->getPendingExpensesForCashier($actor);

        $expensesByCurrency = [];
        foreach ($operations as $op) {
            if ('out' === $op->getType()->value) {
                $code = (string) $op->getCurrency()?->getCode();
                $expensesByCurrency[$code] = ($expensesByCurrency[$code] ?? 0.0) + (float) $op->getAmount();
            }
        }

        return $this->render('admin/agency/cashier/dashboard.html.twig', [
            'page'                  => 'cashier',
            'session'               => $session,
            'balances'              => $balances,
            'expenses_by_currency'  => $expensesByCurrency,
            'operations'            => $operations,
            'pending_expense_count' => \count($pendingExpenses),
        ]);
    }


    #[Route('/admin/agency/cash-register/lookup', name: 'admin_agency_cashier_lookup', methods: ['GET'])]
    public function lookupVoucher(Request $request): JsonResponse
    {
        $agency = $this->actor()->getAgency();
        $ref = (string) $request->query->get('ref', '');

        $result = $this->cashierService->lookupVoucher($agency, $ref);
        if (null === $result) {
            return new JsonResponse(['found' => false, 'message' => 'Aucune réservation ou colis trouvé avec cette référence.'], 404);
        }
        if (isset($result['error'])) {
            return new JsonResponse(['found' => false, 'message' => $result['error']], 422);
        }

        return new JsonResponse(['found' => true, 'voucher' => $result]);
    }

    #[Route('/admin/agency/cash-register/payment', name: 'admin_agency_cashier_payment', methods: ['GET', 'POST'])]
    public function payment(Request $request): Response
    {
        $actor   = $this->actor();
        $agency  = $actor->getAgency();
        $session = $this->cashierService->findCurrentSessionForCashier($actor);

        if (!$session instanceof CashSession || CashSessionStatus::Opened !== $session->getStatus()) {
            $this->addFlash('error', 'Vous devez avoir une session de caisse ouverte pour encaisser un bon.');

            return $this->redirectToRoute('admin_agency_cashier_index');
        }

        $paymentData = new CashierPaymentData();
        $prefillRef = trim((string) $request->query->get('ref', ''));
        if ('' !== $prefillRef) {
            $paymentData->voucherReference = strtoupper($prefillRef);
        }

        $form = $this->createForm(CashierPaymentType::class, $paymentData, [
            'agency' => $agency,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $payment = $this->cashierService->processPayment($paymentData, $session, $actor);
                $this->addFlash('success', sprintf(
                    'Encaissement [%s] de %s %s validé avec succès.',
                    $payment->getReference(),
                    number_format((float) $payment->getAmount(), 2, ',', ' '),
                    $payment->getCurrency()?->getCode()
                ));

                return $this->redirectToRoute('admin_agency_cashier_show_payment', [
                    'id' => $payment->getId(),
                ]);
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('admin/agency/cashier/payment.html.twig', [
            'page'              => 'cashier',
            'form'              => $form->createView(),
            'prefill_ref'       => $prefillRef,
            'active_currencies' => $this->cashierService->getAgencyActiveCurrencies($agency),
        ]);
    }

    #[Route('/admin/agency/cash-register/expense', name: 'admin_agency_cashier_expense', methods: ['GET'])]
    public function expense(): Response
    {
        $actor   = $this->actor();
        $session = $this->cashierService->findCurrentSessionForCashier($actor);

        if (!$session instanceof CashSession || CashSessionStatus::Opened !== $session->getStatus()) {
            return $this->redirectToRoute('admin_agency_cashier_index');
        }

        $balances = $this->cashierService->computeSessionTheoreticalBalances($session);
        $pendingExpenses = $this->cashierService->getPendingExpensesForCashier($actor);

        return $this->render('admin/agency/cashier/expense.html.twig', [
            'page'             => 'cashier',
            'balances'         => $balances,
            'pending_count'    => \count($pendingExpenses),
            'pending_expenses' => $pendingExpenses,
        ]);
    }

    #[Route('/admin/agency/cash-register/execute/expense/{id}', name: 'admin_agency_cashier_expense_exec', methods: ['POST'])]
    public function expenseExec(int $id, Request $request): Response
    {
        $actor   = $this->actor();
        $session = $this->cashierService->findCurrentSessionForCashier($actor);

        if (!$session instanceof CashSession || CashSessionStatus::Opened !== $session->getStatus()) {
            return $this->redirectToRoute('admin_agency_cashier_index');
        }

        if (!$this->isCsrfTokenValid('exec_expense_' . $id, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Jeton CSRF invalide.');
        }

        $expense = $this->em->getRepository(Expense::class)->find($id);
        if (!$expense instanceof Expense || $expense->getAgency()?->getId() !== $actor->getAgency()?->getId()) {
            throw $this->createNotFoundException('Bon de décaissement introuvable.');
        }

        try {
            $this->cashierService->executeExpense($expense, $session, $actor);
            $this->addFlash('success', sprintf('Décaissement [%s] exécuté et inscrit au journal de caisse.', $expense->getName()));
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_agency_cashier_expense');
    }

    #[Route('/admin/agency/cash-register/payment/{id}', name: 'admin_agency_cashier_show_payment', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function showPayment(int $id): Response
    {
        $details = $this->financeService->getOperationAuditDetails($this->actor()->getAgency(), $id, 'payment');
        if (null === $details) {
            throw $this->createNotFoundException('Reçu d\'encaissement introuvable.');
        }

        return $this->render('admin/agency/cashier/show_payment.html.twig', [
            'page'      => 'cashier',
            'operation' => $details,
        ]);
    }

    #[Route('/admin/agency/cash-register/expense/{id}', name: 'admin_agency_cashier_show_expense', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function showExpense(int $id): Response
    {
        $details = $this->financeService->getOperationAuditDetails($this->actor()->getAgency(), $id, 'expense');
        if (null === $details) {
            throw $this->createNotFoundException('Pièce de décaissement introuvable.');
        }

        return $this->render('admin/agency/cashier/show_expense.html.twig', [
            'page'      => 'cashier',
            'operation' => $details,
        ]);
    } //showExpense

    #[Route('/admin/agency/cash-register/dashboard/global', name: 'admin_agency_cashier_dashboard_global', methods: ['GET'])]
    public function global(Request $request): Response
    {
        $actor = $this->actor();
        if (null !== $actor->getBranch()) {
            return $this->redirectToRoute('admin_agency_cashier_dashboard_branch');
        }

        $agency = $actor->getAgency();
        $filters = [
            'branchId' => $request->query->getInt('filter_branch') ?: null,
            'status'   => trim((string) $request->query->get('filter_status', '')) ?: null,
            'query'    => trim((string) $request->query->get('search_query', '')) ?: null,
        ];

        return $this->render('admin/agency/cashier/global.html.twig', [
            'page'    => 'cashier',
            'filters' => $filters,
        ] + $this->financeService->getSessionsMonitoring($agency, $filters));
    } //global


    #[Route('/admin/agency/cash-register/dashboard/branch', name: 'admin_agency_cashier_dashboard_branch', methods: ['GET'])]
    public function branch(): Response
    {
        $actor  = $this->actor();
        $agency = $actor->getAgency();
        $filters = [];
        if (null !== $actor->getBranch()) {
            $filters['branchId'] = $actor->getBranch()->getId();
        }

        return $this->render('admin/agency/cashier/branch.html.twig', [
            'page' => 'cashier',
        ] + $this->financeService->getSessionsMonitoring($agency, $filters));
    }

    #[Route('/admin/agency/cash-register/session/closing', name: 'admin_agency_cashier_session_closing', methods: ['GET', 'POST'])]
    public function sessionClosing(Request $request): Response
    {
        $actor   = $this->actor();
        $session = $this->cashierService->findCurrentSessionForCashier($actor);

        if (!$session instanceof CashSession || CashSessionStatus::Opened !== $session->getStatus()) {
            return $this->redirectToRoute('admin_agency_cashier_index');
        }

        $balances = $this->cashierService->computeSessionTheoreticalBalances($session);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('close_session_' . $session->getId(), (string) $request->request->get('_token'))) {
                throw new BadRequestHttpException('Jeton CSRF invalide.');
            }

            try {
                $closeData = new CashSessionCloseData();
                $closeData->physicalBalances = (array) $request->request->all('physical_balances');
                $closeData->closingObservation = trim((string) $request->request->get('closing_note', '')) ?: null;

                $this->cashierService->closeSession($session, $closeData, $actor);
                $this->addFlash('success', 'Session de caisse clôturée et verrouillée. En attente d\'arbitrage du gérant.');

                return $this->redirectToRoute('admin_agency_cashier_awaiting_init');
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('admin/agency/cashier/session_close.html.twig', [
            'page'        => 'cashier',
            'session'     => $session,
            'sessionCode' => sprintf('CS-%s-%04d', $session->getOpenedAt()?->format('Y') ?? date('Y'), (int) $session->getId()),
            'balances'    => $balances,
        ]);
    }

    #[Route('/admin/agency/cash-register/session/{id}/closing/validation', name: 'admin_agency_cashier_session_closing_validation', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function sessionClosingValidation(int $id, Request $request): Response
    {
        $actor   = $this->actor();
        $session = $this->em->getRepository(CashSession::class)->find($id);
        if (!$session instanceof CashSession || $session->getCashRegister()?->getAgency()?->getId() !== $actor->getAgency()?->getId()) {
            throw $this->createNotFoundException('Session de caisse introuvable.');
        }

        if (null !== $actor->getBranch() && $session->getCashRegister()?->getBranch()?->getId() !== $actor->getBranch()->getId()) {
            throw $this->createAccessDeniedException('Vous ne pouvez arbitrer que les sessions de votre succursale.');
        }


        $validateData = new CashSessionValidateData();
        $form = $this->createForm(CashSessionValidateType::class, $validateData);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->cashierService->validateClosedSession($session, $validateData, $actor);
                $this->addFlash('success', 'Arbitrage signé et session définitivement validée.');
                return $this->redirectToRoute('admin_agency_cashier_dashboard_branch');
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }
        return $this->render('admin/agency/cashier/session_closing_validation.html.twig', [
            'page'        => 'cashier',
            'session'     => $session,
            'sessionCode' => sprintf('CS-%s-%04d', $session->getOpenedAt()?->format('Y') ?? date('Y'), (int) $session->getId()),
            'form'        => $form->createView(),
        ]);
    }

    #[Route('/admin/agency/cash-register/session/initialization', name: 'admin_agency_cashier_session_init', methods: ['GET', 'POST'])]
    public function sessionInit(Request $request): Response
    {
        $actor  = $this->actor();
        $agency = $actor->getAgency();
        $branch = $actor->getBranch();

        $lockedRegister = null;
        $registerId = $request->query->getInt('registerId');
        if ($registerId > 0) {
            $candidate = $this->em->getRepository(\App\Entity\CashRegister::class)->find($registerId);
            if (
                $candidate instanceof \App\Entity\CashRegister
                && $candidate->getAgency()?->getId() === $agency->getId()
                && (null === $branch || $candidate->getBranch()?->getId() === $branch->getId())
            ) {
                $lockedRegister = $candidate;
            }
        }

        $initData = new CashSessionInitData();
        if (null !== $lockedRegister) {
            $initData->cashRegister = $lockedRegister;
        }

        $form = $this->createForm(CashSessionInitType::class, $initData, [
            'agency'          => $agency,
            'branch'          => $branch,
            'locked_register' => $lockedRegister,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                if (null !== $lockedRegister) {
                    $initData->cashRegister = $lockedRegister;
                }
                $initData->amounts = (array) $request->request->all('amounts');
                $session = $this->cashierService->initSession($initData, $actor);

                if ($session->getOpenedBy()?->getId() === $actor->getId()) {
                    $this->addFlash('success', 'Votre session de caisse est ouverte. Vous pouvez encaisser immédiatement.');

                    return $this->redirectToRoute('admin_agency_cashier_dashboard');
                }

                $this->addFlash('success', 'Dotation matinale allouée. Le caissier peut maintenant ouvrir sa session.');

                return $this->redirectToRoute(
                    null === $branch ? 'admin_agency_cashier_dashboard_global' : 'admin_agency_cashier_dashboard_branch'
                );
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('admin/agency/cashier/session_init.html.twig', [
            'page'              => 'cashier',
            'actor'             => $actor,
            'form'              => $form->createView(),
            'active_currencies' => $this->cashierService->getAgencyActiveCurrencies($agency),
        ]);
    }

    #[Route('/admin/agency/cash-register/{id}/toggle-status', name: 'admin_agency_cashier_register_toggle', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function toggleRegister(int $id): Response
    {
        if (!$this->isGranted('ROLE_AGENCY_ADMIN') && !$this->isGranted('ROLE_GENERAL_MANAGER')) {
            throw $this->createAccessDeniedException('Seule la direction générale peut activer ou désactiver un guichet.');
        }

        $actor = $this->actor();
        $register = $this->em->getRepository(\App\Entity\CashRegister::class)->find($id);
        if (!$register instanceof \App\Entity\CashRegister || $register->getAgency()?->getId() !== $actor->getAgency()?->getId()) {
            throw $this->createNotFoundException('Guichet introuvable.');
        }

        try {
            $this->cashierService->toggleRegisterStatus($register, $actor);
            $this->addFlash('success', sprintf('Statut du guichet [%s] mis à jour (%s).', $register->getCode(), $register->getStatus()->value));
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_agency_cashier_dashboard_global');
    }
}
