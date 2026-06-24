<?php

/** Fragment liste dossiers patients */

app_mod_stats([

    ['value' => $stats['actifs'] ?? 0, 'label' => 'Dossiers actifs', 'icon' => 'fa-folder-open', 'mod' => 'teal'],

    ['value' => $stats['inactifs'] ?? 0, 'label' => 'Inactifs', 'icon' => 'fa-folder', 'mod' => 'amber'],

    ['value' => $stats['archives'] ?? 0, 'label' => 'Archivés', 'icon' => 'fa-archive'],

    ['value' => $stats['total'] ?? 0, 'label' => 'Total dossiers', 'icon' => 'fa-folder-plus'],

], 'dossiers-kpis');

?>



<div class="app-mod-filter">

    <form method="GET" class="row g-3 align-items-end">

        <div class="col-lg-4 col-md-6">

            <label class="form-label small text-muted mb-1">Recherche</label>

            <input type="text" class="form-control" name="search" placeholder="Nom, prénom, n° dossier…"

                   value="<?= htmlspecialchars($search) ?>">

        </div>

        <div class="col-lg-2 col-md-3">

            <label class="form-label small text-muted mb-1">Statut</label>

            <select class="form-select" name="statut">

                <option value="">Tous les statuts</option>

                <option value="actif" <?= $statut === 'actif' ? 'selected' : '' ?>>Actif</option>

                <option value="inactif" <?= $statut === 'inactif' ? 'selected' : '' ?>>Inactif</option>

                <option value="archive" <?= $statut === 'archive' ? 'selected' : '' ?>>Archivé</option>

            </select>

        </div>

        <div class="col-lg-2 col-md-3">

            <label class="form-label small text-muted mb-1">Priorité</label>

            <select class="form-select" name="priorite">

                <option value="">Toutes priorités</option>

                <option value="haute" <?= $priorite === 'haute' ? 'selected' : '' ?>>Haute</option>

                <option value="moyenne" <?= $priorite === 'moyenne' ? 'selected' : '' ?>>Moyenne</option>

                <option value="basse" <?= $priorite === 'basse' ? 'selected' : '' ?>>Basse</option>

            </select>

        </div>

        <div class="col-lg-4 col-md-12">

            <div class="d-flex gap-2">

                <button type="submit" class="btn btn-primary flex-grow-1"><i class="fas fa-search me-1"></i>Rechercher</button>

                <a href="index.php" class="btn btn-outline-secondary" title="Effacer"><i class="fas fa-times"></i></a>

            </div>

        </div>

    </form>

    <?php if ($search || $statut || $priorite): app_mod_filter_active((int) $totalDossiers, trim(($search ? '« ' . htmlspecialchars($search) . ' »' : '') . ($statut ? ($search ? ' · ' : '') . 'statut <strong>' . htmlspecialchars($statut) . '</strong>' : '') . ($priorite ? (($search || $statut) ? ' · ' : '') . 'priorité <strong>' . htmlspecialchars($priorite) . '</strong>' : ''))); endif; ?>

</div>



<?php if (empty($dossiers)): ?>

<div class="app-mod-empty">

    <i class="fas fa-folder-open d-block"></i>

    <?php if ($search || $statut || $priorite): ?>

    <h5 class="mb-2">Aucun résultat</h5>

    <p class="mb-3">Aucun dossier ne correspond à vos critères.</p>

    <a href="index.php" class="btn btn-outline-secondary btn-sm me-2">Effacer les filtres</a>

    <a href="nouveau_dossier.php" class="btn btn-primary btn-sm">Nouveau dossier</a>

    <?php else: ?>

    <h5 class="mb-2">Aucun dossier</h5>

    <p class="mb-3">Commencez par créer votre premier dossier patient.</p>

    <a href="nouveau_dossier.php" class="btn btn-primary btn-sm">Créer un dossier</a>

    <?php endif; ?>

</div>

<?php else: ?>

<div class="app-mod-table-wrap">

    <div class="table-responsive">

        <table class="table table-hover align-middle mb-0 mod-list-table">

            <thead>

                <tr>

                    <th>Dossier</th>

                    <th>Patient</th>

                    <th class="d-none d-md-table-cell">Âge</th>

                    <th class="d-none d-lg-table-cell">Groupe</th>

                    <th>Priorité</th>

                    <th>Statut</th>

                    <th class="d-none d-md-table-cell">Créé le</th>

                    <th class="text-end mod-actions-cell">Actions</th>

                </tr>

            </thead>

            <tbody>

            <?php foreach ($dossiers as $dossier):

                $did = (int) $dossier['id'];

                $patientId = (int) ($dossier['patient_id'] ?? 0);

                $fullName = htmlspecialchars($dossier['prenom'] . ' ' . $dossier['nom']);

                $dStatut = $dossier['statut'] ?? 'actif';

                $dPriorite = $dossier['priorite'] ?? 'moyenne';

                $age = null;

                if (!empty($dossier['date_naissance'])) {

                    $age = date_diff(date_create($dossier['date_naissance']), date_create('today'))->y;

                }

            ?>

                <tr data-dossier-id="<?= $did ?>">

                    <td>

                        <a href="voir.php?id=<?= $did ?>" class="mod-name-link">

                            <code class="mod-code">#<?= $did ?></code>

                        </a>

                        <div class="mod-meta"><?= htmlspecialchars($dossier['numero_dossier']) ?></div>

                    </td>

                    <td>

                        <?php if ($patientId): ?>

                        <a href="<?= app_url('patients/voir.php?id=' . $patientId) ?>" class="mod-name-link"><?= $fullName ?></a>

                        <?php else: ?>

                        <?= $fullName ?>

                        <?php endif; ?>

                        <?php if ($age !== null): ?>

                        <div class="mod-meta d-md-none"><?= $age ?> ans</div>

                        <?php endif; ?>

                    </td>

                    <td class="d-none d-md-table-cell text-muted"><?= $age !== null ? $age . ' ans' : '—' ?></td>

                    <td class="d-none d-lg-table-cell">

                        <?= !empty($dossier['groupe_sanguin']) ? '<code class="mod-code">' . htmlspecialchars($dossier['groupe_sanguin']) . '</code>' : '<span class="text-muted">—</span>' ?>

                    </td>

                    <td><?= app_mod_badge($dPriorite, ucfirst($dPriorite)) ?></td>

                    <td><?= app_mod_badge($dStatut) ?></td>

                    <td class="d-none d-md-table-cell text-muted small">

                        <?= !empty($dossier['date_creation']) ? date('d/m/Y H:i', strtotime($dossier['date_creation'])) : '—' ?>

                    </td>

                    <td class="text-end mod-actions-cell">

                        <?php

                        $actions = [

                            ['href' => 'voir.php?id=' . $did, 'label' => 'Voir le dossier', 'icon' => 'fa-folder-open'],

                        ];

                        if ($patientId) {

                            $actions[] = ['href' => app_url('patients/voir.php?id=' . $patientId), 'label' => 'Voir le patient', 'icon' => 'fa-user-injured'];

                        }

                        $actions[] = ['href' => 'modifier.php?id=' . $did, 'label' => 'Modifier', 'icon' => 'fa-edit'];

                        $actions[] = ['divider' => true];

                        $actions[] = ['href' => 'supprimer.php?id=' . $did, 'label' => 'Supprimer', 'icon' => 'fa-trash', 'class' => 'text-danger',

                                      'onclick' => "return confirm('Supprimer ce dossier ?')"];

                        app_mod_actions_dropdown($actions);

                        ?>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

<?php endif; ?>



<?php app_mod_pagination($page, (int) $total_pages, ['search' => $search, 'statut' => $statut, 'priorite' => $priorite], 'Pagination dossiers'); ?>

<?php app_mod_list_count(count($dossiers), (int) $totalDossiers, 'dossier(s)'); ?>

