<?php
// src/Controller/Admin/Platform/AgencyController.php

namespace App\Controller\web\admin\platform;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AgencyController extends AbstractController
{
    /**
     * INDEX : LISTE DES AGENCES ET ETAT DES LICENCES SAAS [MCD 16]
     */
    #[Route('/admin/platform/agencies', name: 'admin_platform_agency_index')]
    public function index(): Response
    {
        // Simulation des agences de transport partenaires (Multi-pays / Multi-devises)
        $agenciesList = [
            [
                'id' => 1,
                'code' => 'agc-006',
                'name' => 'TransKin Express',
                'country' => 'RD Congo',
                'base_currency' => 'CDF',
                'plan_name' => 'Premium Multitenant',
                'expired_at' => new \DateTime('2027-01-15'),
                'status' => 'Actif'
            ],
            [
                'id' => 2,
                'code' => 'agc-006',
                'name' => 'Océan du Gabon',
                'country' => 'Gabon',
                'base_currency' => 'XAF',
                'plan_name' => 'Premium Multitenant',
                'expired_at' => new \DateTime('2026-12-30'),
                'status' => 'Actif'
            ],
            [
                'id' => 3,
                'code' => 'agc-006',
                'name' => 'TransFleuve',
                'country' => 'Congo-Brazzaville',
                'base_currency' => 'XAF',
                'plan_name' => 'Basique Standard',
                'expired_at' => new \DateTime('2026-09-01'),
                'status' => 'Expiré' // Bloqué par automatisme
            ],
            [
                'id' => 4,
                'code' => 'agc-006',
                'name' => 'Kivu Horizons',
                'country' => 'RD Congo',
                'base_currency' => 'USD',
                'plan_name' => 'Premium Multitenant',
                'expired_at' => new \DateTime('2026-10-15'),
                'status' => 'Suspendu' // Gelé manuellement
            ]
        ];

        return $this->render('admin/platform/agency/index.html.twig', [
            'page' => 'agency',
            'companies_list' => $agenciesList // Conserve la variable Twig active pour ton tableau
        ]);
    } //index



    /**
     * AJOUT : FORMULAIRE DE CRÉATION D'UNE COMPAGNIE PARTENAIRE
     */
    #[Route('/admin/platform/agency/add', name: 'admin_platform_agency_add')]
    public function add(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $name = $request->request->get('name');
            $country = $request->request->get('country');
            $baseCurrency = $request->request->get('base_currency');
            $planName = $request->request->get('plan_name');
            $expiredAtInput = $request->request->get('expired_at');
            $expiredAt = new \DateTimeImmutable($expiredAtInput);

            // EN INTÉGRATION DOCTRINE FINALE :
            // 1. Insertion de l'entité globale Company/Agency
            // 2. Initialisation de sa ligne "16. CurrencyAgency" avec isBase = true et code = $baseCurrency
            // 3. Initialisation de sa ligne d'abonnement / échéance contractuelle

            $this->addFlash('success', sprintf('L\'agence [%s] a été initialisée et déployée avec succès en zone %s.', $name, $country));
            return $this->redirectToRoute('admin_platform_agency_index');
        }

        return $this->render('admin/platform/agency/add.html.twig', [
            'page' => 'agency'
        ]);
    } //add


    /**
     * MODIFICATION : FICHE COMPLÈTE ET STATUTS CONTRACTUELS DE L'AGENCE
     */
    #[Route('/admin/platform/agencies/{code}/edit', name: 'admin_platform_agency_edit')]
    public function edit(string $code, Request $request): Response
    {
        // Extraction de la ligne de l'agence sélectionnée (Simulation de ton Entité Agency)
        $agency = [
            'id' => 1,
            'code' => 'AGE-004',
            'name' => 'TransKin Express',
            'email' => 'direction@transkin.com',
            'phone' => '+243 812 345 678',
            'address' => 'Boulevard Lumumba, Limete, Kinshasa',
            'registrationNumber' => 'RCCM-KIN-2024-B452',
            'taxNumber' => 'ID-NAT-01-442-N85',
            'plan_name' => 'Premium Multitenant',
            'expired_at' => new \DateTime('2027-01-15'),
            'isVerified' => true,
            'isActive' => true
        ];

        if ($request->isMethod('POST')) {
            // Interception des variables postées [MCD]
            $name = $request->request->get('name');
            $isVerified = $request->request->has('is_verified'); // Capture 1 ou 0
            $isActive = $request->request->has('is_active');

            // DOCTRINE FINALE :
            // $agencyEntity = $repository->find($id);
            // $agencyEntity->setName($name);
            // $agencyEntity->setIsVerified($isVerified);
            // $agencyEntity->setIsActive($isActive);
            // $entityManager->flush();

            $this->addFlash('success', sprintf('La fiche de l\'agence [%s] a été mise à jour de manière sécurisée.', $name));
            return $this->redirectToRoute('admin_platform_agency_index');
        }

        return $this->render('admin/platform/agency/edit.html.twig', [
            'page' => 'agency',
            'agency' => $agency
        ]);
    } //edit

    /**
     * SHOW : PROFIL DÉTAILLÉ DE L'AGENCE ET DE SES BRANCHES OPERATIONNELLES [MCD 16]
     */
    #[Route('/admin/platform/agencies/{code}', name: 'admin_platform_agency_show')]
    public function show(string $code): Response
    {
        // Extraction de la compagnie d'agence (Simulation Doctrine)
        $agency = [
            'id' => 4,
            'code' => 'AGE-004',
            'name' => 'TransKin Express',
            'email' => 'direction@transkin.com',
            'phone' => '+243 812 345 678',
            'address' => 'Boulevard Lumumba, Limete, Kinshasa',
            'registrationNumber' => 'RCCM-KIN-2024-B452',
            'taxNumber' => 'ID-NAT-01-442-N85',
            'plan_name' => 'Premium Multitenant',
            'expired_at' => new \DateTime('2027-01-15'),
            'base_currency' => 'CDF', // Devise de base lue [MCD 16]
            'isVerified' => true,
            'isActive' => true
        ];

        // Hydratation de ses succursales / gares déployées (Branches)
        $branches = [
            ['id' => 12, 'name' => 'Victoire Centre', 'city' => 'Kinshasa', 'created_at' => new \DateTime('2024-04-10')],
            ['id' => 15, 'name' => 'Matadi Ville', 'city' => 'Matadi (Kongo Central)', 'created_at' => new \DateTime('2024-06-18')],
            ['id' => 19, 'name' => 'Boma Port', 'city' => 'Boma', 'created_at' => new \DateTime('2025-02-22')],
        ];

        return $this->render('admin/platform/agency/show.html.twig', [
            'page' => 'agency',
            'agency' => $agency,
            'branches' => $branches
        ]);
    } //show
}
