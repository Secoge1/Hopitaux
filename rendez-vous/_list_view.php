<?php
/** Fragment liste rendez-vous */

$rdvBuildActions = static function (int $rid, string $pStatut): array {
    $actions = [
        ['href' => 'voir.php?id=' . $rid, 'label' => 'Voir', 'icon' => 'fa-eye'],
        ['href' => 'modifier.php?id=' . $rid, 'label' => 'Modifier', 'icon' => 'fa-edit'],
    ];
    if ($pStatut === 'planifie') {
        $actions[] = [
            'form' => ['action' => 'actions.php', 'fields' => ['action' => 'confirmer', 'rdv_id' => $rid], 'confirm' => 'Confirmer ce rendez-vous ?'],
            'label' => 'Confirmer',
            'icon' => 'fa-check',
            'class' => 'text-success',
        ];
    }
    if (in_array($pStatut, ['planifie', 'confirme'], true)) {
        $actions[] = [
            'form' => ['action' => 'actions.php', 'fields' => ['action' => 'annuler', 'rdv_id' => $rid], 'confirm' => 'Annuler ce rendez-vous ?'],
            'label' => 'Annuler',
            'icon' => 'fa-times',
            'class' => 'text-danger',
        ];
    }
    if ($pStatut === 'confirme') {
        $actions[] = [
            'form' => ['action' => 'actions.php', 'fields' => ['action' => 'terminer', 'rdv_id' => $rid], 'confirm' => 'Marquer comme terminé ?'],
            'label' => 'Terminer',
            'icon' => 'fa-flag-checkered',
        ];
    }
    $actions[] = ['divider' => true];
    $actions[] = [
        'href' => 'supprimer.php?id=' . $rid,
        'label' => 'Supprimer',
        'icon' => 'fa-trash',
        'class' => 'text-danger',
        'onclick' => "return confirm('Supprimer ce rendez-vous ?')",
    ];
    return $actions;
};

app_mod_stats([
    ['value' => $stats['total'] ?? 0, 'label' => 'Total RDV', 'icon' => 'fa-calendar-check'],
    ['value' => $stats['confirme'] ?? 0, 'label' => 'Confirmés', 'icon' => 'fa-check-circle', 'mod' => 'teal'],
    ['value' => $stats['aujourd_hui'] ?? 0, 'label' => "Aujourd'hui", 'icon' => 'fa-calendar-day', 'mod' => 'amber'],
    ['value' => $stats['cette_semaine'] ?? 0, 'label' => 'Cette semaine', 'icon' => 'fa-calendar-week'],
]);
?>

<div class="app-mod-filter">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-lg-4 col-md-5">
            <label class="form-label small text-muted mb-1">Recherche</label>
            <input type="text" class="form-control" name="search" placeholder="Patient, médecin…" value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-lg-2 col-md-3">
            <label class="form-label small text-muted mb-1">Statut</label>
            <select class="form-select" name="statut">
                <option value="">Tous</option>
                <option value="planifie" <?= $statut === 'planifie' ? 'selected' : '' ?>>Planifié</option>
                <option value="confirme" <?= $statut === 'confirme' ? 'selected' : '' ?>>Confirmé</option>
                <option value="annule" <?= $statut === 'annule' ? 'selected' : '' ?>>Annulé</option>
                <option value="termine" <?= $statut === 'termine' ? 'selected' : '' ?>>Terminé</option>
            </select>
        </div>
        <div class="col-lg-2 col-md-4">
            <label class="form-label small text-muted mb-1">Date</label>
            <input type="date" class="form-control" name="date" value="<?= htmlspecialchars($date) ?>">
        </div>
        <div class="col-lg-4 col-md-12">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1"><i class="fas fa-search me-1"></i>Rechercher</button>
                <a href="index.php" class="btn btn-outline-secondary" title="Effacer"><i class="fas fa-times"></i></a>
            </div>
        </div>
    </form>
    <?php if ($search || $statut || $date): app_mod_filter_active($total_rdvs, trim(($search ? '« ' . htmlspecialchars($search) . ' »' : '') . ($statut ? ($search ? ' · ' : '') . 'statut <strong>' . htmlspecialchars($statut) . '</strong>' : '') . ($date ? (($search || $statut) ? ' · ' : '') . date('d/m/Y', strtotime($date)) : ''))); endif; ?>
</div>

<?php if (!empty($rdvs_today)): ?>
<div class="app-mod-table-wrap mb-4 border-warning" style="border-width:2px!important">
    <div class="px-3 py-2 bg-warning bg-opacity-10 border-bottom small fw-semibold">
        <i class="fas fa-calendar-day me-1 text-warning"></i>Rendez-vous du jour (<?= date('d/m/Y') ?>)
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 mod-list-table">
            <thead><tr><th>Heure</th><th>Patient</th><th>Médecin</th><th>Motif</th><th>Statut</th><th class="text-end mod-actions-cell">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rdvs_today as $rdv):
                $rid = (int) $rdv['id'];
                $pStatut = $rdv['statut'] ?? 'planifie';
            ?>
                <tr class="rdv-today">
                    <td><strong><?= htmlspecialchars($rdv['heure_rdv']) ?></strong></td>
                    <td><?= htmlspecialchars($rdv['patient_prenom'] . ' ' . $rdv['patient_nom']) ?></td>
                    <td><?= htmlspecialchars(medecin_profil_format_joined($rdv)) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($rdv['motif'] ?: '—') ?></td>
                    <td><?= app_mod_badge($pStatut) ?></td>
                    <td class="text-end mod-actions-cell">
                        <?php app_mod_actions_dropdown($rdvBuildActions($rid, $pStatut)); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if (empty($rdvs)): ?>
<div class="app-mod-empty">
    <i class="fas fa-calendar-times d-block"></i>
    <h5 class="mb-2">Aucun rendez-vous</h5>
    <p class="mb-3">Ajustez vos filtres ou créez un nouveau RDV.</p>
    <a href="ajouter.php" class="btn btn-primary btn-sm">Nouveau RDV</a>
</div>
<?php else: ?>
<div class="app-mod-table-wrap">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 mod-list-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Heure</th>
                    <th>Patient</th>
                    <th class="d-none d-md-table-cell">Médecin</th>
                    <th class="d-none d-lg-table-cell">Motif</th>
                    <th>Statut</th>
                    <th class="text-end mod-actions-cell">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rdvs as $rdv):
                $rid = (int) $rdv['id'];
                $is_today = date('Y-m-d') === $rdv['date_rdv'];
                $is_week = strtotime($rdv['date_rdv']) >= strtotime('today') && strtotime($rdv['date_rdv']) <= strtotime('+7 days');
                $rowClass = $is_today ? 'rdv-today' : ($is_week ? 'rdv-week' : '');
                $pStatut = $rdv['statut'] ?? 'planifie';
                $actions = $rdvBuildActions($rid, $pStatut);
            ?>
                <tr class="<?= $rowClass ?>">
                    <td>
                        <strong><?= date('d/m/Y', strtotime($rdv['date_rdv'])) ?></strong>
                        <?php if ($is_today): ?><span class="badge bg-warning text-dark ms-1" style="font-size:0.65rem">Auj.</span><?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($rdv['heure_rdv']) ?></td>
                    <td><?= htmlspecialchars($rdv['patient_prenom'] . ' ' . $rdv['patient_nom']) ?></td>
                    <td class="d-none d-md-table-cell">
                        <?= htmlspecialchars(medecin_profil_format_joined($rdv)) ?>
                        <?php if (!empty($rdv['medecin_specialite'])): ?><div class="mod-meta"><?= htmlspecialchars($rdv['medecin_specialite']) ?></div><?php endif; ?>
                    </td>
                    <td class="d-none d-lg-table-cell text-muted"><?= htmlspecialchars($rdv['motif'] ?: '—') ?></td>
                    <td><?= app_mod_badge($pStatut) ?></td>
                    <td class="text-end mod-actions-cell"><?php app_mod_actions_dropdown($actions); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php app_mod_pagination($page, (int) $total_pages, ['search' => $search, 'statut' => $statut, 'date' => $date], 'Pagination rendez-vous'); ?>
<?php app_mod_list_count(count($rdvs), (int) $total_rdvs, 'rendez-vous'); ?>
