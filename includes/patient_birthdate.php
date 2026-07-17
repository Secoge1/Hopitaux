<?php
/**
 * Saisie âge / date de naissance patient.
 * Si seul l'âge est connu, on enregistre le 1er janvier de l'année estimée.
 */

if (!function_exists('patient_date_from_age')) {
    function patient_date_from_age(int $age): string
    {
        if ($age < 0 || $age > 120) {
            throw new InvalidArgumentException('Âge invalide (0 à 120 ans).');
        }
        $year = (int) date('Y') - $age;
        return sprintf('%04d-01-01', $year);
    }
}

if (!function_exists('patient_resolve_date_naissance_from_post')) {
    /**
     * @param array<string, mixed> $post
     */
    function patient_resolve_date_naissance_from_post(array $post): string
    {
        $date = trim((string) ($post['date_naissance'] ?? ''));
        if ($date !== '') {
            $dt = DateTime::createFromFormat('Y-m-d', $date);
            if (!$dt || $dt->format('Y-m-d') !== $date) {
                throw new InvalidArgumentException('Date de naissance invalide.');
            }
            $today = new DateTime('today');
            if ($dt > $today) {
                throw new InvalidArgumentException('La date de naissance ne peut pas être dans le futur.');
            }
            $age = (int) $today->diff($dt)->y;
            if ($age > 120) {
                throw new InvalidArgumentException('Date de naissance invalide.');
            }
            return $date;
        }

        if (!isset($post['age_ans']) || $post['age_ans'] === '') {
            throw new InvalidArgumentException('Veuillez saisir l\'âge du patient.');
        }

        return patient_date_from_age((int) $post['age_ans']);
    }
}
