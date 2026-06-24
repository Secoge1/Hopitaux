<?php
/**
 * Guide utilisateur complet — contenu HTML (aperçu web + export PDF).
 */
require_once __DIR__ . '/documentation_sections.php';
require_once __DIR__ . '/roles.php';

if (!function_exists('user_guide_meta')) {
    function user_guide_meta(): array
    {
        $etab = function_exists('getNomEtablissement') ? getNomEtablissement() : 'Établissement';
        $platform = function_exists('platform_name') ? platform_name() : 'Se.Santé';
        return [
            'title'       => 'Guide utilisateur — ' . $platform,
            'subtitle'    => 'Documentation complète pour les nouveaux utilisateurs',
            'etablissement' => $etab,
            'platform'    => $platform,
            'version'     => '1.0',
            'date'        => date('d/m/Y'),
            'devise'      => function_exists('app_currency_label') ? app_currency_label() : 'FCFA',
        ];
    }
}

if (!function_exists('user_guide_onboarding')) {
    function user_guide_onboarding(): array
    {
        return [
            [
                'title' => '1. Bienvenue et présentation',
                'blocks' => [
                    [
                        'type' => 'p',
                        'text' => 'Ce guide accompagne les nouveaux utilisateurs de la plateforme de gestion hospitalière. Chaque établissement dispose de son propre espace sécurisé (multi-tenant) : vos données sont isolées des autres cliniques.',
                    ],
                    [
                        'type' => 'ul',
                        'items' => [
                            'Connexion avec identifiant et mot de passe fournis par l\'administrateur.',
                            'Tableau de bord personnalisé selon votre rôle.',
                            'Navigation latérale (bureau) ou barre inférieure (mobile / PWA).',
                            'Notifications en temps réel sur l\'accueil.',
                        ],
                    ],
                ],
            ],
            [
                'title' => '2. Première connexion',
                'blocks' => [
                    [
                        'type' => 'ul',
                        'items' => [
                            'Connectez-vous via la page de connexion de votre établissement.',
                            'Changez votre mot de passe si l\'administrateur vous a remis un mot de passe temporaire.',
                            'Vérifiez que votre nom et votre rôle s\'affichent correctement en haut à droite.',
                            'Consultez ce guide PDF et repérez les modules accessibles dans le menu.',
                        ],
                    ],
                    [
                        'type' => 'p',
                        'text' => 'En cas de message « accès refusé » ou liste vide : votre compte n\'est peut-être pas encore rattaché à une fiche médecin ou personnel (voir chapitre 4).',
                    ],
                ],
            ],
            [
                'title' => '3. Rôles et permissions',
                'blocks' => [
                    [
                        'type' => 'p',
                        'text' => 'Dix rôles métier définissent les modules visibles. Seul l\'administrateur accède aux Paramètres complets.',
                    ],
                    [
                        'type' => 'roles_table',
                    ],
                ],
            ],
            [
                'title' => '4. Rattachement compte ↔ fiche métier',
                'blocks' => [
                    [
                        'type' => 'p',
                        'text' => 'Tous les professionnels cliniques (médecins, sages-femmes, infirmiers, laborantins, techniciens, pharmaciens) sont enregistrés dans le module Médecins puis rattachés à leur compte utilisateur.',
                    ],
                    [
                        'type' => 'ul',
                        'items' => [
                            'Créer la fiche dans Médecins → Ajouter un professionnel (choisir le profil : médecin, infirmier, laborantin…).',
                            'Rattacher le compte dans Paramètres → Utilisateurs → Fiche professionnelle (Médecins).',
                            'L\'administrateur effectue le rattachement dans Paramètres → Utilisateurs → Modifier → Fiche métier.',
                            'Statut « Non rattaché » : le compte est actif mais ne voit aucune donnée clinique filtrée.',
                        ],
                    ],
                    [
                        'type' => 'p',
                        'text' => 'Laboratoire : le laborantin ne voit que les analyses où il est assigné comme technicien. Le major voit toutes les analyses de l\'établissement et peut assigner les techniciens. L\'assignation se fait à la création ou modification d\'une analyse, ou automatiquement pour le laborantin connecté.',
                    ],
                ],
            ],
            [
                'title' => '5. Modules cliniques',
                'blocks' => [
                    ['type' => 'modules_detail'],
                ],
            ],
            [
                'title' => '6. Parcours métier recommandés',
                'blocks' => [
                    ['type' => 'workflows'],
                ],
            ],
            [
                'title' => '7. Facturation et tarification',
                'blocks' => [
                    [
                        'type' => 'ul',
                        'items' => [
                            'Tarifs consultations : Paramètres → Tarifs.',
                            'Soins facturables : Paramètres → Soins.',
                            'Analyses laboratoire : Paramètres → Tarifs labo (types et prix par défaut).',
                            'Hospitalisation : Consultations → Gestion tarifs hospitalisation.',
                            'Paiements : enregistrement, reçus PDF, lien comptable automatique.',
                            'Devise par défaut : FCFA (configurable par l\'administrateur).',
                        ],
                    ],
                ],
            ],
            [
                'title' => '8. Intelligence artificielle (IA)',
                'blocks' => [
                    [
                        'type' => 'ul',
                        'items' => [
                            'Suggestions lors de la création de consultations (diagnostics, traitements).',
                            'Suggestions lors des demandes d\'analyses laboratoire.',
                            'L\'IA complète les suggestions locales ; elle est activée au niveau plateforme.',
                            'Le libellé affiché est « IA » (sans nom de fournisseur).',
                        ],
                    ],
                ],
            ],
            [
                'title' => '9. Mobile et PWA',
                'blocks' => [
                    [
                        'type' => 'ul',
                        'items' => [
                            'Interface adaptée smartphone et tablette.',
                            'Installation PWA : ajouter l\'application à l\'écran d\'accueil depuis le navigateur.',
                            'Barre de navigation mobile : accès rapide aux modules selon le rôle.',
                        ],
                    ],
                ],
            ],
            [
                'title' => '10. Administration établissement',
                'blocks' => [
                    [
                        'type' => 'ul',
                        'items' => [
                            'Paramètres → Général : identité, logo, devise, langue, fuseau horaire.',
                            'Paramètres → Utilisateurs : création comptes, rôles, rattachement fiches.',
                            'Paramètres → Sauvegardes : export base et fichiers.',
                            'Paramètres → Journaux : traçabilité des actions.',
                            'Ce guide PDF : Paramètres → Guide utilisateur (accessible à tous les rôles).',
                        ],
                    ],
                ],
            ],
            [
                'title' => '11. Sécurité et bonnes pratiques',
                'blocks' => [
                    [
                        'type' => 'ul',
                        'items' => [
                            'Ne partagez jamais votre mot de passe.',
                            'Déconnectez-vous sur un poste partagé.',
                            'Signalez à l\'administrateur tout accès anormal ou compte non rattaché.',
                            'Les exports PDF incluent le logo de l\'établissement : vérifiez le destinataire avant envoi.',
                        ],
                    ],
                ],
            ],
            [
                'title' => '12. Dépannage fréquent',
                'blocks' => [
                    [
                        'type' => 'ul',
                        'items' => [
                            'Liste patients vide (médecin) : vérifier le rattachement fiche médecin.',
                            'Liste analyses vide (laborantin) : rattachement personnel + technicien assigné sur les analyses.',
                            'Suggestions IA absentes : type d\'analyse non configuré ou IA désactivée.',
                            'Erreur accès module : votre rôle n\'inclut pas ce module — contacter l\'admin.',
                        ],
                    ],
                ],
            ],
        ];
    }
}

if (!function_exists('user_guide_roles_table_rows')) {
    /** @return list<array{role: string, access: string, modules: string}> */
    function user_guide_roles_table_rows(): array
    {
        $labels = app_module_labels();
        $rows = [];
        foreach (app_role_doc_entries() as $entry) {
            $slug = $entry['slug'] ?? '';
            $mods = app_modules_for_role($slug);
            $modLabels = array_map(static function ($m) use ($labels) {
                return $labels[$m] ?? $m;
            }, $mods);
            $rows[] = [
                'role'    => $entry['role'],
                'access'  => $entry['access'],
                'modules' => implode(', ', $modLabels) ?: '—',
            ];
        }
        return $rows;
    }
}

if (!function_exists('user_guide_build_pdf_html')) {
    function user_guide_build_pdf_html(?int $tenantId = null): string
    {
        require_once __DIR__ . '/pdf_branding.php';
        $meta = user_guide_meta();
        $systemParams = pdf_tenant_system_params($tenantId);
        $logo = $systemParams->getPdfLogoBlockHtml([
            'max_height' => 90,
            'max_width' => 320,
            'margin_bottom' => '10px',
            'align' => 'center',
        ]);

        $css = '
        h1 { color: #1e40af; font-size: 18px; margin: 0 0 8px 0; text-align: center; }
        h2 { color: #1e3a8a; font-size: 13px; margin: 18px 0 8px 0; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; }
        p { font-size: 9px; line-height: 1.45; color: #334155; margin: 0 0 6px 0; }
        ul { margin: 4px 0 8px 0; padding-left: 14px; }
        li { font-size: 9px; line-height: 1.4; margin-bottom: 3px; color: #334155; }
        .cover { text-align: center; margin-bottom: 20px; }
        .meta { font-size: 8px; color: #64748b; text-align: center; margin-bottom: 16px; }
        table.roles { width: 100%; border-collapse: collapse; font-size: 8px; margin: 8px 0; }
        table.roles th { background: #e0e7ff; padding: 5px; text-align: left; }
        table.roles td { border: 1px solid #e2e8f0; padding: 4px; vertical-align: top; }
        .mod-group { margin-bottom: 10px; }
        .mod-name { font-weight: bold; font-size: 9px; color: #0f172a; }
        .mod-desc { font-size: 8px; color: #475569; }
        .footer-note { font-size: 7px; color: #94a3b8; margin-top: 20px; text-align: center; }
        ';

        $html = '<style>' . $css . '</style>';
        $html .= '<div class="cover">' . $logo;
        $html .= '<h1>' . htmlspecialchars($meta['title']) . '</h1>';
        $html .= '<p style="text-align:center;font-size:10px;"><strong>' . htmlspecialchars($meta['etablissement']) . '</strong></p>';
        $html .= '<div class="meta">Version ' . htmlspecialchars($meta['version']) . ' — Généré le ' . htmlspecialchars($meta['date']) . '</div></div>';

        foreach (user_guide_onboarding() as $chapter) {
            $html .= '<h2>' . htmlspecialchars($chapter['title']) . '</h2>';
            foreach ($chapter['blocks'] as $block) {
                $html .= user_guide_render_block_html($block);
            }
        }

        $html .= '<div class="footer-note">Document généré par ' . htmlspecialchars($meta['platform'])
            . ' — Usage interne ' . htmlspecialchars($meta['etablissement']) . '. '
            . 'Pour toute question, contactez l\'administrateur de votre établissement.</div>';

        return $html;
    }
}

if (!function_exists('user_guide_render_block_html')) {
    function user_guide_render_block_html(array $block): string
    {
        $type = $block['type'] ?? 'p';
        if ($type === 'p') {
            return '<p>' . htmlspecialchars($block['text'] ?? '') . '</p>';
        }
        if ($type === 'ul') {
            $html = '<ul>';
            foreach ($block['items'] ?? [] as $item) {
                $html .= '<li>' . htmlspecialchars((string) $item) . '</li>';
            }
            return $html . '</ul>';
        }
        if ($type === 'roles_table') {
            $html = '<table class="roles"><thead><tr><th>Rôle</th><th>Résumé</th><th>Modules</th></tr></thead><tbody>';
            foreach (user_guide_roles_table_rows() as $row) {
                $html .= '<tr><td><strong>' . htmlspecialchars($row['role']) . '</strong></td>';
                $html .= '<td>' . htmlspecialchars($row['access']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['modules']) . '</td></tr>';
            }
            return $html . '</tbody></table>';
        }
        if ($type === 'modules_detail') {
            $html = '';
            foreach (doc_module_groups() as $group) {
                $html .= '<p class="mod-name">' . htmlspecialchars($group['title']) . '</p>';
                foreach ($group['items'] as $item) {
                    $html .= '<p class="mod-desc"><strong>' . htmlspecialchars($item['name']) . ' :</strong> '
                        . htmlspecialchars($item['desc']) . '</p>';
                }
            }
            return $html;
        }
        if ($type === 'workflows') {
            $html = '';
            foreach (doc_workflows() as $wf) {
                $html .= '<p class="mod-name">' . htmlspecialchars($wf['title']) . '</p><ul>';
                foreach ($wf['steps'] as $step) {
                    $html .= '<li>' . htmlspecialchars($step) . '</li>';
                }
                $html .= '</ul>';
            }
            return $html;
        }
        return '';
    }
}
