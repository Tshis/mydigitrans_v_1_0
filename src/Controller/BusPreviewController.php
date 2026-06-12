<?php
// src/Controller/Admin/BusPreviewController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BusPreviewController extends AbstractController
{

    #[Route('/admin/agency/bus/{code}/details', name: 'admin_agency_bus_show')]
    public function show(string $code): Response
    {
        $mockBuses = [
            $this->createMockVIPCoaster(),
            $this->createMockGrandScaniaWithWC(),
            $this->createMockBusWithBookedSeats()
        ];

        return $this->render('admin/agency/bus/show.html.twig', [
            'page' => 'bus',
            'bus_code' => $code,
            'buses' => $mockBuses,
        ]);
    }

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
    }

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
    }

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
    }
}
