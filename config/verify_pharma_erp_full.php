<?php

define('APP_SKIP_HTML_CONTENT_TYPE', true);

$_SERVER['REQUEST_URI'] = '/config/verify_pharma_erp_full.php';



error_reporting(E_ALL);

ini_set('display_errors', '1');



$failures = [];

$ok = 0;



function check(bool $cond, string $label): void

{

    global $failures, $ok;

    if ($cond) {

        echo "OK  $label\n";

        $ok++;

    } else {

        echo "FAIL $label\n";

        $failures[] = $label;

    }

}



echo "=== PharmaPro ERP — verification complete ===\n\n";



require_once __DIR__ . '/../config/db.php';



$pdo = getDBSoft();

if (!$pdo instanceof PDO) {

    echo "SKIP DB — connexion MySQL indisponible (WAMP arrêté ou identifiants incorrects)\n\n";

} else {

    try {

        require_once __DIR__ . '/../includes/pharma_erp/PharmaErpSchema.php';

        PharmaErpSchema::ensure();



        echo "-- Core tables --\n";

        foreach (['pe_pharmacies', 'pe_products', 'pe_sales', 'pe_purchase_orders', 'pe_accounts', 'pe_journal_entries'] as $t) {

            check((bool) $pdo->query("SHOW TABLES LIKE '$t'")->fetchColumn(), $t);

        }



        echo "\n-- Migration 008 --\n";

        foreach (['pe_promotions', 'pe_loyalty_accounts', 'pe_inventories', 'pe_patients', 'pe_prescriptions', 'pe_bank_accounts', 'pe_vat_periods', 'pe_returns', 'pe_return_lines'] as $t) {

            check((bool) $pdo->query("SHOW TABLES LIKE '$t'")->fetchColumn(), $t);

        }



        echo "\n-- Migration 009 --\n";

        foreach (['pe_customers', 'pe_fixed_assets'] as $t) {

            check((bool) $pdo->query("SHOW TABLES LIKE '$t'")->fetchColumn(), $t);

        }

        $colStmt = $pdo->prepare(

            "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS

             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pe_sale_lines' AND COLUMN_NAME = 'returned_quantity' LIMIT 1"

        );

        $colStmt->execute();

        check((bool) $colStmt->fetchColumn(), 'pe_sale_lines.returned_quantity');



        $m009 = $pdo->query("SELECT 1 FROM pe_schema_migrations WHERE version = '009' LIMIT 1")->fetchColumn();

        check((bool) $m009, 'pe_schema_migrations version 009');

    } catch (Throwable $e) {

        echo 'DB ERROR: ' . $e->getMessage() . "\n";

        $failures[] = 'database';

    }

}



echo "\n-- PHP models syntax --\n";

foreach (glob(__DIR__ . '/../models/pharma_erp/*.php') ?: [] as $f) {

    exec('php -l ' . escapeshellarg($f) . ' 2>&1', $out, $code);

    check($code === 0, basename($f) . ' syntax');

}



echo "\n-- Web pages exist --\n";

foreach ([

    'pharma_erp/sales/index.php', 'pharma_erp/sales/ticket.php', 'pharma_erp/sales/retours.php',

    'pharma_erp/sales/retour.php', 'pharma_erp/sales/retour_voir.php',

    'pharma_erp/clients/index.php', 'pharma_erp/clients/ajouter.php', 'pharma_erp/clients/modifier.php',

    'pharma_erp/purchases/factures.php', 'pharma_erp/purchases/facture_payer.php',

    'pharma_erp/settings/officines.php', 'pharma_erp/accounting/immobilisations.php',

    'pharma_erp/stock/inventaire.php', 'pharma_erp/promotions/index.php', 'pharma_erp/medical/index.php',

    'pharma_erp/accounting/banque.php', 'pharma_erp/accounting/tva.php',

    'pharma_erp/manifest.webmanifest', 'pharma_erp/sw.js',

    'api/rest/pharma/index.php',

] as $p) {

    check(is_file(__DIR__ . '/../' . $p), $p);

}



echo "\n-- Feature flag --\n";

require_once __DIR__ . '/../includes/saas/PlatformTenantFeatures.php';

check(PlatformTenantFeatures::featureStatus(PlatformTenantFeatures::PHARMA_ERP_SUITE) === 'live', 'pharma_erp_suite status live');



echo "\n-- TCPDF --\n";

check(is_file(__DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php'), 'vendor/tecnickcom/tcpdf/tcpdf.php');



echo "\n-- Class load (PHP " . PHP_VERSION . ") --\n";

foreach ([

    'PePromotion' => __DIR__ . '/../models/pharma_erp/PePromotion.php',

    'PeMedical' => __DIR__ . '/../models/pharma_erp/PeMedical.php',

    'PeInventory' => __DIR__ . '/../models/pharma_erp/PeInventory.php',

    'PeBank' => __DIR__ . '/../models/pharma_erp/PeBank.php',

    'PeSupplier' => __DIR__ . '/../models/pharma_erp/PeSupplier.php',

    'PeCustomer' => __DIR__ . '/../models/pharma_erp/PeCustomer.php',

    'PeReturn' => __DIR__ . '/../models/pharma_erp/PeReturn.php',

    'PeSupplierInvoice' => __DIR__ . '/../models/pharma_erp/PeSupplierInvoice.php',

    'PeFixedAsset' => __DIR__ . '/../models/pharma_erp/PeFixedAsset.php',

] as $name => $path) {

    try {

        require_once $path;

        check(class_exists($name), "load $name");

    } catch (Throwable $e) {

        echo "FAIL load $name — " . $e->getMessage() . "\n";

        $failures[] = "load $name";

    }

}



echo "\n-- API route markers --\n";

$apiSrc = file_get_contents(__DIR__ . '/../api/rest/pharma/index.php') ?: '';

foreach (['customers', 'returns', 'supplier-invoices'] as $route) {

    check(strpos($apiSrc, "'$route'") !== false, "API route $route");

}



echo "\n=== Result: $ok passed, " . count($failures) . " failed ===\n";

if ($failures) {

    foreach ($failures as $f) {

        echo "  - $f\n";

    }

    exit(1);

}

