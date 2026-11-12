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

        $robot = $this->systemActor->get();
        $io->text(sprintf('✓ Traçabilité signée par l\'acteur : <info>%s</info>', $robot->getEmail()));

        $io->section('Déploiement des structures matricielles [MCD Table 8]');

        $layoutsCatalog = [
            [
                'name' => 'Configuration standard 2-2 avec WC (Yutong / Scania / Marcopolo)',
                'code' => 'LYT-STD-22-WC',
                'rows' => 13,
                'columns' => 4,
                'aisles' => array('2'),
                'hasBackExtraSeat' => true,
                'specialPositions' => [
                    ['type' => 'driver', 'row' => 1, 'col' => 1],
                    ['type' => 'aisle',  'row' => 1, 'col' => 2],
                    ['type' => 'aisle',  'row' => 1, 'col' => 3],
                    ['type' => 'door',   'row' => 1, 'col' => 4],
                    ['type' => 'door',   'row' => 7, 'col' => 4],
                    ['type' => 'wc',     'row' => 8, 'col' => 4],
                ],
            ],
            [
                'name' => 'Configuration VIP Business 2-1 (Yutong / Marcopolo Premium)',
                'code' => 'LYT-VIP-21-STD',
                'rows' => 14,
                'columns' => 3,
                'aisles' => array('2'),
                'hasBackExtraSeat' => true,
                'specialPositions' => [
                    ['type' => 'driver', 'row' => 1, 'col' => 1],
                    ['type' => 'aisle',  'row' => 1, 'col' => 2],
                    ['type' => 'door',   'row' => 1, 'col' => 3],
                ],
            ],
            [
                'name' => 'Configuration Haute Capacité 3-2 (Transco Interurbain / Tata LPO)',
                'code' => 'LYT-ECO-32-HIGH',
                'rows' => 13,
                'columns' => 5,
                'aisles' => array('3'),
                'hasBackExtraSeat' => true,
                'specialPositions' => [
                    ['type' => 'driver', 'row' => 1, 'col' => 1],
                    ['type' => 'aisle',  'row' => 1, 'col' => 2],
                    ['type' => 'aisle',  'row' => 1, 'col' => 3],
                    ['type' => 'aisle',  'row' => 1, 'col' => 4],
                    ['type' => 'door',   'row' => 1, 'col' => 5],
                    ['type' => 'door',   'row' => 7, 'col' => 5],
                ],
            ],
            [
                'name' => 'Configuration Haute Capacité 2-3 (Variante Bus Éco Privés)',
                'code' => 'LYT-ECO-23-HIGH',
                'rows' => 13,
                'columns' => 5,
                'aisles' => array('2'),
                'hasBackExtraSeat' => true,
                'specialPositions' => [
                    ['type' => 'driver', 'row' => 1, 'col' => 1],
                    ['type' => 'aisle',  'row' => 1, 'col' => 2],
                    ['type' => 'aisle',  'row' => 1, 'col' => 3],
                    ['type' => 'aisle',  'row' => 1, 'col' => 4],
                    ['type' => 'door',   'row' => 1, 'col' => 5],
                    ['type' => 'door',   'row' => 7, 'col' => 5],
                ],
            ],
            [
                'name' => 'Configuration Petit Porteur 1-2 (Toyota Coaster / Mitsubishi Fuso Rosa)',
                'code' => 'LYT-MINIBUS-12',
                'rows' => 9,
                'columns' => 3,
                'aisles' => array('1'),
                'hasBackExtraSeat' => true,
                'specialPositions' => [
                    ['type' => 'driver', 'row' => 1, 'col' => 1],
                    ['type' => 'aisle',  'row' => 1, 'col' => 2],
                    ['type' => 'door',   'row' => 1, 'col' => 3],
                ],
            ],
        ];

        $this->em->wrapInTransaction(function () use ($layoutsCatalog, $io): void {
            foreach ($layoutsCatalog as $data) {
                $layout = $this->em->getRepository(BusLayout::class)->findOneBy(['code' => $data['code']]);

                if (!$layout) {
                    $layout = new BusLayout();
                    $layout->setCode($data['code']);
                    $layout->setAgency(null);
                    $io->writeln(sprintf(' <info>[CRÉATION]</info> Gabarit : <comment>%s</comment>', $data['name']));
                } else {
                    $io->writeln(sprintf(' <info>[ALIGNEMENT]</info> Gabarit : <info>%s</info>', $data['name']));
                }

                $layout->setName($data['name']);
                $layout->setRows($data['rows']);
                $layout->setColumns($data['columns']);
                $layout->setAisles($data['aisles']);
                $layout->setIsValidated(true);
                $layout->setSpecialPositions($data['specialPositions']);
                $layout->setHasBackExtraSeat($data['hasBackExtraSeat']);

                $this->em->persist($layout);
            }
        });

        $io->success('Les 5 gabarits d\'usine RDC sont enregistrés avec une géométrie propre.');

        return Command::SUCCESS;
    }
}
