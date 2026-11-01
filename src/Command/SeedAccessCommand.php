<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Permission;
use App\Entity\Role;
use App\Entity\RolePermission;
use App\Enum\Scope;
use App\Service\SystemActor; // <-- Votre service d'acteur système virtuel
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:access:seed',
    description: 'Initialise et synchronise la matrice des rôles et des permissions système sous la signature de l\'acteur système.',
)]
final class SeedAccessCommand extends Command
{
    // Constantes de portée propres à votre architecture
    public const SCOPE_AGENCY = Scope::Agency;
    public const SCOPE_PLATFORM =  Scope::Platform;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SystemActor $systemActor // <-- Injection de votre service ici
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Synchronisation du catalogue AccessCatalog MyDigitrans');

        // 1. Récupération ou création immédiate du compte robot système
        $robot = $this->systemActor->get();
        $io->text(sprintf('✓ Traçabilité signée par l\'acteur : <info>%s</info>', $robot->getEmail()));

        // =========================================================================
        // CATALOGUE DES PERMISSIONS ET MATRICE DES RÔLES FIXÉS PAR L'USINE
        // =========================================================================

        // Portée Agence : 32 permissions [MCD 6. Permission]
        $agencyPermissions = [
            'dashboard.view',
            'branch.view',
            'branch.write',
            'bus.view',
            'bus.write',
            'route.view',
            'route.write',
            'trip.view',
            'trip.write',
            'fare.view',
            'fare.write',
            'reservation.view',
            'reservation.write',
            'ticket.view',
            'ticket.write',
            'ticket.refund',
            'ticket.print',
            'shipment.view',
            'shipment.write',
            'payment.view',
            'payment.write',
            'payment.confirm',
            'cash.view',
            'cash.write',
            'expense.view',
            'expense.write',
            'report.view',
            'user.view',
            'user.write',
            'role.view',
            'subscription.view',
            'subscription.write'
        ];

        // Portée Plateforme : 21 permissions [MCD 6. Permission]
        $platformPermissions = [
            'dashboard.view',
            'agency.view',
            'agency.write',
            'partner.view',
            'partner.write',
            'plan.view',
            'plan.write',
            'invoice.view',
            'invoice.write',
            'commission.view',
            'commission.write',
            'cash.view',
            'cash.write',
            'expense.view',
            'expense.write',
            'notification.view',
            'notification.write',
            'report.view',
            'user.view',
            'user.write',
            'role.view'
        ];

        // Matrice d'usine globale des fonctions applicatives
        $rolesCatalog = [
            'ROLE_AGENCY_ADMIN' => [
                'scope' => self::SCOPE_AGENCY,
                'description' => 'Gestion complète de l\'agence et de son personnel.',
                'permissions' => $agencyPermissions
            ],
            'ROLE_GENERAL_MANAGER' => [
                'scope' => self::SCOPE_AGENCY,
                'description' => 'Directeur Général Adjoint / Directeur d\'Exploitation. Supervision globale sans modification de l\'administrateur.',
                'permissions' => [
                    'dashboard.view',
                    'branch.view',
                    'branch.write',
                    'bus.view',
                    'bus.write',
                    'route.view',
                    'route.write',
                    'trip.view',
                    'trip.write',
                    'fare.view',
                    'fare.write',
                    'reservation.view',
                    'reservation.write',
                    'ticket.view',
                    'ticket.write',
                    'ticket.refund',
                    'ticket.print',
                    'shipment.view',
                    'shipment.write',
                    'payment.view',
                    'payment.write',
                    'payment.confirm',
                    'cash.view',
                    'cash.write',
                    'expense.view',
                    'expense.write',
                    'report.view',
                    'user.view',
                    'user.write',
                    'role.view',
                    'subscription.view',
                    'subscription.write'
                ]
            ],
            'ROLE_MANAGER' => [
                'scope' => self::SCOPE_AGENCY,
                'description' => 'Pilotage de l\'exploitation : flotte, lignes, voyages, tarifs, ventes et dépenses.',
                'permissions' => [
                    'dashboard.view',
                    'branch.view',
                    'bus.view',
                    'bus.write',
                    'route.view',
                    'route.write',
                    'trip.view',
                    'trip.write',
                    'fare.view',
                    'reservation.view',
                    'reservation.write',
                    'ticket.view',
                    'ticket.write',
                    'ticket.refund',
                    'ticket.print',
                    'shipment.view',
                    'shipment.write',
                    'payment.view',
                    'payment.write',
                    'payment.confirm',
                    'cash.view',
                    'cash.write',
                    'expense.view',
                    'expense.write',
                    'report.view',
                    'user.view',
                    'role.view'
                ]
            ],
            'ROLE_CASHIER' => [
                'scope' => self::SCOPE_AGENCY,
                'description' => 'Vente de billets, encaissement des colis et tenue de la caisse.',
                'permissions' => [
                    'dashboard.view',
                    'trip.view',
                    'reservation.view',
                    'reservation.write',
                    'ticket.view',
                    'ticket.write',
                    'ticket.print',
                    'shipment.view',
                    'shipment.write',
                    'payment.view',
                    'payment.write',
                    'payment.confirm',
                    'cash.view',
                    'cash.write'
                ]
            ],
            'ROLE_CONTROLLER' => [
                'scope' => self::SCOPE_AGENCY,
                'description' => 'Vérification des billets et des places à l\'embarquement.',
                'permissions' => ['trip.view', 'reservation.view', 'ticket.view']
            ],
            'ROLE_ACCOUNTANT' => [
                'scope' => self::SCOPE_AGENCY,
                'description' => 'Audit financier : paiements, caisse, dépenses et rapports.',
                'permissions' => [
                    'dashboard.view',
                    'payment.view',
                    'cash.view',
                    'expense.view',
                    'expense.write',
                    'report.view',
                    'ticket.view',
                    'shipment.view'
                ]
            ],
            'ROLE_DISPATCHER' => [
                'scope' => self::SCOPE_AGENCY,
                'description' => 'Planification des voyages et affectation des bus.',
                'permissions' => ['dashboard.view', 'bus.view', 'route.view', 'trip.view', 'trip.write', 'fare.view', 'reservation.view']
            ],
            'ROLE_DRIVER' => [
                'scope' => self::SCOPE_AGENCY,
                'description' => 'Conduite des voyages : consultation du planning.',
                'permissions' => ['trip.view']
            ],
            'ROLE_SUPER_ADMIN' => [
                'scope' => self::SCOPE_PLATFORM,
                'description' => 'Gestion complète de la plateforme SaaS MyDigitrans.',
                'permissions' => $platformPermissions
            ],
            'ROLE_PLATFORM_ACCOUNTANT' => [
                'scope' => self::SCOPE_PLATFORM,
                'description' => 'Facturation, commissions, caisse et dépenses de la plateforme.',
                'permissions' => [
                    'dashboard.view',
                    'agency.view',
                    'partner.view',
                    'plan.view',
                    'invoice.view',
                    'invoice.write',
                    'commission.view',
                    'commission.write',
                    'cash.view',
                    'cash.write',
                    'expense.view',
                    'expense.write',
                    'report.view'
                ]
            ],
            'ROLE_PLATFORM_CASHIER' => [
                'scope' => self::SCOPE_PLATFORM,
                'description' => 'Encaissement des abonnements et tenue de la caisse plateforme.',
                'permissions' => ['dashboard.view', 'invoice.view', 'cash.view', 'cash.write', 'expense.view']
            ],
            'ROLE_PLATFORM_SUPPORT' => [
                'scope' => self::SCOPE_PLATFORM,
                'description' => 'Accompagnement applicatif des agences et des partenaires.',
                'permissions' => ['dashboard.view', 'agency.view', 'agency.write', 'partner.view', 'notification.view', 'notification.write', 'user.view']
            ],
        ];

        // Catalogue d'iconographie d'usine par module applicatif
        $moduleIcons = [
            'dashboard'    => 'fa-chart-pie',
            'branch'       => 'fa-code-branch',
            'bus'          => 'fa-bus-simple',
            'route'        => 'fa-route',
            'trip'         => 'fa-calendar-days',
            'fare'         => 'fa-tags',
            'reservation'  => 'fa-receipt',
            'ticket'       => 'fa-ticket',
            'shipment'     => 'fa-box',
            'payment'      => 'fa-credit-card',
            'cash'         => 'fa-cash-register',
            'expense'      => 'fa-wallet',
            'report'       => 'fa-file-invoice-dollar',
            'user'         => 'fa-users',
            'role'         => 'fa-shield-halved',
            'subscription' => 'fa-key',
            'agency'       => 'fa-building',
            'partner'      => 'fa-handshake',
            'plan'         => 'fa-cubes',
            'invoice'      => 'fa-file-invoice',
            'commission'   => 'fa-percent',
            'notification' => 'fa-bell',
        ];


        // =========================================================================
        // 2. SYNCHRONISATION DE LA TABLE "6. Permission (system)"
        // =========================================================================
        $io->section('1. Synchronisation des permissions system (MCD Table 6)');
        $permissionEntities = [];

        // Traitement de la portée Agence
        foreach ($agencyPermissions as $code) {
            // Le code stocké en BDD devient unique en combinant le scope et l'action
            $uniqueDbCode = self::SCOPE_AGENCY->value . ':' . $code;

            $permission = $this->em->getRepository(Permission::class)->findOneBy(['code' => $uniqueDbCode]);
            if (!$permission) {
                $moduleName = explode('.', $code)[0];
                $icon = $moduleIcons[$moduleName] ?? 'fa-circle-dot';

                $permission = new Permission();
                $permission->setCode($uniqueDbCode); // <-- Stockage sécurisé et unique (ex: agency:dashboard.view)
                $permission->setScope(self::SCOPE_AGENCY);
                $permission->setModule($moduleName);
                $permission->setIcon($icon);
                $permission->setName(sprintf('Action %s [Agence]', $code));
                $permission->setCreatedBy($robot);
                $this->em->persist($permission);
            }
            $permissionEntities['agency_' . $code] = $permission;
        }

        // Traitement de la portée Plateforme
        foreach ($platformPermissions as $code) {
            // Le code stocké en BDD devient unique en combinant le scope et l'action
            $uniqueDbCode = self::SCOPE_PLATFORM->value . ':' . $code;

            $permission = $this->em->getRepository(Permission::class)->findOneBy(['code' => $uniqueDbCode]);
            if (!$permission) {
                $moduleName = explode('.', $code)[0];
                $icon = $moduleIcons[$moduleName] ?? 'fa-circle-dot';

                $permission = new Permission();
                $permission->setCode($uniqueDbCode); // <-- Stockage sécurisé et unique (ex: platform:dashboard.view)
                $permission->setScope(self::SCOPE_PLATFORM);
                $permission->setModule($moduleName);
                $permission->setIcon($icon);
                $permission->setName(sprintf('Action %s [Plateforme]', $code));
                $permission->setCreatedBy($robot);
                $this->em->persist($permission);
            }
            $permissionEntities['platform_' . $code] = $permission;
        }
        $this->em->flush();
        $io->writeln('✓ Permissions système synchronisées de manière unique.');



        // =========================================================================
        // 3. SYNCHRONISATION DE LA TABLE ASSOCIATIVE "7. rolePermission"
        // =========================================================================
        $io->section('2. Synchronisation de la matrice des droits (MCD Table 4 et 7)');
        foreach ($rolesCatalog as $currentRoleCode => $meta) {
            $role = $this->em->getRepository(Role::class)->findOneBy([
                'code' => $currentRoleCode,
                'scope' => $meta['scope']
            ]);
            if (!$role) {
                $role = new Role();
                $role->setCode($currentRoleCode);
                $role->setScope($meta['scope']);
                $role->setIsActive(true);
            }
            $role->setName(str_replace('ROLE_', '', $currentRoleCode));
            $role->setDescription($meta['description']);
            $this->em->persist($role);
            $this->em->flush();
            // Nettoyage complet des anciennes liaisons d'usine pour reconstruire la matrice
            $oldRelations = $this->em->getRepository(RolePermission::class)->findBy(['role' => $role]);
            foreach ($oldRelations as $oldRelation) {
                $this->em->remove($oldRelation);
            }
            $this->em->flush();
            // Affectation des liaisons fraîches
            foreach ($meta['permissions'] as $permCode) {
                $permKey = ($meta['scope'] === self::SCOPE_AGENCY ? 'agency_' : 'platform_') . $permCode;
                if (isset($permissionEntities[$permKey])) {
                    $rolePermission = new RolePermission();
                    $rolePermission->setRole($role);
                    $rolePermission->setPermission($permissionEntities[$permKey]);
                    $rolePermission->setAssignedBy($robot); // <-- Renseigne la colonne obligatoire 'assignedBy' avec le robot ! [MCD 7]
                    $rolePermission->setAgency(null);
                    $rolePermission->setBranch(null);
                    $this->em->persist($rolePermission);
                }
            }
            $this->em->flush();
        }
        $io->success('Félicitations Daniel ! La matrice des rôles et des droits d\'usine est initialisée à 100 % sous la signature de votre SystemActor.');
        return Command::SUCCESS;
    }
}
