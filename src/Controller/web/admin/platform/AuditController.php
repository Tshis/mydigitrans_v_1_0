<?php
// src/Controller/Admin/Platform/PlatformAuditController.php

namespace App\Controller\web\admin\platform;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AuditController extends AbstractController
{
    /**
     * 50. INDEX : TOUR DE CONTRÔLE ET REGISTRE D'SURVEILLANCE DES ATTAQUES ET LOGS APPLICATIFS [MCD 50]
     */
    #[Route('/admin/platform/security-logs', name: 'admin_platform_audit_index', methods: ['GET'])]
    public function index(): Response
    {
        // Hydratation exhaustive conforme à l'entité "50. AuditLog" de ton MCD
        $logsList = [
            [
                'id' => 9001,
                'user_name' => 'Daniel Lukonu',
                'action' => 'confirm_payment',
                'entityName' => 'reservation',
                'entityId' => 8412,
                'oldValues' => ['status' => 'pending'],
                'newValue' => ['status' => 'success'],
                'ipAddress' => '197.242.144.52',
                'device_type' => 'POS', // Guichet de gare RDC
                'user_agent' => 'Mozilla/5.0 (Android; POS-Terminal V2)',
                'createdAt' => new \DateTime('now')
            ],
            [
                'id' => 9002,
                'user_name' => 'Mireille K.',
                'action' => 'cancel',
                'entityName' => 'Invoice',
                'entityId' => 92,
                'oldValues' => ['status' => 'issued', 'total' => 250.00],
                'newValue' => ['status' => 'cancelled'],
                'ipAddress' => '197.242.144.10',
                'device_type' => 'Desktop',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/126.0.0',
                'createdAt' => new \DateTime('-1 hour')
            ],
            [
                'id' => 9003,
                'user_name' => 'Agent Moov',
                'action' => 'update',
                'entityName' => 'subscriptionPlan',
                'entityId' => 2,
                'oldValues' => ['price' => 200.00],
                'newValue' => ['price' => 250.00],
                'ipAddress' => '105.235.224.81',
                'device_type' => 'Desktop',
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
                'createdAt' => new \DateTime('-2 days')
            ]
        ];

        return $this->render('admin/platform/audit/index.html.twig', [
            'page' => 'audit',
            'logs_list' => $logsList
        ]);
    }
}
