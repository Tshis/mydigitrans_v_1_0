<?php

declare(strict_types=1);

namespace App\Controller\web\admin\agency;

use App\Controller\web\admin\AbstractAdminController;
use App\Entity\CurrencyAgency;
use App\Entity\ExchangeRate;
use App\Enum\Scope;
use App\Service\AgencyDashboardService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractAdminController
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {}

    protected function scope(): Scope
    {
        return Scope::Agency;
    }

    #[Route('/admin/agency/dashboard', name: 'admin_agency_dashboard', methods: ['GET'])]
    public function index(Request $request, AgencyDashboardService $dashboard): Response
    {
        $user = $this->actor(); // 🔒 Isolation multi-tenant stricte
        $agency = $user->getAgency();

        $view = $dashboard->build(
            $user,
            (string) $request->query->get('branch_code', ''),
            new \DateTimeImmutable()
        );

        // 1. Recherche de la monnaie de base de CETTE agence dans la table pivot
        $baseCurrencyAgency = $this->em->getRepository(CurrencyAgency::class)->findOneBy([
            'agency' => $agency,
            'isBase' => true,
            'isActive' => true
        ]);

        // 🟢 ALIGNEMENT : Si aucune ligne n'est trouvée, la base_currency vaut explicitement 'Non configurée'
        $baseCurrencyCode = $baseCurrencyAgency ? $baseCurrencyAgency->getCurrency()->getCode() : 'Non configurée';
        $baseCurrencyName = $baseCurrencyAgency ? $baseCurrencyAgency->getCurrency()->getName() : 'Ajustement requis dans l\'Action A';

        // 2. Extraction des monnaies associées
        $agencyRelations = $this->em->getRepository(CurrencyAgency::class)->findBy([
            'agency' => $agency,
            'isActive' => true
        ]);

        $acceptedCurrenciesData = [];
        foreach ($agencyRelations as $rel) {
            $currency = $rel->getCurrency();
            if (!$currency) {
                continue;
            }
            $code = $currency->getCode();

            $lastRate = 1.00;
            if (!$rel->isBase() && $baseCurrencyAgency !== null) {
                $latestActiveRate = $this->em->createQueryBuilder()
                    ->select('er.rate')
                    ->from(ExchangeRate::class, 'er')
                    ->andWhere('er.agency = :agency')
                    ->andWhere('er.baseCurrency = :base')
                    ->andWhere('er.targetCurrency = :target')
                    ->andWhere('er.effectiveFrom <= :now')
                    ->setParameter('agency', $agency)
                    ->setParameter('base', $baseCurrencyAgency->getCurrency())
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

            $acceptedCurrenciesData[] = [
                'code' => $code,
                'name' => $currency->getName(),
                'exchange_rate' => $rel->isBase() ? null : $lastRate,
                'isActive' => $rel->isActive()
            ];
        }

        $view['agency_currency_settings'] = [
            'base_currency' => $baseCurrencyCode,
            'accepted_currencies' => $acceptedCurrenciesData
        ];

        return $this->render('admin/agency/dashboard/index.html.twig', ['page' => 'dashboard'] + $view);
    }
}
