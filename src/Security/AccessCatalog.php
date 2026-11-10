<?php

declare(strict_types=1);

namespace App\Security;

use App\Enum\Scope;

/**
 * Catalogue des permissions et des rôles du système, séparés par portée (agence / plateforme).
 *
 * Les rôles sont FIXÉS PAR LE SYSTÈME : ni les agences ni la plateforme n'en créent. Ce catalogue est la source
 * de vérité, copiée en base par la commande app:access:seed (idempotente). Un utilisateur reçoit un rôle de sa
 * portée via UserRole ; ses permissions sont celles de ce rôle (RolePermission).
 *
 * Codes de permission : « module.action », par exemple ticket.view, user.write.
 */
final class AccessCatalog
{
    /** Rôle d'administration de chaque portée : protégé (dernier administrateur, attribution réservée). */
    public const AGENCY_ADMIN = 'ROLE_AGENCY_ADMIN';
    public const SUPER_ADMIN = 'ROLE_SUPER_ADMIN';

    /** Mêmes droits que l'administrateur d'agence, sans pouvoir gérer les administrateurs. */
    public const GENERAL_MANAGER = 'ROLE_GENERAL_MANAGER';

    private const ACTION_LABELS = [
        'view' => 'Consulter',
        'write' => 'Créer / modifier',
        'configure' => 'Configurer / Activer', // 🟢 Label sémantique d'agence
        'refund' => 'Rembourser',
        'print' => 'Imprimer',
        'confirm' => 'Confirmer',
        'activate' => 'Activer / bloquer',
    ];

    /** module => [libellé, icône, actions] */
    private const AGENCY_MODULES = [
        'dashboard' => ['Tableau de bord', 'fa-gauge', ['view']],
        'branch' => ['Succursales', 'fa-folder-tree', ['view', 'write']],
        'bus' => ['Flotte (Bus)', 'fa-bus-simple', ['view', 'write']],
        'route' => ['Lignes', 'fa-route', ['view', 'write']],
        'trip' => ['Voyages', 'fa-calendar-days', ['view', 'write']],
        'fare' => ['Tarifs', 'fa-tags', ['view', 'write']],
        'reservation' => ['Réservations', 'fa-file-circle-check', ['view', 'write']],
        'ticket' => ['Billets', 'fa-ticket', ['view', 'write', 'refund', 'print']],
        'shipment' => ['Colis (Fret)', 'fa-box-open', ['view', 'write']],
        'payment' => ['Paiements', 'fa-money-bill', ['view', 'write', 'confirm']],
        'cash' => ['Caisse', 'fa-cash-register', ['view', 'write']],

        // 🟢 CÔTÉ AGENCE : Uniquement le droit de voir et de configurer / activer localement (Pas de write)
        'currency' => ['Devises & Taux', 'fa-money-bill-transfer', ['view', 'configure']],

        'expense' => ['Dépenses', 'fa-receipt', ['view', 'write']],
        'report' => ['Rapports', 'fa-chart-line', ['view']],
        'user' => ['Utilisateurs', 'fa-users', ['view', 'write', 'activate']],
        'role' => ['Fonctions et droits', 'fa-user-shield', ['view']],
        'subscription' => ['Abonnement', 'fa-id-badge', ['view', 'write']],
    ];

    private const PLATFORM_MODULES = [
        'dashboard' => ['Tableau de bord', 'fa-gauge', ['view']],
        'agency' => ['Agences', 'fa-city', ['view', 'write']],
        'partner' => ['Partenaires', 'fa-handshake', ['view', 'write']],
        'plan' => ['Plans d\'abonnement', 'fa-id-badge', ['view', 'write']],
        'invoice' => ['Facturation', 'fa-file-invoice', ['view', 'write']],
        'commission' => ['Commissions', 'fa-percent', ['view', 'write']],
        'cash' => ['Caisse plateforme', 'fa-cash-register', ['view', 'write']],

        // 🟢 CÔTÉ PLATEFORME : Le Super Admin a le droit exclusif de créer les devises usines globales
        'currency' => ['Catalogue Devises', 'fa-money-bill-transfer', ['view', 'write']],

        'expense' => ['Dépenses plateforme', 'fa-receipt', ['view', 'write']],
        'notification' => ['Notifications', 'fa-bell', ['view', 'write']],
        'report' => ['Rapports', 'fa-chart-line', ['view']],
        'user' => ['Utilisateurs', 'fa-users', ['view', 'write', 'activate']],
        'role' => ['Fonctions et droits', 'fa-user-shield', ['view', 'write']],
    ];

    /** code du rôle => [libellé, description, motifs de permissions : '*', 'module.*' ou 'module.action'] */
    private const AGENCY_ROLES = [
        self::AGENCY_ADMIN => ['Administrateur d\'agence', 'Gestion complète de l\'agence et de son personnel.', ['*']],
        self::GENERAL_MANAGER => ['General Manager', 'Tous les accès de l\'administrateur, sans pouvoir gérer les administrateurs.', ['*']],
        'ROLE_MANAGER' => [
            'Gestionnaire',
            'Pilotage de l\'exploitation : flotte, lignes, voyages, tarifs, ventes et dépenses.',
            ['dashboard.view', 'branch.view', 'bus.*', 'route.*', 'trip.*', 'fare.*', 'reservation.*', 'ticket.*', 'shipment.*', 'payment.*', 'cash.*', 'currency.view', 'expense.*', 'report.view', 'user.view', 'user.write', 'role.view']
        ],
        'ROLE_CASHIER' => [
            'Caissier / Guichetier',
            'Vente de billets, encaissement des colis et tenue de la caisse.',
            ['dashboard.view', 'trip.view', 'reservation.*', 'ticket.view', 'ticket.write', 'ticket.print', 'shipment.*', 'payment.*', 'cash.*']
        ],
        'ROLE_CONTROLLER' => [
            'Contrôleur de bord',
            'Vérification des billets et des places à l\'embarquement.',
            ['trip.view', 'reservation.view', 'ticket.view']
        ],
        'ROLE_ACCOUNTANT' => [
            'Comptable d\'agence',
            'Audit financier : paiements, caisse, dépenses et rapports.',
            // 🟢 Le comptable d'agence a le droit de voir le catalogue et de configurer / fixer le taux journalier
            ['dashboard.view', 'payment.view', 'cash.view', 'currency.view', 'currency.configure', 'expense.*', 'report.view', 'ticket.view', 'shipment.view']
        ],
        'ROLE_DISPATCHER' => [
            'Dispatcheur',
            'Planification des voyages et affectation des bus.',
            ['dashboard.view', 'bus.view', 'route.view', 'trip.*', 'fare.view', 'reservation.view']
        ],
        'ROLE_DRIVER' => ['Chauffeur', 'Conduite des voyages : consultation du planning.', ['trip.view']],
    ];

    private const PLATFORM_ROLES = [
        self::SUPER_ADMIN => ['Super administrateur', 'Gestion complète de la plateforme.', ['*']],
        'ROLE_PLATFORM_ACCOUNTANT' => [
            'Comptable plateforme',
            'Facturation, commissions, caisse et dépenses de la plateforme.',
            ['dashboard.view', 'agency.view', 'partner.view', 'plan.view', 'invoice.*', 'commission.*', 'cash.*', 'currency.view', 'expense.*', 'report.view']
        ],
        'ROLE_PLATFORM_CASHIER' => [
            'Caissier plateforme',
            'Encaissement des abonnements et tenue de la caisse plateforme.',
            ['dashboard.view', 'invoice.view', 'cash.*', 'expense.view']
        ],
        'ROLE_PLATFORM_SUPPORT' => [
            'Support plateforme',
            'Accompagnement des agences et des partenaires.',
            ['dashboard.view', 'agency.*', 'partner.view', 'currency.view', 'notification.*', 'user.view']
        ],
    ];

    public static function isAdminRole(?string $code): bool
    {
        return \in_array($code, [self::AGENCY_ADMIN, self::SUPER_ADMIN], true);
    }

    public static function protectedRole(Scope $scope): string
    {
        return Scope::Platform === $scope ? self::SUPER_ADMIN : self::AGENCY_ADMIN;
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: list<string>}>
     */
    public static function modules(Scope $scope): array
    {
        return Scope::Platform === $scope ? self::PLATFORM_MODULES : self::AGENCY_MODULES;
    }

    /**
     * @return array{label: string, icon: string}
     */
    public static function moduleMeta(Scope $scope, string $module): array
    {
        $meta = self::modules($scope)[$module] ?? null;

        return ['label' => $meta[0] ?? ucfirst($module), 'icon' => $meta[1] ?? 'fa-circle'];
    }

    /**
     * @return array<string, array{name: string, module: string, icon: string, description: string}> code => fiche
     */
    public static function permissions(Scope $scope): array
    {
        $out = [];

        foreach (self::modules($scope) as $module => [$label, $icon, $actions]) {
            foreach ($actions as $action) {
                $actionLabel = self::ACTION_LABELS[$action] ?? $action;
                $out[$module . '.' . $action] = [
                    'name' => $label . ' : ' . $actionLabel,
                    'module' => $module,
                    'icon' => $icon,
                    'description' => sprintf('%s — %s.', $actionLabel, mb_strtolower($label)),
                ];
            }
        }

        return $out;
    }

    /**
     * @return array<string, array{name: string, description: string, permissions: list<string>}> code du rôle => fiche
     */
    public static function roles(Scope $scope): array
    {
        $all = array_keys(self::permissions($scope));
        $definitions = Scope::Platform === $scope ? self::PLATFORM_ROLES : self::AGENCY_ROLES;
        $out = [];

        foreach ($definitions as $code => [$name, $description, $patterns]) {
            $granted = [];

            foreach ($patterns as $pattern) {
                if ('*' === $pattern) {
                    $matches = $all;
                } elseif (str_ends_with($pattern, '.*')) {
                    $prefix = substr($pattern, 0, -1);
                    $matches = array_values(array_filter($all, static fn(string $c): bool => str_starts_with($c, $prefix)));
                } else {
                    $matches = \in_array($pattern, $all, true) ? [$pattern] : [];
                }

                if ([] === $matches) {
                    throw new \LogicException(sprintf('Le motif « %s » du rôle %s ne correspond à aucune permission.', $pattern, $code));
                }

                $granted = array_merge($granted, $matches);
            }

            $out[$code] = ['name' => $name, 'description' => $description, 'permissions' => array_values(array_unique($granted))];
        }
        return $out;
    }
}
