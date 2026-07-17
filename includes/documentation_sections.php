<?php
/**
 * Contenu détaillé de la documentation publique SeSanté.
 */

if (!function_exists('doc_module_groups')) {
    function doc_module_groups(): array
    {
        return [
            [
                'title' => 'Gestion clinique',
                'icon' => 'fa-heartbeat',
                'items' => [
                    [
                        'name' => 'Patients & dossiers',
                        'icon' => 'fa-user-injured',
                        'desc' => 'Fiches patients, recherche avancée, historique médical, documents numériques (PDF, imagerie), export dossier PDF, archivage et restauration.',
                    ],
                    [
                        'name' => 'Dossiers patients',
                        'icon' => 'fa-folder-open',
                        'desc' => 'Module dédié : création, suivi, recherche et archivage formalisé des dossiers avec statuts et priorités.',
                    ],
                    [
                        'name' => 'Médecins',
                        'icon' => 'fa-user-md',
                        'desc' => 'Annuaire des praticiens, spécialités, affectation aux consultations et rendez-vous.',
                    ],
                    [
                        'name' => 'Consultations',
                        'icon' => 'fa-stethoscope',
                        'desc' => 'Actes médicaux, diagnostics, prescriptions, soins facturables, tickets d\'impression, tarification personnalisable.',
                    ],
                    [
                        'name' => 'Hospitalisation',
                        'icon' => 'fa-procedures',
                        'desc' => 'Gestion des séjours, catégories de lits, suivi journalier et facturation par jour depuis les consultations.',
                    ],
                    [
                        'name' => 'Rendez-vous',
                        'icon' => 'fa-calendar-check',
                        'desc' => 'Agenda, planification, calendrier visuel, rappels et suivi des statuts de rendez-vous.',
                    ],
                    [
                        'name' => 'Laboratoire',
                        'icon' => 'fa-flask',
                        'desc' => 'Analyses (sang, urine, imagerie), priorités, rapports PDF/HTML, suivi des résultats en temps réel.',
                    ],
                ],
            ],
            [
                'title' => 'Facturation & finances',
                'icon' => 'fa-coins',
                'items' => [
                    [
                        'name' => 'Paiements & facturation',
                        'icon' => 'fa-credit-card',
                        'badge' => 'Nouveau',
                        'desc' => 'Génération de paiement depuis une consultation ou une analyse labo, reçus PDF, écriture comptable automatique au statut Payé, verrouillage des encaissements et contre-passation en cas d\'annulation.',
                    ],
                    [
                        'name' => 'Finances & comptabilité',
                        'icon' => 'fa-calculator',
                        'desc' => 'Écritures comptables, plan de comptes, budgets, validation et rapports — devise FCFA.',
                    ],
                    [
                        'name' => 'Assurances & mutuelles',
                        'icon' => 'fa-shield-alt',
                        'desc' => 'Contrats d\'assurance, tiers payant et suivi des remboursements patients.',
                    ],
                    [
                        'name' => 'Tarifs & barèmes',
                        'icon' => 'fa-tags',
                        'desc' => 'Tarifs consultations, soins, catégories d\'hospitalisation configurables par l\'administrateur.',
                    ],
                ],
            ],
            [
                'title' => 'Opérations & ressources',
                'icon' => 'fa-cogs',
                'items' => [
                    [
                        'name' => 'Pharmacie & stocks',
                        'icon' => 'fa-pills',
                        'desc' => 'Médicaments, mouvements de stock, alertes de rupture et dates de péremption (module HIS).',
                    ],
                    [
                        'name' => 'PharmaPro ERP',
                        'icon' => 'fa-cash-register',
                        'badge' => 'Add-on bêta',
                        'desc' => 'ERP officine premium activé par l\'admin plateforme : POS scanner, achats, stock lots FEFO, comptabilité SYSCOHADA, RH, pont Finances HIS, API mobile caisse et rapports PDF.',
                    ],
                    [
                        'name' => 'Personnel & RH',
                        'icon' => 'fa-user-tie',
                        'desc' => 'Fiches employés, postes, départements, horaires et congés.',
                    ],
                    [
                        'name' => 'Communication interne',
                        'icon' => 'fa-comments',
                        'desc' => 'Messagerie entre équipes, annonces par rôle, notifications et badge messages non lus.',
                    ],
                    [
                        'name' => 'Maintenance & logistique',
                        'icon' => 'fa-tools',
                        'desc' => 'Équipements médicaux, interventions, alertes préventives et stocks matériel.',
                    ],
                ],
            ],
            [
                'title' => 'Intelligence & intégrations',
                'icon' => 'fa-brain',
                'items' => [
                    [
                        'name' => 'Diagnostic assisté (IA)',
                        'icon' => 'fa-robot',
                        'desc' => 'Suggestions de diagnostic par symptômes, analyse dermatologique, évaluation du risque patient.',
                    ],
                    [
                        'name' => 'Rapports & statistiques',
                        'icon' => 'fa-chart-line',
                        'desc' => 'Tableaux de bord, tendances, rapports personnalisés et indicateurs de performance.',
                    ],
                    [
                        'name' => 'API REST',
                        'icon' => 'fa-plug',
                        'desc' => 'Interface pour applications mobiles (Flutter, PWA React) : patients, RDV, consultations, laboratoire, stats et avis fonctionnalités (tenant/notices).',
                    ],
                    [
                        'name' => 'Application mobile & PWA',
                        'icon' => 'fa-mobile-alt',
                        'desc' => 'Interface tactile mobile, mode PWA installable, application React et client Flutter natif.',
                    ],
                ],
            ],
            [
                'title' => 'Administration établissement',
                'icon' => 'fa-sliders-h',
                'items' => [
                    [
                        'name' => 'Utilisateurs & rôles',
                        'icon' => 'fa-users-cog',
                        'desc' => 'Comptes par établissement, rôles prédéfinis, gestion des accès et quotas selon la licence.',
                    ],
                    [
                        'name' => 'Paramètres système',
                        'icon' => 'fa-cog',
                        'desc' => 'Identité (nom, logo), devise FCFA, langue, fuseau horaire (Africa/Bamako), thème.',
                    ],
                    [
                        'name' => 'Sauvegardes',
                        'icon' => 'fa-database',
                        'desc' => 'Sauvegarde base de données et fichiers, téléchargement, rétention configurable.',
                    ],
                    [
                        'name' => 'Journaux d\'audit',
                        'icon' => 'fa-clipboard-list',
                        'desc' => 'Traçabilité des actions utilisateurs, filtres et export des logs système.',
                    ],
                ],
            ],
        ];
    }

    function doc_module_count(): int
    {
        $n = 0;
        foreach (doc_module_groups() as $group) {
            $n += count($group['items']);
        }
        return $n;
    }

    function doc_user_roles(): array
    {
        require_once __DIR__ . '/roles.php';
        return array_map(static function (array $entry): array {
            return [
                'role'   => $entry['role'],
                'icon'   => $entry['icon'],
                'access' => $entry['access'],
            ];
        }, app_role_doc_entries());
    }

    function doc_workflows(): array
    {
        return [
            [
                'title' => 'Parcours patient',
                'icon' => 'fa-route',
                'steps' => [
                    'Enregistrement du patient et création du dossier médical',
                    'Prise de rendez-vous ou consultation directe',
                    'Consultation, prescription et éventuelle hospitalisation',
                    'Analyses laboratoire et suivi des résultats',
                    'Facturation, paiement et écriture comptable automatique',
                    'Archivage documents et export PDF du dossier',
                ],
            ],
            [
                'title' => 'Parcours consultation',
                'icon' => 'fa-stethoscope',
                'steps' => [
                    'Planification via agenda ou accueil direct',
                    'Saisie diagnostic, soins et actes facturables',
                    'Demande d\'analyses ou orientation hospitalisation',
                    'Impression ticket / ordonnance',
                    'Génération du paiement lié à la consultation',
                ],
            ],
            [
                'title' => 'Parcours facturation',
                'icon' => 'fa-file-invoice-dollar',
                'steps' => [
                    'Création du paiement depuis la fiche consultation ou analyse labo',
                    'Enregistrement paiement (espèces, Mobile Money, assurance)',
                    'Passage au statut Payé → écriture comptable automatique',
                    'Encaissement verrouillé — annulation par contre-passation (ERP)',
                    'Suivi des paiements en attente depuis le tableau de bord',
                ],
            ],
        ];
    }

    function doc_tech_features(): array
    {
        return [
            ['icon' => 'fa-lock', 'title' => 'Sécurité', 'text' => 'Sessions sécurisées, protection CSRF, limitation des tentatives de connexion, mots de passe hashés.'],
            ['icon' => 'fa-bell', 'title' => 'Notifications', 'text' => 'Alertes en temps réel par utilisateur et par rôle sur le tableau de bord.'],
            ['icon' => 'fa-bolt', 'title' => 'Cache intelligent', 'text' => 'Statistiques dashboard mises en cache pour des performances optimales.'],
            ['icon' => 'fa-file-pdf', 'title' => 'Exports PDF', 'text' => 'Dossiers patients, paiements, analyses labo, tickets — avec logo de l\'établissement.'],
            ['icon' => 'fa-globe-africa', 'title' => 'Localisation Mali', 'text' => 'FCFA, fuseau Africa/Bamako, interface en français.'],
            ['icon' => 'fa-tablet-alt', 'title' => 'Multi-appareils', 'text' => 'Bureau, tablette et smartphone — layout mobile automatique, PWA installable et apps Flutter/React.'],
            ['icon' => 'fa-sync-alt', 'title' => 'Sync Paiements · Finances', 'text' => 'Consultation ou analyse labo → paiement → écriture comptable au statut Payé. Feature activable par établissement.'],
        ];
    }

    /** Détails publics de la sync Paiements / Finances / Analyses (feature live). */
    function doc_payment_sync_public(): array
    {
        return [
            'title' => 'Synchronisation Paiements · Finances · Analyses',
            'tagline' => 'Consultation, labo, caisse et compta reliés en un seul flux.',
            'summary' => 'Lie consultations et analyses aux paiements, écritures comptables automatiques et encaissement unifié.',
            'steps' => [
                'Générez un paiement depuis une fiche consultation ou analyse laboratoire',
                'Au statut Payé, l\'écriture comptable est créée automatiquement dans Finances',
                'Les encaissements validés sont verrouillés — toute annulation produit une contre-passation',
                'Un bandeau « Nouveau » informe l\'équipe à la première connexion (web, PWA et app Flutter)',
            ],
            'flash' => [
                ['icon' => 'fa-stethoscope', 'title' => 'Depuis la consultation', 'text' => 'Générez le paiement en un clic depuis la fiche patient ou consultation.'],
                ['icon' => 'fa-flask', 'title' => 'Depuis le laboratoire', 'text' => 'Même flux pour les analyses : ticket caisse puis facturation.'],
                ['icon' => 'fa-book', 'title' => 'Comptabilité auto', 'text' => 'Au statut Payé, l\'écriture est créée dans Finances sans ressaisie.'],
                ['icon' => 'fa-shield-alt', 'title' => 'Encaissement sécurisé', 'text' => 'Paiements validés verrouillés ; annulation = contre-passation tracée.'],
            ],
            'activation' => 'Activée par l\'administrateur plateforme pour chaque établissement (Admin plateforme → Fonctionnalités).',
        ];
    }

    /**
     * Catalogue public des fonctionnalités plateforme (live, bêta, à venir).
     * @return list<array{key: string, label: string, description: string, status: string, status_label: string}>
     */
    function doc_platform_features_catalog(): array
    {
        require_once __DIR__ . '/saas/PlatformTenantFeatures.php';

        $statusLabels = [
            'live' => 'Disponible',
            'beta' => 'Bêta',
            'planned' => 'À venir',
        ];

        $out = [];
        foreach (PlatformTenantFeatures::catalog() as $item) {
            $status = $item['status'] ?? 'planned';
            $out[] = [
                'key' => $item['key'],
                'label' => $item['label'],
                'description' => $item['description'],
                'status' => $status,
                'status_label' => $statusLabels[$status] ?? 'À venir',
            ];
        }

        return $out;
    }
}
