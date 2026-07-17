<?php
/**
 * Rôles métier — source unique de vérité (modules, UI, validation, documentation).
 *
 * Note multi-tenant : les slugs de rôles sont globaux à l'application ; l'isolation
 * des utilisateurs et des données se fait via tenant_id (session + TenantScope /
 * Utilisateur::tenantFilter), pas via des rôles différents par établissement.
 */

if (!defined('APP_ROLE_LABELS')) {
    define('APP_ROLE_LABELS', [
        'admin'       => 'Administrateur',
        'medecin'     => 'Médecin',
        'sage_femme'  => 'Sage-femme',
        'infirmier'   => 'Infirmier(ère)',
        'secretaire'  => 'Secrétaire',
        'comptable'   => 'Comptable',
        'pharmacien'  => 'Pharmacien',
        'pharma_manager' => 'Gérant PharmaPro',
        'pharma_cashier' => 'Caissier PharmaPro',
        'laborantin'  => 'Laborantin',
        'major'       => 'Major',
        'technicien'  => 'Technicien',
    ]);
}

if (!defined('MODULE_ROLES')) {
    define('MODULE_ROLES', [
        'patients'      => ['admin', 'medecin', 'sage_femme', 'infirmier', 'secretaire'],
        'medecins'      => ['admin', 'medecin', 'sage_femme', 'infirmier', 'laborantin', 'pharmacien', 'technicien'],
        'rdv'           => ['admin', 'medecin', 'sage_femme', 'secretaire'],
        'consultations' => ['admin', 'medecin', 'sage_femme'],
        'laboratoire'   => ['admin', 'medecin', 'sage_femme', 'infirmier', 'laborantin', 'major'],
        'paiements'     => ['admin', 'secretaire', 'comptable'],
        'personnel'     => ['admin', 'secretaire'],
        'pharmacie'     => ['admin', 'medecin', 'pharmacien'],
        'pharma_erp'    => ['admin', 'pharmacien', 'comptable', 'pharma_manager', 'pharma_cashier'],
        'finances'      => ['admin', 'comptable', 'secretaire'],
        'assurances'    => ['admin', 'secretaire'],
        'communication' => ['admin', 'medecin', 'sage_femme', 'infirmier', 'secretaire', 'comptable', 'pharmacien', 'laborantin', 'major', 'technicien'],
        'maintenance'   => ['admin', 'technicien'],
        'dossiers'      => ['admin', 'medecin', 'sage_femme', 'infirmier', 'secretaire'],
        'parametres'    => ['admin'],
    ]);
}

if (!function_exists('app_role_keys')) {
    /** @return list<string> */
    function app_role_keys(): array
    {
        return array_keys(APP_ROLE_LABELS);
    }
}

if (!function_exists('app_role_label')) {
    function app_role_label(string $role): string
    {
        return APP_ROLE_LABELS[$role] ?? ucfirst($role);
    }
}

if (!function_exists('app_role_is_valid')) {
    function app_role_is_valid(string $role): bool
    {
        return isset(APP_ROLE_LABELS[$role]);
    }
}

if (!function_exists('app_role_medecin_scope_roles')) {
    /** Rôles filtrés via fiche médecin (medecin_id) — même périmètre patients / consultations / RDV. */
    function app_role_medecin_scope_roles(): array
    {
        return ['medecin', 'sage_femme'];
    }
}

if (!function_exists('app_role_has_medecin_scope')) {
    function app_role_has_medecin_scope(string $role): bool
    {
        return in_array($role, app_role_medecin_scope_roles(), true);
    }
}

if (!function_exists('app_module_roles')) {
    /** @return list<string> */
    function app_module_roles(string $moduleKey): array
    {
        return MODULE_ROLES[$moduleKey] ?? ['admin'];
    }
}

if (!function_exists('app_role_has_module')) {
    function app_role_has_module(string $role, string $moduleKey, ?int $tenantId = null): bool
    {
        if ($tenantId !== null && $tenantId > 0) {
            require_once __DIR__ . '/tenant_permissions.php';
            $has = TenantPermissions::roleHasModule($tenantId, $role, $moduleKey);
        } else {
            $has = in_array($role, app_module_roles($moduleKey), true);
        }

        if (!$has && $role === 'secretaire' && $moduleKey === 'medecins') {
            require_once __DIR__ . '/medecin_settings.php';
            if (secretaire_medecin_add_allowed()) {
                return true;
            }
        }

        return $has;
    }
}

if (!function_exists('app_nav_item_visible')) {
    /** Élément de navigation visible selon droits tenant et rôle admin. */
    function app_nav_item_visible($auth, array $item): bool
    {
        if (!empty($item['admin_only']) && !$auth->estAdmin()) {
            return false;
        }
        if (!empty($item['feature'])) {
            if (!function_exists('tenant_feature_enabled')) {
                require_once __DIR__ . '/saas/saas_helpers.php';
            }
            if (!tenant_feature_enabled((string) $item['feature'])) {
                return false;
            }
        }
        if (!empty($item['module'])) {
            return $auth->aAccesModule((string) $item['module']);
        }
        if (!empty($item['roles']) && !$auth->aUnRole($item['roles'])) {
            return false;
        }
        return true;
    }
}

if (!function_exists('app_role_finance_write_roles')) {
    /** Création, modification, suppression et validation comptable. */
    function app_role_finance_write_roles(): array
    {
        return ['admin', 'comptable'];
    }
}

if (!function_exists('app_role_paiements_write_roles')) {
    /** Encaissements, facturation et suppression de paiements. */
    function app_role_paiements_write_roles(): array
    {
        return ['admin', 'secretaire', 'comptable'];
    }
}

if (!function_exists('app_modules_for_role')) {
    /**
     * @return list<string> Clés de modules accessibles pour un rôle.
     */
    function app_modules_for_role(string $role, ?int $tenantId = null): array
    {
        if ($tenantId !== null && $tenantId > 0) {
            require_once __DIR__ . '/tenant_permissions.php';
            return TenantPermissions::getModulesForRole($tenantId, $role);
        }
        if (!function_exists('app_modules_for_role_default')) {
            require_once __DIR__ . '/tenant_permissions.php';
        }
        return app_modules_for_role_default($role);
    }
}

if (!function_exists('app_module_labels')) {
    /** Labels français des modules (affichage UI). */
    function app_module_labels(): array
    {
        return [
            'patients'      => 'Patients',
            'medecins'      => 'Médecins',
            'rdv'           => 'Rendez-vous',
            'consultations' => 'Consultations',
            'laboratoire'   => 'Laboratoire',
            'paiements'     => 'Paiements',
            'personnel'     => 'Personnel',
            'pharmacie'     => 'Pharmacie',
            'pharma_erp'    => 'PharmaPro ERP',
            'finances'      => 'Finances',
            'assurances'    => 'Assurances',
            'communication' => 'Communication',
            'maintenance'   => 'Maintenance',
            'dossiers'      => 'Dossiers',
            'parametres'    => 'Paramètres',
        ];
    }
}

if (!function_exists('app_permissions_legacy_map')) {
    /** Compatibilité config.php — aligné sur MODULE_ROLES. */
    function app_permissions_legacy_map(): array
    {
        return [
            'patients'      => app_module_roles('patients'),
            'consultations' => app_module_roles('consultations'),
            'rendez_vous'   => app_module_roles('rdv'),
            'paiements'     => app_module_roles('paiements'),
            'laboratoire'   => app_module_roles('laboratoire'),
            'utilisateurs'  => app_module_roles('parametres'),
            'rapports'      => ['admin', 'medecin', 'comptable'],
            'parametres'    => app_module_roles('parametres'),
        ];
    }
}

if (!function_exists('app_role_profiles_for_ui')) {
    /**
     * Profils affichés dans parametres/utilisateurs.php (aperçu permissions).
     * @return array<string, array{permissions: list<string>, actions: list<string>}>
     */
    function app_role_profiles_for_ui(?int $tenantId = null): array
    {
        $labels = app_module_labels();
        $profiles = [];

        $actionsByModule = [
            'patients'      => 'Consulter et gérer les dossiers patients',
            'medecins'      => 'Gérer l\'équipe médicale',
            'rdv'           => 'Planifier et suivre les rendez-vous',
            'consultations' => 'Créer et suivre les consultations',
            'laboratoire'   => 'Saisir et valider les analyses',
            'paiements'     => 'Facturer et encaisser',
            'personnel'     => 'Gérer le personnel et les congés',
            'pharmacie'     => 'Gérer stocks et mouvements',
            'finances'      => 'Écritures, comptes, budgets, validation et bilan (écriture : admin et comptable)',
            'assurances'    => 'Contrats et remboursements',
            'communication' => 'Messagerie et annonces internes',
            'maintenance'   => 'Équipements et interventions',
            'dossiers'      => 'Archives et documents',
            'parametres'    => 'Configuration de l\'établissement',
        ];

        foreach (app_role_keys() as $role) {
            $mods = app_modules_for_role($role, $tenantId);
            $permissions = [];
            $actions = [];
            foreach ($mods as $mod) {
                $permissions[] = $labels[$mod] ?? $mod;
                if (isset($actionsByModule[$mod])) {
                    $actions[] = $actionsByModule[$mod];
                }
            }
            if ($role === 'admin') {
                $permissions[] = 'Tous les modules';
                $actions[] = 'Accès complet et gestion des utilisateurs';
            }
            $profiles[$role] = [
                'permissions' => $permissions ?: ['Aucun module assigné'],
                'actions'     => $actions ?: ['Accès limité — contactez l\'administrateur'],
            ];
        }

        return $profiles;
    }
}

if (!function_exists('app_role_doc_entries')) {
    /** Entrées documentation publique (#roles). */
    function app_role_doc_entries(): array
    {
        $icons = [
            'admin'      => 'fa-user-shield',
            'medecin'    => 'fa-user-md',
            'sage_femme' => 'fa-baby',
            'infirmier'  => 'fa-user-nurse',
            'secretaire' => 'fa-user-tie',
            'comptable'  => 'fa-calculator',
            'pharmacien' => 'fa-pills',
            'pharma_manager' => 'fa-prescription-bottle-medical',
            'pharma_cashier' => 'fa-cash-register',
            'laborantin' => 'fa-vial',
            'major'      => 'fa-user-graduate',
            'technicien' => 'fa-wrench',
        ];
        $summaries = [
            'admin'      => 'Accès complet : paramètres, utilisateurs, tous les modules, sauvegardes et journaux.',
            'medecin'    => 'Patients, consultations, rendez-vous, laboratoire, pharmacie, dossiers et communication.',
            'sage_femme' => 'Comme le médecin : patients suivis, consultations, rendez-vous, laboratoire, dossiers et communication (fiche Médecins requise).',
            'infirmier'  => 'Patients, soins, laboratoire (support), dossiers et communication.',
            'secretaire' => 'Rendez-vous, paiements, personnel, finances, assurances, dossiers administratifs et communication.',
            'comptable'  => 'Paiements (création, modification, suppression), finances complètes (écritures, comptes, budgets, validation, bilan).',
            'pharmacien' => 'Gestion pharmacie : stocks, commandes et mouvements de médicaments.',
            'pharma_manager' => 'PharmaPro ERP complet : POS, stock, achats, comptabilité SYSCOHADA, RH et paramètres.',
            'pharma_cashier' => 'PharmaPro caisse : point de vente, ventes et scan codes-barres uniquement.',
            'laborantin' => 'Saisie et suivi des analyses de laboratoire, résultats et file d\'attente.',
            'major'      => 'Supervision du laboratoire : toutes les analyses de l\'établissement, validation et assignation des techniciens.',
            'technicien' => 'Maintenance des équipements, interventions et suivi technique.',
        ];
        $entries = [];
        foreach (APP_ROLE_LABELS as $slug => $label) {
            $entries[] = [
                'role'   => $label,
                'slug'   => $slug,
                'icon'   => $icons[$slug] ?? 'fa-user',
                'access' => $summaries[$slug] ?? '',
            ];
        }
        return $entries;
    }
}

if (!function_exists('app_roles_select_options')) {
    /**
     * @param string $selected Valeur sélectionnée
     * @param bool $withEmpty Option vide en tête (création)
     */
    function app_roles_select_options(string $selected = '', bool $withEmpty = false): void
    {
        if ($withEmpty) {
            echo '<option value="">Sélectionner...</option>';
        }
        foreach (APP_ROLE_LABELS as $value => $label) {
            $sel = ($selected === $value) ? ' selected' : '';
            echo '<option value="' . htmlspecialchars($value) . '"' . $sel . '>'
                . htmlspecialchars($label) . '</option>';
        }
    }
}
