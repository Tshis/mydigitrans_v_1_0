<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Currency;
use App\Service\SystemActor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:platform:seed-currencies',
    description: 'Initialise le catalogue mondial des devises système (USD, CDF, EUR, XAF, XOF) conformément au MCD MyDigitrans.',
)]
final class PlatformSeedCurrenciesCommand extends Command
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
        $io->title('Initialisation des Devises Système MyDigitrans');

        // 1. Récupération du robot système pour signer les écritures d'usine [MCD 18]
        $robot = $this->systemActor->get();
        $io->text(sprintf('✓ Traçabilité signée par l\'acteur : <info>%s</info>', $robot->getEmail()));

        // =========================================================================
        // 2. CATALOGUE DE CONFIGURATION DES DEVISES (Table 18 du MCD)
        // =========================================================================
        $currenciesCatalog = [
            'USD' => [
                'name' => 'Dollar Américain'
            ],
            'CDF' => [
                'name' => 'Franc Congolais'
            ],
            'EUR' => [
                'name' => 'Euro'
            ],
            'XAF' => [
                'name' => 'Franc CFA (BEAC)' // Zone d'Afrique Centrale (Congo-Brazza, Gabon, etc.)
            ],
            'XOF' => [
                'name' => 'Franc CFA (BCEAO)' // Zone d'Afrique de l'Ouest
            ]
        ];

        $io->section('Synchronisation des devises de tarification [MCD Table 18]');

        foreach ($currenciesCatalog as $code => $meta) {
            // Recherche si la devise existe déjà sous ce code de clé unique [MCD 18]
            $currency = $this->em->getRepository(Currency::class)->findOneBy(['code' => $code]);

            if (!$currency) {
                $currency = new Currency();
                $currency->setCode($code); // Clé unique (USD, CDF, EUR, XAF, XOF)
                $currency->setCreatedBy($robot); // Attribue la création à l'acteur robot système [MCD 18]

                $io->writeln(sprintf(' <info>[CRÉATION]</info> Devise : <comment>%s</comment> (%s)', $code, $meta['name']));
            } else {
                $io->writeln(sprintf(' <info>[ALIGNEMENT]</info> Devise : <info>%s</info> (%s)', $code, $meta['name']));
            }

            $currency->setName($meta['name']);
            $this->em->persist($currency);
        }

        // Sauvegarde et exécution définitive de la transaction SQL
        $this->em->flush();

        $io->success('Succès ! Le dictionnaire d\'usine des devises internationales est opérationnel.');

        return Command::SUCCESS;
    }
}
