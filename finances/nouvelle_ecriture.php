<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/module_guard.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
module_require_write('finances');
extract(app_module_context('finances'));

require_once __DIR__ . '/../models/Finances.php';

$financesModel = new Finances();

$message = '';
$error = '';

$prefillDebit = isset($_GET['compte_debit_id']) ? (int) $_GET['compte_debit_id'] : 0;
$prefillCredit = isset($_GET['compte_credit_id']) ? (int) $_GET['compte_credit_id'] : 0;

// Récupérer les comptes pour les listes déroulantes
try {
    $comptes = $financesModel->getComptes(1, 1000);
} catch (Exception $e) {
    $comptes = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'date_ecriture' => $_POST['date_ecriture'],
            'compte_debit_id' => $_POST['compte_debit_id'],
            'compte_credit_id' => $_POST['compte_credit_id'],
            'montant' => $_POST['montant'],
            'libelle' => $_POST['libelle'],
            'reference' => $_POST['reference'] ?: null,
            'valide' => isset($_POST['valide']) ? 1 : 0,
            'cree_par' => $auth->getUtilisateur()['id']
        ];

        if ($financesModel->createEcriture($data)) {
            header("Location: index.php?success=1");
            exit;
        } else {
            $error = "Erreur lors de la création de l'écriture.";
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

?>
<?php
app_module_page_start([
    'active'    => 'finances',
    'title'     => 'Nouvelle Écriture Comptable',
    'subtitle'  => 'Saisie comptable',
    'icon'      => 'fa-calculator',
    'extra_css' => ['assets/css/app-finances.css'],
]);
app_module_back_toolbar(app_url('finances/index.php'), 'Retour à la liste');
app_module_flash();
?>
<?php if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<div class="fin-panel">
    <div class="fin-panel-head">
        <h2><i class="fas fa-book me-2"></i>Écriture comptable</h2>
    </div>
    <div class="fin-panel-body fin-form-body">
        <form method="POST" class="row g-3">
                    <div class="col-md-4">
                        <label for="date_ecriture" class="form-label">Date de l'Écriture *</label>
                        <input type="date" class="form-control" id="date_ecriture" name="date_ecriture" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="compte_debit_id" class="form-label">Compte Débit *</label>
                        <select class="form-select" id="compte_debit_id" name="compte_debit_id" required>
                            <option value="">Choisir un compte...</option>
                            <?php foreach ($comptes as $compte):
                                $lib = $compte['libelle'] ?? $compte['nom_compte'] ?? '';
                            ?>
                                <option value="<?php echo $compte['id']; ?>"<?= (int) $compte['id'] === $prefillDebit ? ' selected' : '' ?>>
                                    <?php echo htmlspecialchars($compte['numero_compte']); ?> - 
                                    <?php echo htmlspecialchars($lib); ?>
                                    (<?php echo ucfirst($compte['type_compte']); ?>)
                                </option>
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
                                <option value="<?php echo $compte['id']; ?>"<?= (int) $compte['id'] === $prefillCredit ? ' selected' : '' ?>>
                                    <?php echo htmlspecialchars($compte['numero_compte']); ?> - 
                                    <?php echo htmlspecialchars($lib); ?>
                                    (<?php echo ucfirst($compte['type_compte']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="montant" class="form-label">Montant (FCFA) *</label>
                        <input type="number" class="form-control" id="montant" name="montant" step="0.01" required>
                    </div>

                    <div class="col-md-6">
                        <label for="reference" class="form-label">Référence</label>
                        <input type="text" class="form-control" id="reference" name="reference" placeholder="Ex: FACT-2024-001">
                    </div>

                    <div class="col-12">
                        <label for="libelle" class="form-label">Libellé *</label>
                        <textarea class="form-control" id="libelle" name="libelle" rows="3" required placeholder="Description de l'écriture..."></textarea>
                    </div>

                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="valide" name="valide" value="1">
                            <label class="form-check-label" for="valide">
                                Valider immédiatement cette écriture
                            </label>
                        </div>
                        <small class="text-muted">Si coché, l'écriture sera validée et les soldes des comptes seront mis à jour</small>
                    </div>

                    <div class="col-12">
                        <hr>
                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Annuler
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Enregistrer
                            </button>
                        </div>
                    </div>
                </form>
    </div>
</div>
<?php app_module_page_end(); ?>
