<?php

declare(strict_types=1);

namespace App\Controller\web\admin\agency;

use App\Controller\web\admin\AbstractAdminController;
use App\Dto\admin\agency\ExpenseCreateData;
use App\Entity\FundTransfer;
use App\Enum\Scope;
use App\Form\agency\ExpenseCreateType;
use App\Service\AgencyFinanceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class FinanceController extends AbstractAdminController
{
    public function __construct(
        private readonly AgencyFinanceService $financeService,
    ) {}

    protected function scope(): Scope
    {
        return Scope::Agency;
    }

    #[Route('/admin/agency/finances', name: 'admin_agency_finance_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted('finance.view');
        $actor  = $this->actor();
        $agency = $actor->getAgency();

        $filters = [
            'date'     => trim((string) $request->query->get('filter_date', '')) ?: null,
            'branchId' => $actor->getBranch()?->getId() ?? ($request->query->getInt('filter_branch') ?: null),
            'type'     => trim((string) $request->query->get('filter_type', '')) ?: null,
        ];

        return $this->render('admin/agency/finance/index.html.twig', [
            'page'     => 'finances',
            'filters'  => $filters,
            'treasury' => $this->financeService->getTreasurySummary($agency, $filters['branchId']),
        ] + $this->financeService->getFinanceOverview($agency, $filters));
    }

    #[Route('/admin/agency/finance/show/{id}/{type}', name: 'admin_agency_finance_show', requirements: ['id' => '\d+', 'type' => 'payment|expense'], methods: ['GET'])]
    public function show(int $id, string $type): Response
    {
        $this->denyAccessUnlessGranted('finance.view');
        $details = $this->financeService->getOperationAuditDetails($this->actor()->getAgency(), $id, $type);
        if (null === $details) {
            throw $this->createNotFoundException('Opération financière introuvable dans votre agence.');
        }

        return $this->render('admin/agency/finance/show.html.twig', [
            'page'      => 'finances',
            'type'      => $type,
            'operation' => $details,
        ]);
    }

    #[Route('/admin/agency/finance/expense/add', name: 'admin_agency_finance_expense_add', methods: ['GET', 'POST'])]
    public function expenseAdd(Request $request): Response
    {
        $this->denyAccessUnlessGranted('expense.write');
        $actor  = $this->actor();
        $agency = $actor->getAgency();
        $restrictedBranch = $actor->getBranch();

        $expenseData = new ExpenseCreateData();
        if (null !== $restrictedBranch) {
            $expenseData->branch = $restrictedBranch;
        }

        $form = $this->createForm(ExpenseCreateType::class, $expenseData, [
            'agency'            => $agency,
            'restricted_branch' => $restrictedBranch,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $expense = $this->financeService->createExpense($expenseData, $actor);
                $this->addFlash('success', sprintf(
                    'Dépense [%s] (%s %s) enregistrée avec succès.',
                    $expense->getName(),
                    number_format((float) $expense->getAmount(), 2, ',', ' '),
                    $expense->getCurrency()?->getCode()
                ));

                return $this->redirectToRoute('admin_agency_finance_index');
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('admin/agency/finance/expense_add.html.twig', [
            'page'               => 'finances',
            'is_network_manager' => null === $restrictedBranch,
            'form'               => $form->createView(),
        ]);
    }

    #[Route('/admin/agency/finance/cashier/journal/{code}', name: 'admin_agency_finance_journal', requirements: ['code' => '\d+'], methods: ['GET'])]
    public function journalCaissier(int $code): Response
    {
        $this->denyAccessUnlessGranted('finance.view');
        $journal = $this->financeService->getSessionJournalDetails($this->actor()->getAgency(), $code);
        if (null === $journal) {
            throw $this->createNotFoundException('Session de caisse introuvable.');
        }

        return $this->render('admin/agency/finance/journal_caissier.html.twig', [
            'page' => 'finances',
        ] + $journal);
    }

    #[Route('/admin/agency/finance/transfers', name: 'admin_agency_finance_transfers', methods: ['GET', 'POST'])]
    public function transfers(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('finance.view');
        $actor            = $this->actor();
        $agency           = $actor->getAgency();
        $restrictedBranch = $actor->getBranch();
        $branchId         = $restrictedBranch?->getId();

        $headquarters = $em->getRepository(\App\Entity\Branch::class)->findOneBy([
            'agency' => $agency,
            'code'   => \App\Service\AgencyRegistrationService::HEADQUARTERS_CODE,
        ]);

        $initialData = [
            'destinationType'   => \App\Enum\FundTransferDestinationType::Bank,
            'branchDestination' => (null !== $headquarters && $headquarters->getId() !== $restrictedBranch?->getId()) ? $headquarters : null,
        ];
        if (null === $restrictedBranch && null !== $headquarters) {
            $initialData['branchSource'] = $headquarters;
        }

        $form = $this->createForm(\App\Form\agency\FundTransferCreateType::class, $initialData, [
            'agency'            => $agency,
            'restricted_branch' => $restrictedBranch,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->denyAccessUnlessGranted('expense.write');
            $data = $form->getData();

            try {
                $transfer = $this->financeService->createFundTransfer([
                    'branchSourceId'      => $restrictedBranch?->getId() ?? $data['branchSource']?->getId(),
                    'destinationType'     => $data['destinationType']->value,
                    'branchDestinationId' => $data['branchDestination']?->getId(),
                    'bankName'            => $data['bankName'] ?? '',
                    'bankReference'       => $data['bankReference'] ?? '',
                    'amount'              => (float) ($data['amount'] ?? 0),
                    'currencyId'          => (int) $data['currency']?->getId(),
                ], $actor);

                $this->addFlash('success', sprintf('Transfert de fonds [%s] enregistré (en attente de réception).', $transfer->getReference()));

                return $this->redirectToRoute('admin_agency_finance_transfers');
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('admin/agency/finance/transfers.html.twig', [
            'page'      => 'finances',
            'treasury'  => $this->financeService->getTreasurySummary($agency, $branchId),
            'transfers' => $this->financeService->listFundTransfers($agency, $branchId),
            'form'      => $form->createView(),
        ]);
    }


    #[Route('/admin/agency/finance/transfers/{id}/receive', name: 'admin_agency_finance_transfer_receive', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function receiveTransfer(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('finance.view');
        if (!$this->isCsrfTokenValid('receive_transfer_' . $id, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Jeton CSRF invalide.');
        }

        $actor    = $this->actor();
        $transfer = $em->getRepository(FundTransfer::class)->find($id);
        if (!$transfer instanceof FundTransfer || $transfer->getAgency()?->getId() !== $actor->getAgency()?->getId()) {
            throw $this->createNotFoundException('Transfert introuvable.');
        }

        try {
            $this->financeService->confirmFundTransferReceipt($transfer, $actor);
            $this->addFlash('success', sprintf('Réception des fonds [%s] confirmée.', $transfer->getReference()));
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_agency_finance_transfers');
    }
}
