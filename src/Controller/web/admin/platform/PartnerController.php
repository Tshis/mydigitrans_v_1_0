<?php

namespace App\Controller\web\admin\platform;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PartnerController extends AbstractController
{

    /**
     * REGISTRE : INDEXATION DU RÉSEAU DES APPORTEURS D'AFFAIRES ET SPONSORS MLM
     */
    #[Route('/admin/platform/partners', name: 'admin_platform_partner_index', methods: ['GET'])]
    public function index(): Response
    {
        // Hydratation stricte selon tes champs d'auto-relation parent_id et token public
        $partnersList = [
            [
                'id' => 10,
                'name' => 'Dieudonné Ilunga',
                'email' => 'dieudonne@mydigitrans.com',
                'referral_token' => 'MOMBONGO85',
                'parent_name' => 'Daniel Lukonu', // Sponsor parent_id
                'children_count' => 3,
                'total_gains' => 142.50,
                'createdAt' => new \DateTime('2025-01-15'),
                'isActive' => true
            ],
            [
                'id' => 11,
                'name' => 'Blaise Mvumbi',
                'email' => 'blaise@mydigitrans.com',
                'referral_token' => 'REF-BLAISE9',
                'parent_name' => 'Dieudonné Ilunga', // Niveau 2 !
                'children_count' => 1,
                'total_gains' => 38.00,
                'createdAt' => new \DateTime('2025-03-22'),
                'isActive' => true
            ],
            [
                'id' => 12,
                'name' => 'Gabriel Nsimba',
                'email' => 'gabriel@mydigitrans.com',
                'referral_token' => 'GAB-TRANS-26',
                'parent_name' => null, // Racine
                'children_count' => 0,
                'total_gains' => 0.00,
                'createdAt' => new \DateTime('2026-06-05'),
                'isActive' => false
            ]
        ];

        return $this->render('admin/platform/partner/index.html.twig', [
            'page' => 'partner',
            'partners_list' => $partnersList
        ]);
    } //index
}
