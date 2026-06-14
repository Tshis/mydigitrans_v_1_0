<?php

namespace App\Controller\web\admin\agency;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BusController extends AbstractController
{
    #[Route('/admin/agency/bus/list', name: 'admin_agency_bus_index')]
    public function index(): Response
    {
        return $this->render('admin/agency/bus/index.html.twig', [
            'page' => 'bus',
        ]);
    } //index

    #[Route('/admin/agency/bus/add', name: 'admin_agency_bus_add')]
    public function add(): Response
    {
        return $this->render('admin/agency/bus/add.html.twig', [
            'page' => 'bus',
        ]);
    } //add

    #[Route('/admin/agency/bus/{code}/details', name: 'admin_agency_bus_show')]
    public function show(string $code): Response
    {
        $mockBuses = [
            $this->createMockVIPCoaster(),
            $this->createMockGrandScaniaWithWC(),
            $this->createMockBusWithBookedSeats(),
            $this->createMockYutong55()
        ];

        return $this->render('admin/agency/bus/show.html.twig', [
            'page' => 'bus',
            'bus_code' => $code,
            'buses' => $mockBuses,
        ]);
    } //show()

    private function createMockVIPCoaster(): array
    {
        $seats = [];
        $rows = 8;
        $cols = 3;
        $seatLabelIndex = 1;
        $seats[] = ['id' => 1, 'row' => 1, 'column' => 1, 'type' => 'driver', 'seat_label' => 'CH', 'is_booked' => false];
        $seats[] = ['id' => 2, 'row' => 1, 'column' => 2, 'type' => 'aisle', 'seat_label' => '', 'is_booked' => false];
        $seats[] = ['id' => 3, 'row' => 1, 'column' => 3, 'type' => 'vip', 'seat_label' => 'V1', 'is_booked' => false];

        for ($r = 2; $r <= $rows; $r++) {
            $seats[] = ['id' => uniqid(), 'row' => $r, 'column' => 1, 'type' => 'vip', 'seat_label' => 'V' . ++$seatLabelIndex, 'is_booked' => false];
            $seats[] = ['id' => uniqid(), 'row' => $r, 'column' => 2, 'type' => 'aisle', 'seat_label' => '', 'is_booked' => false];
            $seats[] = ['id' => uniqid(), 'row' => $r, 'column' => 3, 'type' => 'vip', 'seat_label' => 'V' . ++$seatLabelIndex, 'is_booked' => false];
        }
        return ['id' => 1, 'name' => 'Toyota Coaster VIP', 'total_columns' => $cols, 'seats' => $seats];
    } //createMockVIPCoaster

    private function createMockGrandScaniaWithWC(): array
    {
        $seats = [];
        $rows = 12;
        $cols = 5;
        $seatNum = 1;

        for ($r = 1; $r <= $rows; $r++) {

            for ($c = 1; $c <= $cols; $c++) {

                if ($r === 1 && $c === 1) {
                    $seats[] = ['id' => uniqid(), 'row' => 1, 'column' => 1, 'type' => 'driver', 'seat_label' => 'CH', 'is_booked' => false];
                    continue;
                }

                if ($r === 1 && $c === 2) {
                    $seats[] = ['id' => uniqid(), 'row' => 1, 'column' => 2, 'type' => 'door', 'seat_label' => 'PORT', 'is_booked' => false];
                    continue;
                }

                if ($r === 7 && ($c === 4 || $c === 5)) {
                    if ($c === 4) {
                        $seats[] = ['id' => uniqid(), 'row' => 7, 'column' => 4, 'type' => 'wc', 'seat_label' => 'WC', 'is_booked' => false];
                    }
                    continue;
                }

                if ($c === 3 && $r < $rows) {
                    $seats[] = ['id' => uniqid(), 'row' => $r, 'column' => 3, 'type' => 'aisle', 'seat_label' => '', 'is_booked' => false];
                    continue;
                }

                $seats[] = ['id' => uniqid(), 'row' => $r, 'column' => $c, 'type' => 'normal', 'seat_label' => (string)$seatNum++, 'is_booked' => false];
            }
        }

        return ['id' => 2, 'name' => 'Scania K410 (Avec WC)', 'total_columns' => $cols, 'seats' => $seats];
    } //createMockGrandScaniaWithWC

    private function createMockBusWithBookedSeats(): array
    {
        $seats = [];
        $rows = 6;
        $cols = 4;
        $seatNum = 1;
        $seats[] = ['id' => uniqid(), 'row' => 1, 'column' => 1, 'type' => 'driver', 'seat_label' => 'CH', 'is_booked' => false];
        $seats[] = ['id' => uniqid(), 'row' => 1, 'column' => 2, 'type' => 'aisle', 'seat_label' => '', 'is_booked' => false];
        $seats[] = ['id' => uniqid(), 'row' => 1, 'column' => 3, 'type' => 'normal', 'seat_label' => (string)$seatNum++, 'is_booked' => false];
        $seats[] = ['id' => uniqid(), 'row' => 1, 'column' => 4, 'type' => 'normal', 'seat_label' => (string)$seatNum++, 'is_booked' => false];

        for ($r = 2; $r <= $rows; $r++) {
            for ($c = 1; $c <= $cols; $c++) {
                if ($c === 2) {
                    $seats[] = ['id' => uniqid(), 'row' => $r, 'column' => 2, 'type' => 'aisle', 'seat_label' => '', 'is_booked' => false];
                    continue;
                }
                $seats[] = ['id' => uniqid(), 'row' => $r, 'column' => $c, 'type' => 'normal', 'seat_label' => (string)$seatNum++, 'is_booked' => in_array($seatNum, [4, 7, 11])];
            }
        }
        return ['id' => 3, 'name' => 'Mercedes Sprinter (Ventes en cours)', 'total_columns' => $cols, 'seats' => $seats];
    } //createMockBusWithBookedSeats

    /**
     * Configuration réelle d'un autocar Yutong 55 places avec WC et Porte centrale
     */
    private function createMockYutong55(): array
    {
        $seats = [];
        $rows = 13; // 13 rangées de long
        $cols = 5;  // 5 colonnes de large
        $seatNum = 1;
        $uniqueId = 1000;

        for ($r = 1; $r <= $rows; $r++) {
            for ($c = 1; $c <= $cols; $c++) {

                // --- RANGÉE 1 : CABINE AVANT DÉGAGÉE ---
                if ($r === 1) {
                    if ($c === 1) {
                        $seats[] = ['id' => ++$uniqueId, 'row' => 1, 'column' => 1, 'type' => 'driver', 'seat_label' => 'CH', 'is_booked' => false];
                    } else {
                        $seats[] = ['id' => ++$uniqueId, 'row' => 1, 'column' => $c, 'type' => 'aisle', 'seat_label' => '', 'is_booked' => false];
                    }
                    continue;
                }

                // --- RANGÉE 7 : ZONE INTERMÉDIAIRE (PORTE DE SECOURS + SIÈGE EXTRA) ---
                if ($r === 7) {
                    if ($c === 3) {
                        $seats[] = ['id' => ++$uniqueId, 'row' => 7, 'column' => 3, 'type' => 'aisle', 'seat_label' => '', 'is_booked' => false];
                    } elseif ($c === 5) {
                        // La portière passager du milieu (Escalier)
                        $seats[] = ['id' => ++$uniqueId, 'row' => 7, 'column' => 5, 'type' => 'door', 'seat_label' => 'PORT', 'is_booked' => false];
                    } else {
                        // Les sièges de la rangée 7 (souvent des places avec plus d'espace pour les jambes)
                        $seats[] = ['id' => ++$uniqueId, 'row' => 7, 'column' => $c, 'type' => 'extra', 'seat_label' => (string)$seatNum++, 'is_booked' => false];
                    }
                    continue;
                }

                // --- RANGÉE 13 : LE FOND DU BUS (WC À DROITE + BANQUETTE DE 3 PLACES) ---
                if ($r === $rows) {
                    if ($c === 4 || $c === 5) {
                        // Bloc Sanitaire / Toilettes double cabine tout au fond à droite
                        if ($c === 4) {
                            $seats[] = ['id' => ++$uniqueId, 'row' => $rows, 'column' => 4, 'type' => 'wc', 'seat_label' => 'WC', 'is_booked' => false];
                        } else {
                            $seats[] = ['id' => ++$uniqueId, 'row' => $rows, 'column' => 5, 'type' => 'aisle', 'seat_label' => '', 'is_booked' => false];
                        }
                    } else {
                        // Banquette arrière gauche de 3 places collées
                        $seats[] = ['id' => ++$uniqueId, 'row' => $rows, 'column' => $c, 'type' => 'normal', 'seat_label' => (string)$seatNum++, 'is_booked' => false];
                    }
                    continue;
                }

                // --- ALLÉE CENTRALE DU BUS (COLONNE 3) ---
                if ($c === 3) {
                    $seats[] = ['id' => ++$uniqueId, 'row' => $r, 'column' => 3, 'type' => 'aisle', 'seat_label' => '', 'is_booked' => false];
                    continue;
                }

                // --- SIÈGES PASSAGERS STANDARDS ---
                $seats[] = [
                    'id' => ++$uniqueId,
                    'row' => $r,
                    'column' => $c,
                    'type' => 'normal',
                    'seat_label' => (string)$seatNum++,
                    'is_booked' => false
                ];
            }
        }

        return [
            'id' => 4,
            'name' => 'Autocar Yutong Inter-Urbain',
            'total_columns' => $cols,
            'seats' => $seats
        ];
    } //createMockYutong55
}
