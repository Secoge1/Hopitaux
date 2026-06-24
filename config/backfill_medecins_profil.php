<?php
/**
 * Crée des fiches medecins pour le personnel clinique existant (infirmiers, laborantins…).
 * Usage CLI : php config/backfill_medecins_profil.php
 * URL (admin) : /config/backfill_medecins_profil.php
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/saas/TenantSchema.php';
require_once __DIR__ . '/../includes/saas/TenantContext.php';
require_once __DIR__ . '/../includes/medecin_profil.php';
require_once __DIR__ . '/../includes/staff_mirror.php';
require_once __DIR__ . '/../models/Medecin.php';

TenantSchema::ensure();
$pdo = getDB();

$isCli = PHP_SAPI === 'cli';
$lines = [];

function bl(string $msg): void
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
    bl('Colonne medecins.type_profil absente — exécutez migrate_saas_multitenant.php');
    if (!$isCli) {
        header('Content-Type: text/plain; charset=utf-8');
        echo implode("\n", $lines);
    }
    exit(1);
}

$mapPoste = [
    'infirmier' => 'infirmier',
    'infirmière' => 'infirmier',
    'laborantin' => 'laborantin',
    'technicien' => 'technicien',
    'pharmacien' => 'pharmacien',
];

$stmt = $pdo->query("SELECT * FROM personnel WHERE statut = 'actif' ORDER BY id");
$personnel = $stmt->fetchAll(PDO::FETCH_ASSOC);
$medecinModel = new Medecin();
$created = 0;
$linked = 0;

foreach ($personnel as $p) {
    $poste = strtolower((string) ($p['poste'] ?? ''));
    $type = null;
    foreach ($mapPoste as $needle => $profil) {
        if (strpos($poste, $needle) !== false) {
            $type = $profil;
            break;
        }
    }
    if ($type === null) {
        continue;
    }

    if (!empty($p['utilisateur_id'])) {
        $chk = $pdo->prepare('SELECT id FROM medecins WHERE utilisateur_id = ? AND tenant_id <=> ? LIMIT 1');
        $chk->execute([(int) $p['utilisateur_id'], $p['tenant_id'] ?? null]);
        if ($chk->fetchColumn()) {
            continue;
        }
    }

    $chk2 = $pdo->prepare('SELECT id FROM medecins WHERE personnel_id = ? LIMIT 1');
    $chk2->execute([(int) $p['id']]);
    if ($chk2->fetchColumn()) {
        continue;
    }

    TenantContext::setTenantId((int) ($p['tenant_id'] ?: 1));
    $_SESSION['tenant_id'] = (int) ($p['tenant_id'] ?: 1);

    $newId = $medecinModel->create([
        'numero_licence' => $medecinModel->generateNumeroLicence(),
        'type_profil' => $type,
        'nom' => $p['nom'],
        'prenom' => $p['prenom'],
        'specialite' => $p['poste'] ?: medecin_profil_label($type),
        'telephone' => $p['telephone'] ?? null,
        'email' => $p['email'] ?? null,
        'adresse' => $p['adresse'] ?? null,
        'ville' => $p['ville'] ?? null,
        'code_postal' => $p['code_postal'] ?? null,
        'pays' => $p['pays'] ?? 'Mali',
        'date_embauche' => $p['date_embauche'] ?? date('Y-m-d'),
        'statut' => 'actif',
    ]);

    if (!$newId) {
        bl("Échec création medecin pour personnel #{$p['id']}");
        continue;
    }
    $created++;

    $pdo->prepare('UPDATE medecins SET personnel_id = ? WHERE id = ?')->execute([(int) $p['id'], (int) $newId]);
    if (!empty($p['utilisateur_id'])) {
        $pdo->prepare('UPDATE medecins SET utilisateur_id = ? WHERE id = ?')->execute([(int) $p['utilisateur_id'], (int) $newId]);
        StaffMirror::syncUtilisateurFromMedecinLink((int) $newId, (int) $p['utilisateur_id']);
        $linked++;
    } else {
        StaffMirror::syncPersonnelFromMedecin((int) $newId);
    }

    bl("OK personnel #{$p['id']} → medecin #{$newId} ({$type})");
}

bl("Terminé : {$created} fiche(s) créée(s), {$linked} compte(s) resynchronisé(s).");

if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    echo implode("\n", $lines);
}
