<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/module_guard.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
module_require_write('finances');
extract(app_module_context('finances'));

require_once __DIR__ . '/../models/Finances.php';

$financesModel = new Finances();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: index.php");
    exit;
}

$message = '';
$error = '';

// Récupérer l'écriture existante
try {
    $ecriture = $financesModel->getEcritureById($id);
    if (!$ecriture) {
        header("Location: index.php");
        exit;
    }
} catch (Exception $e) {
    die("Erreur: " . $e->getMessage());
}

// Récupérer les comptes pour les listes déroulantes
try {
    $comptes = $financesModel->getComptes(1, 1000);
} catch (Exception $e) {
    $comptes = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Vérifier si l'écriture est validée - si oui, on ne peut modifier que certains champs
        $peut_modifier = !$ecriture['valide'] || $auth->peutEcrireFinances();

        if (!$peut_modifier) {
            $error = 'Cette écriture est validée et ne peut être modifiée que par un administrateur ou un comptable.';
        } else {
            $data = [
                'date_ecriture' => $_POST['date_ecriture'],
                'compte_debit_id' => $_POST['compte_debit_id'],
                'compte_credit_id' => $_POST['compte_credit_id'],
                'montant' => $_POST['montant'],
                'libelle' => $_POST['libelle'],
                'reference' => $_POST['reference'] ?: null,
                'valide' => isset($_POST['valide']) ? 1 : 0
            ];

            if ($financesModel->updateEcriture($id, $data)) {
                header("Location: voir_ecriture.php?id=$id&success=1");
                exit;
            } else {
                $error = "Erreur lors de la modification de l'écriture.";
            }
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
    
    // Recharger l'écriture après modification
    try {
        $ecriture = $financesModel->getEcritureById($id);
    } catch (Exception $e) {
        // Ignorer
    }
}

?>
<?php
app_module_page_start([
    'active'    => 'finances',
    'title'     => 'Modifier Écriture',
    'subtitle'  => 'Modification comptable',
    'icon'      => 'fa-calculator',
    'extra_css' => ['assets/css/app-finances.css'],
]);
app_module_back_toolbar(app_url('finances/voir_ecriture.php?id=' . $id), 'Retour au détail');
app_module_flash();
?>
<?php if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<div class="fin-panel">
    <div class="fin-panel-head">
        <h2><i class="fas fa-book me-2"></i>Écriture comptable</h2>
        <span class="mod-badge mod-badge--<?= $ecriture['valide'] ? 'terminee' : 'en_attente' ?>">
            <?= $ecriture['valide'] ? 'Validée' : 'En attente' ?>
        </span>
    </div>
    <div class="fin-panel-body fin-form-body">
        <form method="POST" class="row g-3">
                    <div class="col-md-4">
                        <label for="date_ecriture" class="form-label">Date de l'Écriture *</label>
                        <input type="date" class="form-control" id="date_ecriture" name="date_ecriture" 
                               value="<?php echo htmlspecialchars($ecriture['date_ecriture']); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="compte_debit_id" class="form-label">Compte Débit *</label>
                        <select class="form-select" id="compte_debit_id" name="compte_debit_id" required>
                            <option value="">Choisir un compte...</option>
                            <?php foreach ($comptes as $compte):
                                $lib = $compte['libelle'] ?? $compte['nom_compte'] ?? '';
                            ?>
                                <option value="<?php echo $compte['id']; ?>" 
                                        <?php echo $compte['id'] == $ecriture['compte_debit_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($compte['numero_compte']); ?> - 
                                    <?php echo htmlspecialchars($lib); ?>
                                    (<?php echo ucfirst($compte['type_compte']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="compte_credit_id" class="form-label">Compte Crédit *</label>
                        <select class="form-select" id="compte_credit_id" name="compte_credit_id" required>
                            <option value="">Choisir un compte...</option>
                            <?php foreach ($comptes as $compte):
                                $lib = $compte['libelle'] ?? $compte['nom_compte'] ?? '';
                            ?>
                                <option value="<?php echo $compte['id']; ?>" 
                                        <?php echo $compte['id'] == $ecriture['compte_credit_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($compte['numero_compte']); ?> - 
                                    <?php echo htmlspecialchars($lib); ?>
                                    (<?php echo ucfirst($compte['type_compte']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="montant" class="form-label">Montant (FCFA) *</label>
                        <input type="number" class="form-control" id="montant" name="montant" 
                               step="0.01" value="<?php echo htmlspecialchars($ecriture['montant']); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="reference" class="form-label">Référence</label>
                        <input type="text" class="form-control" id="reference" name="reference" 
                               value="<?php echo htmlspecialchars($ecriture['reference'] ?? ''); ?>" 
                               placeholder="Ex: FACT-2024-001">
                    </div>

                    <div class="col-12">
                        <label for="libelle" class="form-label">Libellé *</label>
                        <textarea class="form-control" id="libelle" name="libelle" rows="3" required 
                                  placeholder="Description de l'écriture..."><?php echo htmlspecialchars($ecriture['libelle']); ?></textarea>
                    </div>

                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="valide" name="valide" value="1"
                                   <?php echo $ecriture['valide'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="valide">
                                Valider cette écriture
                            </label>
                        </div>
                        <small class="text-muted">
                            <?php if ($ecriture['valide']): ?>
                                L'écriture est actuellement validée. Décocher pour invalider.
                            <?php else: ?>
                                Si coché, l'écriture sera validée et les soldes des comptes seront mis à jour.
                            <?php endif; ?>
                        </small>
                    </div>

                    <div class="col-12">
                        <hr>
                        <div class="d-flex justify-content-between">
                            <a href="voir_ecriture.php?id=<?php echo $id; ?>" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Annuler
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Enregistrer les Modifications
                            </button>
                        </div>
                    </div>
                </form>
    </div>
</div>

<div class="fin-panel mt-4">
    <div class="fin-panel-head fin-panel-head--slate">
        <h2><i class="fas fa-info-circle me-2"></i>Informations</h2>
    </div>
    <div class="fin-panel-body">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="fin-detail-label">Numéro d'écriture</div>
                <div class="fin-detail-val"><?php echo htmlspecialchars($ecriture['numero_ecriture']); ?></div>
            </div>
            <div class="col-md-6">
                <div class="fin-detail-label">Créée le</div>
                <div class="fin-detail-val text-muted"><?php echo date('d/m/Y H:i', strtotime($ecriture['date_creation'])); ?></div>
            </div>
            <?php if ($ecriture['valide'] && isset($ecriture['date_validation'])): ?>
            <div class="col-md-6">
                <div class="fin-detail-label">Validée le</div>
                <div class="fin-detail-val text-muted"><?php echo date('d/m/Y H:i', strtotime($ecriture['date_validation'])); ?></div>
            </div>
            <?php endif; ?>
            <?php if ($ecriture['cree_par_nom']): ?>
            <div class="col-md-6">
                <div class="fin-detail-label">Créée par</div>
                <div class="fin-detail-val text-muted"><?php echo htmlspecialchars($ecriture['cree_par_nom']); ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php app_module_page_end(); ?>
