<?php
/** Fragment liste équipe médicale */
require_once __DIR__ . '/../includes/medecin_profil.php';
if (!function_exists('medecin_create_allowed')) {
    require_once __DIR__ . '/../includes/medecin_settings.php';
}
$canCreateMedecin = medecin_create_allowed($auth ?? null);
$canManageMedecin = medecin_admin_actions_allowed($auth ?? null);
$typeProfil = $typeProfil ?? '';
app_mod_stats([
    ['value' => $stats['total'] ?? 0, 'label' => 'Total professionnels', 'icon' => 'fa-user-md', 'id' => 'stat-total'],
    ['value' => $stats['actif'] ?? 0, 'label' => 'Actifs', 'icon' => 'fa-check-circle', 'mod' => 'teal', 'id' => 'stat-actif'],
    ['value' => $stats['conge'] ?? $stats['inactif'] ?? 0, 'label' => 'En congé', 'icon' => 'fa-calendar-times', 'mod' => 'amber', 'id' => 'stat-conge'],
    ['value' => count($stats['specialites'] ?? []), 'label' => 'Spécialités', 'icon' => 'fa-stethoscope', 'id' => 'stat-specialites'],
], 'medecins-kpis');
?>

<div class="app-mod-filter">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-lg-5 col-md-6">
            <label class="form-label small text-muted mb-1">Recherche</label>
            <input type="text" class="form-control" name="search" placeholder="Nom, licence, spécialité…"
                   value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-lg-2 col-md-4">
            <label class="form-label small text-muted mb-1">Profil</label>
            <select class="form-select" name="type_profil">
                <option value="">Tous</option>
                <?php foreach (medecin_profil_keys() as $tp): ?>
                <option value="<?= htmlspecialchars($tp) ?>" <?= $typeProfil === $tp ? 'selected' : '' ?>><?= htmlspecialchars(medecin_profil_label($tp)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2 col-md-4">
            <label class="form-label small text-muted mb-1">Spécialité</label>
            <select class="form-select" name="specialite">
                <option value="">Toutes</option>
                <?php foreach ($specialites as $spec): ?>
                <option value="<?= htmlspecialchars($spec) ?>" <?= $specialite === $spec ? 'selected' : '' ?>><?= htmlspecialchars($spec) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-4 col-md-12">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1"><i class="fas fa-search me-1"></i>Rechercher</button>
                <a href="index.php" class="btn btn-outline-secondary" title="Effacer"><i class="fas fa-times"></i></a>
            </div>
        </div>
    </form>
    <?php if ($search || $specialite || $typeProfil): app_mod_filter_active($total_medecins, ($search ? '« ' . htmlspecialchars($search) . ' »' : '') . ($typeProfil ? ($search ? ' · ' : '') . 'profil <strong>' . htmlspecialchars(medecin_profil_label($typeProfil)) . '</strong>' : '') . ($specialite ? (($search || $typeProfil) ? ' · ' : '') . 'spé. <strong>' . htmlspecialchars($specialite) . '</strong>' : '')); endif; ?>
</div>

<?php if (empty($medecins)): ?>
<div class="app-mod-empty">
    <i class="fas fa-user-md d-block"></i>
    <h5 class="mb-2">Aucun professionnel</h5>
    <p class="mb-3"><?php
        if ($search || $specialite) {
            echo 'Aucun résultat pour ces critères.';
        } elseif (!empty($auth) && $auth->estClinicienScope() && !$auth->estAdmin()) {
            echo 'Votre compte n\'est pas encore rattaché à une fiche professionnelle. Demandez à l\'administrateur de créer votre fiche dans ce module puis de vous lier dans Paramètres → Utilisateurs.';
        } else {
            echo 'Ajoutez votre premier professionnel (médecin, infirmier, laborantin…).';
        }
    ?></p>
    <?php if ($search || $specialite): ?><a href="index.php" class="btn btn-outline-secondary btn-sm me-2">Effacer</a><?php endif; ?>
    <?php if ($canCreateMedecin): ?><a href="ajouter.php" class="btn btn-primary btn-sm">Ajouter un professionnel</a><?php endif; ?>
</div>
<?php else: ?>
<div class="app-mod-table-wrap">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 mod-list-table">
            <thead>
                <tr>
                    <th>Licence</th>
                    <th>Professionnel</th>
                    <th class="d-none d-md-table-cell">Profil</th>
                    <th class="d-none d-lg-table-cell">Spécialité / service</th>
                    <th class="d-none d-lg-table-cell">Téléphone</th>
                    <th>Statut</th>
                    <th class="text-end mod-actions-cell">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($medecins as $medecin):
                $mid = (int) $medecin['id'];
                $name = htmlspecialchars($medecin['prenom'] . ' ' . $medecin['nom']);
                $pStatut = $medecin['statut'] ?? 'actif';
                $profil = $medecin['type_profil'] ?? 'medecin';
                $prefix = medecin_profil_display_prefix($profil);
            ?>
                <tr data-medecin-id="<?= $mid ?>">
                    <td><?php if ($medecin['numero_licence']): ?><code class="mod-code"><?= htmlspecialchars($medecin['numero_licence']) ?></code><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                    <td>
                        <a href="voir.php?id=<?= $mid ?>" class="mod-name-link"><?= $prefix . $name ?></a>
                    </td>
                    <td class="d-none d-md-table-cell"><span class="badge bg-light text-dark border"><?= htmlspecialchars(medecin_profil_label($profil)) ?></span></td>
                    <td class="d-none d-lg-table-cell"><?= $medecin['specialite'] ? htmlspecialchars($medecin['specialite']) : '<span class="text-muted">—</span>' ?></td>
                    <td class="d-none d-lg-table-cell"><?= $medecin['telephone'] ? htmlspecialchars($medecin['telephone']) : '<span class="text-muted">—</span>' ?></td>
                    <td><?= app_mod_badge($pStatut) ?></td>
                    <td class="text-end mod-actions-cell">
                        <?php
                        $medecinActions = [
                            ['href' => 'voir.php?id=' . $mid, 'label' => 'Voir la fiche', 'icon' => 'fa-eye'],
                        ];
                        if ($canManageMedecin) {
                            $medecinActions[] = ['href' => 'modifier.php?id=' . $mid, 'label' => 'Modifier', 'icon' => 'fa-edit'];
                            $medecinActions[] = ['divider' => true];
                            $medecinActions[] = [
                                'button' => true,
                                'label' => 'Supprimer',
                                'icon' => 'fa-trash',
                                'tone' => 'danger',
                                'class' => 'js-mod-delete-trigger',
                                'attrs' => [
                                    'data-delete-id' => (string) $mid,
                                    'data-delete-name' => $medecin['prenom'] . ' ' . $medecin['nom'],
                                ],
                            ];
                        }
                        app_mod_actions_dropdown($medecinActions);
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php app_mod_pagination($page, (int) $total_pages, ['search' => $search, 'specialite' => $specialite, 'type_profil' => $typeProfil], 'Pagination équipe'); ?>
<?php app_mod_list_count(count($medecins), (int) $total_medecins, 'professionnel(s)'); ?>
