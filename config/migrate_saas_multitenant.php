<?php
/**
 * Migration SaaS multi-tenant — à exécuter une fois.
 * URL : /config/migrate_saas_multitenant.php
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/saas/TenantSchema.php';

header('Content-Type: text/html; charset=utf-8');

$messages = [];

try {
    TenantSchema::finalizeIsolation();
    $messages[] = ['ok', 'Schéma multi-tenant créé / mis à jour avec succès.'];
    $messages[] = ['info', 'Lignes orphelines (tenant_id NULL) rattachées au premier tenant si nécessaire.'];
    $messages[] = ['info', 'Tables : tenants, subscription_orders'];
    $messages[] = ['info', 'Colonnes tenant_id ajoutées aux tables métier.'];
    $messages[] = ['info', 'Tenant par défaut créé pour les données existantes.'];
    $messages[] = ['info', 'Plans disponibles : Essentiel 70 000 FCFA (5 utilisateurs) · Pro 100 000 FCFA (15 utilisateurs) · Licence à vie 550 000 FCFA'];
} catch (Throwable $e) {
    $messages[] = ['err', 'Erreur : ' . $e->getMessage()];
}

$tables = TenantSchema::getScopedTables();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Migration SaaS — Efficasante</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-5">
    <div class="container" style="max-width:720px">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h1 class="h5 mb-0">Migration SaaS multi-tenant</h1>
            </div>
            <div class="card-body">
                <?php foreach ($messages as [$type, $msg]): ?>
                <div class="alert alert-<?php echo $type === 'ok' ? 'success' : ($type === 'err' ? 'danger' : 'info'); ?>">
                    <?php echo htmlspecialchars($msg); ?>
                </div>
                <?php endforeach; ?>

                <h2 class="h6 mt-3">Tables avec isolation tenant_id</h2>
                <ul class="small">
                    <?php foreach ($tables as $t): ?>
                    <li><code><?php echo htmlspecialchars($t); ?></code></li>
                    <?php endforeach; ?>
                </ul>

                <div class="d-flex gap-2 mt-4">
                    <a href="../tarifs.php" class="btn btn-primary">Voir les tarifs</a>
                    <a href="../admin_tenants.php" class="btn btn-outline-secondary">Admin SaaS</a>
                    <a href="../dashboard.php" class="btn btn-outline-secondary">Dashboard</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
