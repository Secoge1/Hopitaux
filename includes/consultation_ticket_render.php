<?php
/**
 * Rendu ticket de consultation — A4 compact (écran + impression + BDD).
 */

require_once __DIR__ . '/../config/CurrencyConfig.php';
require_once __DIR__ . '/../config/SystemParameters.php';
require_once __DIR__ . '/pdf_branding.php';

if (!function_exists('consultation_ticket_load_data')) {
    /**
     * @return array<string, mixed>|null
     */
    function consultation_ticket_load_data(Consultation $consultationModel, int $consultationId): ?array
    {
        require_once __DIR__ . '/../models/SejourHospitalisation.php';

        $consultation = $consultationModel->getById($consultationId);
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
        $totalGeneral = $prixConsultation + $totalSoins + $totalHospitalisation;

        return [
            'consultation' => $consultation,
            'soins' => $soins,
            'sejours' => $sejours,
            'total_soins' => $totalSoins,
            'total_hospitalisation' => $totalHospitalisation,
            'total_general' => $totalGeneral,
            'system' => function_exists('pdf_tenant_system_params')
                ? pdf_tenant_system_params()
                : SystemParameters::getInstance(),
        ];
    }
}

if (!function_exists('ct_money')) {
    function ct_money(float $amount): string
    {
        return function_exists('formatFCFA') ? formatFCFA($amount) : number_format($amount, 0, ',', ' ') . ' FCFA';
    }
}

if (!function_exists('ct_kv')) {
    function ct_kv(string $label, string $value, bool $strong = false): string
    {
        $content = $strong ? '<strong>' . $value . '</strong>' : $value;
        return '<div class="ct-dl-item"><dt>' . htmlspecialchars($label) . '</dt><dd>' . $content . '</dd></div>';
    }
}

if (!function_exists('ct_strip')) {
    function ct_strip(string $label, string $value): string
    {
        return '<div class="ct-doc-strip-item"><span class="ct-doc-strip-label">' . htmlspecialchars($label) . '</span><span class="ct-doc-strip-value">' . $value . '</span></div>';
    }
}

if (!function_exists('consultation_ticket_render_doc')) {
    /** @param array<string, mixed> $data */
    function consultation_ticket_render_doc(array $data): string
    {
        $c = $data['consultation'];
        $sys = $data['system'];
        $soins = $data['soins'];
        $sejours = $data['sejours'];

        $nomClinique = $sys->get('nom_etablissement') ?: 'Clinique et Hôpital';
        $contact = array_filter([
            $sys->get('adresse') ?: '',
            $sys->get('ville') ?: '',
            $sys->get('telephone') ? 'Tél. ' . $sys->get('telephone') : '',
        ]);

        $patient = htmlspecialchars(trim(($c['patient_prenom'] ?? '') . ' ' . ($c['patient_nom'] ?? '')));
        $medecin = htmlspecialchars(medecin_profil_format_joined($c));
        $medecinLabel = htmlspecialchars(medecin_profil_attribution_label_from_row($c));
        $typeConsult = ucfirst(str_replace('_', ' ', (string) ($c['type_consultation'] ?? '')));
        $statut = ucfirst(str_replace('_', ' ', (string) ($c['statut'] ?? '')));

        ob_start();
        ?>
<div class="ct-doc">
    <header class="ct-doc-head">
        <div class="ct-doc-brand">
            <?= $sys->getPdfLogoBlockHtml(['align' => 'left', 'max_height' => 48, 'max_width' => 130, 'margin_bottom' => '6px']) ?>
            <h1><?= htmlspecialchars($nomClinique) ?></h1>
            <?php if ($contact): ?>
            <p><?= htmlspecialchars(implode(' · ', $contact)) ?></p>
            <?php endif; ?>
        </div>
        <div class="ct-doc-meta">
            <div class="ct-doc-meta-kicker">Ticket de consultation</div>
            <div class="ct-doc-meta-num"><?= htmlspecialchars($c['numero_ticket'] ?? ('#' . ($c['id'] ?? ''))) ?></div>
            <div class="ct-doc-meta-date">Généré le <?= date('d/m/Y H:i') ?></div>
        </div>
    </header>

    <div class="ct-doc-strip">
        <?= ct_strip('Date', !empty($c['date_consultation']) ? date('d/m/Y H:i', strtotime($c['date_consultation'])) : '—') ?>
        <?= ct_strip('Type', htmlspecialchars($typeConsult)) ?>
        <?= ct_strip('Statut', htmlspecialchars($statut)) ?>
        <?= ct_strip('Consultation', '#' . (int) ($c['id'] ?? 0)) ?>
    </div>

    <div class="ct-doc-body">
        <section class="ct-section">
            <h2 class="ct-section-title">Patient &amp; professionnel</h2>
            <dl class="ct-dl">
                <?= ct_kv('Patient', $patient, true) ?>
                <?= ct_kv($medecinLabel, $medecin) ?>
                <?= ct_kv('Spécialité', htmlspecialchars($c['medecin_specialite'] ?? '—')) ?>
            </dl>
        </section>

        <?php if (!empty($soins)): ?>
        <section class="ct-section">
            <h2 class="ct-section-title">Soins administrés</h2>
            <table class="ct-table">
                <thead>
                    <tr>
                        <th>Soin</th>
                        <th class="text-center">Qté</th>
                        <th class="text-end">P.U.</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($soins as $soin): ?>
                    <tr>
                        <td><?= htmlspecialchars($soin['nom'] ?? '') ?></td>
                        <td class="text-center"><?= (int) ($soin['quantite'] ?? 0) ?></td>
                        <td class="text-end"><?= ct_money((float) ($soin['prix_unitaire'] ?? 0)) ?></td>
                        <td class="text-end"><strong><?= ct_money((float) ($soin['prix_total'] ?? 0)) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><th colspan="3">Total soins</th><th><?= ct_money((float) $data['total_soins']) ?></th></tr>
                </tfoot>
            </table>
        </section>
        <?php endif; ?>

        <?php if (!empty($sejours)): ?>
        <section class="ct-section">
            <h2 class="ct-section-title">Hospitalisation</h2>
            <table class="ct-table">
                <thead>
                    <tr>
                        <th>Catégorie</th>
                        <th class="text-center">Admission</th>
                        <th class="text-center">Sortie</th>
                        <th class="text-center">Durée</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($sejours as $sejour): ?>
                    <tr>
                        <td><?= htmlspecialchars($sejour['categorie_nom'] ?? '') ?></td>
                        <td class="text-center"><?= !empty($sejour['date_admission']) ? date('d/m/Y H:i', strtotime($sejour['date_admission'])) : '—' ?></td>
                        <td class="text-center"><?= !empty($sejour['date_sortie']) ? date('d/m/Y H:i', strtotime($sejour['date_sortie'])) : 'En cours' ?></td>
                        <td class="text-center"><?= (int) ($sejour['duree_jours'] ?? 0) ?> j</td>
                        <td class="text-end"><strong><?= ct_money((float) ($sejour['prix_total'] ?? 0)) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><th colspan="4">Total hospitalisation</th><th><?= ct_money((float) $data['total_hospitalisation']) ?></th></tr>
                </tfoot>
            </table>
        </section>
        <?php endif; ?>

        <section class="ct-section">
            <h2 class="ct-section-title">Récapitulatif</h2>
            <div class="ct-recap">
                <div class="ct-recap-item"><span>Consultation</span><strong><?= ct_money((float) ($c['prix_consultation'] ?? 0)) ?></strong></div>
                <div class="ct-recap-item"><span>Soins</span><strong><?= ct_money((float) $data['total_soins']) ?></strong></div>
                <div class="ct-recap-item"><span>Hospitalisation</span><strong><?= ct_money((float) $data['total_hospitalisation']) ?></strong></div>
            </div>
            <div class="ct-total">
                <span class="ct-total-label">Total général</span>
                <span class="ct-total-value"><?= ct_money((float) $data['total_general']) ?></span>
            </div>
        </section>

        <?php if (!empty($c['symptomes']) || !empty($c['diagnostic']) || !empty($c['traitement'])): ?>
        <section class="ct-section ct-medical">
            <h2 class="ct-section-title">Informations médicales</h2>
            <?php if (!empty($c['symptomes'])): ?>
            <p><strong>Symptômes</strong><br><?= nl2br(htmlspecialchars($c['symptomes'])) ?></p>
            <?php endif; ?>
            <?php if (!empty($c['diagnostic'])): ?>
            <p><strong>Diagnostic</strong><br><?= nl2br(htmlspecialchars($c['diagnostic'])) ?></p>
            <?php endif; ?>
            <?php if (!empty($c['traitement'])): ?>
            <p><strong>Traitement</strong><br><?= nl2br(htmlspecialchars($c['traitement'])) ?></p>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <footer class="ct-doc-foot">
            Merci de votre confiance · <?= htmlspecialchars($nomClinique) ?>
        </footer>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('consultation_ticket_render_page')) {
    /** @param array<string, mixed> $data */
    function consultation_ticket_render_page(array $data, bool $autoPrint = false, bool $withControls = true): string
    {
        $cssUrl = function_exists('app_url')
            ? htmlspecialchars(app_url('assets/css/consultation-ticket.css'))
            : '../assets/css/consultation-ticket.css';
        $doc = consultation_ticket_render_doc($data);
        $title = 'Ticket ' . htmlspecialchars($data['consultation']['numero_ticket'] ?? '');

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?= $cssUrl ?>" rel="stylesheet">
</head>
<body class="ct-print-page">
    <?php if ($withControls):
        $backId = (int) ($data['consultation']['id'] ?? 0);
        $backHref = function_exists('app_url') ? app_url('consultations/voir.php?id=' . $backId) : 'voir.php?id=' . $backId;
    ?>
    <div class="ct-print-controls">
        <button type="button" class="btn-ct btn-ct--primary" onclick="window.print()"><i class="fas fa-print"></i> Imprimer</button>
        <a href="<?= htmlspecialchars($backHref) ?>" class="btn-ct btn-ct--ghost"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>
    <?php endif; ?>
    <div class="ct-print-wrap"><?= $doc ?></div>
    <?php if ($autoPrint): ?>
    <script>setTimeout(function () { window.print(); }, 350);</script>
    <?php endif; ?>
</body>
</html>
        <?php
        return (string) ob_get_clean();
    }
}
