<?php

/** Fragment liste maintenance / équipements */

app_mod_stats([

    ['value' => $stats['total_equipements'] ?? 0, 'label' => 'Total équipements', 'icon' => 'fa-cogs'],

    ['value' => $stats['equipements_disponibles'] ?? 0, 'label' => 'Disponibles', 'icon' => 'fa-check-circle', 'mod' => 'teal'],

    ['value' => $stats['equipements_en_maintenance'] ?? 0, 'label' => 'En maintenance', 'icon' => 'fa-wrench', 'mod' => 'amber'],

    ['value' => $stats['interventions_planifiees'] ?? 0, 'label' => 'Interventions planifiées', 'icon' => 'fa-calendar-check'],

], 'maintenance-kpis');

?>



<div class="app-mod-filter">

    <form method="GET" class="row g-3 align-items-end">

        <div class="col-lg-4 col-md-6">

            <label class="form-label small text-muted mb-1">Recherche</label>

            <input type="text" class="form-control" name="search" placeholder="Nom, n° série…"

                   value="<?= htmlspecialchars($search) ?>">

        </div>

        <div class="col-lg-2 col-md-4">

            <label class="form-label small text-muted mb-1">Statut</label>

            <select class="form-select" name="statut">

                <option value="">Tous les statuts</option>

                <option value="disponible" <?= $statut === 'disponible' ? 'selected' : '' ?>>Disponible</option>

                <option value="en_utilisation" <?= $statut === 'en_utilisation' ? 'selected' : '' ?>>En utilisation</option>

                <option value="en_maintenance" <?= $statut === 'en_maintenance' ? 'selected' : '' ?>>En maintenance</option>

                <option value="hors_service" <?= $statut === 'hors_service' ? 'selected' : '' ?>>Hors service</option>

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

            $parts[] = 'statut <strong>' . htmlspecialchars(str_replace('_', ' ', $statut)) . '</strong>';

        }

        if ($categorie) {

            $parts[] = 'cat. <strong>' . htmlspecialchars($categorie) . '</strong>';

        }

        app_mod_filter_active((int) $total, implode(' · ', $parts));

    endif;

    ?>

</div>



<?php if (empty($equipements)): ?>

<div class="app-mod-empty">

    <i class="fas fa-tools d-block"></i>

    <h5 class="mb-2">Aucun équipement</h5>

    <p class="mb-3"><?= ($search || $statut || $categorie) ? 'Aucun résultat pour ces critères.' : 'Ajoutez votre premier équipement.' ?></p>

    <?php if ($search || $statut || $categorie): ?><a href="index.php" class="btn btn-outline-secondary btn-sm me-2">Effacer</a><?php endif; ?>

    <a href="ajouter_equipement.php" class="btn btn-primary btn-sm">Nouvel équipement</a>

</div>

<?php else: ?>

<div class="app-mod-table-wrap">

    <div class="table-responsive">

        <table class="table table-hover align-middle mb-0 mod-list-table">

            <thead>

                <tr>

                    <th>N° Série</th>

                    <th>Équipement</th>

                    <th class="d-none d-md-table-cell">Catégorie</th>

                    <th class="d-none d-lg-table-cell">Localisation</th>

                    <th class="d-none d-lg-table-cell">Prochaine maint.</th>

                    <th>Statut</th>

                    <th class="text-end mod-actions-cell">Actions</th>

                </tr>

            </thead>

            <tbody>

            <?php foreach ($equipements as $eq):

                $eid = (int) $eq['id'];

                $pStatut = $eq['statut'] ?? 'disponible';

                $rowClass = '';

                if (!empty($eq['prochaine_maintenance'])) {

                    $dateProchaine = new DateTime($eq['prochaine_maintenance']);

                    $today = new DateTime();

                    if ($dateProchaine <= (clone $today)->modify('+7 days')) {

                        $rowClass = 'table-warning';

                    }

                }

            ?>

                <tr class="<?= $rowClass ?>" data-equipement-id="<?= $eid ?>">

                    <td><code class="mod-code"><?= htmlspecialchars($eq['numero_serie']) ?></code></td>

                    <td>

                        <a href="voir_equipement.php?id=<?= $eid ?>" class="mod-name-link"><?= htmlspecialchars($eq['nom']) ?></a>

                        <?php if (!empty($eq['date_derniere_maintenance'])): ?>

                        <div class="mod-meta d-lg-none">Dernière : <?= date('d/m/Y', strtotime($eq['date_derniere_maintenance'])) ?></div>

                        <?php endif; ?>

                    </td>

                    <td class="d-none d-md-table-cell text-muted"><?= $eq['categorie'] ? htmlspecialchars($eq['categorie']) : '—' ?></td>

                    <td class="d-none d-lg-table-cell text-muted"><?= $eq['localisation'] ? htmlspecialchars($eq['localisation']) : '—' ?></td>

                    <td class="d-none d-lg-table-cell">

                        <?php if (!empty($eq['prochaine_maintenance'])):

                            $dateProchaine = new DateTime($eq['prochaine_maintenance']);

                            $today = new DateTime();

                            $days = $today->diff($dateProchaine)->days;

                            $maintKey = $days <= 7 ? 'rupture' : ($days <= 30 ? 'en_maintenance' : 'disponible');

                        ?>

                        <span class="mod-badge mod-badge--<?= $maintKey ?>"><?= date('d/m/Y', strtotime($eq['prochaine_maintenance'])) ?></span>

                        <?php else: ?>

                        <span class="text-muted">—</span>

                        <?php endif; ?>

                    </td>

                    <td><?= app_mod_badge($pStatut, ucfirst(str_replace('_', ' ', $pStatut))) ?></td>

                    <td class="text-end mod-actions-cell">

                        <?php app_mod_actions_dropdown([

                            ['href' => 'voir_equipement.php?id=' . $eid, 'label' => 'Voir la fiche', 'icon' => 'fa-eye'],

                            ['href' => 'modifier_equipement.php?id=' . $eid, 'label' => 'Modifier', 'icon' => 'fa-edit'],

                            ['href' => 'intervention.php?equipement_id=' . $eid, 'label' => 'Maintenance', 'icon' => 'fa-wrench'],

                            ['divider' => true],

                            ['href' => 'supprimer_equipement.php?id=' . $eid, 'label' => 'Supprimer', 'icon' => 'fa-trash', 'class' => 'text-danger',

                             'onclick' => "return confirm('Supprimer cet équipement ?')"],

                        ]); ?>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

<?php endif; ?>



<?php app_mod_pagination($page, (int) $total_pages, ['search' => $search, 'statut' => $statut, 'categorie' => $categorie], 'Pagination équipements'); ?>

<?php app_mod_list_count(count($equipements), (int) $total, 'équipement(s)'); ?>

