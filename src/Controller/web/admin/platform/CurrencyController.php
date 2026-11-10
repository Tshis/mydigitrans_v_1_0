<?php

declare(strict_types=1);

namespace App\Controller\web\admin\platform;

use App\Controller\web\admin\AbstractAdminController;
use App\Entity\Currency;
use App\Enum\Scope;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity; // 🟢 FIX : Import indispensable pour lier le {code}
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
        return Scope::Platform;
    }

    /**
     * INDEX : LISTER L'ENSEMBLE DES MONNAIES DISPONIBLES EN BASE DE DONNÉES
     */
    #[Route('/admin/platform/currencies', name: 'admin_platform_currency_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('cash.view');
        $this->actor();

        $currencies = $this->em->createQueryBuilder()
            ->select('c')
            ->from(Currency::class, 'c')
            ->orderBy('c.code', 'ASC')
            ->getQuery()
            ->setHint(\Doctrine\ORM\Query::HINT_REFRESH, true)
            ->getResult();

        return $this->render('admin/platform/currency/index.html.twig', [
            'page' => 'currency',
            'currencies_list' => $currencies
        ]);
    }

    /**
     * ADD CURRENCY : CRÉATION D'UNE MONNAIE APPLICATIVE D'USINE
     */
    #[Route('/admin/platform/currencies/add', name: 'admin_platform_currency_add', methods: ['GET', 'POST'])]
    public function add(Request $request): Response
    {
        $this->denyAccessUnlessGranted('cash.write');
        $actor = $this->actor();

        if ($request->isMethod('POST')) {
            $code = strtoupper(trim((string) $request->request->get('code')));
            $name = trim((string) $request->request->get('name'));

            if (empty($code) || empty($name)) {
                $this->addFlash('error', 'Le code ISO et le nom complet sont obligatoires.');
                return $this->redirectToRoute('admin_platform_currency_add');
            }

            $existing = $this->em->createQueryBuilder()
                ->select('c')
                ->from(Currency::class, 'c')
                ->andWhere('c.code = :code')
                ->setParameter('code', $code)
                ->getQuery()
                ->setHint(\Doctrine\ORM\Query::HINT_REFRESH, true)
                ->getOneOrNullResult();

            if ($existing) {
                $this->addFlash('error', sprintf('La devise [%s] existe déjà dans le système.', $code));
                return $this->redirectToRoute('admin_platform_currency_add');
            }

            $currency = new Currency();
            $currency->setCode($code);
            $currency->setName($name);
            $currency->setCreatedBy($actor);

            $this->em->persist($currency);
            $this->em->flush();

            $this->addFlash('success', sprintf('La devise [%s] a été implantée avec succès dans le catalogue MyDigitrans.', $code));
            return $this->redirectToRoute('admin_platform_currency_index');
        }

        return $this->render('admin/platform/currency/form.html.twig', [
            'page' => 'currency',
            'is_edit' => false
        ]);
    }

    /**
     * EDIT CURRENCY : AJUSTEMENT D'UNE MONNAIE SYSTEME
     */
    #[Route('/admin/platform/currencies/{code}/edit', name: 'admin_platform_currency_edit', methods: ['GET', 'POST'])]
    public function edit(
        #[MapEntity(mapping: ['code' => 'code'])] Currency $currency, // 🟢 FIX : Indique à Symfony de chercher par la colonne 'code'
        Request $request
    ): Response {
        $this->denyAccessUnlessGranted('cash.write');
        $this->actor();

        if ($request->isMethod('POST')) {
            $name = trim((string) $request->request->get('name'));

            if (empty($name)) {
                $this->addFlash('error', 'Le nom complet de la monnaie est obligatoire.');
                return $this->redirectToRoute('admin_platform_currency_edit', ['code' => $currency->getCode()]);
            }

            $currency->setName($name);
            $this->em->flush();

            $this->addFlash('success', sprintf('Les spécifications de la devise %s ont été mises à jour.', $currency->getCode()));
            return $this->redirectToRoute('admin_platform_currency_index');
        }

        return $this->render('admin/platform/currency/form.html.twig', [
            'page' => 'currency',
            'is_edit' => true,
            'currency' => $currency
        ]);
    }
}
