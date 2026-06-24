<?php
/**
 * Rattache les patients sans médecin référent au médecin lié au compte donné.
 * Utile après déploiement de medecin_referent_id (patients créés avant la correction).
 *
 * Usage : php config/backfill_patient_referent.php [nom_utilisateur]
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/staff_link.php';
require_once dirname(__DIR__) . '/includes/saas/TenantSchema.php';
require_once dirname(__DIR__) . '/includes/saas/TenantContext.php';

$login = $argv[1] ?? 'docteur1';
$pdo = getDB();

function db_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1'
    );
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}

$stmt = $pdo->prepare('SELECT id, nom_utilisateur, role, tenant_id FROM utilisateurs WHERE nom_utilisateur = ? LIMIT 1');
$stmt->execute([$login]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user || ($user['role'] ?? '') !== 'medecin') {
    echo "Compte médecin « {$login} » introuvable.\n";
    exit(1);
}

$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['user_role'] = 'medecin';
$_SESSION['user_connected'] = true;
$_SESSION['tenant_id'] = (int) ($user['tenant_id'] ?? 1);

if (!db_column_exists($pdo, 'patients', 'medecin_referent_id')) {
    echo "Colonne absente — migration TenantSchema en cours...\n";
    TenantSchema::ensure();
    if (!db_column_exists($pdo, 'patients', 'medecin_referent_id')) {
        echo "Colonne patients.medecin_referent_id toujours absente après migration.\n";
        exit(1);
    }
}

$link = StaffLink::getLinkForUser((int) $user['id']);
if (($link['type'] ?? '') !== 'medecin' || empty($link['id'])) {
    echo "Le compte n'est pas rattaché à une fiche médecin.\n";
    exit(1);
}

$medecinId = (int) $link['id'];
$tenantId = (int) ($user['tenant_id'] ?? 1);

$sql = "UPDATE patients SET medecin_referent_id = ?
        WHERE tenant_id = ?
          AND (medecin_referent_id IS NULL OR medecin_referent_id = 0)
          AND (statut IS NULL OR statut <> 'supprime')
          AND id NOT IN (
              SELECT DISTINCT c.patient_id FROM consultations c
              WHERE c.medecin_id = ? AND c.tenant_id = ?
          )
          AND id NOT IN (
              SELECT DISTINCT rv.patient_id FROM rendez_vous rv
              WHERE rv.medecin_id = ? AND rv.tenant_id = ?
          )";

$stmt = $pdo->prepare($sql);
$stmt->execute([$medecinId, $tenantId, $medecinId, $tenantId, $medecinId, $tenantId]);
$updated = $stmt->rowCount();

echo "Médecin #{$medecinId} ({$link['label']}) — {$updated} patient(s) rattaché(s) comme référent.\n";
