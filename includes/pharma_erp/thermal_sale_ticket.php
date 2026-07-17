<?php
/**
 * Ticket thermique 80 mm — vente PharmaPro POS.
 */

if (!function_exists('pharma_erp_render_sale_ticket_html')) {
    function pharma_erp_render_sale_ticket_html(array $sale, array $pharmacy = []): string
    {
        $lines = $sale['lines'] ?? [];
        $payments = $sale['payments'] ?? [];
        $pharmacyName = htmlspecialchars($pharmacy['name'] ?? 'PharmaPro');
        $saleNumber = htmlspecialchars($sale['sale_number'] ?? '');
        $date = !empty($sale['completed_at']) ? date('d/m/Y H:i', strtotime($sale['completed_at'])) : date('d/m/Y H:i');

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ticket <?= $saleNumber ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(app_url('assets/css/thermal-ticket.css')) ?>">
    <style>
        body.thermal-body { background: #fff; }
        .thermal-ticket { max-width: 72mm; margin: 0 auto; font-family: 'Courier New', monospace; font-size: 12px; }
        .thermal-ticket h1 { font-size: 14px; text-align: center; margin: 0 0 4px; }
        .thermal-ticket .meta { text-align: center; margin-bottom: 8px; color: #555; }
        .thermal-ticket table { width: 100%; border-collapse: collapse; }
        .thermal-ticket td { padding: 2px 0; vertical-align: top; }
        .thermal-ticket .sep { border-top: 1px dashed #000; margin: 6px 0; }
        .thermal-ticket .total { font-weight: bold; font-size: 13px; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body class="thermal-body">
<div class="thermal-ticket">
    <h1><?= $pharmacyName ?></h1>
    <div class="meta">PharmaPro ERP · Ticket de caisse</div>
    <div class="meta"><?= $saleNumber ?> · <?= $date ?></div>
    <?php if (!empty($sale['customer_name'])): ?>
    <div class="meta">Client : <?= htmlspecialchars($sale['customer_name']) ?></div>
    <?php endif; ?>
    <div class="sep"></div>
    <table>
        <?php foreach ($lines as $line): ?>
        <tr>
            <td><?= htmlspecialchars($line['product_name']) ?><br>
                <small><?= (int) $line['quantity'] ?> × <?= number_format((float) $line['unit_price'], 0, ',', ' ') ?></small>
            </td>
            <td style="text-align:right; white-space:nowrap;"><?= number_format((float) $line['line_total'], 0, ',', ' ') ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <div class="sep"></div>
    <table>
        <tr><td>Sous-total HT</td><td style="text-align:right;"><?= number_format((float) ($sale['subtotal_ht'] ?? 0), 0, ',', ' ') ?></td></tr>
        <?php if ((float) ($sale['discount_amount'] ?? 0) > 0): ?>
        <tr><td>Remise</td><td style="text-align:right;">-<?= number_format((float) $sale['discount_amount'], 0, ',', ' ') ?></td></tr>
        <?php endif; ?>
        <tr><td>TVA</td><td style="text-align:right;"><?= number_format((float) ($sale['vat_amount'] ?? 0), 0, ',', ' ') ?></td></tr>
        <tr class="total"><td>TOTAL TTC</td><td style="text-align:right;"><?= number_format((float) ($sale['total_ttc'] ?? 0), 0, ',', ' ') ?> F</td></tr>
        <tr><td>Reçu</td><td style="text-align:right;"><?= number_format((float) ($sale['amount_paid'] ?? 0), 0, ',', ' ') ?></td></tr>
        <?php if ((float) ($sale['change_amount'] ?? 0) > 0): ?>
        <tr><td>Monnaie</td><td style="text-align:right;"><?= number_format((float) $sale['change_amount'], 0, ',', ' ') ?></td></tr>
        <?php endif; ?>
    </table>
    <?php if (!empty($payments[0]['payment_method'])): ?>
    <div class="meta mt-2">Paiement : <?= htmlspecialchars($payments[0]['payment_method']) ?></div>
    <?php endif; ?>
    <div class="sep"></div>
    <div class="meta">Merci de votre confiance</div>
</div>
<p class="no-print text-center mt-3">
    <button type="button" onclick="window.print()" class="btn btn-dark btn-sm">Imprimer</button>
</p>
<script>window.onload = function () { setTimeout(function () { window.print(); }, 300); };</script>
</body>
</html>
        <?php
        return (string) ob_get_clean();
    }
}
