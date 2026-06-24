<?php
/** Fragment liste paiements */

app_mod_stats([
    ['value' => formatFCFA($stats['total_encaisse'] ?? 0), 'label' => 'Total encaissé', 'icon' => 'fa-coins', 'mod' => 'teal'],
    ['value' => formatFCFA($stats['en_attente'] ?? 0), 'label' => 'En attente', 'icon' => 'fa-clock', 'mod' => 'amber'],
    ['value' => formatFCFA(($stats['en_attente'] ?? 0) + ($stats['total_encaisse'] ?? 0)), 'label' => 'Factures émises', 'icon' => 'fa-file-invoice'],
    ['value' => $stats['total'] ?? 0, 'label' => 'Transactions', 'icon' => 'fa-credit-card'],
]);
?>

<div class="app-mod-filter">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-lg-3 col-md-6">
            <label class="form-label small text-muted mb-1">Recherche</label>
            <input type="text" class="form-control" name="search" placeholder="Patient, facture…"
                   value="<?= htmlspecialchars($search) ?>">
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
        <div class="col-lg-2 col-md-4">
            <label class="form-label small text-muted mb-1">Type</label>
            <select class="form-select" name="type_paiement">
                <option value="">Tous les types</option>
                <?php foreach ($typesPaiement as $key => $label): ?>
                <option value="<?= htmlspecialchars($key) ?>" <?= $type_paiement === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
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
    if ($search || $statut || $type_paiement):
        $parts = [];
        if ($search) {
            $parts[] = '« ' . htmlspecialchars($search) . ' »';
        }
        if ($statut) {
            $parts[] = 'statut <strong>' . htmlspecialchars($statuts[$statut] ?? $statut) . '</strong>';
        }
        if ($type_paiement) {
            $parts[] = 'type <strong>' . htmlspecialchars($typesPaiement[$type_paiement] ?? $type_paiement) . '</strong>';
        }
        app_mod_filter_active((int) $totalPaiements, implode(' · ', $parts));
    endif;
    ?>
</div>

<?php if (empty($paiements)): ?>
<div class="app-mod-empty">
    <i class="fas fa-credit-card d-block"></i>
    <h5 class="mb-2">Aucun paiement trouvé</h5>
    <p class="mb-3">Commencez par créer votre premier paiement.</p>
    <a href="ajouter.php" class="btn btn-primary btn-sm">Nouveau paiement</a>
</div>
<?php else: ?>
<div class="app-mod-table-wrap">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 mod-list-table">
            <thead>
                <tr>
                    <th>Facture</th>
                    <th>Patient</th>
                    <th>Montant</th>
                    <th class="d-none d-md-table-cell">Type</th>
                    <th>Statut</th>
                    <th class="d-none d-lg-table-cell">Date</th>
                    <th class="text-end mod-actions-cell">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($paiements as $paiement):
                $pid = (int) $paiement['id'];
                $typeLabel = $typesPaiement[$paiement['type_paiement']] ?? $paiement['type_paiement'];
                $statutLabel = $statuts[$paiement['statut']] ?? ucfirst($paiement['statut']);
            ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($paiement['numero_facture']) ?></strong>
                        <?php if (!empty($paiement['consultation_id'])): ?>
                        <div class="mod-meta">Consultation #<?= (int) $paiement['consultation_id'] ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= htmlspecialchars($paiement['patient_nom'] . ' ' . $paiement['patient_prenom']) ?>
                        <div class="mod-meta"><?= htmlspecialchars($paiement['numero_dossier']) ?></div>
                    </td>
                    <td><span class="amount-positive"><?= formatFCFA($paiement['montant']) ?></span></td>
                    <td class="d-none d-md-table-cell"><?= app_mod_badge($paiement['type_paiement'], $typeLabel) ?></td>
                    <td><?= app_mod_badge($paiement['statut'], $statutLabel) ?></td>
                    <td class="d-none d-lg-table-cell text-muted small">
                        <?= date('d/m/Y H:i', strtotime($paiement['date_paiement'])) ?>
                    </td>
                    <td class="text-end mod-actions-cell">
                        <?php app_mod_actions_dropdown([
                            ['href' => 'voir.php?id=' . $pid, 'label' => 'Voir', 'icon' => 'fa-eye'],
                            ['href' => 'modifier.php?id=' . $pid, 'label' => 'Modifier', 'icon' => 'fa-edit'],
                            ['href' => 'facture_paiement.php?id=' . $pid . '&print=1', 'label' => 'Imprimer', 'icon' => 'fa-print', 'target' => '_blank'],
                            ['divider' => true],
                            [
                                'href' => 'supprimer.php?id=' . $pid,
                                'label' => 'Supprimer',
                                'icon' => 'fa-trash',
                                'class' => 'text-danger',
                                'onclick' => "return confirm('Êtes-vous sûr de vouloir supprimer ce paiement ?')",
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
app_mod_pagination($page, (int) $totalPages, [
    'search' => $search,
    'statut' => $statut,
    'type_paiement' => $type_paiement,
], 'Pagination paiements');
app_mod_list_count(count($paiements), (int) $totalPaiements, 'paiement(s)');
?>
