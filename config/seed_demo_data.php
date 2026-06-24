<?php
/**
 * Jeu de données de démonstration pour tous les modules SeSanté / EfficaSanté.
 *
 * Usage :
 *   php config/seed_demo_data.php --confirm
 *   php config/seed_demo_data.php --confirm --reset   # vide d'abord les données métier
 */
declare(strict_types=1);

ob_start();

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI uniquement.\n");
}

$confirm = in_array('--confirm', $argv, true);
$reset = in_array('--reset', $argv, true);

if (!$confirm) {
    fwrite(STDERR, "Usage: php config/seed_demo_data.php --confirm [--reset]\n");
    exit(1);
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/saas/TenantContext.php';
require_once __DIR__ . '/../includes/saas/TenantScope.php';
require_once __DIR__ . '/../includes/CacheSystem.php';

// Bootstrap léger (évite init.php qui envoie des headers HTTP en CLI)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

TenantContext::ensureSchema();
$pdo = getDB();
$tenantId = 1;
TenantContext::setTenantId($tenantId);

// ── Helpers ──────────────────────────────────────────────────────────────────

function seedLog(string $msg): void
{
    echo $msg . PHP_EOL;
}

function seedTableHasColumn(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = "$table.$column";
    if (!isset($cache[$key])) {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);
        $cache[$key] = (bool) $stmt->fetchColumn();
    }
    return $cache[$key];
}

function seedInsert(PDO $pdo, string $table, array $data, int $tenantId): int
{
    if (seedTableHasColumn($pdo, $table, 'tenant_id') && !array_key_exists('tenant_id', $data)) {
        $data['tenant_id'] = $tenantId;
    }
    $cols = array_keys($data);
    $placeholders = array_fill(0, count($cols), '?');
    $sql = 'INSERT INTO `' . str_replace('`', '``', $table) . '` (`'
        . implode('`,`', $cols) . '`) VALUES (' . implode(',', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($data));
    return (int) $pdo->lastInsertId();
}

function seedCount(PDO $pdo, string $table): int
{
    return (int) $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
}

function seedParam(PDO $pdo, int $tenantId, string $cle, string $valeur, string $desc = ''): void
{
    $params = [$cle];
    $where = 'cle = ?';
    if (seedTableHasColumn($pdo, 'parametres_systeme', 'tenant_id')) {
        $where .= ' AND tenant_id = ?';
        $params[] = $tenantId;
    }
    $stmt = $pdo->prepare("SELECT id FROM parametres_systeme WHERE $where LIMIT 1");
    $stmt->execute($params);
    if ($stmt->fetchColumn()) {
        $stmt = $pdo->prepare("UPDATE parametres_systeme SET valeur = ? WHERE $where");
        $stmt->execute(array_merge([$valeur], $params));
        return;
    }
    $row = ['cle' => $cle, 'valeur' => $valeur, 'description' => $desc];
    seedInsert($pdo, 'parametres_systeme', $row, $tenantId);
}

// ── Reset optionnel ───────────────────────────────────────────────────────────

if ($reset) {
    seedLog('Réinitialisation des données métier…');
    passthru('php ' . escapeshellarg(__DIR__ . '/vider_donnees.php') . ' --metier --confirm 2>&1');
    TenantContext::setTenantId($tenantId);
}

seedLog('=== Génération des données de démonstration (tenant #' . $tenantId . ') ===');

// ── Paramètres établissement ────────────────────────────────────────────────

seedParam($pdo, $tenantId, 'nom_etablissement', 'Clinique et Hôpital', 'Nom affiché');
seedParam($pdo, $tenantId, 'adresse_etablissement', 'ACI 2000, Bamako', 'Adresse');
seedParam($pdo, $tenantId, 'telephone_etablissement', '+223 20 22 33 44', 'Téléphone');
seedLog('✓ Paramètres système');

// ── Utilisateurs (si absents) ─────────────────────────────────────────────────

$users = [
    ['admin', 'admin@clinique.local', 'admin123', 'admin', 1],
    ['medecin', 'medecin@clinique.local', 'medecin123', 'medecin', 0],
    ['secretaire', 'secretaire@clinique.local', '123456', 'secretaire', 0],
    ['infirmier', 'infirmier@clinique.local', 'infirmier123', 'infirmier', 0],
    ['comptable', 'comptable@clinique.local', 'comptable123', 'comptable', 0],
    ['pharmacien', 'pharmacien@clinique.local', 'pharmacien123', 'pharmacien', 0],
    ['laborantin', 'laborantin@clinique.local', 'laborantin123', 'laborantin', 0],
    ['technicien', 'technicien@clinique.local', 'technicien123', 'technicien', 0],
];

$userIds = [];
foreach ($users as [$login, $email, $pass, $role, $platformAdmin]) {
    $stmt = $pdo->prepare(
        seedTableHasColumn($pdo, 'utilisateurs', 'tenant_id')
            ? 'SELECT id FROM utilisateurs WHERE nom_utilisateur = ? AND tenant_id = ?'
            : 'SELECT id FROM utilisateurs WHERE nom_utilisateur = ?'
    );
    $stmt->execute(seedTableHasColumn($pdo, 'utilisateurs', 'tenant_id') ? [$login, $tenantId] : [$login]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        $userIds[$login] = (int) $existing;
        $pdo->prepare('UPDATE utilisateurs SET role = ?, statut = ? WHERE id = ?')
            ->execute([$role, 'actif', (int) $existing]);
        continue;
    }
    $row = [
        'nom_utilisateur' => $login,
        'email' => $email,
        'mot_de_passe' => password_hash($pass, PASSWORD_DEFAULT),
        'role' => $role,
        'statut' => 'actif',
    ];
    if (seedTableHasColumn($pdo, 'utilisateurs', 'tenant_id')) {
        $row['tenant_id'] = $tenantId;
    }
    if (seedTableHasColumn($pdo, 'utilisateurs', 'is_platform_admin')) {
        $row['is_platform_admin'] = $platformAdmin;
    }
    $userIds[$login] = seedInsert($pdo, 'utilisateurs', $row, $tenantId);
}
$adminId = $userIds['admin'];
seedLog('✓ Utilisateurs (' . count($userIds) . ') — admin/admin123, medecin, secretaire, infirmier, comptable, pharmacien, laborantin, technicien');

// ── Modèles ─────────────────────────────────────────────────────────────────

require_once __DIR__ . '/../models/Medecin.php';
require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../models/Consultation.php';
require_once __DIR__ . '/../models/RendezVous.php';
require_once __DIR__ . '/../models/Analyse.php';
require_once __DIR__ . '/../models/Paiement.php';
require_once __DIR__ . '/../models/Personnel.php';
require_once __DIR__ . '/../models/Medicament.php';
require_once __DIR__ . '/../models/Assurance.php';
require_once __DIR__ . '/../models/Dossier.php';
require_once __DIR__ . '/../models/Finances.php';
require_once __DIR__ . '/../models/Maintenance.php';
require_once __DIR__ . '/../models/Communication.php';

$medecinModel = new Medecin();
$patientModel = new Patient();
$consultationModel = new Consultation();
$rdvModel = new RendezVous();
$analyseModel = new Analyse();
$paiementModel = new Paiement();
$personnelModel = new Personnel();
$medicamentModel = new Medicament();
$assuranceModel = new Assurance();
$dossierModel = new Dossier();
$financesModel = new Finances();
$maintenanceModel = new Maintenance();
$commModel = new Communication();

// ── Médecins ──────────────────────────────────────────────────────────────────

$medecinDefs = [
    ['Coulibaly', 'Aminata', 'Médecine générale', 'MED20260001'],
    ['Traoré', 'Moussa', 'Cardiologie', 'MED20260002'],
    ['Diallo', 'Fatoumata', 'Pédiatrie', 'MED20260003'],
];
$medecinIds = [];
foreach ($medecinDefs as $i => [$nom, $prenom, $spec, $licence]) {
    $medecinModel->create([
        'numero_ordre' => 'DEMO-MED-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
        'numero_licence' => $licence,
        'nom' => $nom,
        'prenom' => $prenom,
        'specialite' => $spec,
        'telephone' => '7600000' . ($i + 1),
        'email' => strtolower($prenom) . '.' . strtolower($nom) . '@clinique.local',
        'adresse' => 'Bamako',
        'ville' => 'Bamako',
        'pays' => 'Mali',
        'date_embauche' => date('Y-m-d', strtotime('-' . (3 - $i) . ' years')),
        'statut' => 'actif',
    ]);
    $medecinIds[] = (int) $pdo->query('SELECT MAX(id) FROM medecins')->fetchColumn();
}
if (seedTableHasColumn($pdo, 'medecins', 'utilisateur_id') && !empty($userIds['medecin']) && !empty($medecinIds[0])) {
    $pdo->prepare('UPDATE medecins SET utilisateur_id = ? WHERE id = ?')
        ->execute([$userIds['medecin'], $medecinIds[0]]);
}
seedLog('✓ Médecins (' . count($medecinIds) . ')');

// ── Patients ──────────────────────────────────────────────────────────────────

$patientDefs = [
    ['Koné', 'Ibrahim', 'M', '1990-05-12', 'O+'],
    ['Sissoko', 'Mariam', 'F', '1985-11-03', 'A+'],
    ['Keita', 'Oumar', 'M', '1978-02-20', 'B+'],
    ['Diarra', 'Awa', 'F', '1995-08-07', 'AB+'],
    ['Cissé', 'Modibo', 'M', '2000-01-15', 'O-'],
    ['Touré', 'Salimata', 'F', '1988-06-30', 'A-'],
    ['Ba', 'Cheick', 'M', '1972-12-01', 'B-'],
    ['Sanogo', 'Rokia', 'F', '1998-04-18', 'O+'],
];
$patientIds = [];
$year = date('Y');
foreach ($patientDefs as $i => [$nom, $prenom, $sexe, $dn, $gs]) {
    $num = 'P' . $year . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);
    $id = $patientModel->create([
        'numero_dossier' => $num,
        'nom' => $nom,
        'prenom' => $prenom,
        'date_naissance' => $dn,
        'genre' => $sexe,
        'groupe_sanguin' => $gs,
        'telephone' => '65' . str_pad((string) (100000 + $i), 6, '0', STR_PAD_LEFT),
        'email' => strtolower($prenom) . '.' . strtolower($nom) . '@demo.ml',
        'adresse' => 'Quartier ' . ($i + 1) . ', Bamako',
        'ville' => 'Bamako',
        'pays' => 'Mali',
        'statut' => 'actif',
        'antecedents_medicaux' => $i % 2 === 0 ? 'Hypertension légère' : null,
        'allergies' => $i === 3 ? 'Pénicilline' : null,
    ]);
    $patientIds[] = (int) $id;
}
seedLog('✓ Patients (' . count($patientIds) . ')');

// ── Référentiels (hospitalisation, soins, tarifs) ─────────────────────────────

$catHospId = seedInsert($pdo, 'categories_hospitalisation', [
    'nom' => 'Chambre standard',
    'description' => 'Hospitalisation en chambre commune',
    'prix_jour' => 15000,
    'statut' => 'actif',
], $tenantId);
$catHospVipId = seedInsert($pdo, 'categories_hospitalisation', [
    'nom' => 'Chambre VIP',
    'description' => 'Chambre individuelle climatisée',
    'prix_jour' => 35000,
    'statut' => 'actif',
], $tenantId);

$soinIds = [];
foreach ([
    ['Pansement simple', 'soins_infirmiers', 5000],
    ['Injection IM', 'soins_infirmiers', 3000],
    ['NFS (Numération)', 'examens_complementaires', 8000],
] as [$nom, $type, $prix]) {
    $soinIds[] = seedInsert($pdo, 'soins_consultation', [
        'nom' => $nom,
        'description' => 'Soin de démonstration',
        'prix' => $prix,
        'type_soin' => $type,
        'duree_minutes' => 20,
        'statut' => 'actif',
    ], $tenantId);
}

seedInsert($pdo, 'tarifs_consultation', [
    'type_consultation' => 'consultation_simple',
    'specialite' => 'generaliste',
    'prix' => 10000,
    'description' => 'Consultation généraliste',
    'statut' => 'actif',
], $tenantId);
seedInsert($pdo, 'tarifs_consultation', [
    'type_consultation' => 'consultation_specialisee',
    'specialite' => 'cardiologie',
    'prix' => 20000,
    'description' => 'Consultation spécialisée',
    'statut' => 'actif',
], $tenantId);
seedLog('✓ Référentiels (hospitalisation, soins, tarifs)');

// ── Consultations (certaines aujourd'hui) ─────────────────────────────────────

$today = date('Y-m-d');
$consultationIds = [];
$consultationPlans = [
    [$patientIds[0], $medecinIds[0], "$today 09:00:00", 'terminee', 'Fièvre et fatigue', 10000],
    [$patientIds[1], $medecinIds[1], "$today 10:30:00", 'en_cours', 'Douleur thoracique', 20000],
    [$patientIds[2], $medecinIds[0], date('Y-m-d H:i:s', strtotime('-2 days 14:00')), 'terminee', 'Contrôle tension', 10000],
    [$patientIds[3], $medecinIds[2], date('Y-m-d H:i:s', strtotime('+1 day 11:00')), 'planifiee', 'Suivi pédiatrique', 15000],
    [$patientIds[4], $medecinIds[1], date('Y-m-d H:i:s', strtotime('-5 days 08:30')), 'terminee', 'Palpitations', 20000],
];

foreach ($consultationPlans as $idx => [$pid, $mid, $date, $statut, $sympt, $prix]) {
    $cid = (int) $consultationModel->create([
        'patient_id' => $pid,
        'medecin_id' => $mid,
        'date_consultation' => $date,
        'symptomes' => $sympt,
        'diagnostic' => $statut === 'terminee' ? 'Diagnostic établi — cas démo' : null,
        'traitement' => $statut === 'terminee' ? 'Repos, hydratation, suivi' : null,
        'statut' => $statut,
        'prix_consultation' => $prix,
        'type_consultation' => $prix >= 20000 ? 'consultation_specialisee' : 'consultation_simple',
        'soins_data' => $idx === 0 ? [[
            'id' => $soinIds[0],
            'quantite' => 1,
            'prix' => 5000,
            'total' => 5000,
        ]] : null,
        'hospitalisation_data' => $idx === 1 ? [
            'categorie_id' => $catHospId,
            'duree' => 2,
            'prix_jour' => 15000,
            'prix_total' => 30000,
            'date_admission' => date('Y-m-d H:i:s'),
            'statut' => 'en_cours',
        ] : null,
    ]);
    $consultationIds[] = $cid;
}
seedLog('✓ Consultations (' . count($consultationIds) . ', dont ' . count(array_filter($consultationPlans, function ($p) use ($today) {
    return strpos($p[2], $today) === 0;
})) . " aujourd'hui)");

// ── Rendez-vous ───────────────────────────────────────────────────────────────

$rdvPlans = [
    [$patientIds[5], $medecinIds[0], $today, '09:30:00', 'Contrôle annuel', 'confirme'],
    [$patientIds[6], $medecinIds[1], $today, '14:00:00', 'ECG de contrôle', 'planifie'],
    [$patientIds[7], $medecinIds[2], date('Y-m-d', strtotime('+2 days')), '10:00:00', 'Vaccination enfant', 'planifie'],
];
foreach ($rdvPlans as [$pid, $mid, $date, $heure, $motif, $statut]) {
    $rdvModel->create([
        'patient_id' => $pid,
        'medecin_id' => $mid,
        'date_rdv' => $date,
        'heure_rdv' => $heure,
        'motif' => $motif,
        'statut' => $statut,
    ]);
}
seedLog('✓ Rendez-vous (' . count($rdvPlans) . ', dont 2 aujourd\'hui)');

// ── Analyses laboratoire ──────────────────────────────────────────────────────

$analysePlans = [
    [$patientIds[0], $medecinIds[0], 'NFS', 'en_cours'],
    [$patientIds[1], $medecinIds[1], 'Glycémie', 'en_cours'],
    [$patientIds[2], $medecinIds[0], 'Créatinine', 'termine'],
    [$patientIds[3], $medecinIds[2], 'Groupage sanguin', 'en_attente'],
];
foreach ($analysePlans as [$pid, $mid, $type, $statut]) {
    $analyseModel->create([
        'patient_id' => $pid,
        'medecin_id' => $mid,
        'type_analyse' => $type,
        'priorite' => 'normale',
        'description' => "Analyse $type — démo",
        'statut' => $statut,
        'prix_analyse' => 7500,
    ]);
}
seedLog('✓ Analyses laboratoire (' . count($analysePlans) . ', 2 en cours)');

// ── Paiements ─────────────────────────────────────────────────────────────────

$paiementPlans = [
    [$patientIds[0], $consultationIds[0], 'FAC-' . date('Y') . '-001', 15000, 'paye', 'mobile_money'],
    [$patientIds[1], $consultationIds[1], 'FAC-' . date('Y') . '-002', 50000, 'en_attente', 'especes'],
    [$patientIds[2], $consultationIds[2], 'FAC-' . date('Y') . '-003', 10000, 'paye', 'especes'],
    [$patientIds[4], $consultationIds[4], 'FAC-' . date('Y') . '-004', 20000, 'partiel', 'carte'],
];
foreach ($paiementPlans as [$pid, $cid, $facture, $montant, $statut, $type]) {
    try {
        $paiementModel->create([
            'patient_id' => $pid,
            'consultation_id' => $cid,
            'numero_facture' => $facture,
            'montant' => $montant,
            'type_paiement' => $type,
            'statut' => $statut,
            'description' => 'Paiement consultation — démo',
            'date_paiement' => date('Y-m-d H:i:s'),
        ]);
    } catch (Throwable $e) {
        seedInsert($pdo, 'paiements', [
            'patient_id' => $pid,
            'consultation_id' => $cid,
            'numero_facture' => $facture,
            'montant' => $montant,
            'type_paiement' => $type,
            'statut' => $statut,
            'description' => 'Paiement consultation — démo',
            'date_paiement' => date('Y-m-d H:i:s'),
        ], $tenantId);
    }
}
seedLog('✓ Paiements (' . count($paiementPlans) . ')');

// ── Assurances ────────────────────────────────────────────────────────────────

$assurId = (int) $assuranceModel->create([
    'nom' => 'AMO Mali Demo',
    'type' => 'mutuelle',
    'numero_agrement' => 'AMO-2026-001',
    'telephone' => '20202020',
    'taux_remboursement' => 80,
    'statut' => 'actif',
]);
if (seedTableHasColumn($pdo, 'assurances', 'tenant_id')) {
    $pdo->prepare('UPDATE assurances SET tenant_id = ? WHERE id = ?')->execute([$tenantId, $assurId]);
}

$paiementId = (int) $pdo->query('SELECT id FROM paiements ORDER BY id ASC LIMIT 1')->fetchColumn();

$contratId = seedInsert($pdo, 'contrats_assurance', [
    'patient_id' => $patientIds[0],
    'assurance_id' => $assurId,
    'numero_contrat' => 'CTR-001',
    'numero_adherent' => 'ADH-1001',
    'date_debut' => date('Y-m-d', strtotime('-1 year')),
    'taux_remboursement' => 80,
    'statut' => 'actif',
], $tenantId);
seedInsert($pdo, 'remboursements', [
    'contrat_id' => $contratId,
    'paiement_id' => $paiementId ?: null,
    'consultation_id' => $consultationIds[0] ?? null,
    'montant_total' => 15000,
    'montant_rembourse' => 12000,
    'date_demande' => date('Y-m-d', strtotime('-10 days')),
    'statut' => 'approuve',
], $tenantId);
seedLog('✓ Assurances (1 assureur + contrat + remboursement)');

// ── Personnel ─────────────────────────────────────────────────────────────────

$personnelIds = [];
foreach ([
    ['Diakité', 'Aissata', 'Infirmière', 'Soins', 'infirmier@clinique.local'],
    ['Doumbia', 'Sekou', 'Secrétaire médical', 'Administration', 'secretaire@clinique.local'],
    ['Kanté', 'Hawa', 'Comptable', 'Finances', 'comptable@clinique.local'],
    ['Sangaré', 'Mamadou', 'Laborantin', 'Laboratoire', 'laborantin@clinique.local'],
] as [$nom, $prenom, $poste, $dept, $email]) {
    $personnelModel->create([
        'nom' => $nom,
        'prenom' => $prenom,
        'poste' => $poste,
        'departement' => $dept,
        'date_embauche' => date('Y-m-d', strtotime('-1 year')),
        'telephone' => '76' . random_int(1000000, 9999999),
        'email' => $email,
        'statut' => 'actif',
        'type_contrat' => 'CDI',
    ]);
    $personnelIds[] = (int) $pdo->query('SELECT MAX(id) FROM personnel')->fetchColumn();
}

$infirmierPersonnelId = $personnelIds[0] ?? null;
$laborantinPersonnelId = $personnelIds[3] ?? null;
if (seedTableHasColumn($pdo, 'personnel', 'utilisateur_id')) {
    if ($infirmierPersonnelId) {
        $pdo->prepare('UPDATE personnel SET utilisateur_id = ? WHERE id = ?')
            ->execute([$userIds['infirmier'], $infirmierPersonnelId]);
    }
    if ($laborantinPersonnelId) {
        $pdo->prepare('UPDATE personnel SET utilisateur_id = ? WHERE id = ?')
            ->execute([$userIds['laborantin'], $laborantinPersonnelId]);
    }
}
if ($infirmierPersonnelId && seedTableHasColumn($pdo, 'consultation_soins', 'personnel_id') && !empty($consultationIds[0])) {
    $pdo->prepare('UPDATE consultation_soins SET personnel_id = ? WHERE consultation_id = ?')
        ->execute([$infirmierPersonnelId, $consultationIds[0]]);
}
if ($laborantinPersonnelId && seedTableHasColumn($pdo, 'analyses', 'technicien_id')) {
    $pdo->prepare('UPDATE analyses SET technicien_id = ? WHERE statut IN (\'en_cours\', \'en_attente\') LIMIT 2')
        ->execute([$laborantinPersonnelId]);
}

if (!empty($personnelIds)) {
    seedInsert($pdo, 'horaires_personnel', [
        'personnel_id' => $personnelIds[0],
        'jour_semaine' => 'lundi',
        'heure_debut' => '08:00:00',
        'heure_fin' => '17:00:00',
        'actif' => 1,
    ], $tenantId);
    seedInsert($pdo, 'conges_personnel', [
        'personnel_id' => $personnelIds[0],
        'type_conge' => 'annuel',
        'date_debut' => date('Y-m-d', strtotime('+30 days')),
        'date_fin' => date('Y-m-d', strtotime('+35 days')),
        'nombre_jours' => 5,
        'statut' => 'approuve',
        'motif' => 'Congé annuel — démo',
    ], $tenantId);
}
seedLog('✓ Personnel (' . count($personnelIds) . ' + horaires/congés)');

// ── Pharmacie ─────────────────────────────────────────────────────────────────

$medIds = [];
foreach ([
    ['PARA500', 'Paracétamol 500mg', 'Paracétamol', 500, 250],
    ['AMOX500', 'Amoxicilline 500mg', 'Amoxicilline', 200, 1200],
    ['IBUP400', 'Ibuprofène 400mg', 'Ibuprofène', 350, 800],
] as [$code, $nom, $gen, $stock, $prix]) {
    $medIds[] = (int) $medicamentModel->create([
        'code_medicament' => $code,
        'nom_commercial' => $nom,
        'nom_generique' => $gen,
        'categorie' => 'Antalgiques',
        'forme' => 'comprime',
        'stock_actuel' => $stock,
        'stock_minimum' => 50,
        'prix_unitaire' => $prix,
        'fournisseur' => 'Pharma Mali SARL',
        'date_peremption' => date('Y-m-d', strtotime('+18 months')),
        'statut' => 'disponible',
    ]);
    if (seedTableHasColumn($pdo, 'medicaments', 'tenant_id')) {
        $pdo->prepare('UPDATE medicaments SET tenant_id = ? WHERE id = ?')->execute([$tenantId, end($medIds)]);
    }
}

$commandeId = seedInsert($pdo, 'commandes_pharmacie', [
    'numero_commande' => 'CMD-' . date('Y') . '-001',
    'fournisseur' => 'Pharma Mali SARL',
    'date_commande' => date('Y-m-d'),
    'date_livraison_prevue' => date('Y-m-d', strtotime('+7 days')),
    'montant_total' => 450000,
    'statut' => 'confirmee',
    'cree_par' => $adminId,
], $tenantId);
seedInsert($pdo, 'lignes_commande_pharmacie', [
    'commande_id' => $commandeId,
    'medicament_id' => $medIds[0],
    'quantite' => 200,
    'prix_unitaire' => 250,
], $tenantId);
if ($pdo->query("SHOW TABLES LIKE 'mouvements_stock_pharmacie'")->fetchColumn()) {
    seedInsert($pdo, 'mouvements_stock_pharmacie', [
        'medicament_id' => $medIds[0],
        'type_mouvement' => 'entree',
        'quantite' => 200,
        'motif' => 'Réception commande démo',
        'reference' => 'CMD-' . date('Y') . '-001',
        'utilisateur_id' => $adminId,
    ], $tenantId);
}
seedLog('✓ Pharmacie (' . count($medIds) . ' médicaments + 1 commande)');

// ── Dossiers médicaux ─────────────────────────────────────────────────────────

foreach (array_slice($patientIds, 0, 5) as $i => $pid) {
    $dossierModel->create([
        'patient_id' => $pid,
        'groupe_sanguin' => ['O+', 'A+', 'B+', 'AB+', 'O-'][$i],
        'priorite' => ['basse', 'moyenne', 'haute', 'moyenne', 'basse'][$i],
        'antecedents' => 'Antécédents démo patient #' . ($i + 1),
        'allergies' => $i === 3 ? 'Pénicilline' : 'Aucune connue',
        'statut' => 'actif',
    ]);
}
seedLog('✓ Dossiers médicaux (5)');

// ── Finances ──────────────────────────────────────────────────────────────────

try {
    $compteCaisse = (int) $pdo->query(
        "SELECT id FROM comptes_comptables WHERE type_compte = 'actif' ORDER BY id LIMIT 1"
    )->fetchColumn();
    $compteRecettes = (int) $pdo->query(
        "SELECT id FROM comptes_comptables WHERE type_compte = 'produit' ORDER BY id LIMIT 1"
    )->fetchColumn();

    if (!$compteCaisse) {
        $compteCaisse = (int) $financesModel->createCompte([
            'numero_compte' => 'DEMO-531000',
            'libelle' => 'Caisse principale',
            'type_compte' => 'actif',
            'categorie' => 'Trésorerie',
            'statut' => 'actif',
        ]);
    }
    if (!$compteRecettes) {
        $compteRecettes = (int) $financesModel->createCompte([
            'numero_compte' => 'DEMO-706000',
            'libelle' => 'Recettes consultations',
            'type_compte' => 'produit',
            'categorie' => 'Activité',
            'statut' => 'actif',
        ]);
    }
    if (seedCount($pdo, 'ecritures_comptables') === 0 && $compteCaisse && $compteRecettes) {
        seedInsert($pdo, 'ecritures_comptables', [
            'numero_ecriture' => 'ECR-' . date('Y') . '-001',
            'date_ecriture' => date('Y-m-d'),
            'compte_debit_id' => $compteCaisse,
            'compte_credit_id' => $compteRecettes,
            'montant' => 15000,
            'libelle' => 'Encaissement consultation démo',
            'valide' => 1,
            'statut' => 'valide',
            'cree_par' => $adminId,
        ], $tenantId);
    }
    if (seedCount($pdo, 'budgets') === 0) {
        seedInsert($pdo, 'budgets', [
            'annee' => (int) date('Y'),
            'departement' => 'Médical',
            'categorie' => 'Consultations',
            'montant_alloue' => 5000000,
            'montant_utilise' => 450000,
            'statut' => 'en_cours',
            'cree_par' => $adminId,
        ], $tenantId);
    }
    seedLog('✓ Finances (comptes, écriture, budget)');
} catch (Throwable $e) {
    seedLog('⚠ Finances partiel : ' . $e->getMessage());
}

// ── Maintenance ───────────────────────────────────────────────────────────────

try {
    $equipId = (int) $maintenanceModel->createEquipement([
        'numero_serie' => 'EQ-2026-001',
        'nom' => 'Électrocardiographe',
        'categorie' => 'Cardiologie',
        'marque' => 'Philips',
        'date_acquisition' => date('Y-m-d', strtotime('-2 years')),
        'valeur' => 2500000,
        'localisation' => 'Bloc cardiologie',
        'statut' => 'disponible',
    ]);
    $maintenanceModel->createIntervention([
        'equipement_id' => $equipId,
        'type_intervention' => 'preventive',
        'date_intervention' => date('Y-m-d', strtotime('-30 days')),
        'technicien' => 'Technicien Demo',
        'cout' => 75000,
        'description' => 'Maintenance préventive annuelle',
        'statut' => 'terminee',
        'prochaine_intervention' => date('Y-m-d', strtotime('+11 months')),
        'cree_par' => $adminId,
    ]);
    if ($pdo->query("SHOW TABLES LIKE 'stocks_materiel'")->fetchColumn()) {
        seedInsert($pdo, 'stocks_materiel', [
            'code_materiel' => 'STK-001',
            'nom' => 'Gants stériles (boîte)',
            'categorie' => 'Consommables',
            'stock_actuel' => 120,
            'stock_minimum' => 30,
            'unite' => 'boîte',
            'prix_unitaire' => 2500,
            'fournisseur' => 'MedSupply Mali',
            'statut' => 'disponible',
        ], $tenantId);
    }
    seedLog('✓ Maintenance (équipement + intervention + stock)');
} catch (Throwable $e) {
    seedLog('⚠ Maintenance partiel : ' . $e->getMessage());
}

// ── Communication ─────────────────────────────────────────────────────────────

$commModel->createMessage([
    'expediteur_id' => $adminId,
    'destinataire_id' => $userIds['medecin'],
    'sujet' => 'Réunion staff médical',
    'message' => 'Réunion demain à 8h en salle de conférence. Merci de confirmer votre présence.',
    'priorite' => 'normale',
]);
$commModel->createMessage([
    'expediteur_id' => $userIds['secretaire'],
    'destinataire_id' => $adminId,
    'sujet' => 'Stock pharmacie',
    'message' => 'Alerte : le stock de Paracétamol descend sous le seuil minimum.',
    'priorite' => 'haute',
]);
$commModel->createAnnonce([
    'titre' => 'Bienvenue sur Se.Santé Demo',
    'contenu' => 'Jeu de données de démonstration généré automatiquement. Explorez tous les modules.',
    'type' => 'information',
    'destinataires' => 'tous',
    'date_debut' => date('Y-m-d H:i:s'),
    'date_fin' => date('Y-m-d H:i:s', strtotime('+30 days')),
    'actif' => 1,
    'cree_par' => $adminId,
]);
seedLog('✓ Communication (2 messages + 1 annonce)');

// ── Notifications ─────────────────────────────────────────────────────────────

seedInsert($pdo, 'notifications', [
    'user_id' => $adminId,
    'type' => 'info',
    'titre' => 'Données de démo chargées',
    'message' => 'Le jeu de données de test a été généré avec succès.',
    'module' => 'systeme',
    'lu' => 0,
], $tenantId);
seedInsert($pdo, 'notifications', [
    'user_id' => $userIds['medecin'],
    'type' => 'warning',
    'titre' => '2 analyses en cours',
    'message' => 'Des analyses laboratoire nécessitent votre attention.',
    'module' => 'laboratoire',
    'lien' => 'laboratoire/index.php',
    'lu' => 0,
], $tenantId);
seedLog('✓ Notifications (2)');

// ── Cache ─────────────────────────────────────────────────────────────────────

CacheSystem::getInstance()->clear();
seedLog('✓ Cache vidé');

// ── Résumé ────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/../includes/saas/DashboardStats.php';
TenantContext::setTenantId($tenantId);
$stats = DashboardStats::get();

seedLog('');
seedLog('=== Terminé — KPIs dashboard ===');
seedLog('  Patients              : ' . $stats['patients']);
seedLog('  Consult. aujourd\'hui  : ' . $stats['consultations_aujourd_hui']);
seedLog('  RDV du jour           : ' . $stats['rdv_aujourd_hui']);
seedLog('  Analyses en cours     : ' . $stats['analyses_en_cours']);
seedLog('  Médecins actifs       : ' . $stats['medecins_actifs']);
seedLog('  Paiements             : ' . $stats['paiements_total']);
seedLog('');
seedLog('Comptes de connexion :');
seedLog('  admin / admin123');
seedLog('  medecin / medecin123');
seedLog('  secretaire / 123456');
seedLog('  infirmier / infirmier123');
