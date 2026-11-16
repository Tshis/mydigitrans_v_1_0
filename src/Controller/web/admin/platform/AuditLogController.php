<?php

declare(strict_types=1);

namespace App\Controller\web\admin\platform;

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
        return Scope::Platform;
    }

    #[Route('/admin/platform/audit-logs', name: 'admin_platform_audit_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('role.view');

        return $this->render('admin/platform/audit/index.html.twig', [
            'page'      => 'audit',
            'logs_list' => $this->auditLogger->listPlatformLogs(250),
        ]);
    }
}
