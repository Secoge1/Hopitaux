<?php
/**
 * Vérification génération PDF guide utilisateur.
 * Usage : php config/verify_user_guide_pdf.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("CLI uniquement.\n");
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/saas/TenantContext.php';
require_once __DIR__ . '/../includes/user_guide_content.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ok = 0;
$fail = 0;

function vok(string $m): void { global $ok; $ok++; echo "OK  $m\n"; }
function vfail(string $m): void { global $fail; $fail++; echo "FAIL $m\n"; }

echo "=== Vérification guide utilisateur PDF ===\n\n";

$tcpdf = dirname(__DIR__) . '/vendor/tecnickcom/tcpdf/tcpdf.php';
is_file($tcpdf) ? vok('TCPDF installé') : vfail('TCPDF manquant');

is_file(dirname(__DIR__) . '/includes/user_guide_content.php') ? vok('user_guide_content.php') : vfail('user_guide_content.php');
is_file(dirname(__DIR__) . '/includes/user_guide_pdf.php') ? vok('user_guide_pdf.php') : vfail('user_guide_pdf.php');
is_file(dirname(__DIR__) . '/parametres/guide_utilisateurs.php') ? vok('parametres/guide_utilisateurs.php') : vfail('guide_utilisateurs.php');
is_file(dirname(__DIR__) . '/parametres/generer_guide_pdf.php') ? vok('parametres/generer_guide_pdf.php') : vfail('generer_guide_pdf.php');

$chapters = user_guide_onboarding();
count($chapters) >= 10 ? vok(count($chapters) . ' chapitres') : vfail('Chapitres insuffisants');

$roles = user_guide_roles_table_rows();
count($roles) === 8 ? vok('8 rôles documentés') : vfail('Rôles : ' . count($roles));

$_SESSION['tenant_id'] = (int) (getDB()->query('SELECT id FROM tenants ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 1);
TenantContext::setTenantId((int) $_SESSION['tenant_id']);

$html = user_guide_build_pdf_html((int) $_SESSION['tenant_id']);
strlen($html) > 2000 ? vok('HTML PDF généré (' . strlen($html) . ' octets)') : vfail('HTML PDF trop court');

if (is_file($tcpdf)) {
    require_once __DIR__ . '/../includes/pdf_branding.php';
    require_once $tcpdf;
    $out = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'guide_test_' . getmypid() . '.pdf';
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage();
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output($out, 'F');
    if (is_file($out) && filesize($out) > 5000) {
        vok('PDF écrit (' . filesize($out) . ' octets)');
        @unlink($out);
    } else {
        vfail('PDF non généré ou trop petit');
    }
}

echo "\n=== Résultat : {$ok} OK / {$fail} FAIL ===\n";
exit($fail > 0 ? 1 : 0);
