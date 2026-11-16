<?php

declare(strict_types=1);

namespace App\Controller\web\admin\agency;

use App\Controller\web\admin\AbstractAdminController;
use App\Enum\Scope;
use App\Service\AuditLogger;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AuditLogController extends AbstractAdminController
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    protected function scope(): Scope
    {
        return Scope::Agency;
    }

    #[Route('/admin/agency/audit-logs', name: 'admin_agency_audit_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('role.view');
        $agency = $this->actor()->getAgency();

        return $this->render('admin/agency/audit/index.html.twig', [
            'page'      => 'audit',
            'logs_list' => $this->auditLogger->listAgencyLogs($agency, 250),
        ]);
    }
}
