<?php

declare(strict_types=1);

namespace App\Security;

use App\Enum\Scope;

/**
 * Source de vérité unique pour les rôles, permissions et la matrice de sécurité du système.
 */
final class AccessCatalog
{
    // Constantes des rôles clés pour l'initialisation du système
    public const SUPER_ADMIN = 'ROLE_SUPER_ADMIN';
    public const AGENCY_ADMIN = 'ROLE_AGENCY_ADMIN';

    /**
     * Retourne les définitions des permissions pour un scope donné (Agence ou Plateforme).
     *
     * @return array<string, array{name: string, module: string, icon: string, description: string}>
     */
    public static function permissions(Scope $scope): array
    {
        if ($scope === Scope::Agency) {
            return [
                // Tableau de bord
                'dashboard.view' => ['name' => 'Consulter le tableau de bord', 'module' => 'dashboard', 'icon' => 'fa-tachometer-alt', 'description' => 'Accès aux indicateurs généraux de l\'agence'],

                // Succursales
                'branch.view' => ['name' => 'Consulter les succursales', 'module' => 'branch', 'icon' => 'fa-building', 'description' => 'Voir la liste des gares et succursales'],
                'branch.write' => ['name' => 'Créer / Modifier les succursales', 'module' => 'branch', 'icon' => 'fa-edit', 'description' => 'Ajouter ou modifier des succursales'],

                // Flotte (Bus)
                'bus.view' => ['name' => 'Consulter la flotte', 'module' => 'bus', 'icon' => 'fa-bus', 'description' => 'Voir les bus disponibles'],
                'bus.write' => ['name' => 'Créer / Modifier la flotte', 'module' => 'bus', 'icon' => 'fa-plus-circle', 'description' => 'Ajouter ou modifier des bus de l\'agence'],

                // Lignes
                'route.view' => ['name' => 'Consulter les lignes', 'module' => 'route', 'icon' => 'fa-route', 'description' => 'Voir les trajets et itinéraires gérés'],
                'route.write' => ['name' => 'Créer / Modifier les lignes', 'module' => 'route', 'icon' => 'fa-road', 'description' => 'Gérer les itinéraires et distances'],

                // Voyages
                'trip.view' => ['name' => 'Consulter les voyages', 'module' => 'trip', 'icon' => 'fa-calendar-alt', 'description' => 'Voir le planning des départs'],
                'trip.write' => ['name' => 'Créer / Modifier les voyages', 'module' => 'trip', 'icon' => 'fa-calendar-plus', 'description' => 'Planifier des trajets et affecter des bus'],

                // Tarifs
                'fare.view' => ['name' => 'Consulter les tarifs', 'module' => 'fare', 'icon' => 'fa-money-bill-wave', 'description' => 'Voir la grille tarifaire'],
                'fare.write' => ['name' => 'Créer / Modifier les tarifs', 'module' => 'fare', 'icon' => 'fa-dollar-sign', 'description' => 'Modifier les prix et les types de tarifs'],

                // Réservations
                'reservation.view' => ['name' => 'Consulter les réservations', 'module' => 'reservation', 'icon' => 'fa-book-open', 'description' => 'Voir l\'état des réservations en attente ou validées'],
                'reservation.write' => ['name' => 'Créer / Modifier les réservations', 'module' => 'reservation', 'icon' => 'fa-bookmark', 'description' => 'Effectuer ou modifier une réservation de place'],

                // Billets
                'ticket.view' => ['name' => 'Consulter les billets', 'module' => 'ticket', 'icon' => 'fa-ticket-alt', 'description' => 'Rechercher et lister les billets émis'],
                'ticket.write' => ['name' => 'Créer / Modifier les billets', 'module' => 'ticket', 'icon' => 'fa-cart-plus', 'description' => 'Vendre un billet au guichet'],
                'ticket.refund' => ['name' => 'Rembourser les billets', 'module' => 'ticket', 'icon' => 'fa-undo', 'description' => 'Annuler un billet et procéder au remboursement'],
                'ticket.print' => ['name' => 'Imprimer les billets', 'module' => 'ticket', 'icon' => 'fa-print', 'description' => 'Générer et imprimer le ticket physique officiel'],

                // Colis (Fret)
                'shipment.view' => ['name' => 'Consulter les colis', 'module' => 'shipment', 'icon' => 'fa-box', 'description' => 'Suivre et rechercher des expéditions de marchandises'],
                'shipment.write' => ['name' => 'Créer / Modifier les colis', 'module' => 'shipment', 'icon' => 'fa-boxes', 'description' => 'Enregistrer un nouveau colis ou valider son retrait'],

                // Paiements
                'payment.write' => ['name' => 'Créer / Modifier les paiements', 'module' => 'payment', 'icon' => 'fa-receipt', 'description' => 'Enregistrer un nouveau paiement'],
                'payment.view' => ['name' => 'Consulter les paiements', 'module' => 'payment', 'icon' => 'fa-receipt', 'description' => 'Voir l\'historique des transactions financières'],
                'payment.confirm' => ['name' => 'Confirmer les paiements', 'module' => 'payment', 'icon' => 'fa-check-double', 'description' => 'Valider la réception effective d\'un paiement'],

                // Caisse
                'cash.view' => ['name' => 'Consulter la caisse', 'module' => 'cash', 'icon' => 'fa-cash-register', 'description' => 'Voir les guichets et états des sessions'],
                'cash.write' => ['name' => 'Créer / Modifier la caisse', 'module' => 'cash', 'icon' => 'fa-calculator', 'description' => 'Gérer les sessions de caisse (ouverture / clôture)'],

                // Dépenses
                'expense.view' => ['name' => 'Consulter les dépenses', 'module' => 'expense', 'icon' => 'fa-wallet', 'description' => 'Voir les sorties de fonds'],
                'expense.write' => ['name' => 'Créer / Modifier les dépenses', 'module' => 'expense', 'icon' => 'fa-hand-holding-usd', 'description' => 'Enregistrer des dépenses opérationnelles'],

                // Rapports
                'report.view' => ['name' => 'Consulter les rapports', 'module' => 'report', 'icon' => 'fa-chart-bar', 'description' => 'Accéder aux audits et statistiques financières de l\'agence'],

                // Utilisateurs
                'user.view' => ['name' => 'Consulter les utilisateurs', 'module' => 'user', 'icon' => 'fa-users', 'description' => 'Voir la liste du personnel de l\'agence'],
                'user.write' => ['name' => 'Créer / Modifier les utilisateurs', 'module' => 'user', 'icon' => 'fa-user-plus', 'description' => 'Ajouter ou éditer les comptes agents'],

                // Fonctions et droits
                'role.view' => ['name' => 'Consulter les fonctions', 'module' => 'role', 'icon' => 'fa-user-shield', 'description' => 'Consulter les permissions fixées du système'],

                // Abonnement
                'subscription.view' => ['name' => 'Consulter l\'abonnement', 'module' => 'subscription', 'icon' => 'fa-key', 'description' => 'Voir l\'état de la licence SaaS d\'agence'],
                'subscription.write' => ['name' => 'Créer / Modifier l\'abonnement', 'module' => 'subscription', 'icon' => 'fa-file-invoice-dollar', 'description' => 'Initier ou modifier un abonnement de l\'agence'],
            ];
        }

        return [
            // Portée Plateforme
            'dashboard.view' => ['name' => 'Consulter le tableau de bord plateforme', 'module' => 'dashboard', 'icon' => 'fa-chart-line', 'description' => 'Indicateurs de performance globaux du SaaS'],
            'agency.view' => ['name' => 'Consulter les agences', 'module' => 'agency', 'icon' => 'fa-landmark', 'description' => 'Voir la liste des agences clientes'],
            'agency.write' => ['name' => 'Créer / Modifier les agences', 'module' => 'agency', 'icon' => 'fa-plus-square', 'description' => 'Enregistrer ou suspendre des agences'],
            'partner.view' => ['name' => 'Consulter les partenaires', 'module' => 'partner', 'icon' => 'fa-handshake', 'description' => 'Voir la liste des apporteurs d\'affaires'],
            'partner.write' => ['name' => 'Créer / Modifier les partenaires', 'module' => 'partner', 'icon' => 'fa-user-check', 'description' => 'Gérer les profils partenaires'],
            'plan.view' => ['name' => 'Consulter les plans d\'abonnement', 'module' => 'plan', 'icon' => 'fa-th-list', 'description' => 'Voir les formules de souscription disponibles'],
            'plan.write' => ['name' => 'Créer / Modifier les plans d\'abonnement', 'module' => 'plan', 'icon' => 'fa-stream', 'description' => 'Éditer les prix et plafonds des plans'],
            'invoice.view' => ['name' => 'Consulter la facturation plateforme', 'module' => 'invoice', 'icon' => 'fa-file-invoice', 'description' => 'Voir les factures de licences émises'],
            'invoice.write' => ['name' => 'Créer / Modifier la facturation plateforme', 'module' => 'invoice', 'icon' => 'fa-file-signature', 'description' => 'Gérer l\'état des facturations'],
            'commission.view' => ['name' => 'Consulter les commissions', 'module' => 'commission', 'icon' => 'fa-percentage', 'description' => 'Suivre les gains des apporteurs d\'affaires'],
            'commission.write' => ['name' => 'Créer / Modifier les commissions', 'module' => 'commission', 'icon' => 'fa-coins', 'description' => 'Approuver ou traiter les paiements de commissions'],
            'cash.view' => ['name' => 'Consulter la caisse plateforme', 'module' => 'cash', 'icon' => 'fa-vault', 'description' => 'Suivre les encaissements centraux de la plateforme'],
            'cash.write' => ['name' => 'Créer / Modifier la caisse plateforme', 'module' => 'cash', 'icon' => 'fa-calculator', 'description' => 'Tenue des écritures comptables de la plateforme'],
            'expense.view' => ['name' => 'Consulter les dépenses plateforme', 'module' => 'expense', 'icon' => 'fa-balance-scale-left', 'description' => 'Voir les charges d\'infrastructure de la plateforme'],

            'expense.write' => ['name' => 'Créer / Modifier les dépenses plateforme', 'module' => 'expense', 'icon' => 'fa-credit-card', 'description' => 'Ajouter des charges d\'exploitation plateforme'],
            'notification.view' => ['name' => 'Consulter les notifications', 'module' => 'notification', 'icon' => 'fa-bell', 'description' => 'Voir l\'état des envois de notifications'],
            'notification.write' => ['name' => 'Créer / Modifier les notifications', 'module' => 'notification', 'icon' => 'fa-paper-plane', 'description' => 'Émettre des alertes ou éditer les templates'],
            'report.view' => ['name' => 'Consulter les rapports globaux', 'module' => 'report', 'icon' => 'fa-analytics', 'description' => 'Analyses financières consolidées du SaaS'],
            'user.view' => ['name' => 'Consulter les utilisateurs plateforme', 'module' => 'user', 'icon' => 'fa-users-cog', 'description' => 'Voir l\'équipe technique et support'],
            'user.write' => ['name' => 'Créer / Modifier les utilisateurs plateforme', 'module' => 'user', 'icon' => 'fa-user-shield', 'description' => 'Gérer les comptes de la plateforme'],
            'role.view' => ['name' => 'Consulter les rôles plateforme', 'module' => 'role', 'icon' => 'fa-shield-alt', 'description' => 'Consulter l\'arborescence des droits plateforme'],
        ];
    }//permissions


    /**
     * Retourne la matrice d'attribution des permissions associées à chaque rôle pour un scope donné.
     *
     * @return array<string, array{name: string, description: string, permissions: list}>
     */
    public static function roles(Scope $scope): array
    {
        if ($scope === Scope::Agency) {
            return [
                self::AGENCY_ADMIN => [
                    'name' => 'Administrateur d\'agence, Le proprio',
                    'description' => 'Gestion complète de l\'agence et de son personnel. Toutes les permissions.',
                    'permissions' => array_keys(self::permissions(Scope::Agency)),
                ],

                'ROLE_GENERAL_MANAGER' => [
                    'scope' => 'agency',
                    'description' => 'Directeur Général / Directeur d\'Exploitation. Supervision globale sans modification de l\'administrateur.',
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
                        'fare.write', // Le GM a le droit de piloter la stratégie tarifaire
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
                        'user.write', // Peut gérer le personnel
                        'role.view', // Peut consulter les fonctions, mais PAS 'role.write'
                        'subscription.view',
                        'subscription.write' // Peut réapprovisionner l'infrastructure SaaS
                    ]
                ],


                'ROLE_MANAGER' => [
                    'name' => 'Gestionnaire',
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
                        'payment.write',
                        'payment.view',
                        'payment.confirm',
                        'cash.view',
                        'cash.write',
                        'expense.view',
                        'expense.write',
                        'report.view',
                        'user.view',
                        'role.view'
                    ],
                ],
                'ROLE_CASHIER' => [
                    'name' => 'Caissier / Guichetier',
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
                        'payment.write',
                        'payment.view',
                        'payment.confirm',
                        'cash.view',
                        'cash.write'
                    ],
                ],
                'ROLE_CONTROLLER' => [
                    'name' => 'Contrôleur de bord',
                    'description' => 'Vérification des billets et des places à l\'embarquement.',
                    'permissions' => ['trip.view', 'reservation.view', 'ticket.view', 'shipment.view'],
                ],
                'ROLE_ACCOUNTANT' => [
                    'name' => 'Comptable d\'agence',
                    'description' => 'Audit financier : paiements, caisse, dépenses et rapports.',
                    'permissions' => [
                        'dashboard.view',
                        'ticket.view',
                        'shipment.view',
                        'payment.view',
                        'cash.view',
                        'expense.view',
                        'expense.write',
                        'report.view'
                    ],
                ],
                'ROLE_DISPATCHER' => [
                    'name' => 'Dispatcheur',
                    'description' => 'Planification des voyages et affectation des bus.',
                    'permissions' => ['dashboard.view', 'bus.view', 'route.view', 'trip.view', 'trip.write', 'fare.view', 'reservation.view'],
                ],
                'ROLE_DRIVER' => [
                    'name' => 'Chauffeur',
                    'description' => 'Conduite des voyages : consultation du planning.',
                    'permissions' => ['trip.view'],
                ],
            ];
        }
        return [
            self::SUPER_ADMIN => [
                'name' => 'Super administrateur',
                'description' => 'Gestion complète de la plateforme. Toutes les permissions.',
                'permissions' => array_keys(self::permissions(Scope::Platform)),
            ],
            'ROLE_PLATFORM_ACCOUNTANT' => [
                'name' => 'Comptable plateforme',
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
                ],
            ],
            'ROLE_PLATFORM_CASHIER' => [
                'name' => 'Caissier plateforme',
                'description' => 'Encaissement des abonnements et tenue de la caisse plateforme.',
                'permissions' => ['dashboard.view', 'invoice.view', 'cash.view', 'cash.write', 'expense.view'],
            ],
            'ROLE_PLATFORM_SUPPORT' => [
                'name' => 'Support plateforme',
                'description' => 'Accompagnement des agences et des partenaires.',
                'permissions' => ['dashboard.view', 'agency.view', 'agency.write', 'partner.view', 'notification.view', 'notification.write', 'user.view'],
            ],
        ];
    }
}
