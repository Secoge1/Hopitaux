<?php
/**
 * Types de consultation — alignés sur parametres/tarifs.php et tarifs_consultation.
 */

if (!function_exists('consultation_types_preset')) {
    /** @return list<string> */
    function consultation_types_preset(): array
    {
        return [
            'normale',
            'urgence',
            'domicile',
            'suivi',
            'controle',
            'specialiste',
            'consultation_simple',
            'consultation_specialisee',
        ];
    }
}

if (!function_exists('consultation_type_label')) {
    function consultation_type_label(string $type): string
    {
        static $labels = [
            'normale' => 'Consultation normale',
            'urgence' => 'Urgence',
            'domicile' => 'Consultation à domicile',
            'suivi' => 'Suivi',
            'controle' => 'Contrôle',
            'specialiste' => 'Spécialiste',
            'consultation_simple' => 'Consultation simple',
            'consultation_specialisee' => 'Consultation spécialisée',
        ];

        if (isset($labels[$type])) {
            return $labels[$type];
        }

        return ucfirst(str_replace(['_', '-'], ' ', $type));
    }
}

if (!function_exists('consultation_types_for_form')) {
    /**
     * Types proposés dans les formulaires : presets paramètres + types actifs en base.
     *
     * @return list<string>
     */
    function consultation_types_for_form($tarifModel = null): array
    {
        $types = consultation_types_preset();

        if ($tarifModel && method_exists($tarifModel, 'getTypes')) {
            $fromDb = $tarifModel->getTypes();
            if (is_array($fromDb)) {
                $types = array_merge($types, $fromDb);
            }
        }

        $types = array_values(array_unique(array_filter(array_map('strval', $types))));
        sort($types, SORT_NATURAL | SORT_FLAG_CASE);

        return $types;
    }
}

if (!function_exists('consultation_type_resolve_from_post')) {
    /**
     * @param list<string> $allowedTypes
     */
    function consultation_type_resolve_from_post(array $post, array $allowedTypes): string
    {
        $typePost = (string) ($post['type_consultation'] ?? '');

        if ($typePost === '__autre__') {
            $typeAutre = trim((string) ($post['type_consultation_autre'] ?? ''));
            if ($typeAutre === '') {
                throw new Exception('Veuillez préciser le type de consultation lorsque « Autre (préciser) » est sélectionné.');
            }

            return preg_replace('/\s+/', '_', mb_strtolower($typeAutre, 'UTF-8'));
        }

        if ($typePost !== '' && in_array($typePost, $allowedTypes, true)) {
            return $typePost;
        }

        if ($typePost !== '' && preg_match('/^[a-z0-9_\-]+$/i', $typePost)) {
            return $typePost;
        }

        return $allowedTypes[0] ?? 'normale';
    }
}
