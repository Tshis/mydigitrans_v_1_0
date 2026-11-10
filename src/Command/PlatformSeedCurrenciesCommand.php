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

        // 1. Récupération du robot système pour signer les écritures d'usine
        $robot = $this->systemActor->get();
        $io->text(sprintf('✓ Traçabilité signée par l\'acteur : <info>%s</info>', $robot->getEmail()));

        $currenciesCatalog = [
            'USD' => ['name' => 'Dollar Américain'],
            'CDF' => ['name' => 'Franc Congolais'],
            'EUR' => ['name' => 'Euro'],
            'XAF' => ['name' => 'Franc CFA (BEAC)'],
            'XOF' => ['name' => 'Franc CFA (BCEAO)']
        ];

        $io->section('Synchronisation des devises de tarification [MCD Table 18]');

        // 🟢 LE FIX : On force l'ouverture d'une transaction isolée et dédiée pour blinder l'écriture PostgreSQL
        $this->em->wrapInTransaction(function () use ($currenciesCatalog, $robot, $io) {
            foreach ($currenciesCatalog as $code => $meta) {
                $currency = $this->em->getRepository(Currency::class)->findOneBy(['code' => $code]);

                if (!$currency) {
                    $currency = new Currency();
                    $currency->setCode($code);
                    $currency->setCreatedBy($robot);

                    $io->writeln(sprintf(' <info>[CRÉATION]</info> Devise : <comment>%s</comment> (%s)', $code, $meta['name']));
                } else {
                    $io->writeln(sprintf(' <info>[ALIGNEMENT]</info> Devise : <info>%s</info> (%s)', $code, $meta['name']));
                }

                $currency->setName($meta['name']);
                $this->em->persist($currency);
            }
        }); // 🟢 Le commit physique est forcé ici de manière étanche par Doctrine

        $io->success('Succès réel ! Le dictionnaire d\'usine des devises internationales est opérationnel en base de données.');

        return Command::SUCCESS;
    }
}
