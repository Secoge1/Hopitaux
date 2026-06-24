<?php
/**
 * Impression A4 compacte — bilan comptable et compte de résultat.
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/module_guard.php';
module_require_roles('finances');

require_once __DIR__ . '/../models/Finances.php';
require_once __DIR__ . '/../includes/pdf_branding.php';

$date_debut = isset($_GET['date_debut']) ? trim($_GET['date_debut']) : date('Y-01-01');
$date_fin = isset($_GET['date_fin']) ? trim($_GET['date_fin']) : date('Y-m-d');

if ($date_debut === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_debut)) {
    $date_debut = date('Y-01-01');
}
if ($date_fin === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_fin)) {
    $date_fin = date('Y-m-d');
}
if (strtotime($date_debut) > strtotime($date_fin)) {
    [$date_debut, $date_fin] = [$date_fin, $date_debut];
}

$autoPrint = isset($_GET['print']) && $_GET['print'] === '1';

try {
    $financesModel = new Finances();
    $bilan = $financesModel->getBilan($date_debut, $date_fin);
    $comptesActifs = $financesModel->getComptes(1, 200, '', 'actif', 'actif');
    $comptesPassifs = $financesModel->getComptes(1, 200, '', 'passif', 'actif');
} catch (Exception $e) {
    die('Erreur : ' . htmlspecialchars($e->getMessage()));
}

$fmtFcfa = static function ($amount): string {
    return number_format((float) $amount, 0, ',', ' ');
};

$filterNonZero = static function (array $comptes): array {
    return array_values(array_filter($comptes, static function (array $c): bool {
        return abs((float) ($c['solde_actuel'] ?? 0)) >= 0.01;
    }));
};

$comptesActifs = $filterNonZero($comptesActifs);
$comptesPassifs = $filterNonZero($comptesPassifs);

$ecartBilan = (float) $bilan['actifs'] - (float) $bilan['passifs'];
$bilanEquilibre = abs($ecartBilan) < 0.01;

$systemParams = pdf_tenant_system_params();
$nomEtablissement = $systemParams->get('nom_etablissement') ?: 'Établissement de Santé';
$logoHTML = $systemParams->getPdfLogoBlockHtml(['max_height' => 48, 'max_width' => 160]);

$periodeLabel = date('d/m/Y', strtotime($date_debut)) . ' — ' . date('d/m/Y', strtotime($date_fin));
$genereLe = date('d/m/Y à H:i');

$renderCompteRows = static function (array $comptes) use ($fmtFcfa): string {
    if ($comptes === []) {
        return '<tr><td colspan="3" class="fin-pr-empty">Aucun solde significatif</td></tr>';
    }
    $html = '';
    foreach ($comptes as $compte) {
        $num = htmlspecialchars((string) ($compte['numero_compte'] ?? ''), ENT_QUOTES, 'UTF-8');
        $lib = htmlspecialchars((string) ($compte['libelle'] ?? $compte['nom_compte'] ?? ''), ENT_QUOTES, 'UTF-8');
        $solde = $fmtFcfa($compte['solde_actuel'] ?? 0);
        $html .= '<tr><td class="fin-pr-num">' . $num . '</td><td>' . $lib . '</td><td class="fin-pr-amt">' . $solde . '</td></tr>';
    }
    return $html;
};

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bilan comptable — <?= htmlspecialchars($nomEtablissement) ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            line-height: 1.35;
            color: #1e293b;
            background: #eef2f7;
        }
        .fin-pr-controls {
            position: fixed;
            top: 12px;
            right: 12px;
            z-index: 100;
            display: flex;
            gap: 8px;
            background: #fff;
            padding: 8px 10px;
            border-radius: 8px;
            box-shadow: 0 4px 16px rgba(0,0,0,.12);
        }
        .fin-pr-controls button {
            border: none;
            border-radius: 6px;
            padding: 7px 12px;
            font-size: 13px;
            cursor: pointer;
            color: #fff;
            background: #1b4f9b;
        }
        .fin-pr-controls button.fin-pr-close { background: #64748b; }
        .fin-pr-sheet {
            max-width: 210mm;
            margin: 16px auto;
            background: #fff;
            padding: 10mm 12mm;
            box-shadow: 0 2px 12px rgba(0,0,0,.08);
        }
        .fin-pr-head {
            text-align: center;
            border-bottom: 2px solid #1b4f9b;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        .fin-pr-head h1 {
            margin: 6px 0 2px;
            font-size: 16px;
            color: #1b4f9b;
            letter-spacing: 0.04em;
        }
        .fin-pr-head .fin-pr-sub { color: #64748b; font-size: 11px; }
        .fin-pr-meta {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 10px;
            font-size: 9px;
            color: #475569;
        }
        .fin-pr-summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .fin-pr-summary th,
        .fin-pr-summary td {
            border: 1px solid #cbd5e1;
            padding: 5px 8px;
            text-align: center;
        }
        .fin-pr-summary th {
            background: #f1f5f9;
            font-size: 8px;
            text-transform: uppercase;
            color: #64748b;
        }
        .fin-pr-summary td { font-weight: 700; font-size: 11px; }
        .fin-pr-summary .ok { color: #0d9488; }
        .fin-pr-summary .warn { color: #b45309; }
        .fin-pr-cols {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 10px;
        }
        .fin-pr-cols > tbody > tr > td {
            width: 50%;
            vertical-align: top;
            padding: 0 6px 0 0;
        }
        .fin-pr-cols > tbody > tr > td + td { padding: 0 0 0 6px; }
        .fin-pr-block-title {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            color: #fff;
            padding: 4px 8px;
            margin-bottom: 0;
        }
        .fin-pr-block-title--actif { background: #0d9488; }
        .fin-pr-block-title--passif { background: #d97706; }
        .fin-pr-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .fin-pr-table th,
        .fin-pr-table td {
            border: 1px solid #e2e8f0;
            padding: 3px 5px;
            font-size: 8.5px;
        }
        .fin-pr-table th {
            background: #f8fafc;
            font-size: 7.5px;
            text-transform: uppercase;
            color: #64748b;
        }
        .fin-pr-num { white-space: nowrap; width: 52px; font-weight: 600; }
        .fin-pr-amt { text-align: right; white-space: nowrap; font-weight: 600; width: 72px; }
        .fin-pr-total td { font-weight: 700; background: #f8fafc; }
        .fin-pr-empty { color: #94a3b8; font-style: italic; text-align: center; }
        .fin-pr-result {
            border: 1px solid #6366f1;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 8px;
        }
        .fin-pr-result-head {
            background: #6366f1;
            color: #fff;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 4px 8px;
        }
        .fin-pr-result-grid {
            width: 100%;
            border-collapse: collapse;
        }
        .fin-pr-result-grid td {
            padding: 6px 8px;
            border-right: 1px solid #e2e8f0;
            text-align: center;
            width: 33.33%;
        }
        .fin-pr-result-grid td:last-child { border-right: none; }
        .fin-pr-result-grid .lbl {
            display: block;
            font-size: 7.5px;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .fin-pr-result-grid .val { font-size: 11px; font-weight: 700; }
        .fin-pr-result-grid .val.pos { color: #0d9488; }
        .fin-pr-result-grid .val.neg { color: #dc2626; }
        .fin-pr-foot {
            border-top: 1px dashed #cbd5e1;
            padding-top: 6px;
            font-size: 7.5px;
            color: #94a3b8;
            text-align: center;
        }
        @media print {
            @page { size: A4 portrait; margin: 8mm; }
            body { background: #fff; }
            .fin-pr-controls { display: none !important; }
            .fin-pr-sheet {
                max-width: none;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
            .fin-pr-cols, .fin-pr-table, .fin-pr-result { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="fin-pr-controls">
        <button type="button" onclick="window.print()"><i class="fas fa-print"></i> Imprimer</button>
        <button type="button" class="fin-pr-close" onclick="window.close()"><i class="fas fa-times"></i> Fermer</button>
    </div>

    <div class="fin-pr-sheet">
        <header class="fin-pr-head">
            <?= $logoHTML ?>
            <h1>BILAN COMPTABLE</h1>
            <div class="fin-pr-sub"><?= htmlspecialchars($nomEtablissement) ?></div>
        </header>

        <div class="fin-pr-meta">
            <span><strong>Période résultat :</strong> <?= htmlspecialchars($periodeLabel) ?></span>
            <span><strong>Édité le :</strong> <?= htmlspecialchars($genereLe) ?></span>
        </div>

        <table class="fin-pr-summary">
            <thead>
                <tr>
                    <th>Total actif</th>
                    <th>Total passif</th>
                    <th>Équilibre</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= $fmtFcfa($bilan['actifs']) ?> FCFA</td>
                    <td><?= $fmtFcfa($bilan['passifs']) ?> FCFA</td>
                    <td class="<?= $bilanEquilibre ? 'ok' : 'warn' ?>">
                        <?php if ($bilanEquilibre): ?>
                        Équilibré
                        <?php else: ?>
                        Écart <?= $fmtFcfa(abs($ecartBilan)) ?> FCFA
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <table class="fin-pr-cols">
            <tr>
                <td>
                    <div class="fin-pr-block-title fin-pr-block-title--actif">Actif</div>
                    <table class="fin-pr-table">
                        <thead>
                            <tr><th>N°</th><th>Libellé</th><th>Solde</th></tr>
                        </thead>
                        <tbody>
                            <?= $renderCompteRows($comptesActifs) ?>
                            <tr class="fin-pr-total">
                                <td colspan="2" align="right">Total actif</td>
                                <td class="fin-pr-amt"><?= $fmtFcfa($bilan['actifs']) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </td>
                <td>
                    <div class="fin-pr-block-title fin-pr-block-title--passif">Passif</div>
                    <table class="fin-pr-table">
                        <thead>
                            <tr><th>N°</th><th>Libellé</th><th>Solde</th></tr>
                        </thead>
                        <tbody>
                            <?= $renderCompteRows($comptesPassifs) ?>
                            <tr class="fin-pr-total">
                                <td colspan="2" align="right">Total passif</td>
                                <td class="fin-pr-amt"><?= $fmtFcfa($bilan['passifs']) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>

        <div class="fin-pr-result">
            <div class="fin-pr-result-head">Compte de résultat (période)</div>
            <table class="fin-pr-result-grid">
                <tr>
                    <td>
                        <span class="lbl">Produits</span>
                        <span class="val"><?= $fmtFcfa($bilan['produits']) ?> FCFA</span>
                    </td>
                    <td>
                        <span class="lbl">Charges</span>
                        <span class="val"><?= $fmtFcfa($bilan['charges']) ?> FCFA</span>
                    </td>
                    <td>
                        <span class="lbl">Résultat net</span>
                        <span class="val <?= ($bilan['resultat'] >= 0) ? 'pos' : 'neg' ?>">
                            <?= $fmtFcfa($bilan['resultat']) ?> FCFA
                        </span>
                    </td>
                </tr>
            </table>
        </div>

        <footer class="fin-pr-foot">
            Document généré par le module Finances — <?= htmlspecialchars($nomEtablissement) ?> — <?= htmlspecialchars($genereLe) ?>
        </footer>
    </div>

    <?php if ($autoPrint): ?>
    <script>window.addEventListener('load', function () { window.print(); });</script>
    <?php endif; ?>
</body>
</html>
