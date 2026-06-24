<?php
/**
 * Ticket thermique 80 mm — accueil patient → paiement (ESC/POS + aperçu HTML).
 */

require_once __DIR__ . '/EscPosPrinter.php';
require_once __DIR__ . '/thermal_printer_config.php';
require_once __DIR__ . '/consultation_ticket_render.php';
require_once __DIR__ . '/medecin_profil.php';

if (!function_exists('efficasante_logo_url')) {
    require_once __DIR__ . '/header_logo.php';
}

if (!function_exists('thermal_ticket_logo_url')) {
    function thermal_ticket_logo_url(): string
    {
        return efficasante_logo_url(280, 90);
    }
}

if (!function_exists('thermal_ticket_generic_logo_image')) {
    /** @return resource|\GdImage|false */
    function thermal_ticket_generic_logo_image()
    {
        if (!extension_loaded('gd')) {
            return false;
        }

        $width = 280;
        $height = 72;
        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            return false;
        }

        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);

        $teal = imagecolorallocate($image, 23, 161, 184);
        $tealDark = imagecolorallocate($image, 15, 122, 138);
        $white = imagecolorallocate($image, 255, 255, 255);

        $cx = (int) ($width * 0.22);
        $cy = (int) ($height / 2);
        $r = (int) min($height * 0.38, 28);
        imagefilledellipse($image, $cx, $cy, $r * 2, $r * 2, $teal);
        imageellipse($image, $cx, $cy, $r * 2, $r * 2, $tealDark);
        imagefilledrectangle($image, $cx - (int) ($r * 0.35), $cy - (int) ($r * 0.55), $cx + (int) ($r * 0.35), $cy + (int) ($r * 0.55), $white);
        imagefilledrectangle($image, $cx - (int) ($r * 0.55), $cy - (int) ($r * 0.18), $cx + (int) ($r * 0.55), $cy + (int) ($r * 0.18), $white);

        return $image;
    }
}

if (!function_exists('thermal_ticket_append_logo_escpos')) {
    /**
     * @param SystemParameters $sys
     */
    function thermal_ticket_append_logo_escpos(EscPosPrinter $printer, $sys, int $widthMm): void
    {
        $maxPx = $widthMm >= 80 ? 384 : 256;
        $logoPath = $sys->getLogoPath();
        if ($logoPath && is_readable($logoPath)) {
            $printer->image($logoPath, $maxPx);
            return;
        }

        $generic = thermal_ticket_generic_logo_image();
        if ($generic !== false) {
            $printer->imageResource($generic, $maxPx);
        }
    }
}

if (!function_exists('thermal_ticket_load_data')) {
    function thermal_ticket_load_data(Consultation $consultationModel, int $consultationId): ?array
    {
        require_once __DIR__ . '/../models/SejourHospitalisation.php';

        $consultation = $consultationModel->getByIdForPatientModule($consultationId);
        if (!$consultation) {
            return null;
        }

        $soins = $consultationModel->getConsultationSoins($consultationId);
        $sejours = (new SejourHospitalisation())->getByConsultation($consultationId);

        $totalSoins = 0.0;
        foreach ($soins as $soin) {
            $totalSoins += (float) ($soin['prix_total'] ?? 0);
        }
        $totalHospitalisation = 0.0;
        foreach ($sejours as $sejour) {
            $totalHospitalisation += (float) ($sejour['prix_total'] ?? 0);
        }
        $prixConsultation = (float) ($consultation['prix_consultation'] ?? 0);

        return [
            'consultation' => $consultation,
            'soins' => $soins,
            'sejours' => $sejours,
            'total_soins' => $totalSoins,
            'total_hospitalisation' => $totalHospitalisation,
            'total_general' => $prixConsultation + $totalSoins + $totalHospitalisation,
            'system' => function_exists('pdf_tenant_system_params')
                ? pdf_tenant_system_params()
                : SystemParameters::getInstance(),
        ];
    }
}

if (!function_exists('thermal_ticket_build_escpos')) {
    /**
     * @param array<string, mixed> $data from consultation_ticket_load_data
     */
    function thermal_ticket_build_escpos(array $data): string
    {
        $settings = thermal_printer_settings();
        $width = thermal_printer_line_width((int) $settings['largeur_mm']);
        $printer = new EscPosPrinter($width);
        $c = $data['consultation'];
        $sys = $data['system'];

        $nomClinique = $sys->get('nom_etablissement') ?: 'Clinique';
        $patient = trim(($c['patient_prenom'] ?? '') . ' ' . ($c['patient_nom'] ?? ''));
        $medecin = medecin_profil_format_joined($c);
        $medecinLabel = medecin_profil_attribution_label_from_row($c);
        $ticketNo = (string) ($c['numero_ticket'] ?? ('#' . ($c['id'] ?? '')));
        $date = !empty($c['date_consultation'])
            ? date('d/m/Y H:i', strtotime($c['date_consultation']))
            : date('d/m/Y H:i');
        $prixConsultation = ct_money((float) ($c['prix_consultation'] ?? 0));
        $totalSoins = ct_money((float) ($data['total_soins'] ?? 0));
        $totalHospitalisation = ct_money((float) ($data['total_hospitalisation'] ?? 0));
        $total = ct_money((float) ($data['total_general'] ?? 0));
        $consultId = (int) ($c['id'] ?? 0);
        $tel = trim((string) $sys->get('telephone', ''));

        $printer->init()->align('center');
        thermal_ticket_append_logo_escpos($printer, $sys, (int) $settings['largeur_mm']);
        $printer->textLarge($nomClinique, true)->feed(1);

        if ($tel !== '') {
            $printer->align('center')->text('Tél : ' . $tel);
        }

        $printer->feed(1)
            ->align('center')->bold(true)->text('TICKET CONSULTATION')->normal()
            ->feed(1)
            ->textLarge($ticketNo, true)
            ->feed(1)
            ->separator('=')
            ->align('left')
            ->text('Patient : ' . $patient)
            ->text($medecinLabel . ' : ' . $medecin);

        if (!empty($c['medecin_specialite'])) {
            $printer->text('Spécialité : ' . $c['medecin_specialite']);
        }

        $printer->text('Date : ' . $date);

        if (!empty($c['symptomes'])) {
            $printer->feed(1)->bold(true)->text('Motif :')->normal();
            $printer->textWrapped((string) $c['symptomes']);
        }

        $printer->feed(1)->separator('-')
            ->align('left')->text('Consultation : ' . $prixConsultation);

        if ((float) ($data['total_soins'] ?? 0) > 0) {
            $printer->text('Soins : ' . $totalSoins);
        }
        if ((float) ($data['total_hospitalisation'] ?? 0) > 0) {
            $printer->text('Hospitalisation : ' . $totalHospitalisation);
        }

        $printer->feed(1)->separator('-')
            ->align('center')->bold(true)->text('TOTAL A PAYER')->normal()
            ->textLarge($total, true)
            ->feed(1)->separator('-')
            ->align('center')->textWrapped('Présentez ce ticket à la caisse')
            ->align('center')->text('pour effectuer le paiement')
            ->feed(1)
            ->align('center')->text('Consultation #' . $consultId)
            ->align('center')->text(date('d/m/Y H:i:s'))
            ->feed(1)
            ->align('center')->text($settings['modele'])
            ->cut();

        return $printer->getBuffer();
    }
}

if (!function_exists('thermal_ticket_sanitize_return_url')) {
    function thermal_ticket_sanitize_return_url(?string $return): string
    {
        $return = trim((string) $return);
        if ($return === '') {
            return '';
        }

        if (preg_match('#\.\.#', $return)) {
            return '';
        }

        if (preg_match('#^https?://#i', $return)) {
            $site = defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : '';
            if ($site === '' || strpos($return, $site) !== 0) {
                return '';
            }
            return $return;
        }

        if ($return[0] !== '/') {
            return '';
        }

        return $return;
    }
}

if (!function_exists('thermal_ticket_default_return_url')) {
    /**
     * @param array<string, mixed> $consultation
     */
    function thermal_ticket_default_return_url(array $consultation): string
    {
        $patientId = (int) ($consultation['patient_id'] ?? 0);
        if ($patientId > 0 && function_exists('app_url')) {
            return app_url('patients/voir.php?id=' . $patientId);
        }
        return function_exists('app_url') ? app_url('patients/index.php') : '/patients/index.php';
    }
}

if (!function_exists('thermal_ticket_render_html')) {
    /**
     * @param array<string, mixed> $data
     */
    function thermal_ticket_render_html(array $data, bool $autoPrint = false, string $returnUrl = ''): string
    {
        $c = $data['consultation'];
        $sys = $data['system'];
        $nomClinique = htmlspecialchars($sys->get('nom_etablissement') ?: 'Clinique');
        $patient = htmlspecialchars(trim(($c['patient_prenom'] ?? '') . ' ' . ($c['patient_nom'] ?? '')));
        $medecin = htmlspecialchars(medecin_profil_format_joined($c));
        $medecinLabel = htmlspecialchars(medecin_profil_attribution_label_from_row($c));
        $ticketNo = htmlspecialchars((string) ($c['numero_ticket'] ?? ('#' . ($c['id'] ?? ''))));
        $date = !empty($c['date_consultation'])
            ? date('d/m/Y H:i', strtotime($c['date_consultation']))
            : date('d/m/Y H:i');
        $prixConsultation = htmlspecialchars(ct_money((float) ($c['prix_consultation'] ?? 0)));
        $totalSoins = htmlspecialchars(ct_money((float) ($data['total_soins'] ?? 0)));
        $totalHospitalisation = htmlspecialchars(ct_money((float) ($data['total_hospitalisation'] ?? 0)));
        $total = htmlspecialchars(ct_money((float) ($data['total_general'] ?? 0)));
        $consultId = (int) ($c['id'] ?? 0);
        $settings = thermal_printer_settings();
        $widthMm = max(58, (int) ($settings['largeur_mm'] ?? 80));
        $tel = trim((string) $sys->get('telephone', ''));
        $adresse = trim((string) $sys->get('adresse', ''));
        $specialite = trim((string) ($c['medecin_specialite'] ?? ''));
        $typeConsult = trim((string) ($c['type_consultation'] ?? ''));
        $typeConsultLabel = $typeConsult ? ucfirst(str_replace('_', ' ', $typeConsult)) : '';
        $returnUrl = thermal_ticket_sanitize_return_url($returnUrl) ?: thermal_ticket_default_return_url($c);
        $returnUrlEsc = htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8');
        $cssUrl = function_exists('app_url')
            ? htmlspecialchars(app_url('assets/css/thermal-ticket.css'))
            : '../assets/css/thermal-ticket.css';
        $logoUrl = htmlspecialchars(thermal_ticket_logo_url(), ENT_QUOTES, 'UTF-8');
        $logoAlt = htmlspecialchars($sys->get('nom_etablissement') ?: 'Logo', ENT_QUOTES, 'UTF-8');
        $now = date('d/m/Y H:i:s');

        // Séparateur wave ASCII (répété pour remplir 80mm ~ 32 caractères utiles)
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
            <button type="button" id="btnThermalSend" class="btn-thermal btn-thermal--dark"
                    data-consultation="<?= $consultId ?>">
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

    <!-- ====== TICKET 80mm ====== -->
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

            <!-- Bandeau titre -->
            <div class="thermal-title-band">&#9679; TICKET DE CONSULTATION &#9679;</div>

            <!-- Numéro de ticket -->
            <div class="thermal-ticket-no-block">
                <div class="thermal-ticket-no"><?= $ticketNo ?></div>
                <div class="thermal-ticket-date-small"><?= htmlspecialchars($date) ?></div>
            </div>

            <hr class="thermal-sep thermal-sep--solid">

            <!-- Informations patient / médecin -->
            <div class="thermal-info-block">
                <div class="thermal-line">
                    <span class="thermal-line-label">Patient</span>
                    <span class="thermal-line-value"><?= $patient ?></span>
                </div>
                <div class="thermal-line">
                    <span class="thermal-line-label"><?= $medecinLabel ?></span>
                    <span class="thermal-line-value"><?= $medecin ?></span>
                </div>
                <?php if ($specialite !== ''): ?>
                <div class="thermal-line">
                    <span class="thermal-line-label">Spécialité</span>
                    <span class="thermal-line-value"><?= htmlspecialchars($specialite) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($typeConsultLabel !== ''): ?>
                <div class="thermal-line">
                    <span class="thermal-line-label">Type</span>
                    <span class="thermal-line-value"><?= htmlspecialchars($typeConsultLabel) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($c['symptomes'])): ?>
            <hr class="thermal-sep">
            <div class="thermal-line-label" style="font-size:10px;margin-bottom:2px;">Motif :</div>
            <div class="thermal-line--full"><?= htmlspecialchars((string) $c['symptomes']) ?></div>
            <?php endif; ?>

            <hr class="thermal-sep">

            <!-- Détail des prix -->
            <div class="thermal-price-block">
                <div class="thermal-price-row">
                    <span class="thermal-price-row__label">Consultation :</span>
                    <span class="thermal-price-row__amount"><?= $prixConsultation ?></span>
                </div>
                <?php if ((float) ($data['total_soins'] ?? 0) > 0): ?>
                <div class="thermal-price-row">
                    <span class="thermal-price-row__label">Soins</span>
                    <span class="thermal-price-row__amount"><?= $totalSoins ?></span>
                </div>
                <?php endif; ?>
                <?php if ((float) ($data['total_hospitalisation'] ?? 0) > 0): ?>
                <div class="thermal-price-row">
                    <span class="thermal-price-row__label">Hospitalisation</span>
                    <span class="thermal-price-row__amount"><?= $totalHospitalisation ?></span>
                </div>
                <?php endif; ?>
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
                <span>Réf. consultation #<?= $consultId ?></span>
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
            var heightPx  = receipt.scrollHeight;
            var heightMm  = Math.ceil(heightPx / (96 / 25.4)) + 8; // +8mm marge bas
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

    /* ── Bouton Xprinter réseau ── */
    document.getElementById('btnThermalSend')?.addEventListener('click', function () {
        var btn = this;
        var id  = btn.getAttribute('data-consultation');
        btn.disabled    = true;
        btn.textContent = 'Envoi en cours…';
        fetch('api_imprimer_thermique.php?consultation_id=' + encodeURIComponent(id), {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            alert(d.success ? '✓ Ticket envoyé à l\'imprimante Xprinter.' : (d.message || 'Erreur impression'));
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

if (!function_exists('thermal_ticket_print')) {
    /**
     * @param array<string, mixed> $data
     * @return array{ok:bool,message:string}
     */
    function thermal_ticket_print(array $data): array
    {
        if (!thermal_printer_is_configured()) {
            return ['ok' => false, 'message' => 'Imprimante thermique non configurée (IP réseau requise).'];
        }
        $settings = thermal_printer_settings();
        $payload = thermal_ticket_build_escpos($data);
        $result = EscPosPrinter::sendToNetwork($settings['ip'], $settings['port'], $payload);
        if (!$result['ok']) {
            return ['ok' => false, 'message' => $result['error'] ?? 'Erreur impression'];
        }
        return ['ok' => true, 'message' => 'Ticket imprimé sur ' . $settings['modele']];
    }
}
