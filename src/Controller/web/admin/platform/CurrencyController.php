<?php
// src/Controller/Admin/Platform/PlatformCurrencyController.php

namespace App\Controller\web\admin\platform;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CurrencyController extends AbstractController
{


    /**
     * 15. INDEX : LISTER L'ENSEMBLE DES MONNAIES DISPONIBLES EN BASE DE DONNÉES
     */
    #[Route('/admin/platform/currencies', name: 'admin_platform_currency_index', methods: ['GET'])]
    public function index(): Response
    {
        // Simulation du dictionnaire système global [MCD 15]
        $currenciesList = [
            ['code' => 'USD', 'name' => 'Dollar Américain', 'symbol' => '$', 'isActive' => true],
            ['code' => 'CDF', 'name' => 'Franc Congolais (RDC)', 'symbol' => 'FC', 'isActive' => true],
            ['code' => 'XAF', 'name' => 'Franc CFA (BEAC) - Zone CEMAC', 'symbol' => 'FCFA', 'isActive' => true],
            ['code' => 'XOF', 'name' => 'Franc CFA (BCEAO) - Zone UEMOA', 'symbol' => 'FCFA', 'isActive' => false],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'isActive' => false]
        ];

        return $this->render('admin/platform/currency/index.html.twig', [
            'page' => 'currency',
            'currencies_list' => $currenciesList
        ]);
    }//index

    /**
     * 15. TOGGLE STATUS : COMMUTER L'ACTIVATION GLOBALE DE LA MONNAIE
     */
    #[Route('/admin/platform/currencies/{code}/toggle', name: 'admin_platform_currency_toggle', methods: ['POST'])]
    public function toggle(string $code): Response
    {
        // LOGIQUE ORM DE RECUL :
        // $currency = $em->getRepository(Currency::class)->findOneBy(['code' => $code]);
        // $currency->setIsActive(!$currency->isIsActive()); $em->flush();

        $this->addFlash('success', sprintf('Le statut d\'utilisation usine de la devise %s a été modifié.', $code));
        return $this->redirectToRoute('admin_platform_currency_index');
    }//toggle


    /**
     * 15. ADD CURRENCY : CRÉATION D'UNE MONNAIE APPLICATIVE D'USINE [MCD 15]
     */
    #[Route('/admin/platform/currencies/add', name: 'admin_platform_currency_add', methods: ['GET', 'POST'])]
    public function add(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $code = strtoupper($request->request->get('code'));

            // EN INTÉGRATION DOCTRINE FINALE :
            // $currency = new Currency();
            // $currency->setCode($code);
            // $currency->setName($request->request->get('name'));
            // $currency->setSymbol($request->request->get('symbol'));
            // $currency->setIsActive($request->request->has('isActive'));
            // $em->persist($currency); $em->flush();

            $this->addFlash('success', sprintf('La devise [%s] a été implantée avec succès dans le catalogue mondial MyDigitrans.', $code));
            return $this->redirectToRoute('admin_platform_dashboard');
        }

        return $this->render('admin/platform/currency/form.html.twig', [
            'page' => 'currency',
            'is_edit' => false
        ]);
    }//add

    /**
     * 15. EDIT CURRENCY : AJUSTEMENT D'UNE MONNAIE SYSTEME
     */
    #[Route('/admin/platform/currencies/{code}/edit', name: 'admin_platform_currency_edit', methods: ['GET', 'POST'])]
    public function edit(string $code, Request $request): Response
    {
        // Extraction de l'entité (Simulation BDD)
        $currency = [
            'code' => $code,
            'name' => 'Franc CFA (BEAC)',
            'symbol' => 'XAF',
            'isActive' => true
        ];

        if ($request->isMethod('POST')) {
            $this->addFlash('success', sprintf('Les spécifications de la devise %s ont été mises à jour.', $code));
            return $this->redirectToRoute('admin_platform_dashboard');
        }

        return $this->render('admin/platform/currency/form.html.twig', [
            'page' => 'currency',
            'is_edit' => true,
            'currency' => $currency
        ]);
    } //edit
}
