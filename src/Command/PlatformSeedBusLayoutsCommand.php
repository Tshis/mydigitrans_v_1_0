<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\BusLayout;
use App\Service\SystemActor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:platform:seed-bus-layouts',
    description: 'Initialise les gabarits de bus d\'usine (Yutong, Scania, Coaster) fréquemment utilisés en RDC.',
)]
final class PlatformSeedBusLayoutsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SystemActor $systemActor
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Initialisation des Gabarits de Bus Interurbains RDC');

        // 1. Récupération de l'acteur robot système pour signer les écritures [MCD 8]
        $robot = $this->systemActor->get();
        $io->text(sprintf('✓ Traçabilité signée par l\'acteur : <info>%s</info>', $robot->getEmail()));

        $io->section('Déploiement des structures matricielles [MCD Table 8]');


        // =========================================================================
        // CATALOGUE DES UNIQUE GEOMETRIC CONFIGURATIONS (SANS DOUBLONS STRUCTURELS)
        // =========================================================================
        $layoutsCatalog = [
            [
                'name' => 'Configuration standard 2-2 avec WC (Yutong / Scania / Marcopolo)',
                'code' => 'LYT-STD-22-WC',
                'rows' => 13,
                'columns' => 4, // 2 sièges à gauche, 2 sièges à droite (l'allée n'est pas comptée)
                'aisles' => ["2"],
                'hasBackExtraSeat' => true,
                'specialPositions' => [
                    // --- Rangée 1 : Accès avant d'usine ---
                    ['type' => 'driver', 'row' => 1, 'col' => 1],
                    ['type' => 'aisle',  'row' => 1, 'col' => 2],
                    ['type' => 'aisle',  'row' => 1, 'col' => 3],
                    ['type' => 'door',   'row' => 1, 'col' => 4],
                    // --- Rangées 7 & 8 : Bloc technique central réel ---
                    ['type' => 'door',   'row' => 7, 'col' => 4], // Escaliers de descente
                    ['type' => 'wc',     'row' => 8, 'col' => 4], // Cabine WC adossée aux marches
                ]
            ],
            [
                'name' => 'Configuration VIP Business 2-1 (Yutong / Marcopolo Premium)',
                'code' => 'LYT-VIP-21-STD',
                'rows' => 14,
                'columns' => 3, // 2 sièges à gauche, 1 siège à droite
                'aisles' => ["2"],
                'hasBackExtraSeat' => true,
                'specialPositions' => [
                    // --- Rangée 1 : Cabine avant ---
                    ['type' => 'driver', 'row' => 1, 'col' => 1],
                    ['type' => 'aisle',  'row' => 1, 'col' => 2],
                    ['type' => 'door',   'row' => 1, 'col' => 3],
                ]
            ],
            [
                'name' => 'Configuration Haute Capacité 3-2 (Transco Interurbain / Tata LPO)',
                'code' => 'LYT-ECO-32-HIGH',
                'rows' => 13,
                'columns' => 5, // 3 sièges à gauche, 2 sièges à droite
                'aisles' => ["3"], // Allée injectée après la colonne 3
                'hasBackExtraSeat' => true,
                'specialPositions' => [
                    // --- Rangée 1 : Accès avant ---
                    ['type' => 'driver', 'row' => 1, 'col' => 1],
                    ['type' => 'aisle',  'row' => 1, 'col' => 2],
                    ['type' => 'aisle',  'row' => 1, 'col' => 3],
                    ['type' => 'aisle',  'row' => 1, 'col' => 4],
                    ['type' => 'door',   'row' => 1, 'col' => 5],
                    // --- Aménagement milieu ---
                    ['type' => 'door',   'row' => 7, 'col' => 5],
                ]
            ],
            [
                'name' => 'Configuration Haute Capacité 2-3 (Variante Bus Éco Privés)',
                'code' => 'LYT-ECO-23-HIGH',
                'rows' => 13,
                'columns' => 5, // 2 sièges à gauche, 3 sièges à droite
                'aisles' => ["2"], // Allée injectée après la colonne 2
                'hasBackExtraSeat' => true,
                'specialPositions' => [
                    // --- Rangée 1 : Accès avant ---
                    ['type' => 'driver', 'row' => 1, 'col' => 1],
                    ['type' => 'aisle',  'row' => 1, 'col' => 2],
                    ['type' => 'aisle',  'row' => 1, 'col' => 3],
                    ['type' => 'aisle',  'row' => 1, 'col' => 4],
                    ['type' => 'door',   'row' => 1, 'col' => 5],
                    // --- Aménagement milieu ---
                    ['type' => 'door',   'row' => 7, 'col' => 5],
                ]
            ],
            [
                'name' => 'Configuration Petit Porteur 1-2 (Toyota Coaster / Mitsubishi Fuso Rosa)',
                'code' => 'LYT-MINIBUS-12',
                'rows' => 9,
                'columns' => 3, // 1 siège à gauche, 2 sièges à droite
                'aisles' => ["1"], // Allée injectée après la colonne 1
                'hasBackExtraSeat' => true,
                'specialPositions' => [
                    // --- Rangée 1 : Cabine avant ---
                    ['type' => 'driver', 'row' => 1, 'col' => 1],
                    ['type' => 'aisle',  'row' => 1, 'col' => 2],
                    ['type' => 'door',   'row' => 1, 'col' => 3],
                ]
            ]
        ];



        foreach ($layoutsCatalog as $data) {
            $finalSpecialPositions = $data['specialPositions'];

            // Calcul automatisé des cases 'aisle' vides pour tout le long du couloir
            for ($r = 1; $r <= $data['rows']; $r++) {

                // Si c'est la dernière rangée et qu'il y a une banquette arrière complète (ExtraSeat), 
                // le couloir s'arrête pour laisser place aux sièges.
                if ($r === $data['rows'] && $data['hasBackExtraSeat']) {
                    continue;
                }

                foreach ($data['aisles'] as $aisleCol) {
                    $aisleColumnIndex = (int) $aisleCol;

                    // On s'assure de ne pas écraser une structure déjà définie manuellement (Chauffeur, Portière, WC)
                    $isOccupied = false;
                    foreach ($data['specialPositions'] as $spec) {
                        if ($spec['row'] === $r && $spec['col'] === $aisleColumnIndex) {
                            $isOccupied = true;
                            break;
                        }
                    }

                    // Si la case du couloir est libre sur cette rangée, on y injecte le type 'aisle'
                    if (!$isOccupied) {
                        $finalSpecialPositions[] = [
                            'type' => 'aisle',
                            'row' => $r,
                            'col' => $aisleColumnIndex
                        ];
                    }
                }
            }

            // Recherche ou initialisation de la configuration en base de données
            $layout = $this->em->getRepository(BusLayout::class)->findOneBy(['code' => $data['code']]);

            if (!$layout) {
                $layout = new BusLayout();
                $layout->setCode($data['code']);
                $layout->setAgency(null); // Configuration mondiale partagée par l'ensemble des gares

                $io->writeln(sprintf(' <info>[CRÉATION]</info> Gabarit conforme : <comment>%s</comment>', $data['name']));
            } else {
                $io->writeln(sprintf(' <info>[ALIGNEMENT]</info> Alignement géométrique : <info>%s</info>', $data['name']));
            }

            $layout->setName($data['name']);
            $layout->setRows($data['rows']);
            $layout->setColumns($data['columns']);
            $layout->setAisles($data['aisles']);
            $layout->setIsValidated(true);
            $layout->setSpecialPositions($finalSpecialPositions); // Enregistrement de la matrice complète en JSON
            $layout->setHasBackExtraSeat($data['hasBackExtraSeat']);


            $this->em->persist($layout);
        }

        // Sauvegarde définitive de la structure dans PostgreSQL / MySQL
        $this->em->flush();

        $io->success('Félicitations Daniel ! Tes gabarits d\'usine intègrent désormais parfaitement les allées vides.');

        return Command::SUCCESS;
    }
}
