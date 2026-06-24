<?php

/** Fragment liste assurances */

app_mod_stats([

    ['value' => $stats['assurances_actives'] ?? 0, 'label' => 'Assurances actives', 'icon' => 'fa-shield-alt', 'mod' => 'teal'],

    ['value' => $stats['contrats_actifs'] ?? 0, 'label' => 'Contrats actifs', 'icon' => 'fa-file-contract'],

    ['value' => $stats['remboursements_en_attente'] ?? 0, 'label' => 'Remb. en attente', 'icon' => 'fa-clock', 'mod' => 'amber'],

    ['value' => number_format($stats['montant_rembourse_total'] ?? 0, 0, ',', ' '), 'label' => 'Remboursé (FCFA)', 'icon' => 'fa-money-bill-wave'],

], 'assurances-kpis');

?>



<div class="app-mod-filter">

    <form method="GET" class="row g-3 align-items-end">

        <div class="col-lg-5 col-md-6">

            <label class="form-label small text-muted mb-1">Recherche</label>

            <input type="text" class="form-control" name="search" placeholder="Nom, type, contact…"

                   value="<?= htmlspecialchars($search) ?>">

        </div>

        <div class="col-lg-3 col-md-4">

            <label class="form-label small text-muted mb-1">Statut</label>

            <select class="form-select" name="statut">

                <option value="">Tous les statuts</option>

                <option value="actif" <?= $statut === 'actif' ? 'selected' : '' ?>>Actif</option>

                <option value="inactif" <?= $statut === 'inactif' ? 'selected' : '' ?>>Inactif</option>

            </select>

        </div>

        <div class="col-lg-4 col-md-12">

            <div class="d-flex gap-2">

                <button type="submit" class="btn btn-primary flex-grow-1"><i class="fas fa-search me-1"></i>Rechercher</button>

                <a href="index.php" class="btn btn-outline-secondary" title="Effacer"><i class="fas fa-times"></i></a>

            </div>

        </div>

    </form>

    <?php if ($search || $statut): app_mod_filter_active((int) $total, trim(($search ? '« ' . htmlspecialchars($search) . ' »' : '') . ($statut ? ($search ? ' · ' : '') . 'statut <strong>' . htmlspecialchars($statut) . '</strong>' : ''))); endif; ?>

</div>



<?php if (empty($assurances)): ?>

<div class="app-mod-empty">

    <i class="fas fa-shield-alt d-block"></i>

    <h5 class="mb-2">Aucune assurance</h5>

    <p class="mb-3"><?= ($search || $statut) ? 'Aucun résultat pour ces critères.' : 'Ajoutez votre première assurance.' ?></p>

    <?php if ($search || $statut): ?><a href="index.php" class="btn btn-outline-secondary btn-sm me-2">Effacer</a><?php endif; ?>

    <a href="ajouter.php" class="btn btn-primary btn-sm">Nouvelle assurance</a>

</div>

<?php else: ?>

<div class="app-mod-table-wrap">

    <div class="table-responsive">

        <table class="table table-hover align-middle mb-0 mod-list-table">

            <thead>

                <tr>

                    <th>Assurance</th>

                    <th class="d-none d-md-table-cell">Type</th>

                    <th class="d-none d-lg-table-cell">Taux</th>

                    <th class="d-none d-lg-table-cell">Contact</th>

                    <th>Statut</th>

                    <th class="text-end mod-actions-cell">Actions</th>

                </tr>

            </thead>

            <tbody>

            <?php foreach ($assurances as $assurance):

                $aid = (int) $assurance['id'];

                $pStatut = $assurance['statut'] ?? 'actif';

            ?>

                <tr data-assurance-id="<?= $aid ?>">

                    <td>

                        <a href="voir.php?id=<?= $aid ?>" class="mod-name-link"><?= htmlspecialchars($assurance['nom']) ?></a>

                    </td>

                    <td class="d-none d-md-table-cell"><?= app_mod_badge($assurance['type'], ucfirst($assurance['type'])) ?></td>

                    <td class="d-none d-lg-table-cell"><?= number_format($assurance['taux_remboursement'], 2, ',', ' ') ?> %</td>

                    <td class="d-none d-lg-table-cell text-muted small">

                        <?php if (!empty($assurance['telephone'])): ?>

                        <?= htmlspecialchars($assurance['telephone']) ?>

                        <?php elseif (!empty($assurance['email'])): ?>

                        <?= htmlspecialchars($assurance['email']) ?>

                        <?php else: ?>

                        <span class="text-muted">—</span>

                        <?php endif; ?>

                    </td>

                    <td><?= app_mod_badge($pStatut) ?></td>

                    <td class="text-end mod-actions-cell">

                        <?php app_mod_actions_dropdown([

                            ['href' => 'voir.php?id=' . $aid, 'label' => 'Voir la fiche', 'icon' => 'fa-eye'],

                            ['href' => 'modifier.php?id=' . $aid, 'label' => 'Modifier', 'icon' => 'fa-edit'],

                            ['href' => 'contrats.php?assurance_id=' . $aid, 'label' => 'Contrats', 'icon' => 'fa-file-contract'],

                            ['divider' => true],

                            ['href' => 'supprimer.php?id=' . $aid, 'label' => 'Supprimer', 'icon' => 'fa-trash', 'class' => 'text-danger',

                             'onclick' => "return confirm('Supprimer cette assurance ?')"],

                        ]); ?>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

<?php endif; ?>



<?php app_mod_pagination($page, (int) $total_pages, ['search' => $search, 'statut' => $statut], 'Pagination assurances'); ?>

<?php app_mod_list_count(count($assurances), (int) $total, 'assurance(s)'); ?>

