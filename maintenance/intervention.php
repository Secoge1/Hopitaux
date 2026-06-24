<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('maintenance'));

require_once __DIR__ . '/../models/Maintenance.php';

$maintenanceModel = new Maintenance();

$equipement_id = isset($_GET['equipement_id']) ? (int)$_GET['equipement_id'] : 0;

if (!$equipement_id) {
    header("Location: index.php");
    exit;
}

$message = '';
$error = '';

// Récupérer l'équipement
try {
    $equipement = $maintenanceModel->getEquipementById($equipement_id);
    if (!$equipement) {
        header("Location: index.php");
        exit;
    }
    
    // Récupérer les interventions
    $interventions = $maintenanceModel->getInterventions($equipement_id);
} catch (Exception $e) {
    die("Erreur: " . $e->getMessage());
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'equipement_id' => $equipement_id,
            'type_intervention' => $_POST['type_intervention'],
            'date_intervention' => $_POST['date_intervention'],
            'technicien' => $_POST['technicien'] ?: null,
            'cout' => $_POST['cout'] ?: 0.00,
            'description' => $_POST['description'],
            'resultat' => $_POST['resultat'] ?: null,
            'statut' => $_POST['statut'] ?? 'planifiee',
            'prochaine_intervention' => $_POST['prochaine_intervention'] ?: null,
            'cree_par' => $auth->getUtilisateur()['id']
        ];

        if ($maintenanceModel->createIntervention($data)) {
            $message = "Intervention créée avec succès !";
            // Recharger les interventions
            $interventions = $maintenanceModel->getInterventions($equipement_id);
            // Recharger l'équipement pour mettre à jour les dates
            $equipement = $maintenanceModel->getEquipementById($equipement_id);
        } else {
            $error = "Erreur lors de la création de l'intervention.";
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

?>
<?php
$typeLabels = [
    'preventive'  => 'Préventive',
    'corrective'  => 'Corrective',
    'reparation'  => 'Réparation',
    'calibrage'   => 'Calibrage',
    'autre'       => 'Autre',
];
$statutLabels = [
    'planifiee' => 'Planifiée',
    'en_cours'  => 'En cours',
    'terminee'  => 'Terminée',
];
$interventionCount = count($interventions);

app_module_page_start([
    'active'    => 'maintenance',
    'title'     => 'Interventions de Maintenance',
    'subtitle'  => $equipement['nom'] . ' - ' . $equipement['numero_serie'],
    'icon'      => 'fa-tools',
    'extra_css' => ['assets/css/app-maintenance.css'],
]);
app_module_back_toolbar(app_url('maintenance/index.php'), 'Retour à la liste', [
    ['href' => app_url('maintenance/voir_equipement.php?id=' . $equipement_id), 'label' => 'Fiche équipement', 'icon' => 'fa-eye', 'class' => 'btn-outline-secondary'],
    ['href' => app_url('maintenance/modifier_equipement.php?id=' . $equipement_id), 'label' => 'Modifier équipement', 'icon' => 'fa-edit', 'class' => 'btn-outline-primary'],
]);
app_module_flash();
if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="fas fa-check-circle me-2"></i>Intervention enregistrée avec succès.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif;
if (isset($_GET['deleted'])): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="fas fa-check-circle me-2"></i>Intervention supprimée.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif;
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="maint-sidebar-panel">
            <div class="maint-panel-head">
                <h2><i class="fas fa-info-circle me-2"></i>Équipement</h2>
            </div>
            <div class="maint-panel-body">
                <dl class="maint-info-list">
                    <div class="maint-info-row">
                        <dt>Nom</dt>
                        <dd><?= htmlspecialchars($equipement['nom']) ?></dd>
                    </div>
                    <div class="maint-info-row">
                        <dt>N° série</dt>
                        <dd><code class="mod-code"><?= htmlspecialchars($equipement['numero_serie']) ?></code></dd>
                    </div>
                    <?php if (!empty($equipement['categorie'])): ?>
                    <div class="maint-info-row">
                        <dt>Catégorie</dt>
                        <dd><?= htmlspecialchars($equipement['categorie']) ?></dd>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($equipement['localisation'])): ?>
                    <div class="maint-info-row">
                        <dt>Localisation</dt>
                        <dd><?= htmlspecialchars($equipement['localisation']) ?></dd>
                    </div>
                    <?php endif; ?>
                    <div class="maint-info-row">
                        <dt>Statut</dt>
                        <dd><?= app_mod_badge($equipement['statut'], ucfirst(str_replace('_', ' ', $equipement['statut']))) ?></dd>
                    </div>
                    <?php if (!empty($equipement['date_derniere_maintenance'])): ?>
                    <div class="maint-info-row">
                        <dt>Dernière maint.</dt>
                        <dd><?= date('d/m/Y', strtotime($equipement['date_derniere_maintenance'])) ?></dd>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($equipement['prochaine_maintenance'])):
                        $dateProchaine = new DateTime($equipement['prochaine_maintenance']);
                        $today = new DateTime();
                        $days = $today->diff($dateProchaine)->days;
                        $maintKey = $days <= 7 ? 'rupture' : ($days <= 30 ? 'en_maintenance' : 'disponible');
                    ?>
                    <div class="maint-info-row">
                        <dt>Prochaine maint.</dt>
                        <dd><span class="mod-badge mod-badge--<?= $maintKey ?>"><?= date('d/m/Y', strtotime($equipement['prochaine_maintenance'])) ?></span></dd>
                    </div>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="maint-panel mb-4">
            <div class="maint-panel-head">
                <h2><i class="fas fa-plus-circle me-2"></i>Nouvelle intervention</h2>
            </div>
            <div class="maint-panel-body">
                        <form method="POST" class="row g-3">
                            <div class="col-md-6">
                                <label for="type_intervention" class="form-label">Type d'Intervention *</label>
                                <select class="form-select" id="type_intervention" name="type_intervention" required>
                                    <option value="">Choisir...</option>
                                    <option value="preventive">Préventive</option>
                                    <option value="corrective">Corrective</option>
                                    <option value="reparation">Réparation</option>
                                    <option value="calibrage">Calibrage</option>
                                    <option value="autre">Autre</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="date_intervention" class="form-label">Date d'Intervention *</label>
                                <input type="date" class="form-control" id="date_intervention" name="date_intervention" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="technicien" class="form-label">Technicien</label>
                                <input type="text" class="form-control" id="technicien" name="technicien" placeholder="Nom du technicien">
                            </div>

                            <div class="col-md-6">
                                <label for="cout" class="form-label">Coût (FCFA)</label>
                                <input type="number" class="form-control" id="cout" name="cout" step="0.01" min="0" value="0">
                            </div>

                            <div class="col-md-6">
                                <label for="statut" class="form-label">Statut</label>
                                <select class="form-select" id="statut" name="statut">
                                    <option value="planifiee">Planifiée</option>
                                    <option value="en_cours">En Cours</option>
                                    <option value="terminee">Terminée</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="prochaine_intervention" class="form-label">Prochaine Intervention</label>
                                <input type="date" class="form-control" id="prochaine_intervention" name="prochaine_intervention">
                            </div>

                            <div class="col-12">
                                <label for="description" class="form-label">Description *</label>
                                <textarea class="form-control" id="description" name="description" rows="3" required placeholder="Décrire l'intervention..."></textarea>
                            </div>

                            <div class="col-12">
                                <label for="resultat" class="form-label">Résultat</label>
                                <textarea class="form-control" id="resultat" name="resultat" rows="2" placeholder="Résultat de l'intervention..."></textarea>
                            </div>

                            <div class="col-12">
                                <hr>
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Enregistrer l'Intervention
                                    </button>
                                </div>
                            </div>
                        </form>
            </div>
        </div>

        <div class="maint-panel">
            <div class="maint-panel-head">
                <h2><i class="fas fa-list me-2"></i>Historique des interventions</h2>
                <span class="maint-count-badge">
                    <i class="fas fa-wrench"></i>
                    <span><strong><?= (int) $interventionCount ?></strong> intervention<?= $interventionCount > 1 ? 's' : '' ?></span>
                </span>
            </div>
            <div class="maint-panel-body">
                <?php if (empty($interventions)): ?>
                <div class="app-mod-empty">
                    <i class="fas fa-clipboard-list d-block"></i>
                    <h5 class="mb-2">Aucune intervention</h5>
                    <p class="mb-0">Enregistrez la première intervention via le formulaire ci-dessus.</p>
                </div>
                <?php else: ?>
                <div class="app-mod-table-wrap">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 mod-list-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th class="d-none d-md-table-cell">Technicien</th>
                                            <th class="d-none d-lg-table-cell">Coût</th>
                                            <th>Statut</th>
                                            <th class="d-none d-xl-table-cell">Description</th>
                                            <th class="text-end mod-actions-cell">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($interventions as $intervention):
                                            $iid = (int) $intervention['id'];
                                            $istatut = $intervention['statut'] ?? 'planifiee';
                                            $itype = (string) ($intervention['type_intervention'] ?? 'autre');
                                            $actions = [
                                                ['href' => 'modifier_intervention.php?id=' . $iid, 'label' => 'Modifier', 'icon' => 'fa-edit', 'tone' => 'warning'],
                                            ];
                                            if ($istatut !== 'terminee') {
                                                $actions[] = [
                                                    'form' => [
                                                        'action' => 'actions_intervention.php',
                                                        'fields' => [
                                                            'id' => $iid,
                                                            'equipement_id' => $equipement_id,
                                                            'action' => 'terminer',
                                                        ],
                                                        'confirm' => 'Marquer cette intervention comme terminée ?',
                                                    ],
                                                    'label' => 'Marquer terminée',
                                                    'icon' => 'fa-check-circle',
                                                    'tone' => 'success',
                                                ];
                                            }
                                            if ($istatut === 'planifiee') {
                                                $actions[] = [
                                                    'form' => [
                                                        'action' => 'actions_intervention.php',
                                                        'fields' => [
                                                            'id' => $iid,
                                                            'equipement_id' => $equipement_id,
                                                            'action' => 'en_cours',
                                                        ],
                                                    ],
                                                    'label' => 'Passer en cours',
                                                    'icon' => 'fa-play-circle',
                                                    'tone' => 'primary',
                                                ];
                                            }
                                            $actions[] = ['divider' => true];
                                            $actions[] = [
                                                'href' => 'supprimer_intervention.php?id=' . $iid,
                                                'label' => 'Supprimer',
                                                'icon' => 'fa-trash',
                                                'tone' => 'danger',
                                            ];
                                        ?>
                                        <tr>
                                            <td class="text-nowrap"><?= date('d/m/Y', strtotime($intervention['date_intervention'])) ?></td>
                                            <td><?= app_mod_badge($itype, $typeLabels[$itype] ?? ucfirst($itype)) ?></td>
                                            <td class="d-none d-md-table-cell"><?= htmlspecialchars($intervention['technicien'] ?? '—') ?></td>
                                            <td class="d-none d-lg-table-cell text-nowrap">
                                                <span class="maint-cout"><?= number_format((float) $intervention['cout'], 0, ',', ' ') ?></span>
                                                <small class="text-muted d-block">FCFA</small>
                                            </td>
                                            <td><?= app_mod_badge($istatut, $statutLabels[$istatut] ?? ucfirst(str_replace('_', ' ', $istatut))) ?></td>
                                            <td class="d-none d-xl-table-cell">
                                                <?php
                                                $desc = $intervention['description'] ?? '';
                                                $len = function_exists('mb_strlen') ? mb_strlen($desc, 'UTF-8') : strlen($desc);
                                                $short = $len > 50
                                                    ? (function_exists('mb_substr') ? mb_substr($desc, 0, 47, 'UTF-8') : substr($desc, 0, 47)) . '…'
                                                    : $desc;
                                                ?>
                                                <span class="maint-desc" title="<?= htmlspecialchars($desc) ?>"><?= htmlspecialchars($short) ?></span>
                                            </td>
                                            <td class="text-end mod-actions-cell">
                                                <?php app_mod_actions_dropdown($actions); ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                        </table>
                    </div>
                </div>
                <?php app_mod_list_count($interventionCount, $interventionCount, 'intervention(s)'); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php app_module_page_end(); ?>
