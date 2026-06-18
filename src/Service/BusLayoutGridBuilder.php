<?php


namespace App\Service;

class BusLayoutGridBuilder
{

    public function build(array $layout): array
    {
        $totalRows = $layout['rows'];
        $seatCols = $layout['columns'];

        $aisles = $layout['aisles'] ?? [];
        sort($aisles);

        $specials = $layout['specialPositions'] ?? [];
        $hasBackExtra = $layout['hasBackExtraSeat'];

        $cells = [];
        $seatNumber = 1;

        // 🔥 AJOUT : index direct row-col => label
        $seatIndex = [];

        for ($r = 1; $r <= $totalRows; $r++) {

            $seatColIndex = 0;
            $aislesForRow = $aisles;

            for ($c = 1; $c <= ($seatCols + count($aisles)); $c++) {

                $cell = [
                    'row' => $r,
                    'col' => null,
                    'type' => 'seat',
                    'label' => null,
                ];



                $isLastRow = ($r === $totalRows);
                $shouldDrawAisle = in_array($seatColIndex, $aislesForRow, true);

                if ($shouldDrawAisle && (!$isLastRow || !$hasBackExtra)) {

                    $cell['type'] = 'aisle';
                    $cells[] = $cell;

                    $aislesForRow = array_values(array_diff($aislesForRow, [$seatColIndex]));
                    continue;
                }

                $seatColIndex++;
                $cell['col'] = $seatColIndex;

                foreach ($specials as $special) {
                    if (
                        isset($special['row'], $special['col']) &&
                        (int)$special['row'] === $r &&
                        (int)$special['col'] === $seatColIndex
                    ) {
                        $cell['type'] = $special['type'];
                        break;
                    }
                }

                // label logic
                if (in_array($cell['type'], ['seat', 'vip'], true)) {
                    $cell['label'] = $seatNumber++;

                    $key = $r . '-' . $seatColIndex;
                    $seatIndex[$key] = $cell['label'];

                    $cell['id'] = $key;

                    $seatIndex[$key] = $cell['label'];
                } elseif ($cell['type'] === 'driver') {
                    $cell['label'] = 'CH';
                } elseif ($cell['type'] === 'wc') {
                    $cell['label'] = 'WC';
                } elseif ($cell['type'] === 'door') {
                    $cell['label'] = 'PORT';
                }

                $cells[] = $cell;
            }
        }

        return [
            'name' => $layout['name'] ?? null,
            'totalColumns' => $seatCols + count($aisles),
            'cells' => $cells,

            // 🔥 AJOUT IMPORTANT
            'seatIndex' => $seatIndex,
        ];
    } //build


    public function getLabel(array $layout, string $seatTrip): ?string
    {
        [$row, $col] = array_map('intval', explode('-', $seatTrip));

        foreach ($layout['cells'] as $cell) {
            if (
                ($cell['row'] ?? null) === $row &&
                ($cell['col'] ?? null) === $col
            ) {
                return $cell['label'] ?? null;
            }
        }

        return null;
    } //getLabel

}
