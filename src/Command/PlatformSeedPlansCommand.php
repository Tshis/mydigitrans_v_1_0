<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Feature;
use App\Entity\SubscriptionPlan;
use App\Entity\PlanFeature;
use App\Enum\ActiveStatus;
use App\Service\SystemActor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:platform:seed-plans',
    description: 'Initialise le catalogue d\'usine des modules applicatifs et des plans d\'abonnement SaaS sous la signature du Super Admin.',
)]
final class PlatformSeedPlansCommand extends Command
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
        $io->title('Initialisation du catalogue des forfaits SaaS MyDigitrans');

        // 1. Récupération de l'acteur système représentant l'autorité Super Admin
        $superAdminRobot = $this->systemActor->get();
        $io->text(sprintf('✓ Actions signées par l\'autorité : <info>%s</info>', $superAdminRobot->getEmail()));

        // =========================================================================
        // 2. DICTIONNAIRE DES SYNCHRONISATIONS DE LA TABLE "35. Feature"
        // =========================================================================
        $io->section('1. Alignement des modules techniques applicatifs (Table 35)');

        $featuresCatalog = [
            'ticketing'        => ['name' => 'Billetterie au guichet', 'desc' => 'Vente et impression des billets aux guichets des gares routières.'],
            'cash_management'   => ['name' => 'Gestion de caisse', 'desc' => 'Ouverture/clôture de sessions de caisse, encaissements et dépenses.'],
            'online_booking'    => ['name' => 'Réservation en ligne', 'desc' => 'Recherche et réservation publiques des places par les clients.'],
            'shipment_module'   => ['name' => 'Gestion des colis', 'desc' => 'Enregistrement, pesée, suivi et retrait sécurisé du fret.'],
            'multi_branch'      => ['name' => 'Multi-succursales', 'desc' => 'Gestion de plusieurs gares avec cloisonnement des accès par succursale.'],
            'advanced_reports'  => ['name' => 'Rapports avancés', 'desc' => 'Statistiques d\'exploitation détaillées et livres de comptes financiers.'],
            'sms_notifications' => ['name' => 'Notifications SMS', 'desc' => 'Envoi automatique des confirmations de billets et alertes de départ.'],
            'bus_tracking'      => ['name' => 'Suivi GPS des bus', 'desc' => 'Cartographie et positionnement géographique de la flotte en temps réel.'],
            'custom_branding'   => ['name' => 'Personnalisation', 'desc' => 'Intégration du logo et de l\'identité visuelle de l\'agence sur les billets.'],
            'api_access'        => ['name' => 'Accès API', 'desc' => 'Ouverture des passerelles d\'intégration pour les systèmes tiers de l\'agence.'],
            'priority_support'  => ['name' => 'Support prioritaire', 'desc' => 'Assistance technique dédiée avec engagement de délai de réponse réduit.'],
        ];

        $featureEntities = [];
        foreach ($featuresCatalog as $code => $meta) {
            $feature = $this->em->getRepository(Feature::class)->findOneBy(['code' => $code]);
            if (!$feature) {
                $feature = new Feature();
                $feature->setCode($code);
                $feature->setCreatedBy($superAdminRobot); // Marqué par le Super Admin
            }
            $feature->setName($meta['name']);
            $feature->setDescription($meta['desc']);
            $feature->setStatus(ActiveStatus::Active); // Statut actif d'usine

            $this->em->persist($feature);
            $featureEntities[$code] = $feature;
        }
        $this->em->flush();
        $io->writeln('✓ Catalogue mondial des modules applicatifs prêt.');

        // =========================================================================
        // 3. CATALOGUE OFFICIEL DES PLANS DE LA TABLE "36. subscriptionPlan"
        // =========================================================================
        $io->section('2. Déploiement des forfaits commerciaux d\'infrastructure (Table 36 & 37)');

        $plansCatalog = [
            'Essai Gratuit' => [
                'price' => 0.0,
                'days' => 14,
                'branches' => 2,
                'users' => 3,
                'buses' => 2,
                'currency' => 'USD',
                'features' => ['ticketing', 'cash_management', 'online_booking']
            ],
            'Starter' => [
                'price' => 49.0,
                'days' => 30,
                'branches' => 1,
                'users' => 5,
                'buses' => 3,
                'currency' => 'USD',
                'features' => ['ticketing', 'cash_management', 'online_booking']
            ],
            'Pro' => [
                'price' => 250.0,
                'days' => 30,
                'branches' => 5,
                'users' => 30,
                'buses' => 15,
                'currency' => 'USD',
                'features' => ['ticketing', 'cash_management', 'online_booking', 'shipment_module', 'multi_branch', 'advanced_reports', 'sms_notifications']
            ],
            'Premium' => [
                'price' => 450.0,
                'days' => 30,
                'branches' => 10,
                'users' => 80,
                'buses' => 40,
                'currency' => 'USD',
                'features' => ['ticketing', 'cash_management', 'online_booking', 'shipment_module', 'multi_branch', 'advanced_reports', 'sms_notifications', 'bus_tracking', 'custom_branding', 'priority_support']
            ],
            'Entreprise' => [
                'price' => 900.0,
                'days' => 30,
                'branches' => null,
                'users' => null,
                'buses' => null,
                'currency' => 'USD',
                'features' => ['ticketing', 'cash_management', 'online_booking', 'shipment_module', 'multi_branch', 'advanced_reports', 'sms_notifications', 'bus_tracking', 'custom_branding', 'priority_support', 'api_access']
            ],
            'Starter annuel' => [
                'price' => 490.0,
                'days' => 365,
                'branches' => 1,
                'users' => 5,
                'buses' => 3,
                'currency' => 'USD',
                'features' => ['ticketing', 'cash_management', 'online_booking']
            ],
            'Pro annuel' => [
                'price' => 2500.0,
                'days' => 365,
                'branches' => 5,
                'users' => 30,
                'buses' => 15,
                'currency' => 'USD',
                'features' => ['ticketing', 'cash_management', 'online_booking', 'shipment_module', 'multi_branch', 'advanced_reports', 'sms_notifications']
            ],
            'Premium annuel' => [
                'price' => 4500.0,
                'days' => 365,
                'branches' => 10,
                'users' => 80,
                'buses' => 40,
                'currency' => 'USD',
                'features' => ['ticketing', 'cash_management', 'online_booking', 'shipment_module', 'multi_branch', 'advanced_reports', 'sms_notifications', 'bus_tracking', 'custom_branding', 'priority_support']
            ],
            'Entreprise annuel' => [
                'price' => 9000.0,
                'days' => 365,
                'branches' => null,
                'users' => null,
                'buses' => null,
                'currency' => 'USD',
                'features' => ['ticketing', 'cash_management', 'online_booking', 'shipment_module', 'multi_branch', 'advanced_reports', 'sms_notifications', 'bus_tracking', 'custom_branding', 'priority_support', 'api_access']
            ],
        ];

        // Cache local pour éviter de refaire la requête de devise à chaque tour de boucle
        $currenciesCache = [];

        foreach ($plansCatalog as $name => $meta) {
            $plan = $this->em->getRepository(SubscriptionPlan::class)->findOneBy(['name' => $name]);
            if (!$plan) {
                $plan = new SubscriptionPlan();
                $plan->setName($name);
                $plan->setCreatedBy($superAdminRobot);
            }

            // --- RECOUVREMENT DE L'ENTITÉ CURRENCY REQUIS PAR LE MCD ---
            $currencyCode = $meta['currency'];
            if (!isset($currenciesCache[$currencyCode])) {
                $currency = $this->em->getRepository(\App\Entity\Currency::class)->findOneBy(['code' => $currencyCode]);

                // Si la devise système n'existe pas (ex: USD), on l'initialise à la volée [MCD 18]
                if (!$currency) {
                    $currency = new \App\Entity\Currency();
                    $currency->setCode($currencyCode);
                    $currency->setName($currencyCode === 'USD' ? 'Dollar Américain' : 'Franc Congolais');
                    $currency->setCreatedBy($superAdminRobot);
                    $this->em->persist($currency);
                    $this->em->flush();
                }
                $currenciesCache[$currencyCode] = $currency;
            }

            $plan->setPrice((string) $meta['price']);
            $plan->setCurrency($currenciesCache[$currencyCode]); // <-- FIX : On passe l'entité Currency complète
            $plan->setDurationDays((int) $meta['days']);
            $plan->setMaxBranches($meta['branches']);
            $plan->setMaxUsers($meta['users']);
            $plan->setMaxBuses($meta['buses']);
            $plan->setStatus(ActiveStatus::Active);

            $this->em->persist($plan);
            $this->em->flush();

            // =========================================================================
            // 4. ALIGNEMENT DE LA TABLE ASSOCIATIVE "37. PlanFeature"
            // =========================================================================
            $oldRelations = $this->em->getRepository(PlanFeature::class)->findBy(['plan' => $plan]);
            foreach ($oldRelations as $oldRelation) {
                $this->em->remove($oldRelation);
            }
            $this->em->flush();

            foreach ($meta['features'] as $featureCode) {
                if (isset($featureEntities[$featureCode])) {
                    $planFeature = new PlanFeature();
                    $planFeature->setPlan($plan);
                    $planFeature->setFeature($featureEntities[$featureCode]);
                    $planFeature->setCreatedBy($superAdminRobot);

                    $this->em->persist($planFeature);
                }
            }
        }


        $this->em->flush();
        $io->success('Succès complet ! Le catalogue mondial des forfaits SaaS et des verrous applicatifs a été déployé.');

        return Command::SUCCESS;
    }
}
