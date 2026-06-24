<?php
/**
 * Ticket thermique 80 mm — analyse laboratoire → caisse (ESC/POS + aperçu HTML).
 */

require_once __DIR__ . '/thermal_ticket_render.php';
require_once __DIR__ . '/../models/TarifAnalyseLaboratoire.php';

if (!function_exists('thermal_lab_type_label')) {
    function thermal_lab_type_label(string $code): string
    {
        $map = TarifAnalyseLaboratoire::getTypesMapForTenant();
        $code = TarifAnalyseLaboratoire::normalizeCode($code);
        if (isset($map[$code])) {
            return (string) $map[$code];
        }
        return ucfirst(str_replace('_', ' ', $code));
    }
}

if (!function_exists('thermal_lab_ticket_load_data')) {
    function thermal_lab_ticket_load_data(Analyse $analyseModel, int $analyseId): ?array
    {
        $analyse = $analyseModel->getById($analyseId);
        if (!$analyse) {
            return null;
        }

        $prix = (float) ($analyse['prix_analyse'] ?? 0);

        return [
            'analyse' => $analyse,
            'type_label' => thermal_lab_type_label((string) ($analyse['type_analyse'] ?? '')),
            'total_general' => $prix,
            'system' => function_exists('pdf_tenant_system_params')
                ? pdf_tenant_system_params()
                : SystemParameters::getInstance(),
        ];
    }
}

if (!function_exists('thermal_lab_ticket_build_escpos')) {
    /** @param array<string, mixed> $data */
    function thermal_lab_ticket_build_escpos(array $data): string
    {
        $settings = thermal_printer_settings();
        $width = thermal_printer_line_width((int) $settings['largeur_mm']);
        $printer = new EscPosPrinter($width);
        $a = $data['analyse'];
        $sys = $data['system'];

        $nomClinique = $sys->get('nom_etablissement') ?: 'Clinique';
        $patient = trim(($a['patient_prenom'] ?? '') . ' ' . ($a['patient_nom'] ?? ''));
        $medecin = medecin_profil_format_joined($a);
        $ticketNo = (string) ($a['numero_ticket'] ?? ('#' . ($a['id'] ?? '')));
        $date = !empty($a['date_creation'])
            ? date('d/m/Y H:i', strtotime($a['date_creation']))
            : date('d/m/Y H:i');
        $prixAnalyse = ct_money((float) ($a['prix_analyse'] ?? 0));
        $total = ct_money((float) ($data['total_general'] ?? 0));
        $analyseId = (int) ($a['id'] ?? 0);
        $typeLabel = (string) ($data['type_label'] ?? '');
        $tel = trim((string) $sys->get('telephone', ''));

        $printer->init()->align('center');
        thermal_ticket_append_logo_escpos($printer, $sys, (int) $settings['largeur_mm']);
        $printer->bold(true)->size(2, 2)->text($nomClinique)->normal()->feed(1);

        if ($tel !== '') {
            $printer->align('center')->text('Tel: ' . $tel);
        }

        $printer->feed(1)
            ->align('center')->bold(true)->text('TICKET LABORATOIRE')->normal()
            ->feed(1)
            ->align('center')->bold(true)->size(2, 2)->text($ticketNo)->normal()
            ->feed(1)
            ->separator('=')
            ->align('left')
            ->text('Patient : ' . $patient)
            ->text('Medecin : ' . $medecin)
            ->text('Analyse : ' . $typeLabel)
            ->text('Date : ' . $date);

        if (!empty($a['description'])) {
            $printer->feed(1)->bold(true)->text('Motif :')->normal();
            $printer->textWrapped((string) $a['description']);
        }

        $printer->feed(1)->separator('-')
            ->align('left')->text('Analyse : ' . $prixAnalyse)
            ->feed(1)->separator('-')
            ->align('center')->bold(true)->text('TOTAL A PAYER')->normal()
            ->align('center')->bold(true)->size(2, 2)->text($total)->normal()
            ->feed(1)->separator('-')
            ->align('center')->textWrapped('Presentez ce ticket a la caisse')
            ->align('center')->text('pour effectuer le paiement')
            ->feed(1)
            ->align('center')->text('Analyse #' . $analyseId)
            ->align('center')->text(date('d/m/Y H:i:s'))
            ->feed(1)
            ->align('center')->text($settings['modele'])
            ->cut();

        return $printer->getBuffer();
    }
}

if (!function_exists('thermal_lab_ticket_default_return_url')) {
    /** @param array<string, mixed> $analyse */
    function thermal_lab_ticket_default_return_url(array $analyse): string
    {
        $patientId = (int) ($analyse['patient_id'] ?? 0);
        if ($patientId > 0 && function_exists('app_url')) {
            return app_url('patients/voir.php?id=' . $patientId);
        }
        return function_exists('app_url') ? app_url('patients/index.php') : '/patients/index.php';
    }
}

if (!function_exists('thermal_lab_ticket_render_html')) {
    /** @param array<string, mixed> $data */
    function thermal_lab_ticket_render_html(array $data, bool $autoPrint = false, string $returnUrl = ''): string
    {
        $a = $data['analyse'];
        $sys = $data['system'];
        $nomClinique = htmlspecialchars($sys->get('nom_etablissement') ?: 'Clinique');
        $patient = htmlspecialchars(trim(($a['patient_prenom'] ?? '') . ' ' . ($a['patient_nom'] ?? '')));
        $medecin = htmlspecialchars(medecin_profil_format_joined($a));
        $ticketNo = htmlspecialchars((string) ($a['numero_ticket'] ?? ('#' . ($a['id'] ?? ''))));
        $date = !empty($a['date_creation'])
            ? date('d/m/Y H:i', strtotime($a['date_creation']))
            : date('d/m/Y H:i');
        $prixAnalyse = htmlspecialchars(ct_money((float) ($a['prix_analyse'] ?? 0)));
        $total = htmlspecialchars(ct_money((float) ($data['total_general'] ?? 0)));
        $analyseId = (int) ($a['id'] ?? 0);
        $typeLabel = htmlspecialchars((string) ($data['type_label'] ?? ''));
        $settings = thermal_printer_settings();
        $widthMm = max(58, (int) ($settings['largeur_mm'] ?? 80));
        $tel = trim((string) $sys->get('telephone', ''));
        $adresse = trim((string) $sys->get('adresse', ''));
        $returnUrl = thermal_ticket_sanitize_return_url($returnUrl) ?: thermal_lab_ticket_default_return_url($a);
        $returnUrlEsc = htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8');
        $cssUrl = function_exists('app_url')
            ? htmlspecialchars(app_url('assets/css/thermal-ticket.css'))
            : '../assets/css/thermal-ticket.css';
        $logoUrl = htmlspecialchars(thermal_ticket_logo_url(), ENT_QUOTES, 'UTF-8');
        $logoAlt = htmlspecialchars($sys->get('nom_etablissement') ?: 'Logo', ENT_QUOTES, 'UTF-8');
        $apiUrl = function_exists('app_url')
            ? htmlspecialchars(app_url('patients/api_imprimer_thermique_labo.php'), ENT_QUOTES, 'UTF-8')
            : 'api_imprimer_thermique_labo.php';
        $now = date('d/m/Y H:i:s');
        $wave = str_repeat('-~-', 10) . '-';

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket <?= $ticketNo ?></title>
    <link rel="stylesheet" href="<?= $cssUrl ?>">
    <style>
        :root { --thermal-width-mm: <?= (int) $widthMm ?>mm; }
        /* Bandeau laboratoire : rouge sang au lieu de noir */
        .thermal-title-band--lab {
            background: #c62828;
        }
    </style>
    <?php if ($autoPrint): ?>
    <script>
    window.addEventListener('load', function () {
        if (window.__thermalAutoPrintDone) return;
        window.__thermalAutoPrintDone = true;
        setTimeout(function () { window.print(); }, 400);
    }, { once: true });
    </script>
    <?php endif; ?>
</head>
<body class="thermal-body" data-return-url="<?= $returnUrlEsc ?>">

    <!-- ====== CONTRÔLES (écran seulement) ====== -->
    <div class="thermal-controls-wrapper no-print">
        <div class="thermal-controls">
            <button type="button" onclick="window.print()" class="btn-thermal btn-thermal--primary">
                &#128438; &nbsp;Imprimer (navigateur / USB)
            </button>
            <?php if (thermal_printer_is_configured()): ?>
            <button type="button" id="btnThermalLabSend" class="btn-thermal btn-thermal--dark"
                    data-analyse="<?= $analyseId ?>">
                &#128424; &nbsp;Envoyer sur Xprinter 80mm (réseau)
            </button>
            <?php else: ?>
            <span class="thermal-hint">Configurez l'IP dans Paramètres &rarr; Imprimante thermique</span>
            <?php endif; ?>
            <button type="button" id="btnThermalClose" class="btn-thermal btn-thermal--close">
                &larr; &nbsp;Fermer / Retour
            </button>
        </div>
    </div>

    <!-- ====== TICKET LABORATOIRE 80mm ====== -->
    <div class="thermal-page">
        <div class="thermal-receipt" id="thermalReceipt">

            <!-- En-tête : logo + établissement -->
            <div class="thermal-header">
                <div class="thermal-logo">
                    <img src="<?= $logoUrl ?>" alt="<?= $logoAlt ?>">
                </div>
                <div class="thermal-clinic-name"><?= $nomClinique ?></div>
                <?php if ($adresse !== ''): ?>
                <div class="thermal-clinic-tel"><?= htmlspecialchars($adresse) ?></div>
                <?php endif; ?>
                <?php if ($tel !== ''): ?>
                <div class="thermal-clinic-tel">Tél : <?= htmlspecialchars($tel) ?></div>
                <?php endif; ?>
            </div>

            <!-- Bandeau titre laboratoire (rouge) -->
            <div class="thermal-title-band thermal-title-band--lab">
                &#9679; TICKET LABORATOIRE &#9679;
            </div>

            <!-- Numéro de ticket -->
            <div class="thermal-ticket-no-block">
                <div class="thermal-ticket-no"><?= $ticketNo ?></div>
                <div class="thermal-ticket-date-small"><?= htmlspecialchars($date) ?></div>
            </div>

            <hr class="thermal-sep thermal-sep--solid">

            <!-- Informations patient / médecin / analyse -->
            <div class="thermal-info-block">
                <div class="thermal-line">
                    <span class="thermal-line-label">Patient</span>
                    <span class="thermal-line-value"><?= $patient ?></span>
                </div>
                <div class="thermal-line">
                    <span class="thermal-line-label">Prescripteur</span>
                    <span class="thermal-line-value"><?= $medecin ?></span>
                </div>
                <?php if ($typeLabel !== ''): ?>
                <div class="thermal-line">
                    <span class="thermal-line-label">Type d'analyse</span>
                    <span class="thermal-line-value"><?= $typeLabel ?></span>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($a['description'])): ?>
            <hr class="thermal-sep">
            <div class="thermal-line-label" style="font-size:10px;margin-bottom:2px;">Motif / indication :</div>
            <div class="thermal-line--full"><?= htmlspecialchars((string) $a['description']) ?></div>
            <?php endif; ?>

            <hr class="thermal-sep">

            <!-- Détail prix analyse -->
            <div class="thermal-price-block">
                <div class="thermal-price-row">
                    <span class="thermal-price-row__label">Analyse laboratoire</span>
                    <span class="thermal-price-row__amount"><?= $prixAnalyse ?></span>
                </div>
            </div>

            <hr class="thermal-sep thermal-sep--solid">

            <!-- Total -->
            <div class="thermal-total-block">
                <div class="thermal-total-label">&#9654; TOTAL À PAYER &#9664;</div>
                <div class="thermal-total-amount"><?= $total ?></div>
            </div>

            <hr class="thermal-sep thermal-sep--solid">

            <!-- Message caisse -->
            <div class="thermal-message-block">
                <div class="thermal-message">
                    <strong>&#9733; Présentez ce ticket à la caisse &#9733;</strong>
                    pour effectuer votre paiement.
                </div>
            </div>

            <!-- Séparateur décoratif -->
            <div class="thermal-sep--wave"><?= htmlspecialchars($wave) ?></div>

            <!-- Pied du ticket -->
            <div class="thermal-footer">
                <span>Réf. analyse #<?= $analyseId ?></span>
                <span class="thermal-consult-ref">Émis le <?= htmlspecialchars($now) ?></span>
                <span class="thermal-consult-ref"><?= htmlspecialchars($settings['modele']) ?> · <?= (int) $widthMm ?>mm</span>
            </div>

        </div><!-- .thermal-receipt -->
    </div><!-- .thermal-page -->

    <script>
    /* ── Ajuste @page à la hauteur exacte du reçu avant impression ── */
    (function () {
        var styleTag = null;
        function applyReceiptPageSize() {
            var receipt = document.getElementById('thermalReceipt');
            if (!receipt) return;
            var widthMm = parseInt(
                getComputedStyle(document.documentElement)
                    .getPropertyValue('--thermal-width-mm') || '80', 10
            ) || 80;
            var heightPx = receipt.scrollHeight;
            var heightMm = Math.ceil(heightPx / (96 / 25.4)) + 8;
            if (!styleTag) {
                styleTag = document.createElement('style');
                document.head.appendChild(styleTag);
            }
            styleTag.textContent = '@page { size: ' + widthMm + 'mm ' + heightMm + 'mm; margin: 0; }';
        }
        window.addEventListener('load', applyReceiptPageSize);
        window.addEventListener('beforeprint', applyReceiptPageSize);
        window.addEventListener('afterprint', function () {
            if (styleTag) { styleTag.textContent = ''; }
        });
    }());

    /* ── Bouton Fermer / Retour ── */
    function thermalTicketClose() {
        var returnUrl = document.body.getAttribute('data-return-url') || '';
        if (returnUrl) { window.location.href = returnUrl; return; }
        if (window.opener && !window.opener.closed) { window.close(); return; }
        if (document.referrer) {
            try {
                if (new URL(document.referrer).origin === window.location.origin) {
                    window.location.href = document.referrer;
                    return;
                }
            } catch (e) {}
        }
        if (window.history.length > 1) { window.history.back(); return; }
        window.location.href = <?= json_encode(function_exists('app_url') ? app_url('patients/index.php') : '/patients/index.php', JSON_UNESCAPED_UNICODE) ?>;
    }
    document.getElementById('btnThermalClose')?.addEventListener('click', thermalTicketClose);

    /* ── Bouton Xprinter réseau (labo) ── */
    document.getElementById('btnThermalLabSend')?.addEventListener('click', function () {
        var btn = this;
        var id  = btn.getAttribute('data-analyse');
        btn.disabled    = true;
        btn.textContent = 'Envoi en cours…';
        fetch(<?= json_encode($apiUrl) ?> + '?analyse_id=' + encodeURIComponent(id), {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            alert(d.success ? '✓ Ticket laboratoire envoyé à l\'imprimante Xprinter.' : (d.message || 'Erreur impression'));
            btn.disabled    = false;
            btn.textContent = '⎙ Envoyer sur Xprinter 80mm (réseau)';
        })
        .catch(function () {
            alert('Erreur de communication avec le serveur.');
            btn.disabled    = false;
            btn.textContent = '⎙ Envoyer sur Xprinter 80mm (réseau)';
        });
    });
    </script>
</body>
</html>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('thermal_lab_ticket_print')) {
    /** @param array<string, mixed> $data @return array{ok:bool,message:string} */
    function thermal_lab_ticket_print(array $data): array
    {
        if (!thermal_printer_is_configured()) {
            return ['ok' => false, 'message' => 'Imprimante thermique non configurée (IP réseau requise).'];
        }
        $settings = thermal_printer_settings();
        $payload = thermal_lab_ticket_build_escpos($data);
        $result = EscPosPrinter::sendToNetwork($settings['ip'], $settings['port'], $payload);
        if (!$result['ok']) {
            return ['ok' => false, 'message' => $result['error'] ?? 'Erreur impression'];
        }
        return ['ok' => true, 'message' => 'Ticket imprimé sur ' . $settings['modele']];
    }
}
