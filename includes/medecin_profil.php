<?php

/**

 * Types de profil dans le registre unifié (table medecins).

 */



if (!defined('MEDECIN_PROFIL_LABELS')) {

    define('MEDECIN_PROFIL_LABELS', [

        'medecin'     => 'Médecin',

        'sage_femme'  => 'Sage-femme',

        'infirmier'   => 'Infirmier(ère)',

        'laborantin'  => 'Laborantin(e)',

        'pharmacien'  => 'Pharmacien',

        'technicien'  => 'Technicien',

    ]);

}



if (!defined('MEDECIN_PROFIL_DISPLAY_PREFIXES')) {

    /**

     * Préfixe court devant le nom (affichage listes, tickets, exports).

     * Seul le profil « medecin » utilise « Dr. ».

     */

    define('MEDECIN_PROFIL_DISPLAY_PREFIXES', [

        'medecin'     => 'Dr. ',

        'sage_femme'  => 'SF. ',

        'infirmier'   => 'Inf. ',

        'laborantin'  => 'Lab. ',

        'pharmacien'  => 'Pharm. ',

        'technicien'  => 'Tech. ',

    ]);

}



if (!function_exists('medecin_profil_keys')) {

    /** @return list<string> */

    function medecin_profil_keys(): array

    {

        return array_keys(MEDECIN_PROFIL_LABELS);

    }

}



if (!function_exists('medecin_profil_label')) {

    function medecin_profil_label(string $type): string

    {

        return MEDECIN_PROFIL_LABELS[$type] ?? ucfirst($type);

    }

}



if (!function_exists('medecin_profil_is_valid')) {

    function medecin_profil_is_valid(string $type): bool

    {

        return isset(MEDECIN_PROFIL_LABELS[$type]);

    }

}



if (!function_exists('medecin_profil_default_for_role')) {

    function medecin_profil_default_for_role(string $role): string

    {

        $map = [

            'medecin'    => 'medecin',

            'sage_femme' => 'sage_femme',

            'infirmier'  => 'infirmier',

            'laborantin' => 'laborantin',

            'pharmacien' => 'pharmacien',

            'technicien' => 'technicien',

        ];

        return $map[$role] ?? 'medecin';

    }

}



if (!function_exists('medecin_profil_types_for_role')) {

    /**

     * Types de fiche sélectionnables pour un rôle utilisateur.

     *

     * @return list<string>

     */

    function medecin_profil_types_for_role(string $role): array

    {

        $map = [

            'medecin'    => ['medecin'],

            'sage_femme' => ['sage_femme', 'medecin'],

            'infirmier'  => ['infirmier'],

            'laborantin' => ['laborantin'],

            'pharmacien' => ['pharmacien'],

            'technicien' => ['technicien', 'laborantin'],

        ];

        return $map[$role] ?? medecin_profil_keys();

    }

}



if (!function_exists('medecin_profil_needs_personnel_mirror')) {

    function medecin_profil_needs_personnel_mirror(string $type): bool

    {

        return in_array($type, ['infirmier', 'laborantin', 'technicien', 'pharmacien'], true);

    }

}



if (!function_exists('medecin_profil_poste_label')) {

    function medecin_profil_poste_label(string $type): string

    {

        return medecin_profil_label($type);

    }

}



if (!function_exists('medecin_profil_departement_default')) {

    function medecin_profil_departement_default(string $type): ?string

    {

        if (in_array($type, ['laborantin', 'technicien'], true)) {

            return 'Laboratoire';

        }

        if ($type === 'infirmier') {

            return 'Soins';

        }

        if ($type === 'pharmacien') {

            return 'Pharmacie';

        }

        return null;

    }

}



if (!function_exists('medecin_profil_select_options')) {

    function medecin_profil_select_options(string $selected = ''): void

    {

        foreach (MEDECIN_PROFIL_LABELS as $value => $label) {

            $sel = ($selected === $value) ? ' selected' : '';

            echo '<option value="' . htmlspecialchars($value) . '"' . $sel . '>'

                . htmlspecialchars($label) . '</option>';

        }

    }

}



if (!function_exists('medecin_profil_is_paramedical')) {

    function medecin_profil_is_paramedical(string $type): bool

    {

        return in_array($type, ['infirmier', 'laborantin', 'technicien', 'pharmacien'], true);

    }

}



if (!function_exists('medecin_profil_display_prefix')) {

    function medecin_profil_display_prefix(string $type): string

    {

        return MEDECIN_PROFIL_DISPLAY_PREFIXES[$type] ?? '';

    }

}



if (!function_exists('medecin_profil_type_from_row')) {

    function medecin_profil_type_from_row(array $row, string $prefix = 'medecin'): string

    {

        $type = strtolower(trim((string) ($row["{$prefix}_type_profil"] ?? 'medecin')));

        return medecin_profil_is_valid($type) ? $type : 'medecin';

    }

}



if (!function_exists('medecin_profil_attribution_label')) {

    /** Libellé de champ : « Médecin », « Sage-femme », « Laborantin(e) »… */

    function medecin_profil_attribution_label(string $type): string

    {

        return medecin_profil_label($type);

    }

}



if (!function_exists('medecin_profil_attribution_label_from_row')) {

    function medecin_profil_attribution_label_from_row(array $row, string $prefix = 'medecin'): string

    {

        return medecin_profil_attribution_label(medecin_profil_type_from_row($row, $prefix));

    }

}



if (!function_exists('medecin_profil_format_name')) {

    /**

     * @param array{prenom?: string, nom?: string, type_profil?: string}|string $medecin

     */

    function medecin_profil_format_name($medecin, ?string $type = null): string

    {

        if (is_array($medecin)) {

            $prenom = trim((string) ($medecin['prenom'] ?? ''));

            $nom = trim((string) ($medecin['nom'] ?? ''));

            $type = $type ?? (string) ($medecin['type_profil'] ?? 'medecin');

        } else {

            $prenom = '';

            $nom = trim((string) $medecin);

            $type = $type ?? 'medecin';

        }

        return medecin_profil_display_prefix($type) . trim($prenom . ' ' . $nom);

    }

}



if (!function_exists('medecin_profil_specialites_list')) {

    /** @return list<string> */

    function medecin_profil_specialites_list(): array

    {

        return [

            'Cardiologie', 'Dermatologie', 'Endocrinologie', 'Gastro-entérologie',

            'Gynécologie', 'Médecine générale', 'Neurologie', 'Oncologie',

            'Ophtalmologie', 'Orthopédie', 'Pédiatrie', 'Pneumologie',

            'Psychiatrie', 'Radiologie', 'Rhumatologie', 'Urologie',

        ];

    }

}



if (!function_exists('medecin_profil_resolve_specialite_from_post')) {

    function medecin_profil_resolve_specialite_from_post(array $post, string $typeProfil): string

    {

        if (medecin_profil_is_paramedical($typeProfil)) {

            $spec = trim((string) ($post['specialite_libre'] ?? ''));

            return $spec !== '' ? $spec : medecin_profil_label($typeProfil);

        }

        $specValue = (string) ($post['specialite'] ?? '');

        if ($specValue === 'Autre') {

            $specValue = trim((string) ($post['specialite_autre'] ?? ''));

            if ($specValue === '') {

                throw new InvalidArgumentException('Veuillez préciser la spécialité lorsque « Autre » est sélectionné.');

            }

        }

        return $specValue;

    }

}



if (!function_exists('medecin_profil_specialite_form_state')) {

    /**

     * Prépare l'état du formulaire spécialité (liste vs autre vs libre).

     *

     * @return array{select: string, autre: string, libre: string, is_autre: bool}

     */

    function medecin_profil_specialite_form_state(string $typeProfil, string $specialite): array

    {

        if (medecin_profil_is_paramedical($typeProfil)) {

            return ['select' => '', 'autre' => '', 'libre' => $specialite, 'is_autre' => false];

        }

        $list = medecin_profil_specialites_list();

        if ($specialite !== '' && !in_array($specialite, $list, true)) {

            return ['select' => 'Autre', 'autre' => $specialite, 'libre' => '', 'is_autre' => true];

        }

        return ['select' => $specialite, 'autre' => '', 'libre' => '', 'is_autre' => false];

    }

}



if (!function_exists('medecin_profil_sql_fields')) {

    /**

     * Champs SELECT standard pour une jointure medecins.

     */

    function medecin_profil_sql_fields(string $alias = 'm', string $prefix = 'medecin'): string

    {

        return "{$alias}.nom AS {$prefix}_nom, {$alias}.prenom AS {$prefix}_prenom, "

            . "{$alias}.specialite AS {$prefix}_specialite, {$alias}.type_profil AS {$prefix}_type_profil";

    }

}



if (!function_exists('medecin_profil_format_joined')) {

    /**

     * Formate un nom à partir de colonnes jointes (medecin_prenom, medecin_nom, medecin_type_profil…).

     */

    function medecin_profil_format_joined(array $row, string $prefix = 'medecin'): string

    {

        $prenom = trim((string) ($row["{$prefix}_prenom"] ?? ''));

        $nom = trim((string) ($row["{$prefix}_nom"] ?? ''));

        $type = (string) ($row["{$prefix}_type_profil"] ?? 'medecin');

        if ($type === '') {

            $type = 'medecin';

        }

        return medecin_profil_display_prefix($type) . trim($prenom . ' ' . $nom);

    }

}



if (!function_exists('medecin_profil_option_label')) {

    /** Label pour listes déroulantes : « Spécialité - Nom » */

    function medecin_profil_option_label(array $medecin): string

    {

        $spec = trim((string) ($medecin['specialite'] ?? ''));

        $name = medecin_profil_format_name($medecin);

        return ($spec !== '' ? $spec . ' - ' : '') . $name;

    }

}

