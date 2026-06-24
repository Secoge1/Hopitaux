<?php
/** Fragment liste consultations */

if (!$patient_id && $stats !== null):
    app_mod_stats([
        ['value' => $stats['total'] ?? 0, 'label' => 'Total consultations', 'icon' => 'fa-stethoscope'],
        ['value' => $stats['en_cours'] ?? 0, 'label' => 'En cours', 'icon' => 'fa-clock', 'mod' => 'amber'],
        ['value' => $stats['terminee'] ?? 0, 'label' => 'Terminées', 'icon' => 'fa-check-circle', 'mod' => 'teal'],
        ['value' => $stats['planifiee'] ?? 0, 'label' => 'Planifiées', 'icon' => 'fa-calendar-check'],
    ]);
endif;

if (!$patient_id): ?>
<div class="app-mod-filter">
    <form method="GET" class="row g-3 align-items-end" id="searchForm">
        <div class="col-lg-4 col-md-6">
            <label class="form-label small text-muted mb-1" for="searchInput">Recherche</label>
            <div class="autocomplete-container">
                <input type="text" class="form-control" name="search" id="searchInput"
                       placeholder="Patient, médecin…"
                       value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                <div class="autocomplete-suggestions" id="autocompleteSuggestions"></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-4">
            <label class="form-label small text-muted mb-1" for="statutSelect">Statut</label>
            <select class="form-select" name="statut" id="statutSelect">
                <option value="">Tous les statuts</option>
                <option value="planifiee" <?= $statut === 'planifiee' ? 'selected' : '' ?>>Planifiée</option>
                <option value="en_cours" <?= $statut === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                <option value="terminee" <?= $statut === 'terminee' ? 'selected' : '' ?>>Terminée</option>
                <option value="annulee" <?= $statut === 'annulee' ? 'selected' : '' ?>>Annulée</option>
            </select>
        </div>
        <div class="col-lg-5 col-md-12">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1"><i class="fas fa-search me-1"></i>Rechercher</button>
                <a href="index.php" class="btn btn-outline-secondary" title="Effacer"><i class="fas fa-times"></i></a>
            </div>
        </div>
    </form>
    <?php
    if ($search || $statut || $date):
        $parts = [];
        if ($search) {
            $parts[] = '« ' . htmlspecialchars($search) . ' »';
        }
        if ($statut) {
            $parts[] = 'statut <strong>' . htmlspecialchars($statut) . '</strong>';
        }
        if ($date) {
            $parts[] = date('d/m/Y', strtotime($date));
        }
        app_mod_filter_active((int) $total_consultations, implode(' · ', $parts));
    endif;
    ?>
</div>
<?php endif; ?>

<?php if (empty($consultations)): ?>
<div class="app-mod-empty">
    <i class="fas fa-stethoscope d-block"></i>
    <h5 class="mb-2">Aucune consultation trouvée</h5>
    <p class="mb-3">Utilisez la recherche ou créez une nouvelle consultation.</p>
    <a href="ajouter.php<?= $patient_id ? '?patient_id=' . (int) $patient_id : '' ?>" class="btn btn-primary btn-sm">Nouvelle consultation</a>
</div>
<?php else: ?>
<div class="app-mod-table-wrap">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 mod-list-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Patient</th>
                    <th class="d-none d-md-table-cell">Médecin</th>
                    <th class="d-none d-lg-table-cell">Symptômes</th>
                    <th>Prix</th>
                    <th>Statut</th>
                    <th class="text-end mod-actions-cell">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($consultations as $consultation):
                $cid = (int) $consultation['id'];
                $consultation_time = new DateTime($consultation['date_consultation']);
                $current_time = new DateTime();
                $is_today = $consultation_time->format('Y-m-d') === $current_time->format('Y-m-d');
                $is_future = $consultation_time > $current_time;
                $is_week = strtotime($consultation['date_consultation']) >= strtotime('today')
                    && strtotime($consultation['date_consultation']) <= strtotime('+7 days');
                $rowClass = $is_today ? 'consultation-today' : ($is_week ? 'consultation-week' : '');
                $symptomes = $consultation['symptomes'] ?: 'Non renseignés';
                $symptomesShort = strlen($symptomes) > 50 ? substr($symptomes, 0, 50) . '…' : $symptomes;
                $cStatut = $consultation['statut'] ?? '';
                $actions = [
                    ['href' => 'voir.php?id=' . $cid, 'label' => 'Voir', 'icon' => 'fa-eye'],
                    ['href' => 'modifier.php?id=' . $cid, 'label' => 'Modifier', 'icon' => 'fa-edit'],
                ];
                if (!empty($consultation['hospitalisation_requise'])) {
                    $actions[] = ['href' => 'hospitalisation.php?id=' . $cid, 'label' => 'Hospitalisation', 'icon' => 'fa-bed'];
                }
                $actions[] = ['divider' => true];
                $actions[] = [
                    'href' => 'supprimer.php?id=' . $cid,
                    'label' => 'Supprimer',
                    'icon' => 'fa-trash',
                    'class' => 'text-danger',
                    'onclick' => "return confirm('Supprimer cette consultation ?')",
                ];
            ?>
                <tr class="<?= $rowClass ?>">
                    <td>
                        <strong><?= date('d/m/Y H:i', strtotime($consultation['date_consultation'])) ?></strong>
                        <?php if ($is_today): ?>
                        <span class="badge bg-warning text-dark ms-1" style="font-size:0.65rem">Auj.</span>
                        <?php elseif ($is_future): ?>
                        <span class="badge bg-success ms-1" style="font-size:0.65rem">À venir</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($consultation['patient_prenom'] . ' ' . $consultation['patient_nom']) ?></td>
                    <td class="d-none d-md-table-cell">
                        <?= htmlspecialchars(medecin_profil_format_joined($consultation)) ?>
                        <?php if (!empty($consultation['medecin_specialite'])): ?>
                        <div class="mod-meta"><?= htmlspecialchars($consultation['medecin_specialite']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="d-none d-lg-table-cell text-muted" title="<?= htmlspecialchars($symptomes) ?>">
                        <?= htmlspecialchars($symptomesShort) ?>
                    </td>
                    <td>
                        <strong class="text-success"><?= number_format((float) $consultation['prix_consultation'], 0, ',', ' ') ?> FCFA</strong>
                        <?php if (!empty($consultation['hospitalisation_requise'])): ?>
                        <div class="mod-meta"><span class="badge bg-warning text-dark">+ Hospitalisation</span></div>
                        <?php endif; ?>
                    </td>
                    <td><?= $cStatut !== '' ? app_mod_badge($cStatut) : '<span class="mod-badge mod-badge--secondary">Non défini</span>' ?></td>
                    <td class="text-end mod-actions-cell"><?php app_mod_actions_dropdown($actions); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php
$paginationParams = array_filter([
    'search' => $search,
    'statut' => $statut,
    'date' => $date,
    'patient_id' => $patient_id ?: null,
], static fn($v) => $v !== null && $v !== '');
app_mod_pagination($page, (int) $total_pages, $paginationParams, 'Pagination consultations');
app_mod_list_count(count($consultations), (int) $total_consultations, 'consultation(s)');
?>
