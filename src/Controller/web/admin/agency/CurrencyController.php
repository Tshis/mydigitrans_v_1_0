<?php

namespace App\Controller\web\admin\agency;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CurrencyController extends AbstractController
{


    /**
     * INDEX : VUE FINANCIÈRE CENTRALE DE L'AGENCE
     */
    #[Route('/admin/agency/currency/currencies', name: 'admin_agency_currency_index')]
    public function index(Request $request): Response
    {
        $session = $request->getSession();

        // 1. Représentation de l'entité "16. CurrencyAgency" pour extraire la Devise de Base (isBase = true)
        // Exemple : l'agence travaille principalement en Franc CFA (XAF)
        $agencyBaseCurrency = [
            'code' => 'XAF',
            'name' => 'Franc CFA (BEAC)',
            'symbol' => 'FCFA'
        ];

        // 2. Représentation des devises activées pour cette agence via CurrencyAgency (isBase = false)
        // Croisé avec le dernier taux extrait de l'entité "16. ExchangeRate" (rate)
        $activeCurrenciesList = [
            [
                'code' => 'XAF',
                'symbol' => 'FCFA',
                'name' => 'Franc CFA (BEAC)',
                'current_rate' => 1.00,
                'isBase' => true
            ],
            [
                'code' => 'USD',
                'symbol' => '$',
                'name' => 'Dollar Américain',
                'current_rate' => 615.00,
                'isBase' => false // 1 USD = 615 XAF
            ],
            [
                'code' => 'EUR',
                'symbol' => '€',
                'name' => 'Euro',
                'current_rate' => 655.95,
                'isBase' => false // 1 EUR = 655.95 XAF
            ]
        ];

        $rateHistory = [
            ['effectiveFrom' => new \DateTime('2026-09-19 06:30:00'), 'targetCurrency' => 'USD', 'rate' => 615, 'createdBy' => 'Daniel Lukonu'],
            ['effectiveFrom' => new \DateTime('2026-09-18 06:15:00'), 'targetCurrency' => 'USD', 'rate' => 612, 'createdBy' => 'Daniel Lukonu'],
            ['effectiveFrom' => new \DateTime('2026-09-17 06:45:00'), 'targetCurrency' => 'USD', 'rate' => 615, 'createdBy' => 'Kalonji Beya'],
        ];

        return $this->render('admin/agency/currency/index.html.twig', [
            'page' => 'currency',
            'agency_config' => ['baseCurrency' => $agencyBaseCurrency['code']],
            'base_currency_details' => $baseCurrencyDetails ?? ['name' => $agencyBaseCurrency['name'], 'symbol' => $agencyBaseCurrency['symbol']],
            'active_currencies_list' => $activeCurrenciesList,
            'rate_history' => $rateHistory

        ]);
    } //index


    /**
     * ACTION A : INTERFACE DE CONFIGURATION DES DEVISES DE L'AGENCE (CurrencyAgency)
     */
    #[Route('/admin/agency/currency/configure-devises', name: 'admin_agency_currency_configure_base', methods: ['GET', 'POST'])]
    public function configureBase(Request $request): Response
    {
        $session = $request->getSession();

        if ($request->isMethod('POST')) {
            $baseCurrencyCode = $request->request->get('base_currency');
            $acceptedCurrencies = $request->request->all('accepted_currencies'); // Récupère le tableau des cases cochées

            // EN ORM / DOCTRINE :
            // 1. Supprimer ou passer à isBase = false les anciennes lignes CurrencyAgency de cette agence
            // 2. Insérer/Mettre à jour la ligne avec isBase = true pour le $baseCurrencyCode
            // 3. Insérer les lignes CurrencyAgency pour les autres devises cochées

            $this->addFlash('success', 'Le portefeuille d\'acceptation (CurrencyAgency) a été reconfiguré.');
            return $this->redirectToRoute('admin_agency_currency_index');
        }

        // Représentation de l'entité globale système "15. Currency" (Créées uniquement par le SuperAdmin)
        $systemCurrencies = [
            ['code' => 'XAF', 'name' => 'Franc CFA (BEAC)'],
            ['code' => 'CDF', 'name' => 'Franc Congolais'],
            ['code' => 'USD', 'name' => 'Dollar Américain'],
            ['code' => 'EUR', 'name' => 'Euro']
        ];

        // Configuration active de l'agence lue depuis CurrencyAgency
        $agencyConfig = [
            'baseCurrency' => 'XAF',
            'acceptedCurrencies' => ['XAF', 'USD', 'EUR']
        ];

        return $this->render('admin/agency/currency/currency_config.html.twig', [
            'page' => 'currency',
            'agency_config' => $agencyConfig,
            'system_currencies' => $systemCurrencies
        ]);
    } //configureBase

    /**
     * ACTION B : SAISIE QUOTIDIENNE DU TAUX DU MATIN (ExchangeRate)
     */
    #[Route('/admin/agency/currency/configure-taux', name: 'admin_agency_currency_configure_rate', methods: ['GET', 'POST'])]
    public function configureRate(Request $request): Response
    {
        $session = $request->getSession();

        // 1. Extraction de la devise de base de l'agence depuis CurrencyAgency
        $baseCurrencyCode = 'XAF';

        // 2. Extraction des devises acceptées au guichet via CurrencyAgency pour alimenter la grille dynamique
        $acceptedCurrencies = [
            ['code' => 'XAF', 'symbol' => 'FCFA', 'name' => 'Franc CFA (BEAC)'],
            ['code' => 'USD', 'symbol' => '$', 'name' => 'Dollar Américain'],
            ['code' => 'EUR', 'symbol' => '€', 'name' => 'Euro']
        ];

        if ($request->isMethod('POST')) {
            $effectiveFromInput = $request->request->get('effective_from');
            $effectiveFrom = new \DateTimeImmutable($effectiveFromInput);

            // Réception des tableaux d'inputs parallèles générés dynamiquement par Twig
            $targetCurrencyCodes = $request->request->all('target_currency_code');
            $rateValues = $request->request->all('rate_value');

            if (!empty($targetCurrencyCodes)) {
                foreach ($targetCurrencyCodes as $index => $targetCode) {
                    $rate = (float) ($rateValues[$index] ?? 1.0);

                    // EN INTEGRATION ORM / DOCTRINE FINALE :
                    // On persiste une entité 16. ExchangeRate pour CHAQUE devise secondaire
                    // $exchangeRate = new ExchangeRate();
                    // $exchangeRate->setBaseCurrencyId($baseCurrencyCode); // Ex: XAF
                    // $exchangeRate->setTargetCurrencyId($targetCode); // Ex: USD, puis EUR
                    // $exchangeRate->setRate($rate);
                    // $exchangeRate->setEffectiveFrom($effectiveFrom);
                    // $exchangeRate->setAgency($currentAgency);
                    // $entityManager->persist($exchangeRate);
                }
                // $entityManager->flush();
            }

            $this->addFlash('success', sprintf(
                'La grille des taux quotidiens a été programmée pour prendre effet le %s à %s.',
                $effectiveFrom->format('d/m/Y'),
                $effectiveFrom->format('H:i')
            ));

            return $this->redirectToRoute('admin_agency_currency_index');
        }

        return $this->render('admin/agency/currency/rate_config.html.twig', [
            'page' => 'currency',
            'agency_config' => [
                'baseCurrency' => $baseCurrencyCode,
                'acceptedCurrencies' => ['XAF', 'USD', 'EUR']
            ],
            'accepted_currencies' => $acceptedCurrencies
        ]);
    } //configureRate

}
