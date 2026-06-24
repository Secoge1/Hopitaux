<?php
/**
 * Met à jour type_profil des fiches medecins existantes à partir du rôle utilisateur,
 * du poste personnel ou de mots-clés dans la spécialité.
 *
 * Usage CLI : php config/backfill_medecins_type_profil.php
 * URL (admin) : /config/backfill_medecins_type_profil.php
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/saas/TenantSchema.php';
require_once __DIR__ . '/../includes/medecin_profil.php';
require_once __DIR__ . '/../includes/staff_mirror.php';

TenantSchema::ensure();
$pdo = getDB();

$isCli = PHP_SAPI === 'cli';
$lines = [];

function btp(string $msg): void
{
    global $lines, $isCli;
    $lines[] = $msg;
    if ($isCli) {
        echo $msg . PHP_EOL;
    }
}

$hasType = (bool) $pdo->query(
    "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'medecins' AND COLUMN_NAME = 'type_profil'"
)->fetchColumn();

if (!$hasType) {
    btp('Colonne medecins.type_profil absente — exécutez migrate_saas_multitenant.php');
    if (!$isCli) {
        header('Content-Type: text/plain; charset=utf-8');
        echo implode("\n", $lines);
    }
    exit(1);
}

/** @return ?string */
function infer_type_from_text(string $text): ?string
{
    $text = mb_strtolower(trim($text));
    if ($text === '') {
        return null;
    }
    $rules = [
        'sage_femme' => ['sage-femme', 'sage femme', 'maïeut'],
        'infirmier'  => ['infirmier', 'infirmière', 'infirmiere', 'ide'],
        'laborantin' => ['laborantin', 'laborantine', 'biologiste'],
        'pharmacien' => ['pharmacien', 'pharmacienne'],
        'technicien' => ['technicien', 'technicienne', 'radiologue tech'],
    ];
    foreach ($rules as $type => $needles) {
        foreach ($needles as $needle) {
            if (strpos($text, $needle) !== false) {
                return $type;
            }
        }
    }
    return null;
}

$hasUserCol = (bool) $pdo->query(
    "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'medecins' AND COLUMN_NAME = 'utilisateur_id'"
)->fetchColumn();
$hasPersCol = (bool) $pdo->query(
    "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'medecins' AND COLUMN_NAME = 'personnel_id'"
)->fetchColumn();

$sql = 'SELECT m.id, m.nom, m.prenom, m.specialite, m.type_profil';
if ($hasUserCol) {
    $sql .= ', m.utilisateur_id, u.role AS user_role';
}
if ($hasPersCol) {
    $sql .= ', m.personnel_id, p.poste AS personnel_poste';
}
$sql .= ' FROM medecins m';
if ($hasUserCol) {
    $sql .= ' LEFT JOIN utilisateurs u ON u.id = m.utilisateur_id';
}
if ($hasPersCol) {
    $sql .= ' LEFT JOIN personnel p ON p.id = m.personnel_id';
}
$sql .= " WHERE m.statut IS NULL OR m.statut <> 'supprime' ORDER BY m.id";

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$updated = 0;
$unchanged = 0;

$updateStmt = $pdo->prepare('UPDATE medecins SET type_profil = ? WHERE id = ?');

foreach ($rows as $row) {
    $current = strtolower((string) ($row['type_profil'] ?? 'medecin'));
    if ($current === '') {
        $current = 'medecin';
    }

    $inferred = null;

    if (!empty($row['user_role']) && medecin_profil_is_valid((string) $row['user_role'])) {
        $inferred = (string) $row['user_role'];
    }

    if ($inferred === null && !empty($row['personnel_poste'])) {
        $inferred = infer_type_from_text((string) $row['personnel_poste']);
    }

    if ($inferred === null && !empty($row['specialite'])) {
        $inferred = infer_type_from_text((string) $row['specialite']);
    }

    if ($inferred === null || !medecin_profil_is_valid($inferred)) {
        $unchanged++;
        continue;
    }

    if ($inferred === $current) {
        $unchanged++;
        continue;
    }

    $updateStmt->execute([$inferred, (int) $row['id']]);
    StaffMirror::syncPersonnelFromMedecin((int) $row['id']);
    $updated++;
    btp(sprintf(
        'OK #%d %s %s : %s → %s',
        (int) $row['id'],
        $row['prenom'] ?? '',
        $row['nom'] ?? '',
        medecin_profil_label($current),
        medecin_profil_label($inferred)
    ));
}

btp("Terminé : {$updated} fiche(s) mise(s) à jour, {$unchanged} inchangée(s).");
btp('Pour corriger manuellement : Médecins → Modifier → choisir le profil approprié.');

if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    echo implode("\n", $lines);
}
