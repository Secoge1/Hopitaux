<?php
/** Fragment liste laboratoire */

app_mod_stats([
    ['value' => $stats['en_cours'] ?? 0, 'label' => 'Analyses en cours', 'icon' => 'fa-flask', 'mod' => 'amber'],
    ['value' => $stats['en_attente'] ?? 0, 'label' => 'En attente', 'icon' => 'fa-clock', 'mod' => 'amber'],
    ['value' => $stats['termine'] ?? 0, 'label' => 'Terminées', 'icon' => 'fa-check-circle', 'mod' => 'teal'],
    ['value' => $stats['total'] ?? 0, 'label' => 'Total', 'icon' => 'fa-vials'],
]);
?>

<div class="app-mod-filter">
    <form method="GET" class="row g-3 align-items-end" id="searchForm">
        <?php if ($patient_id): ?>
        <input type="hidden" name="patient_id" value="<?= (int) $patient_id ?>">
        <?php endif; ?>
        <div class="col-lg-3 col-md-6">
            <label class="form-label small text-muted mb-1" for="searchInput">Recherche</label>
            <div class="autocomplete-container">
                <input type="text" class="form-control" name="search" id="searchInput"
                       placeholder="Patient, type d'analyse…"
                       value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                <div class="autocomplete-suggestions" id="autocompleteSuggestions"></div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4">
            <label class="form-label small text-muted mb-1">Type</label>
            <select class="form-select" name="type_analyse">
                <option value="">Tous les types</option>
                <?php foreach ($typesAnalyses as $key => $label): ?>
                <option value="<?= htmlspecialchars($key) ?>" <?= $type_analyse === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2 col-md-4">
            <label class="form-label small text-muted mb-1">Statut</label>
            <select class="form-select" name="statut">
                <option value="">Tous les statuts</option>
                <?php foreach ($statuts as $key => $label): ?>
                <option value="<?= htmlspecialchars($key) ?>" <?= $statut === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-5 col-md-12">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1"><i class="fas fa-search me-1"></i>Rechercher</button>
                <a href="index.php<?= $patient_id ? '?patient_id=' . (int) $patient_id : '' ?>" class="btn btn-outline-secondary" title="Effacer"><i class="fas fa-times"></i></a>
            </div>
        </div>
    </form>
    <?php
    if ($search || $statut || $type_analyse):
        $parts = [];
        if ($search) {
            $parts[] = '« ' . htmlspecialchars($search) . ' »';
        }
        if ($type_analyse) {
            $parts[] = 'type <strong>' . htmlspecialchars($typesAnalyses[$type_analyse] ?? $type_analyse) . '</strong>';
        }
        if ($statut) {
            $parts[] = 'statut <strong>' . htmlspecialchars($statuts[$statut] ?? $statut) . '</strong>';
        }
        app_mod_filter_active((int) $totalAnalyses, implode(' · ', $parts));
    endif;
    ?>
</div>

<?php if (empty($analyses)): ?>
<div class="app-mod-empty">
    <i class="fas fa-flask d-block"></i>
    <h5 class="mb-2">Aucune analyse trouvée</h5>
    <p class="mb-3">Commencez par créer votre première analyse de laboratoire.</p>
    <a href="ajouter.php<?= $patient_id ? '?patient_id=' . (int) $patient_id : '' ?>" class="btn btn-primary btn-sm">Nouvelle analyse</a>
</div>
<?php else: ?>
<div class="app-mod-table-wrap">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 mod-list-table">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Type d'analyse</th>
                    <th class="d-none d-md-table-cell">Médecin</th>
                    <th class="d-none d-lg-table-cell">Priorité</th>
                    <th>Prix</th>
                    <th>Statut</th>
                    <th class="d-none d-xl-table-cell">Date création</th>
                    <th class="text-end mod-actions-cell">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($analyses as $analyse):
                $aid = (int) $analyse['id'];
                $priorite = $analyse['priorite'] ?? '';
                $statutLabel = $statuts[$analyse['statut']] ?? ucfirst($analyse['statut'] ?? '');
                $ticketUrl = 'ticket_analyse.php?id=' . $aid . '&print=1';
            ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($analyse['patient_nom'] . ' ' . $analyse['patient_prenom']) ?></strong>
                        <?php if (!empty($analyse['numero_dossier'])): ?>
                        <div class="mod-meta"><?= htmlspecialchars($analyse['numero_dossier']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= app_mod_badge($analyse['type_analyse'] ?? 'analyse', $analyse['type_analyse'] ?? '—') ?></td>
                    <td class="d-none d-md-table-cell">
                        <?= htmlspecialchars(medecin_profil_format_joined($analyse)) ?: '—' ?>
                    </td>
                    <td class="d-none d-lg-table-cell"><?= $priorite !== '' ? app_mod_badge($priorite, ucfirst($priorite)) : '—' ?></td>
                    <td>
                        <strong class="text-success"><?= number_format((float) ($analyse['prix_analyse'] ?? 0), 0, ',', ' ') ?> FCFA</strong>
                        <?php if (!empty($analyse['numero_ticket'])): ?>
                        <div class="mod-meta"><?= htmlspecialchars($analyse['numero_ticket']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= app_mod_badge($analyse['statut'] ?? '', $statutLabel) ?></td>
                    <td class="d-none d-xl-table-cell text-muted small">
                        <?= date('d/m/Y H:i', strtotime($analyse['date_creation'])) ?>
                    </td>
                    <td class="text-end mod-actions-cell">
                        <?php app_mod_actions_dropdown([
                            ['href' => 'voir.php?id=' . $aid, 'label' => 'Voir', 'icon' => 'fa-eye'],
                            [
                                'href' => app_url('patients/ticket_thermique_labo.php?analyse_id=' . $aid . '&print=1&return=' . urlencode(app_url('laboratoire/voir.php?id=' . $aid))),
                                'label' => 'Ticket Caisse',
                                'icon' => 'fa-receipt',
                            ],
                            [
                                'href' => $ticketUrl,
                                'label' => 'Ticket A4',
                                'icon' => 'fa-print',
                                'onclick' => "printTicket(this.href); return false;",
                            ],
                            ['href' => 'modifier.php?id=' . $aid, 'label' => 'Modifier', 'icon' => 'fa-edit'],
                            ['divider' => true],
                            [
                                'href' => 'supprimer.php?id=' . $aid,
                                'label' => 'Supprimer',
                                'icon' => 'fa-trash',
                                'class' => 'text-danger',
                                'onclick' => "return confirm('Êtes-vous sûr de vouloir supprimer cette analyse ?')",
                            ],
                        ]); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php
app_mod_pagination($page, (int) $totalPages, array_filter([
    'search' => $search,
    'statut' => $statut,
    'type_analyse' => $type_analyse,
    'patient_id' => $patient_id ?: null,
]), 'Pagination analyses');
app_mod_list_count(count($analyses), (int) $totalAnalyses, 'analyse(s)');
?>
