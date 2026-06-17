<?php


namespace App\Service;

class BusLayoutGridBuilder
{
    public function build(array $layout): array
    {
        $rows = (int) ($layout['rows'] ?? 0);
        $seatCols = (int) ($layout['columns'] ?? 0);

        $aisles = array_values(array_map('intval', $layout['aisles'] ?? []));
        sort($aisles);

        $specials = $layout['specialPositions'] ?? [];
        $hasBackExtra = !empty($layout['hasBackExtraSeat']);

        // 🔥 indexation O(1)
        $specialMap = [];
        foreach ($specials as $sp) {

            $row = (int) ($sp['row'] ?? 0);
            $col = (int) ($sp['col'] ?? 0);
            $type = $sp['type'] ?? null;

            if ($row <= 0 || $col <= 0 || !$type) {
                continue;
            }

            $specialMap[$row][$col] = $type;
        }

        $cells = [];
        $seatNumber = 1;

        $totalCols = $seatCols + count($aisles);

        for ($r = 1; $r <= $rows; $r++) {

            $seatColIndex = 0;
            $aislesForRow = $aisles;

            for ($c = 1; $c <= $totalCols; $c++) {

                $cell = [
                    'row' => $r,
                    'col' => null,
                    'type' => 'seat',
                    'label' => null,
                ];

                $isLastRow = ($r === $rows);
                $isAisle = in_array($seatColIndex, $aislesForRow, true);

                // 👉 couloir
                if ($isAisle && (!$isLastRow || !$hasBackExtra)) {

                    $cell['type'] = 'aisle';

                    $cells[] = $cell;

                    // équivalent JS splice
                    $aislesForRow = array_values(
                        array_diff($aislesForRow, [$seatColIndex])
                    );

                    continue;
                }

                $seatColIndex++;
                $cell['col'] = $seatColIndex;

                // 👉 special override
                if (isset($specialMap[$r][$seatColIndex])) {
                    $cell['type'] = $specialMap[$r][$seatColIndex];
                }

                // 👉 numérotation (équivalent reindexSeats JS)
                switch ($cell['type']) {

                    case 'seat':
                    case 'vip':
                        $cell['label'] = $seatNumber++;
                        break;

                    case 'driver':
                        $cell['label'] = 'CH';
                        break;

                    case 'wc':
                        $cell['label'] = 'WC';
                        break;

                    case 'door':
                        $cell['label'] = 'PORT';
                        break;

                    default:
                        $cell['label'] = null;
                }

                $cells[] = $cell;
            }
        }

        return [
            'name' => $layout['name'] ?? null,
            'totalColumns' => $totalCols,
            'cells' => $cells,
        ];
    }
}
