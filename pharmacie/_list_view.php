<?php

/** Fragment liste pharmacie */

app_mod_stats([

    ['value' => $stats['total'] ?? 0, 'label' => 'Total médicaments', 'icon' => 'fa-pills'],

    ['value' => $stats['disponibles'] ?? 0, 'label' => 'Disponibles', 'icon' => 'fa-check-circle', 'mod' => 'teal'],

    ['value' => $stats['alertes_stock'] ?? 0, 'label' => 'Stock faible', 'icon' => 'fa-exclamation-triangle', 'mod' => 'amber'],

    ['value' => $stats['alertes_peremption'] ?? 0, 'label' => 'Péremption', 'icon' => 'fa-calendar-times', 'mod' => 'amber'],

], 'pharmacie-kpis');

?>



<div class="app-mod-filter">

    <form method="GET" class="row g-3 align-items-end">

        <div class="col-lg-4 col-md-6">

            <label class="form-label small text-muted mb-1">Recherche</label>

            <input type="text" class="form-control" name="search" placeholder="Nom, code…"

                   value="<?= htmlspecialchars($search) ?>">

        </div>

        <div class="col-lg-2 col-md-4">

            <label class="form-label small text-muted mb-1">Statut</label>

            <select class="form-select" name="statut">

                <option value="">Tous les statuts</option>

                <option value="disponible" <?= $statut === 'disponible' ? 'selected' : '' ?>>Disponible</option>

                <option value="rupture" <?= $statut === 'rupture' ? 'selected' : '' ?>>Rupture</option>

                <option value="perime" <?= $statut === 'perime' ? 'selected' : '' ?>>Périmé</option>

            </select>

        </div>

        <div class="col-lg-2 col-md-4">

            <label class="form-label small text-muted mb-1">Catégorie</label>

            <input type="text" class="form-control" name="categorie" placeholder="Catégorie"

                   value="<?= htmlspecialchars($categorie) ?>">

        </div>

        <div class="col-lg-4 col-md-12">

            <div class="d-flex gap-2">

                <button type="submit" class="btn btn-primary flex-grow-1"><i class="fas fa-search me-1"></i>Rechercher</button>

                <a href="index.php" class="btn btn-outline-secondary" title="Effacer"><i class="fas fa-times"></i></a>

            </div>

        </div>

    </form>

    <?php

    if ($search || $statut || $categorie):

        $parts = [];

        if ($search) {

            $parts[] = '« ' . htmlspecialchars($search) . ' »';

        }

        if ($statut) {

            $parts[] = 'statut <strong>' . htmlspecialchars($statut) . '</strong>';

        }

        if ($categorie) {

            $parts[] = 'cat. <strong>' . htmlspecialchars($categorie) . '</strong>';

        }

        app_mod_filter_active((int) $total, implode(' · ', $parts));

    endif;

    ?>

</div>



<?php if (empty($medicaments)): ?>

<div class="app-mod-empty">

    <i class="fas fa-pills d-block"></i>

    <h5 class="mb-2">Aucun médicament</h5>

    <p class="mb-3"><?= ($search || $statut || $categorie) ? 'Aucun résultat pour ces critères.' : 'Ajoutez votre premier médicament.' ?></p>

    <?php if ($search || $statut || $categorie): ?><a href="index.php" class="btn btn-outline-secondary btn-sm me-2">Effacer</a><?php endif; ?>

    <a href="ajouter.php" class="btn btn-primary btn-sm">Nouveau médicament</a>

</div>

<?php else: ?>

<div class="app-mod-table-wrap">

    <div class="table-responsive">

        <table class="table table-hover align-middle mb-0 mod-list-table">

            <thead>

                <tr>

                    <th>Code</th>

                    <th>Médicament</th>

                    <th class="d-none d-md-table-cell">Stock</th>

                    <th class="d-none d-lg-table-cell">Prix</th>

                    <th class="d-none d-lg-table-cell">Péremption</th>

                    <th>Statut</th>

                    <th class="text-end mod-actions-cell">Actions</th>

                </tr>

            </thead>

            <tbody>

            <?php foreach ($medicaments as $med):

                $mid = (int) $med['id'];

                $pStatut = $med['statut'] ?? 'disponible';

                $stockLow = $med['stock_actuel'] <= $med['stock_minimum'];

                $rowClass = $stockLow ? 'table-warning' : '';

            ?>

                <tr class="<?= $rowClass ?>" data-medicament-id="<?= $mid ?>">

                    <td><code class="mod-code"><?= htmlspecialchars($med['code_medicament']) ?></code></td>

                    <td>

                        <a href="voir.php?id=<?= $mid ?>" class="mod-name-link"><?= htmlspecialchars($med['nom_commercial']) ?></a>

                        <?php if (!empty($med['nom_generique'])): ?>

                        <div class="mod-meta"><?= htmlspecialchars($med['nom_generique']) ?></div>

                        <?php endif; ?>

                        <div class="mod-meta d-md-none">

                            Stock : <?= (int) $med['stock_actuel'] ?> / <?= (int) $med['stock_maximum'] ?>

                        </div>

                    </td>

                    <td class="d-none d-md-table-cell">

                        <span class="mod-badge mod-badge--<?= $stockLow ? 'rupture' : 'disponible' ?>">

                            <?= (int) $med['stock_actuel'] ?> / <?= (int) $med['stock_maximum'] ?>

                        </span>

                    </td>

                    <td class="d-none d-lg-table-cell text-muted"><?= number_format($med['prix_unitaire'], 0, ',', ' ') ?> FCFA</td>

                    <td class="d-none d-lg-table-cell">

                        <?php if ($med['date_peremption']):

                            $datePeremp = new DateTime($med['date_peremption']);

                            $today = new DateTime();

                            $days = $today->diff($datePeremp)->days;

                            $perempKey = $days <= 30 ? 'perime' : ($days <= 60 ? 'rupture' : 'disponible');

                        ?>

                        <span class="mod-badge mod-badge--<?= $perempKey ?>"><?= date('d/m/Y', strtotime($med['date_peremption'])) ?></span>

                        <?php else: ?>

                        <span class="text-muted">—</span>

                        <?php endif; ?>

                    </td>

                    <td><?= app_mod_badge($pStatut) ?></td>

                    <td class="text-end mod-actions-cell">

                        <?php app_mod_actions_dropdown([

                            ['href' => 'voir.php?id=' . $mid, 'label' => 'Voir la fiche', 'icon' => 'fa-eye'],

                            ['href' => 'modifier.php?id=' . $mid, 'label' => 'Modifier', 'icon' => 'fa-edit'],

                            ['href' => 'mouvement.php?id=' . $mid, 'label' => 'Mouvement stock', 'icon' => 'fa-arrows-alt'],

                            ['divider' => true],

                            ['href' => 'supprimer.php?id=' . $mid, 'label' => 'Supprimer', 'icon' => 'fa-trash', 'class' => 'text-danger',

                             'onclick' => "return confirm('Supprimer ce médicament ?')"],

                        ]); ?>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

<?php endif; ?>



<?php app_mod_pagination($page, (int) $total_pages, ['search' => $search, 'statut' => $statut, 'categorie' => $categorie], 'Pagination pharmacie'); ?>

<?php app_mod_list_count(count($medicaments), (int) $total, 'médicament(s)'); ?>

