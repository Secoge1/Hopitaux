<?php
define('APP_SKIP_HTML_CONTENT_TYPE', true);
$_SERVER['REQUEST_URI'] = '/config/verify_pharma_erp.php';

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/pharma_erp/PharmaErpSchema.php';

echo "PharmaPro ERP — verification schema\n";

try {
    PharmaErpSchema::ensure();
    $pdo = getDB();
    $tables = ['pe_pharmacies', 'pe_products', 'pe_sales', 'pe_purchase_orders', 'pe_accounts', 'pe_journal_entries'];
    foreach ($tables as $t) {
        $ok = (bool) $pdo->query("SHOW TABLES LIKE '$t'")->fetchColumn();
        echo ($ok ? 'OK' : 'FAIL') . " $t\n";
    }
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}
