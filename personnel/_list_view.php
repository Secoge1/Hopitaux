<?php

/** Fragment liste personnel */

app_mod_stats([

    ['value' => (int) $total, 'label' => 'Résultats (filtre)', 'icon' => 'fa-filter', 'id' => 'stat-total', 'hint' => $totalEnBase . ' fiche(s) en base (tous statuts)'],

    ['value' => (int) ($stats['actif'] ?? 0), 'label' => 'Actifs', 'icon' => 'fa-check-circle', 'mod' => 'teal', 'id' => 'stat-actif'],

    ['value' => count($stats['par_poste'] ?? []), 'label' => 'Postes distincts', 'icon' => 'fa-briefcase', 'id' => 'stat-postes'],

    ['value' => count($stats['par_departement'] ?? []), 'label' => 'Départements', 'icon' => 'fa-building', 'id' => 'stat-departements'],

], 'personnel-kpis');

?>



<div class="app-mod-filter">

    <form method="GET" class="row g-3 align-items-end">

        <div class="col-lg-3 col-md-6">

            <label class="form-label small text-muted mb-1">Recherche</label>

            <input type="text" class="form-control" name="search" placeholder="Nom, n° employé…"

                   value="<?= htmlspecialchars($search) ?>">

        </div>

        <div class="col-lg-2 col-md-4">

            <label class="form-label small text-muted mb-1">Statut</label>

            <select class="form-select" name="statut">

                <option value="" <?= $statutFilterExplicit && $statut === '' ? 'selected' : '' ?>>Tous les statuts</option>

                <option value="actif" <?= (!$statutFilterExplicit || $statut === 'actif') ? 'selected' : '' ?>>Actif</option>

                <option value="inactif" <?= $statut === 'inactif' ? 'selected' : '' ?>>Inactif</option>

                <option value="suspendu" <?= $statut === 'suspendu' ? 'selected' : '' ?>>Suspendu</option>

                <option value="licencie" <?= $statut === 'licencie' ? 'selected' : '' ?>>Licencié</option>

            </select>

        </div>

        <div class="col-lg-2 col-md-4">

            <label class="form-label small text-muted mb-1">Poste</label>

            <input type="text" class="form-control" name="poste" placeholder="Poste"

                   value="<?= htmlspecialchars($poste) ?>">

        </div>

        <div class="col-lg-2 col-md-4">

            <label class="form-label small text-muted mb-1">Département</label>

            <input type="text" class="form-control" name="departement" placeholder="Département"

                   value="<?= htmlspecialchars($departement) ?>">

        </div>

        <div class="col-lg-3 col-md-12">

            <div class="d-flex gap-2">

                <button type="submit" class="btn btn-primary flex-grow-1"><i class="fas fa-search me-1"></i>Rechercher</button>

                <a href="index.php" class="btn btn-outline-secondary" title="Effacer"><i class="fas fa-times"></i></a>

            </div>

        </div>

    </form>

    <?php

    if ($search !== '' || $statutFilterExplicit || $poste !== '' || $departement !== ''):

        $parts = [];

        if ($search !== '') {

            $parts[] = '« ' . htmlspecialchars($search) . ' »';

        }

        if ($statutFilterExplicit) {

            $parts[] = 'statut <strong>' . ($statut === '' ? 'Tous' : htmlspecialchars($statut)) . '</strong>';

        }

        if ($poste !== '') {

            $parts[] = 'poste <strong>' . htmlspecialchars($poste) . '</strong>';

        }

        if ($departement !== '') {

            $parts[] = 'dép. <strong>' . htmlspecialchars($departement) . '</strong>';

        }

        app_mod_filter_active((int) $total, implode(' · ', $parts));

    endif;

    ?>

</div>



<?php if (empty($personnel)): ?>

<div class="app-mod-empty">

    <i class="fas fa-user-tie d-block"></i>

    <h5 class="mb-2">Aucun membre</h5>

    <p class="mb-3"><?= ($search !== '' || $statutFilterExplicit || $poste !== '' || $departement !== '') ? 'Aucun résultat pour ces critères.' : 'Ajoutez votre premier membre du personnel.' ?></p>

    <?php if ($search !== '' || $statutFilterExplicit || $poste !== '' || $departement !== ''): ?><a href="index.php" class="btn btn-outline-secondary btn-sm me-2">Effacer</a><?php endif; ?>

    <a href="ajouter.php" class="btn btn-primary btn-sm">Nouveau membre</a>

</div>

<?php else: ?>

<div class="app-mod-table-wrap">

    <div class="table-responsive">

        <table class="table table-hover align-middle mb-0 mod-list-table">

            <thead>

                <tr>

                    <th>N° Employé</th>

                    <th>Membre</th>

                    <th class="d-none d-md-table-cell">Poste</th>

                    <th class="d-none d-lg-table-cell">Département</th>

                    <th class="d-none d-xl-table-cell">Embauche</th>

                    <th>Statut</th>

                    <th class="text-end mod-actions-cell">Actions</th>

                </tr>

            </thead>

            <tbody>

            <?php foreach ($personnel as $person):

                $pid = (int) $person['id'];

                $name = htmlspecialchars($person['prenom'] . ' ' . $person['nom']);

                $pStatut = $person['statut'] ?? 'actif';

            ?>

                <tr data-personnel-id="<?= $pid ?>">

                    <td><code class="mod-code"><?= htmlspecialchars($person['numero_employe']) ?></code></td>

                    <td>

                        <a href="voir.php?id=<?= $pid ?>" class="mod-name-link"><?= $name ?></a>

                        <div class="mod-meta d-md-none"><?= htmlspecialchars($person['poste']) ?></div>

                    </td>

                    <td class="d-none d-md-table-cell"><?= htmlspecialchars($person['poste']) ?></td>

                    <td class="d-none d-lg-table-cell"><?= $person['departement'] ? htmlspecialchars($person['departement']) : '<span class="text-muted">—</span>' ?></td>

                    <td class="d-none d-xl-table-cell text-muted small">

                        <?= $person['date_embauche'] ? date('d/m/Y', strtotime($person['date_embauche'])) : '—' ?>

                    </td>

                    <td><?= app_mod_badge($pStatut) ?></td>

                    <td class="text-end mod-actions-cell">

                        <?php app_mod_actions_dropdown([

                            ['href' => 'voir.php?id=' . $pid, 'label' => 'Voir la fiche', 'icon' => 'fa-eye'],

                            ['href' => 'modifier.php?id=' . $pid, 'label' => 'Modifier', 'icon' => 'fa-edit'],

                            ['divider' => true],

                            ['href' => 'supprimer.php?id=' . $pid, 'label' => 'Supprimer', 'icon' => 'fa-trash', 'class' => 'text-danger',

                             'onclick' => "return confirm('Retirer ce membre de la liste (statut inactif) ?')"],

                        ]); ?>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

<?php endif; ?>



<?php app_mod_pagination($page, (int) $total_pages, $paginationQuery, 'Pagination personnel'); ?>

<?php app_mod_list_count(count($personnel), (int) $total, 'membre(s)'); ?>

