<?php
/**
 * Définition des modules affichés sur la page Accueil (espace privé).
 */

if (!function_exists('app_home_date_fr')) {
    function app_home_date_fr(): string
    {
        static $mois = [
            1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
            5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
            9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
        ];
        $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
        $d = (int) date('w');
        $j = (int) date('j');
        $m = (int) date('n');
        return ucfirst($jours[$d]) . ' ' . $j . ' ' . $mois[$m] . ' ' . date('Y');
    }

    function app_home_modules($auth): array
    {
        if (!function_exists('app_module_roles')) {
            require_once __DIR__ . '/roles.php';
        }

        $all = [
            ['group' => 'Soins', 'module' => 'patients', 'icon' => 'fa-user-injured', 'tone' => 'blue', 'title' => 'Patients', 'desc' => 'Dossiers, antécédents et suivi médical.', 'href' => 'patients/', 'action' => 'patients/ajouter.php', 'action_label' => 'Nouveau'],
            ['group' => 'Soins', 'module' => 'consultations', 'icon' => 'fa-stethoscope', 'tone' => 'teal', 'title' => 'Consultations', 'desc' => 'Diagnostics, ordonnances et traitements.', 'href' => 'consultations/', 'action' => 'consultations/ajouter.php', 'action_label' => 'Nouvelle'],
            ['group' => 'Soins', 'module' => 'rdv', 'icon' => 'fa-calendar-check', 'tone' => 'cyan', 'title' => 'Rendez-vous', 'desc' => 'Planning et gestion des créneaux.', 'href' => 'rendez-vous/', 'action' => 'rendez-vous/ajouter.php', 'action_label' => 'Planifier'],
            ['group' => 'Soins', 'module' => 'laboratoire', 'icon' => 'fa-flask', 'tone' => 'amber', 'title' => 'Laboratoire', 'desc' => 'Analyses, résultats et file d\'attente.', 'href' => 'laboratoire/', 'action' => 'laboratoire/ajouter.php', 'action_label' => 'Analyser'],
            ['group' => 'Soins', 'module' => 'medecins', 'icon' => 'fa-user-md', 'tone' => 'indigo', 'title' => 'Médecins', 'desc' => 'Équipe médicale et spécialités.', 'href' => 'medecins/', 'action' => null, 'action_label' => null],
            ['group' => 'Administration', 'module' => 'paiements', 'icon' => 'fa-credit-card', 'tone' => 'rose', 'title' => 'Paiements', 'desc' => 'Facturation et encaissements.', 'href' => 'paiements/', 'action' => 'paiements/ajouter.php', 'action_label' => 'Encaisser'],
            ['group' => 'Administration', 'module' => 'personnel', 'icon' => 'fa-user-tie', 'tone' => 'blue', 'title' => 'Personnel', 'desc' => 'RH, horaires et congés.', 'href' => 'personnel/', 'action' => 'personnel/ajouter.php', 'action_label' => 'Ajouter'],
            ['group' => 'Administration', 'module' => 'finances', 'icon' => 'fa-calculator', 'tone' => 'violet', 'title' => 'Finances', 'desc' => 'Comptabilité et budgets.', 'href' => 'finances/', 'action' => 'finances/nouvelle_ecriture.php', 'action_label' => 'Écriture'],
            ['group' => 'Administration', 'module' => 'assurances', 'icon' => 'fa-shield-alt', 'tone' => 'cyan', 'title' => 'Assurances', 'desc' => 'Contrats et remboursements.', 'href' => 'assurances/', 'action' => 'assurances/ajouter.php', 'action_label' => 'Contrat'],
            ['group' => 'Logistique', 'module' => 'pharmacie', 'icon' => 'fa-pills', 'tone' => 'teal', 'title' => 'Pharmacie', 'desc' => 'Stocks, commandes et péremption.', 'href' => 'pharmacie/', 'action' => 'pharmacie/ajouter.php', 'action_label' => 'Stock'],
            ['group' => 'Logistique', 'module' => 'maintenance', 'icon' => 'fa-tools', 'tone' => 'amber', 'title' => 'Maintenance', 'desc' => 'Équipements et interventions.', 'href' => 'maintenance/', 'action' => 'maintenance/ajouter_equipement.php', 'action_label' => 'Équipement'],
            ['group' => 'Communication', 'module' => 'communication', 'icon' => 'fa-comments', 'tone' => 'rose', 'title' => 'Communication', 'desc' => 'Messagerie et annonces internes.', 'href' => 'communication/', 'action' => 'communication/nouveau_message.php', 'action_label' => 'Message'],
            ['group' => 'Système', 'module' => 'parametres', 'icon' => 'fa-cog', 'tone' => 'slate', 'title' => 'Paramètres', 'desc' => 'Configuration de l\'établissement.', 'href' => 'parametres/', 'action' => 'parametres/utilisateurs.php', 'action_label' => 'Utilisateurs', 'admin_action' => true],
        ];

        $visible = [];
        foreach ($all as $mod) {
            if (!empty($mod['module']) && !$auth->aAccesModule($mod['module'])) {
                continue;
            }
            if (!empty($mod['admin_action']) && !$auth->estAdmin()) {
                $mod['action'] = null;
                $mod['action_label'] = null;
            }
            if ($mod['module'] === 'finances' && !$auth->peutEcrireFinances()) {
                $mod['action'] = 'finances/';
                $mod['action_label'] = 'Consulter';
            }
            if ($mod['href'] === 'medecins/' && $auth->estClinicienScope() && !$auth->estAdmin()) {
                $mod['title'] = 'Mon profil';
                $mod['desc'] = $auth->aRole('sage_femme')
                    ? 'Votre fiche professionnelle (rattachement requis pour vos dossiers).'
                    : 'Votre fiche et vos coordonnées professionnelles.';
                if (!class_exists('StaffScope')) {
                    require_once __DIR__ . '/staff_scope.php';
                }
                $ctx = StaffScope::context();
                if (!empty($ctx['medecin_id'])) {
                    $mod['href'] = 'medecins/voir.php?id=' . (int) $ctx['medecin_id'];
                }
            }
            $visible[] = $mod;
        }
        return $visible;
    }

    /**
     * Liens « Accès directs » filtrés par rôle (dashboard).
     *
     * @return list<array{href: string, icon: string, label: string}>
     */
    function app_home_dash_links($auth): array
    {
        if (!function_exists('app_module_roles')) {
            require_once __DIR__ . '/roles.php';
        }

        $links = [
            ['href' => 'patients/',       'icon' => 'fa-user-injured',    'label' => 'Patients',      'module' => 'patients'],
            ['href' => 'consultations/',  'icon' => 'fa-stethoscope',     'label' => 'Consultations', 'module' => 'consultations'],
            ['href' => 'rendez-vous/',    'icon' => 'fa-calendar-check',  'label' => 'Rendez-vous',   'module' => 'rdv'],
            ['href' => 'laboratoire/',    'icon' => 'fa-flask',           'label' => 'Laboratoire',   'module' => 'laboratoire'],
            ['href' => 'paiements/',      'icon' => 'fa-credit-card',     'label' => 'Paiements',     'module' => 'paiements'],
            ['href' => 'finances/',       'icon' => 'fa-calculator',      'label' => 'Finances',      'module' => 'finances'],
            ['href' => 'communication/',  'icon' => 'fa-comments',        'label' => 'Communication', 'module' => 'communication'],
        ];

        return array_values(array_filter($links, function ($link) use ($auth) {
            return empty($link['module']) || $auth->aAccesModule($link['module']);
        }));
    }

    /**
     * Raccourcis médecin (profil, accueil).
     *
     * @return list<array{href: string, icon: string, label: string, tone: string}>
     */
    function app_medecin_workspace_links($auth = null): array
    {
        $links = [
            ['href' => 'consultations/', 'icon' => 'fa-stethoscope',    'label' => 'Consultations', 'tone' => 'teal', 'module' => 'consultations'],
            ['href' => 'laboratoire/',   'icon' => 'fa-flask',          'label' => 'Laboratoire',   'tone' => 'amber', 'module' => 'laboratoire'],
            ['href' => 'patients/',      'icon' => 'fa-user-injured',   'label' => 'Patients',      'tone' => 'blue', 'module' => 'patients'],
            ['href' => 'rendez-vous/',   'icon' => 'fa-calendar-check', 'label' => 'Rendez-vous',     'tone' => 'cyan', 'module' => 'rdv'],
        ];
        if ($auth === null) {
            $auth = Auth::getInstance();
        }
        return array_values(array_filter($links, static function ($link) use ($auth) {
            return empty($link['module']) || $auth->aAccesModule($link['module']);
        }));
    }

    /**
     * Grille des modules (accueil / dashboard).
     *
     * @param list<array<string, mixed>> $homeModules
     */
    function app_home_render_modules_grid(array $homeModules, string $searchInputId = 'homeModuleSearch'): void
    {
        ?>
        <div class="home-modules-section">
            <div class="home-section-head">
                <div>
                    <h2><i class="fas fa-th-large me-2 text-primary"></i>Modules</h2>
                    <p><?= count($homeModules) ?> module<?= count($homeModules) > 1 ? 's' : '' ?> accessible<?= count($homeModules) > 1 ? 's' : '' ?> selon votre rôle</p>
                </div>
                <div class="home-search">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input type="search" id="<?= htmlspecialchars($searchInputId) ?>" placeholder="Rechercher un module…" autocomplete="off" autocorrect="off" spellcheck="false" aria-label="Rechercher un module">
                </div>
            </div>

            <div class="home-modules-grid" id="homeModulesGrid">
                <?php foreach ($homeModules as $mod): ?>
                <div class="home-mod home-mod--<?= htmlspecialchars($mod['tone']) ?>"
                     data-search="<?= htmlspecialchars(mb_strtolower($mod['title'] . ' ' . $mod['desc'] . ' ' . $mod['group'], 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>">
                    <a href="<?= app_url($mod['href']) ?>" class="text-decoration-none text-reset d-flex flex-column flex-grow-1">
                        <div class="home-mod-top">
                            <div class="home-mod-icon"><i class="fas <?= htmlspecialchars($mod['icon']) ?>"></i></div>
                            <i class="fas fa-arrow-right home-mod-arrow"></i>
                        </div>
                        <h3><?= htmlspecialchars($mod['title']) ?></h3>
                        <p><?= htmlspecialchars($mod['desc']) ?></p>
                    </a>
                    <div class="home-mod-foot">
                        <span class="home-mod-tag"><?= htmlspecialchars($mod['group']) ?></span>
                        <?php if (!empty($mod['action'])): ?>
                        <a href="<?= app_url($mod['action']) ?>" class="home-mod-action" onclick="event.stopPropagation()">
                            + <?= htmlspecialchars($mod['action_label']) ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="home-no-results" id="homeNoResults" hidden>
                <i class="fas fa-search fa-2x mb-2 d-block opacity-50" aria-hidden="true"></i>
                Aucun module ne correspond à votre recherche.
            </div>
        </div>
        <?php
    }

    function app_home_render_medecin_workspace($auth): void
    {
        if ($auth->estAdmin() || !$auth->estClinicienScope()) {
            return;
        }
        $title = $auth->aRole('sage_femme') ? 'Espace sage-femme — accès rapide' : 'Espace médecin — accès rapide';
        ?>
        <div class="home-panel mb-4">
            <div class="home-panel-head"><i class="fas fa-briefcase-medical"></i><?= htmlspecialchars($title) ?></div>
            <div class="home-panel-body">
                <div class="home-quick-grid home-quick-grid--wide">
                    <?php foreach (app_medecin_workspace_links($auth) as $link): ?>
                    <a href="<?= app_url($link['href']) ?>" class="home-quick home-quick--<?= htmlspecialchars($link['tone']) ?>">
                        <i class="fas <?= htmlspecialchars($link['icon']) ?>"></i>
                        <?= htmlspecialchars($link['label']) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }

    function app_home_quick_actions($auth): array
    {
        if (!function_exists('app_module_roles')) {
            require_once __DIR__ . '/roles.php';
        }

        $actions = [
            ['icon' => 'fa-user-plus', 'label' => 'Patient', 'href' => 'patients/ajouter.php', 'tone' => 'teal', 'module' => 'patients'],
            ['icon' => 'fa-calendar-plus', 'label' => 'RDV', 'href' => 'rendez-vous/ajouter.php', 'tone' => 'cyan', 'module' => 'rdv'],
            ['icon' => 'fa-stethoscope', 'label' => 'Consultation', 'href' => 'consultations/ajouter.php', 'tone' => 'amber', 'module' => 'consultations'],
            ['icon' => 'fa-flask', 'label' => 'Analyse', 'href' => 'laboratoire/ajouter.php', 'tone' => 'blue', 'module' => 'laboratoire'],
            ['icon' => 'fa-credit-card', 'label' => 'Paiement', 'href' => 'paiements/ajouter.php', 'tone' => 'rose', 'module' => 'paiements'],
            ['icon' => 'fa-comments', 'label' => 'Message', 'href' => 'communication/nouveau_message.php', 'tone' => 'violet', 'module' => 'communication'],
        ];

        return array_values(array_filter($actions, function ($a) use ($auth) {
            return empty($a['module']) || $auth->aAccesModule($a['module']);
        }));
    }

    /** @param array<string, mixed> $stats */
    function app_home_render_kpis(array $stats): void
    {
        if (empty($stats)) {
            return;
        }

        $kpis = [
            ['tone' => 'blue',  'icon' => 'fa-user-injured',    'key' => 'patients',                  'val' => $stats['patients'] ?? 0,                  'label' => 'Patients'],
            ['tone' => 'teal',  'icon' => 'fa-stethoscope',     'key' => 'consultations_aujourd_hui', 'val' => $stats['consultations_aujourd_hui'] ?? 0,  'label' => "Consult. aujourd'hui"],
            ['tone' => 'amber', 'icon' => 'fa-calendar-check',  'key' => 'rdv_aujourd_hui',           'val' => $stats['rdv_aujourd_hui'] ?? 0,            'label' => "RDV du jour"],
            ['tone' => 'cyan',  'icon' => 'fa-flask',           'key' => 'analyses_en_cours',         'val' => $stats['analyses_en_cours'] ?? 0,          'label' => 'Analyses en cours'],
            ['tone' => 'slate', 'icon' => 'fa-user-md',         'key' => 'medecins_actifs',           'val' => $stats['medecins_actifs'] ?? 0,            'label' => 'Médecins'],
            ['tone' => 'rose',  'icon' => 'fa-credit-card',     'key' => 'paiements_total',           'val' => $stats['paiements_total'] ?? 0,            'label' => 'Paiements', 'pending' => (int) ($stats['paiements_en_attente'] ?? 0)],
        ];
        ?>
<div class="home-kpis">
    <?php foreach ($kpis as $kpi): ?>
    <div class="home-kpi home-kpi--<?= $kpi['tone'] ?>">
        <div class="home-kpi-icon"><i class="fas <?= $kpi['icon'] ?>"></i></div>
        <div class="home-kpi-val" id="stat-<?= htmlspecialchars(str_replace('_', '-', $kpi['key'])) ?>" data-stat-key="<?= htmlspecialchars($kpi['key']) ?>"><?= $kpi['val'] ?></div>
        <div class="home-kpi-label"><?= $kpi['label'] ?></div>
        <?php if (!empty($kpi['pending'])): ?>
        <div class="home-kpi-note" id="stat-paiements-attente"><i class="fas fa-clock me-1"></i><span id="stat-paiements-attente-val"><?= (int) $kpi['pending'] ?></span> en attente</div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
        <?php
    }
}
