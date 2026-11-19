<?php

declare(strict_types=1);

namespace App\Controller\web\admin\agency;

use App\Controller\web\admin\AbstractAdminController;
use App\Dto\admin\agency\ExpenseCreateData;
use App\Enum\Scope;
use App\Form\agency\ExpenseCreateType;
use App\Service\AgencyFinanceService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        $actor  = $this->actor();
        $agency = $actor->getAgency();

        $filters = [
            'date'     => trim((string) $request->query->get('filter_date', '')) ?: null,
            'branchId' => $actor->getBranch()?->getId() ?? ($request->query->getInt('filter_branch') ?: null),
            'type'     => trim((string) $request->query->get('filter_type', '')) ?: null,
        ];

        return $this->render('admin/agency/finance/index.html.twig', [
            'page'    => 'finances',
            'filters' => $filters,
        ] + $this->financeService->getFinanceOverview($agency, $filters));
    }

    #[Route('/admin/agency/finance/show/{id}/{type}', name: 'admin_agency_finance_show', requirements: ['id' => '\d+', 'type' => 'payment|expense'], methods: ['GET'])]
    public function show(int $id, string $type): Response
    {
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
        $journal = $this->financeService->getSessionJournalDetails($this->actor()->getAgency(), $code);
        if (null === $journal) {
            throw $this->createNotFoundException('Session de caisse introuvable.');
        }

        return $this->render('admin/agency/cashier/journal_caissier.html.twig', [
            'page' => 'finances',
        ] + $journal);
    }
}
