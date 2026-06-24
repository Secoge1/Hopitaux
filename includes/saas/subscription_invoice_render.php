<?php
/**
 * Rendu HTML facture abonnement SaaS (admin plateforme).
 */

require_once __DIR__ . '/SubscriptionPlan.php';
require_once __DIR__ . '/saas_helpers.php';

if (!function_exists('platform_name')) {
    require_once __DIR__ . '/../platform_brand.php';
}

if (!function_exists('sub_invoice_url')) {
    function sub_invoice_url(string $path): string
    {
        if (function_exists('app_url')) {
            return app_url($path);
        }
        return '/' . ltrim($path, '/');
    }
}

if (!function_exists('sub_invoice_order_type_label')) {
    function sub_invoice_order_type_label(string $type): string
    {
        $map = ['new' => 'Nouvelle souscription', 'renewal' => 'Renouvellement', 'upgrade' => 'Mise à niveau'];
        return $map[$type] ?? ucfirst($type);
    }
}

if (!function_exists('sub_invoice_logo_fs_path')) {
    function sub_invoice_logo_fs_path(): ?string
    {
        if (!function_exists('platform_logo_path')) {
            require_once __DIR__ . '/../platform_brand.php';
        }
        $rel = ltrim(str_replace('\\', '/', platform_logo_path()), '/');
        $root = dirname(__DIR__, 2);
        $fs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        return is_file($fs) ? $fs : null;
    }
}

if (!function_exists('sub_invoice_build_pdf_html')) {
    /**
     * HTML compact compatible TCPDF (tables, pas de flex/grid).
     *
     * @param array<string, mixed> $invoice
     */
    function sub_invoice_build_pdf_html(array $invoice): string
    {
        $plan = SubscriptionPlan::get($invoice['license_type'] ?? 'annual');
        $issued = !empty($invoice['issued_at'])
            ? date('d/m/Y H:i', strtotime($invoice['issued_at']))
            : date('d/m/Y H:i');
        $amount = saas_format_amount((int) ($invoice['amount_xof'] ?? 0));
        $sellerName = htmlspecialchars((string) ($invoice['seller_name'] ?? platform_name()), ENT_QUOTES, 'UTF-8');
        $sellerCompany = htmlspecialchars((string) ($invoice['seller_company'] ?? platform_company()), ENT_QUOTES, 'UTF-8');
        $platform = htmlspecialchars(platform_name(), ENT_QUOTES, 'UTF-8');
        $vendorUrl = htmlspecialchars(platform_vendor_url(), ENT_QUOTES, 'UTF-8');
        $invoiceNo = htmlspecialchars((string) ($invoice['invoice_number'] ?? ''), ENT_QUOTES, 'UTF-8');
        $buyerCompany = htmlspecialchars((string) ($invoice['buyer_company'] ?? ''), ENT_QUOTES, 'UTF-8');
        $buyerEmail = htmlspecialchars((string) ($invoice['buyer_email'] ?? ''), ENT_QUOTES, 'UTF-8');
        $buyerPhone = !empty($invoice['buyer_phone'])
            ? htmlspecialchars((string) $invoice['buyer_phone'], ENT_QUOTES, 'UTF-8')
            : '';
        $refCommand = htmlspecialchars((string) ($invoice['ref_command'] ?? ''), ENT_QUOTES, 'UTF-8');
        $orderType = htmlspecialchars(sub_invoice_order_type_label($invoice['order_type'] ?? 'new'), ENT_QUOTES, 'UTF-8');
        $planName = htmlspecialchars($plan['name'], ENT_QUOTES, 'UTF-8');
        $payment = htmlspecialchars((string) ($invoice['payment_method'] ?? 'mobile_money'), ENT_QUOTES, 'UTF-8');
        $lineDesc = htmlspecialchars((string) ($invoice['line_description'] ?? ''), ENT_QUOTES, 'UTF-8');
        $amountEsc = htmlspecialchars($amount, ENT_QUOTES, 'UTF-8');
        $tenantLine = !empty($invoice['tenant_id'])
            ? '<br/><span style="color:#64748b;font-size:8px;">Établissement #' . (int) $invoice['tenant_id'] . '</span>'
            : '';

        $logoFs = sub_invoice_logo_fs_path();
        $logoCell = $logoFs
            ? '<img src="' . htmlspecialchars($logoFs, ENT_QUOTES, 'UTF-8') . '" height="40" />'
            : '';

        $phoneLine = $buyerPhone !== '' ? '<br/>' . $buyerPhone : '';

        return '
<style>
    .si-title { font-size: 15px; color: #1b4f9b; font-weight: bold; margin: 0; }
    .si-muted { color: #64748b; font-size: 8px; }
    .si-label { color: #64748b; font-size: 8px; text-transform: uppercase; }
    .si-badge { background-color: #1b4f9b; color: #ffffff; font-size: 9px; font-weight: bold; padding: 3px 8px; }
    .si-number { font-size: 13px; font-weight: bold; color: #1e293b; }
    .si-box { background-color: #f8fafc; border: 1px solid #e2e8f0; }
    .si-th { background-color: #f1f5f9; color: #475569; font-size: 8px; font-weight: bold; text-transform: uppercase; }
    .si-total { font-size: 11px; font-weight: bold; color: #0d9488; }
    .si-foot { color: #64748b; font-size: 8px; border-top: 1px dashed #cbd5e1; }
</style>
<table cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:12px;border-bottom:2px solid #e2e8f0;">
<tr>
    <td width="58%" valign="top" style="padding-bottom:10px;">
        <table cellpadding="0" cellspacing="0"><tr>
            <td valign="middle" style="padding-right:10px;">' . $logoCell . '</td>
            <td valign="middle">
                <div class="si-title">' . $sellerName . '</div>
                <div class="si-muted">' . $sellerCompany . '</div>
                <div class="si-muted">' . $vendorUrl . '</div>
            </td>
        </tr></table>
    </td>
    <td width="42%" valign="top" align="right" style="padding-bottom:10px;">
        <span class="si-badge">FACTURE</span><br/><br/>
        <span class="si-number">' . $invoiceNo . '</span><br/>
        <span class="si-muted">Émise le ' . htmlspecialchars($issued, ENT_QUOTES, 'UTF-8') . '</span>
    </td>
</tr>
</table>

<table cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:10px;">
<tr>
    <td width="50%" valign="top" class="si-box" style="padding:8px;">
        <div class="si-label">Émetteur</div>
        <strong>' . $sellerCompany . '</strong><br/>' . $platform . '
    </td>
    <td width="2%"></td>
    <td width="48%" valign="top" class="si-box" style="padding:8px;">
        <div class="si-label">Client</div>
        <strong>' . $buyerCompany . '</strong><br/>' . $buyerEmail . $phoneLine . '
    </td>
</tr>
</table>

<table cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:10px;">
<tr>
    <td width="24%" valign="top" class="si-box" style="padding:6px;">
        <div class="si-label">Réf. commande</div><strong style="font-size:8px;">' . $refCommand . '</strong>
    </td>
    <td width="1%"></td>
    <td width="24%" valign="top" class="si-box" style="padding:6px;">
        <div class="si-label">Type</div><strong style="font-size:8px;">' . $orderType . '</strong>
    </td>
    <td width="1%"></td>
    <td width="24%" valign="top" class="si-box" style="padding:6px;">
        <div class="si-label">Licence</div><strong style="font-size:8px;">' . $planName . '</strong>
    </td>
    <td width="1%"></td>
    <td width="24%" valign="top" class="si-box" style="padding:6px;">
        <div class="si-label">Paiement</div><strong style="font-size:8px;">' . $payment . '</strong>
    </td>
</tr>
</table>

<table cellpadding="6" cellspacing="0" width="100%" border="1" bordercolor="#e2e8f0" style="margin-bottom:8px;">
<thead>
<tr>
    <th class="si-th" align="left" width="72%">Description</th>
    <th class="si-th" align="center" width="8%">Qté</th>
    <th class="si-th" align="right" width="20%">Montant</th>
</tr>
</thead>
<tbody>
<tr>
    <td style="font-size:9px;">' . $lineDesc . '</td>
    <td align="center" style="font-size:9px;">1</td>
    <td align="right" style="font-size:9px;">' . $amountEsc . '</td>
</tr>
</tbody>
<tfoot>
<tr>
    <td colspan="2" align="right" style="font-size:10px;"><strong>Total TTC</strong></td>
    <td align="right" class="si-total">' . $amountEsc . '</td>
</tr>
</tfoot>
</table>

<table cellpadding="0" cellspacing="0" width="100%" class="si-foot">
<tr><td style="padding-top:8px;">
    Facture générée automatiquement suite au paiement de l\'abonnement ' . $platform . '.' . $tenantLine . '<br/>
    Propulsé par ' . htmlspecialchars(platform_vendor_name(), ENT_QUOTES, 'UTF-8') . ' — ' . $vendorUrl . '
</td></tr>
</table>';
    }
}

if (!function_exists('sub_invoice_render_html')) {
    /**
     * @param array<string, mixed> $invoice
     */
    function sub_invoice_render_html(array $invoice, bool $forPrint = false): string
    {
        $plan = SubscriptionPlan::get($invoice['license_type'] ?? 'annual');
        $logoUrl = function_exists('platform_logo_url') ? platform_logo_url() : '';
        $vendorUrl = function_exists('platform_vendor_url') ? platform_vendor_url() : '';
        $issued = !empty($invoice['issued_at'])
            ? date('d/m/Y H:i', strtotime($invoice['issued_at']))
            : date('d/m/Y H:i');
        $amount = saas_format_amount((int) ($invoice['amount_xof'] ?? 0));
        $cssUrl = htmlspecialchars(sub_invoice_url('assets/css/subscription-invoice.css'));

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture <?= htmlspecialchars($invoice['invoice_number']) ?></title>
    <link rel="stylesheet" href="<?= $cssUrl ?>">
    <?php if ($forPrint): ?>
    <script>window.addEventListener('load', function () { window.print(); });</script>
    <?php endif; ?>
</head>
<body class="sub-inv-body">
    <?php if (!$forPrint): ?>
    <div class="sub-inv-toolbar no-print">
        <button type="button" onclick="window.print()" class="sub-inv-btn sub-inv-btn--primary">Imprimer</button>
        <a href="<?= htmlspecialchars(sub_invoice_url('admin_platform/facture_abonnement_pdf.php?id=' . (int) $invoice['id'])) ?>" class="sub-inv-btn sub-inv-btn--dark">Télécharger PDF</a>
        <a href="<?= htmlspecialchars(sub_invoice_url('admin_platform/facturation.php')) ?>" class="sub-inv-btn">Retour facturation</a>
    </div>
    <?php endif; ?>

    <article class="sub-inv-doc" id="subInvoiceDoc">
        <header class="sub-inv-head">
            <div class="sub-inv-brand">
                <?php if ($logoUrl !== ''): ?>
                <img src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= htmlspecialchars(platform_name()) ?>" class="sub-inv-logo">
                <?php endif; ?>
                <div>
                    <h1><?= htmlspecialchars($invoice['seller_name'] ?? platform_name()) ?></h1>
                    <?php if (!empty($invoice['seller_company'])): ?>
                    <p class="sub-inv-muted"><?= htmlspecialchars($invoice['seller_company']) ?></p>
                    <?php endif; ?>
                    <?php if ($vendorUrl !== ''): ?>
                    <p class="sub-inv-muted"><a href="<?= htmlspecialchars($vendorUrl) ?>"><?= htmlspecialchars($vendorUrl) ?></a></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="sub-inv-meta">
                <div class="sub-inv-badge">FACTURE</div>
                <div class="sub-inv-number"><?= htmlspecialchars($invoice['invoice_number']) ?></div>
                <div class="sub-inv-muted">Émise le <?= htmlspecialchars($issued) ?></div>
            </div>
        </header>

        <section class="sub-inv-parties">
            <div class="sub-inv-party">
                <h2>Émetteur</h2>
                <p><strong><?= htmlspecialchars($invoice['seller_company'] ?? platform_company()) ?></strong></p>
                <p><?= htmlspecialchars(platform_name()) ?></p>
            </div>
            <div class="sub-inv-party">
                <h2>Client</h2>
                <p><strong><?= htmlspecialchars($invoice['buyer_company']) ?></strong></p>
                <p><?= htmlspecialchars($invoice['buyer_email']) ?></p>
                <?php if (!empty($invoice['buyer_phone'])): ?>
                <p><?= htmlspecialchars($invoice['buyer_phone']) ?></p>
                <?php endif; ?>
            </div>
        </section>

        <section class="sub-inv-details">
            <div class="sub-inv-strip">
                <span>Réf. commande</span><strong><?= htmlspecialchars($invoice['ref_command']) ?></strong>
            </div>
            <div class="sub-inv-strip">
                <span>Type</span><strong><?= htmlspecialchars(sub_invoice_order_type_label($invoice['order_type'] ?? 'new')) ?></strong>
            </div>
            <div class="sub-inv-strip">
                <span>Licence</span><strong><?= htmlspecialchars($plan['name']) ?></strong>
            </div>
            <div class="sub-inv-strip">
                <span>Paiement</span><strong><?= htmlspecialchars($invoice['payment_method'] ?? 'mobile_money') ?></strong>
            </div>
        </section>

        <table class="sub-inv-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="sub-inv-col-qty">Qté</th>
                    <th class="sub-inv-col-amount">Montant</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= htmlspecialchars($invoice['line_description']) ?></td>
                    <td class="sub-inv-col-qty">1</td>
                    <td class="sub-inv-col-amount"><?= htmlspecialchars($amount) ?></td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2"><strong>Total TTC</strong></td>
                    <td class="sub-inv-col-amount sub-inv-total"><?= htmlspecialchars($amount) ?></td>
                </tr>
            </tfoot>
        </table>

        <footer class="sub-inv-foot">
            <p>Facture générée automatiquement suite au paiement de l'abonnement <?= htmlspecialchars(platform_name()) ?>.</p>
            <?php if (!empty($invoice['tenant_id'])): ?>
            <p class="sub-inv-muted">Établissement #<?= (int) $invoice['tenant_id'] ?></p>
            <?php endif; ?>
            <p class="sub-inv-muted"><?= function_exists('platform_powered_by_html') ? platform_powered_by_html('sub-inv-vendor') : '' ?></p>
        </footer>
    </article>
</body>
</html>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('sub_invoice_render_pdf')) {
    /**
     * @param array<string, mixed> $invoice
     */
    function sub_invoice_render_pdf(array $invoice): void
    {
        $tcpdfPath = dirname(__DIR__, 2) . '/vendor/tecnickcom/tcpdf/tcpdf.php';
        if (!is_file($tcpdfPath)) {
            throw new RuntimeException('TCPDF non installé.');
        }
        require_once $tcpdfPath;
        require_once __DIR__ . '/../pdf_branding.php';
        pdf_discard_output_buffers();

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator(platform_name());
        $pdf->SetAuthor(platform_company());
        $pdf->SetTitle('Facture ' . ($invoice['invoice_number'] ?? ''));
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(14, 14, 14);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 9);
        $pdf->writeHTML(sub_invoice_build_pdf_html($invoice), true, false, true, false, '');
        $filename = 'facture_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $invoice['invoice_number'] ?? 'abonnement') . '.pdf';
        pdf_discard_output_buffers();
        $pdf->Output($filename, 'D');
        exit;
    }
}
