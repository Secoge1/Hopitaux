<?php
/** Fragment liste patients — tableau compact (inclus par index.php) */
if (!isset($patients_suppression_actif)) {
    require_once __DIR__ . '/../includes/patient_settings.php';
    $patients_suppression_actif = patient_deletion_allowed();
}
?>
<div class="app-mod-stats patients-kpis mb-4" id="statsRow">
    <div class="app-mod-stat">
        <div class="app-mod-stat-val" id="stat-total"><?= (int) ($stats['total'] ?? 0) ?></div>
        <div class="app-mod-stat-label"><i class="fas fa-user-injured me-1 text-muted"></i>Total patients</div>
    </div>
    <div class="app-mod-stat app-mod-stat--teal">
        <div class="app-mod-stat-val" id="stat-actif"><?= (int) ($stats['actif'] ?? 0) ?></div>
        <div class="app-mod-stat-label"><i class="fas fa-check-circle me-1 text-muted"></i>Actifs</div>
    </div>
    <div class="app-mod-stat app-mod-stat--amber">
        <div class="app-mod-stat-val" id="stat-nouveaux"><?= (int) ($stats['nouveaux_mois'] ?? 0) ?></div>
        <div class="app-mod-stat-label"><i class="fas fa-calendar-plus me-1 text-muted"></i>Nouveaux ce mois</div>
    </div>
    <div class="app-mod-stat">
        <div class="app-mod-stat-val" id="stat-consult"><?= htmlspecialchars((string) ($stats['consultations_moyenne'] ?? 0)) ?></div>
        <div class="app-mod-stat-label"><i class="fas fa-chart-line me-1 text-muted"></i>Consult. moy. / patient</div>
    </div>
</div>

<div class="app-mod-filter">
    <form method="GET" class="row g-3 align-items-end" id="searchForm">
        <div class="col-lg-5 col-md-6">
            <label class="form-label small text-muted mb-1" for="searchInput">Recherche</label>
            <div class="autocomplete-container">
                <input type="text" class="form-control" name="search" id="searchInput"
                       placeholder="Nom, prénom, n° dossier…"
                       value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                <div class="autocomplete-suggestions" id="autocompleteSuggestions"></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-4">
            <label class="form-label small text-muted mb-1" for="statutSelect">Statut</label>
            <select class="form-select" name="statut" id="statutSelect">
                <option value="">Tous (hors supprimés)</option>
                <option value="actif" <?= $statut === 'actif' ? 'selected' : '' ?>>Actif</option>
                <option value="inactif" <?= $statut === 'inactif' ? 'selected' : '' ?>>Inactif</option>
                <option value="archive" <?= $statut === 'archive' ? 'selected' : '' ?>>Archivé</option>
                <option value="supprime" <?= $statut === 'supprime' ? 'selected' : '' ?>>Supprimés</option>
            </select>
        </div>
        <div class="col-lg-4 col-md-12">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="fas fa-search me-1"></i>Rechercher
                </button>
                <a href="index.php" class="btn btn-outline-secondary" title="Effacer"><i class="fas fa-times"></i></a>
            </div>
        </div>
    </form>
    <?php if ($search || $statut): ?>
    <div class="alert alert-light border mt-3 mb-0 py-2 small">
        <i class="fas fa-filter me-1 text-primary"></i>
        <strong><?= (int) $total_patients ?></strong> résultat(s)
        <?php if ($search): ?> · « <?= htmlspecialchars($search) ?> »<?php endif; ?>
        <?php if ($statut): ?> · statut <strong><?= htmlspecialchars($statut) ?></strong><?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php if (empty($patients)): ?>
<div class="app-mod-empty">
    <i class="fas fa-user-injured d-block"></i>
    <?php if ($search || $statut): ?>
    <h5 class="mb-2">Aucun résultat</h5>
    <p class="mb-3">Aucun patient ne correspond à vos critères.</p>
    <a href="index.php" class="btn btn-outline-secondary btn-sm me-2">Effacer les filtres</a>
    <a href="ajouter.php" class="btn btn-primary btn-sm">Ajouter un patient</a>
    <?php else: ?>
    <h5 class="mb-2">Aucun patient</h5>
    <p class="mb-3">Commencez par créer votre premier dossier patient.</p>
    <a href="ajouter.php" class="btn btn-primary btn-sm">Ajouter un patient</a>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="app-mod-table-wrap patients-table-wrap">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 patients-table mod-list-table">
            <thead>
                <tr>
                    <th>Dossier</th>
                    <th>Patient</th>
                    <th class="d-none d-md-table-cell">Âge</th>
                    <th class="d-none d-lg-table-cell">Contact</th>
                    <th class="d-none d-xl-table-cell">Médecin</th>
                    <th class="d-none d-xl-table-cell">Ville</th>
                    <th>Statut</th>
                    <th class="d-none d-md-table-cell">Inscrit</th>
                    <th class="text-end mod-actions-cell">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($patients as $patient):
                $pid = (int) $patient['id'];
                $fullName = htmlspecialchars($patient['prenom'] . ' ' . $patient['nom']);
                $pStatut = $patient['statut'] ?? 'actif';
                $statusClass = in_array($pStatut, ['actif', 'inactif', 'archive', 'supprime'], true) ? $pStatut : 'actif';
                $sexeLabel = ($patient['sexe'] ?? '') === 'M' ? 'H' : (($patient['sexe'] ?? '') === 'F' ? 'F' : '—');
                $ville = trim($patient['ville'] ?? '') ?: '—';
                $medRef = '—';
                if (!empty($patient['medecin_referent_prenom']) || !empty($patient['medecin_referent_nom'])) {
                    $medRef = medecin_profil_format_joined($patient, 'medecin_referent');
                }
                $tel = trim($patient['telephone'] ?? '') ?: '—';
                $age = isset($patient['age']) && $patient['age'] !== null ? (int) $patient['age'] : null;
            ?>
                <tr data-patient-id="<?= $pid ?>">
                    <td>
                        <a href="voir.php?id=<?= $pid ?>"><code class="mod-code"><?= htmlspecialchars($patient['numero_dossier']) ?></code></a>
                    </td>
                    <td>
                        <a href="voir.php?id=<?= $pid ?>" class="mod-name-link"><?= $fullName ?></a>
                        <div class="patients-meta d-md-none">
                            <?= $age !== null ? $age . ' ans' : '—' ?> · <?= $sexeLabel ?>
                            <?php if ($tel !== '—'): ?> · <?= htmlspecialchars($tel) ?><?php endif; ?>
                        </div>
                    </td>
                    <td class="d-none d-md-table-cell text-muted">
                        <?= $age !== null ? $age . ' ans' : '—' ?>
                        <span class="patients-sexe"><?= $sexeLabel ?></span>
                    </td>
                    <td class="d-none d-lg-table-cell">
                        <?php if ($tel !== '—'): ?>
                        <a href="tel:<?= htmlspecialchars(preg_replace('/\s+/', '', $tel)) ?>" class="patients-tel"><?= htmlspecialchars($tel) ?></a>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="d-none d-xl-table-cell text-muted text-truncate" style="max-width:160px" title="<?= htmlspecialchars($medRef) ?>">
                        <?= htmlspecialchars($medRef) ?>
                    </td>
                    <td class="d-none d-xl-table-cell text-muted text-truncate" style="max-width:140px" title="<?= htmlspecialchars($ville) ?>">
                        <?= htmlspecialchars($ville) ?>
                    </td>
                    <td>
                        <span class="mod-badge mod-badge--<?= htmlspecialchars($statusClass) ?>"><?= htmlspecialchars(ucfirst($pStatut)) ?></span>
                    </td>
                    <td class="d-none d-md-table-cell text-muted small">
                        <?= !empty($patient['date_creation']) ? date('d/m/Y', strtotime($patient['date_creation'])) : '—' ?>
                    </td>
                    <td class="text-end mod-actions-cell">
                        <?php
                        $patientActions = [
                            ['href' => 'voir.php?id=' . $pid, 'label' => 'Voir la fiche', 'icon' => 'fa-eye', 'tone' => 'primary'],
                            ['href' => 'ticket_caisse.php?patient_id=' . $pid, 'label' => 'Ticket Caisse', 'icon' => 'fa-receipt', 'tone' => 'primary'],
                            ['href' => 'dossier_medical.php?id=' . $pid, 'label' => 'Dossier médical', 'icon' => 'fa-notes-medical', 'tone' => 'primary'],
                            ['href' => 'gerer_documents.php?id=' . $pid, 'label' => 'Documents', 'icon' => 'fa-file-medical', 'tone' => 'neutral'],
                        ];
                        if ($pStatut === 'supprime') {
                            $patientActions[] = ['divider' => true];
                            $patientActions[] = ['href' => 'restaurer.php?id=' . $pid, 'label' => 'Restaurer', 'icon' => 'fa-undo', 'tone' => 'success', 'onclick' => "return confirm('Restaurer ce patient ?')"];
                            if ($patients_suppression_actif) {
                                $patientActions[] = ['href' => 'supprimer_definitivement.php?id=' . $pid, 'label' => 'Supprimer définitivement', 'icon' => 'fa-trash-alt', 'tone' => 'danger', 'onclick' => "return confirm('Suppression définitive irréversible. Confirmer ?')"];
                            }
                        } else {
                            $patientActions[] = ['href' => 'modifier.php?id=' . $pid, 'label' => 'Modifier', 'icon' => 'fa-edit', 'tone' => 'warning'];
                            if ($patients_suppression_actif) {
                                $patientActions[] = ['divider' => true];
                                $patientActions[] = [
                                    'button' => true,
                                    'label' => 'Supprimer',
                                    'icon' => 'fa-trash',
                                    'tone' => 'danger',
                                    'class' => 'js-mod-delete-trigger',
                                    'attrs' => [
                                        'data-delete-id' => (string) $pid,
                                        'data-delete-name' => $patient['prenom'] . ' ' . $patient['nom'],
                                    ],
                                ];
                            }
                        }
                        app_mod_actions_dropdown($patientActions);
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($total_pages > 1): ?>
<nav aria-label="Pagination patients" class="mt-4 app-mod-pagination">
    <ul class="pagination justify-content-center mb-0">
        <?php if ($page > 1): ?>
        <li class="page-item">
            <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&statut=<?= urlencode($statut) ?>"><i class="fas fa-chevron-left"></i></a>
        </li>
        <?php endif; ?>
        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&statut=<?= urlencode($statut) ?>"><?= $i ?></a>
        </li>
        <?php endfor; ?>
        <?php if ($page < $total_pages): ?>
        <li class="page-item">
            <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&statut=<?= urlencode($statut) ?>"><i class="fas fa-chevron-right"></i></a>
        </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<p class="text-center text-muted small mt-3 mb-0 mod-list-count">
    Affichage de <?= count($patients) ?> patient(s) sur <?= (int) $total_patients ?> au total
</p>
