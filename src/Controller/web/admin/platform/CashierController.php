<?php

namespace App\Controller\web\admin\platform;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CashierController extends AbstractController
{


    /**
     * 43. CASH OPEN : INITIALISATION ET OUVERTURE DE POSTE [MCD 43]
     */
    #[Route('/admin/platform/cashier/open-session', name: 'admin_platform_cashier_open_session', methods: ['GET', 'POST'])]
    public function openSession(Request $request): Response
    {
        return $this->json('A developper plutard une fois qu\'on a des caissiers');

        if ($request->isMethod('POST')) {
            $registerId = (int) $request->request->get('cash_register_id');
            $expected = (float) $request->request->get('opening_balance_expected');
            $declared = (float) $request->request->get('opening_balance_declared');
            $openingNote = $request->request->get('opening_note');
            $observation = $request->request->get('opening_observation');

            // Calcul automatique de la différence d'ouverture de ton MCD [MCD 43]
            $difference = $declared - $expected;

            // EN INTÉGRATION DOCTRINE ORM FINALE :
            // $session = new PlatformCashSession();
            // $session->setPlatformCashRegister($em->find(PlatformCashRegister::class, $registerId));
            // $session->setOpenedBy($this->getUser());
            // $session->setOpenedAt(new \DateTimeImmutable());
            // $session->setStatus('opened'); // Passage immédiat au statut ACTIF
            // $session->setOpeningBalanceExpected($expected);
            // $session->setOpeningBalanceDeclared($declared);
            // $session->setOpenBalanceDifference($difference);
            // $session->setOpeningNote($openingNote);
            // $session->setOpeningObservation($observation);
            // $em->persist($session); $em->flush();

            if ($difference !== 0.0) {
                $this->addFlash('warning', sprintf('La caisse a été ouverte avec un écart de %s $. L\'alerte a été consignée pour l\'audit.', $difference));
            } else {
                $this->addFlash('success', 'La session de caisse a été initialisée et ouverte de manière conforme.');
            }

            return $this->redirectToRoute('admin_platform_cash_index');
        }

        // Simulation des tiroirs-caisses d'usine disponibles (non encore occupés) [MCD 42]
        $availableRegisters = [
            ['id' => 3, 'code' => 'REG-GOMBE-02', 'name' => 'Caisse Guichet Colis B', 'currency' => 'USD'],
            ['id' => 4, 'code' => 'REG-POINTE-01', 'name' => 'Caisse Principale Devises', 'currency' => 'XAF']
        ];

        return $this->render('admin/platform/cashier/open_session_form.html.twig', [
            'page' => 'cashier',
            'available_registers' => $availableRegisters
        ]);
    } //openSession


}
