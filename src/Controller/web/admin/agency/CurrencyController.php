<?php

declare(strict_types=1);

namespace App\Controller\web\admin\agency;

use App\Controller\web\admin\AbstractAdminController;
use App\Entity\Currency;
use App\Entity\CurrencyAgency;
use App\Entity\ExchangeRate;
use App\Enum\Scope;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CurrencyController extends AbstractAdminController
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {}

    protected function scope(): Scope
    {
        return Scope::Agency;
    }

    /**
     * INDEX : VUE FINANCIÈRE CENTRALE DE L'AGENCE
     */
    #[Route('/admin/agency/currency/currencies', name: 'admin_agency_currency_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('currency.view');
        $actor = $this->actor();
        $agency = $actor->getAgency();

        // 1. Récupération de la monnaie de base de l'agence (ex: CDF)
        $baseCurrencyAgency = $this->em->getRepository(CurrencyAgency::class)->findOneBy([
            'agency' => $agency,
            'isBase' => true,
            'isActive' => true
        ]);

        $baseCurrencyObj = $baseCurrencyAgency ? $baseCurrencyAgency->getCurrency() : null;
        $baseCurrencyCode = $baseCurrencyObj ? $baseCurrencyObj->getCode() : null;
        $baseCurrencyName = $baseCurrencyObj ? $baseCurrencyObj->getName() : null;

        if (null === $baseCurrencyCode) {
            $baseCurrencyCode = 'CDF';
            $baseCurrencyName = 'Franc Congolais (Par défaut)';
        }

        // 2. Extraction des monnaies acceptées au guichet
        $agencyRelations = $this->em->getRepository(CurrencyAgency::class)->findBy([
            'agency' => $agency,
            'isActive' => true
        ]);

        $activeCurrenciesList = [];
        foreach ($agencyRelations as $rel) {
            $currency = $rel->getCurrency();
            if (!$currency) {
                continue;
            }
            $code = $currency->getCode();
            $lastRate = 1.00;

            // Si c'est une monnaie secondaire (ex: USD), on extrait le tout dernier taux saisi par l'agence
            if (!$rel->isBase() && $baseCurrencyObj !== null) {
                $latestActiveRate = $this->em->createQueryBuilder()
                    ->select('er.rate')
                    ->from(ExchangeRate::class, 'er')
                    ->andWhere('er.agency = :agency')
                    ->andWhere('er.baseCurrency = :base')
                    ->andWhere('er.targetCurrency = :target')
                    ->andWhere('er.effectiveFrom <= :now')
                    ->setParameter('agency', $agency)
                    ->setParameter('base', $baseCurrencyObj)
                    ->setParameter('target', $currency)
                    ->setParameter('now', new \DateTimeImmutable())
                    ->orderBy('er.effectiveFrom', 'DESC')
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();

                if ($latestActiveRate) {
                    $lastRate = (float) $latestActiveRate['rate'];
                }
            }

            $activeCurrenciesList[] = [
                'code' => $code,
                'name' => $currency->getName(),
                'current_rate' => $lastRate,
                'isBase' => $rel->isBase()
            ];
        }

        // 3. Extraction du journal historique réajusté
        $rateHistoryRaw = $this->em->createQueryBuilder()
            ->select('er', 'bc', 'tc')
            ->from(ExchangeRate::class, 'er')
            ->join('er.baseCurrency', 'bc')
            ->join('er.targetCurrency', 'tc')
            ->andWhere('er.agency = :agency')
            ->setParameter('agency', $agency)
            ->orderBy('er.effectiveFrom', 'DESC')
            ->addOrderBy('er.createdAt', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();

        // 🟢 LE FIX : Groupement par timestamp de planification temporelle
        $rateHistory = [];
        foreach ($rateHistoryRaw as $er) {
            if ($er instanceof ExchangeRate) {
                $timestamp = $er->getEffectiveFrom()->format('Y-m-d H:i:s');

                if (!isset($rateHistory[$timestamp])) {
                    $rateHistory[$timestamp] = [
                        'effectiveFrom' => $er->getEffectiveFrom(),
                        'rates' => [],
                    ];
                }

                $rateHistory[$timestamp]['rates'][] = [
                    'targetCurrency' => $er->getTargetCurrency()?->getCode(),
                    'baseCurrency' => $er->getBaseCurrency()?->getCode(),
                    'rate' => (float) $er->getRate(),
                ];
            }
        }

        return $this->render('admin/agency/currency/index.html.twig', [
            'page' => 'currency',
            'agency_config' => ['baseCurrency' => $baseCurrencyCode],
            'base_currency_details' => ['name' => $baseCurrencyName],
            'active_currencies_list' => $activeCurrenciesList,
            'rate_history' => array_values($rateHistory) // Transmet le tableau groupé
        ]);
    }

    /**
     * ACTION A : PERMET DE SELECTIONNER ET COMPOSER LE PORTEFEUILLE DEVISES (CurrencyAgency)
     */
    #[Route('/admin/agency/currency/configure-devises', name: 'admin_agency_currency_configure_base', methods: ['GET', 'POST'])]
    public function configureBase(Request $request): Response
    {
        $this->denyAccessUnlessGranted('currency.configure');
        $actor = $this->actor();
        $agency = $actor->getAgency();

        if ($request->isMethod('POST')) {
            $baseCurrencyCode = $request->request->get('base_currency');
            $submittedAcceptedCodes = $request->request->all('accepted_currencies') ?? [];

            if (!in_array($baseCurrencyCode, $submittedAcceptedCodes, true)) {
                $submittedAcceptedCodes[] = $baseCurrencyCode;
            }

            $this->em->createQueryBuilder()
                ->update(CurrencyAgency::class, 'ca')
                ->set('ca.isActive', ':inactive')
                ->set('ca.isBase', ':inactive')
                ->andWhere('ca.agency = :agency')
                ->setParameter('inactive', false)
                ->setParameter('agency', $agency)
                ->getQuery()
                ->execute();

            foreach ($submittedAcceptedCodes as $code) {
                $currencyObj = $this->em->getRepository(Currency::class)->findOneBy(['code' => $code]);
                if (!$currencyObj) {
                    continue;
                }

                $currencyAgency = $this->em->getRepository(CurrencyAgency::class)->findOneBy([
                    'agency' => $agency,
                    'currency' => $currencyObj
                ]);

                if (!$currencyAgency) {
                    $currencyAgency = new CurrencyAgency();
                    $currencyAgency->setAgency($agency);
                    $currencyAgency->setCurrency($currencyObj);
                }

                $currencyAgency->setIsActive(true);
                $currencyAgency->setIsBase($code === $baseCurrencyCode);
                $this->em->persist($currencyAgency);
            }

            $this->em->flush();
            $this->addFlash('success', 'La configuration de vos devises acceptées a été mise à jour.');

            return $this->redirectToRoute('admin_agency_currency_index');
        }

        $systemCurrencies = $this->em->getRepository(Currency::class)->findBy([], ['code' => 'ASC']);
        $currentConfigs = $this->em->getRepository(CurrencyAgency::class)->findBy([
            'agency' => $agency,
            'isActive' => true
        ]);

        $baseCurrencyCode = 'CDF';
        $acceptedCodes = [];
        foreach ($currentConfigs as $config) {
            $code = $config->getCurrency()->getCode();
            $acceptedCodes[] = $code;
            if ($config->isBase()) {
                $baseCurrencyCode = $code;
            }
        }

        return $this->render('admin/agency/currency/configure_base.html.twig', [
            'page' => 'currency',
            'agency_config' => [
                'baseCurrency' => $baseCurrencyCode,
                'acceptedCurrencies' => $acceptedCodes
            ],
            'system_currencies' => $systemCurrencies
        ]);
    }

    /**
     * ACTION B : CAPTURE ET ENREGISTREMENT DES TABLEAUX DE TAUX JOURNALIERS (ExchangeRate)
     */
    #[Route('/admin/agency/currency/configure-taux', name: 'admin_agency_currency_configure_rate', methods: ['GET', 'POST'])]
    public function configureRate(Request $request): Response
    {
        $this->denyAccessUnlessGranted('currency.configure');
        $actor = $this->actor();
        $agency = $actor->getAgency();

        // Récupération de la devise pivot de l'agence (ex: CDF)
        $baseCurrencyAgency = $this->em->getRepository(CurrencyAgency::class)->findOneBy([
            'agency' => $agency,
            'isBase' => true,
            'isActive' => true
        ]);

        $baseCurrencyObj = $baseCurrencyAgency ? $baseCurrencyAgency->getCurrency() : null;
        if (null === $baseCurrencyObj) {
            $this->addFlash('error', 'Veuillez d\'abord définir votre devise de base (Action A) avant de fixer des taux.');
            return $this->redirectToRoute('admin_agency_currency_configure_base');
        }

        // Devises acceptées pour alimenter le formulaire dynamique
        $agencyRelations = $this->em->getRepository(CurrencyAgency::class)->findBy([
            'agency' => $agency,
            'isActive' => true
        ]);

        $acceptedCurrencies = [];
        $acceptedCodes = [];
        foreach ($agencyRelations as $rel) {
            $currency = $rel->getCurrency();
            if (!$currency) {
                continue;
            }
            $acceptedCodes[] = $currency->getCode();
            $acceptedCurrencies[] = [
                'code' => $currency->getCode(),
                'name' => $currency->getName()
            ];
        }

        if ($request->isMethod('POST')) {

            $effectiveFromInput = $request->request->get('effective_from');
            $effectiveFrom = $effectiveFromInput ? new \DateTimeImmutable($effectiveFromInput) : new \DateTimeImmutable();
            // 🟢 CAPTURE DES TABLEAUX PARALLÈLES DE TON TEMPLATE
            $targetCurrencyCodes = $request->request->all('target_currency_code') ?? [];
            $rateValues = $request->request->all('rate_value') ?? [];
            if (!empty($targetCurrencyCodes)) {
                $this->em->wrapInTransaction(function () use ($targetCurrencyCodes, $rateValues, $baseCurrencyObj, $effectiveFrom, $agency) {
                    foreach ($targetCurrencyCodes as $index => $targetCode) {
                        $targetCurrencyObj = $this->em->getRepository(Currency::class)->findOneBy(['code' => $targetCode]);
                        if (!$targetCurrencyObj) {
                            continue;
                        }
                        // Extraction et nettoyage de la valeur reçue (ex: 2650.00 pour 1 USD)
                        $rawRate = str_replace([' ', ','], ['', '.'], (string)($rateValues[$index] ?? '1.0'));
                        $rateValue = (float) $rawRate;
                        $exchangeRate = new ExchangeRate();
                        $exchangeRate->setAgency($agency);
                        $exchangeRate->setBaseCurrency($baseCurrencyObj);     // Objet Pivot (ex: CDF)
                        $exchangeRate->setTargetCurrency($targetCurrencyObj); // Objet Cible (ex: USD)
                        $exchangeRate->setRate(sprintf('%.6f', $rateValue));  // Respect de la précision Décimale (scale 6)
                        $exchangeRate->setEffectiveFrom($effectiveFrom);
                        $this->em->persist($exchangeRate);
                    }
                });
            }
            $this->addFlash('success', sprintf('La nouvelle grille des taux a été enregistrée avec succès pour prendre effet le %s à %s.', $effectiveFrom->format('d/m/Y'), $effectiveFrom->format('H:i')));
            return $this->redirectToRoute('admin_agency_currency_index');
        }
        return $this->render('admin/agency/currency/rate_config.html.twig', [
            'page' => 'currency',
            'agency_config' => [
                'baseCurrency' => $baseCurrencyObj->getCode(),
                'acceptedCurrencies' => $acceptedCodes
            ],
            'accepted_currencies' => $acceptedCurrencies
        ]);
    }
}
