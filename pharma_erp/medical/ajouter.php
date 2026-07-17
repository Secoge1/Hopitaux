<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';

extract(pharma_erp_context());

require_once __DIR__ . '/../../models/pharma_erp/PeMedical.php';
require_once __DIR__ . '/../../models/pharma_erp/PePharmacy.php';
require_once __DIR__ . '/../../models/pharma_erp/PeProduct.php';

$medicalModel = new PeMedical();
$pharmacyModel = new PePharmacy();
$productModel = new PeProduct();
$pharmacy = $pharmacyModel->getDefault();
$error = '';
$searchResults = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!empty($_POST['search_patient'])) {
            $searchResults = $medicalModel->searchHisPatients(trim($_POST['patient_search'] ?? ''));
        } elseif (!empty($_POST['create_rx'])) {
            if (!$pharmacy) {
                throw new RuntimeException('Officine non configurée.');
            }
            $pePatientId = null;
            if (!empty($_POST['his_patient_id'])) {
                $pePatientId = $medicalModel->linkHisPatient((int) $_POST['his_patient_id']);
            }
            $lines = [];
            $names = $_POST['line_name'] ?? [];
            $qtys = $_POST['line_qty'] ?? [];
            $productIds = $_POST['line_product_id'] ?? [];
            foreach ($names as $i => $name) {
                $name = trim((string) $name);
                if ($name === '') {
                    continue;
                }
                $lines[] = [
                    'product_id' => !empty($productIds[$i]) ? (int) $productIds[$i] : null,
                    'product_name' => $name,
                    'quantity' => max(1, (int) ($qtys[$i] ?? 1)),
                ];
            }
            if (empty($lines)) {
                throw new InvalidArgumentException('Ajoutez au moins un médicament.');
            }
            $rxId = $medicalModel->createPrescription([
                'pharmacy_id' => (int) $pharmacy['id'],
                'pe_patient_id' => $pePatientId,
                'prescriber_name' => trim($_POST['prescriber_name'] ?? ''),
                'notes' => trim($_POST['notes'] ?? ''),
                'lines' => $lines,
            ]);
            if (!$rxId) {
                throw new RuntimeException('Erreur création ordonnance.');
            }
            redirectWithMessage(pharma_erp_url('medical/'), 'Ordonnance enregistrée.', 'success');
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$products = $productModel->getAll(1, 50, '');

pharma_erp_page_start(['active' => 'medical', 'title' => 'Nouvelle ordonnance', 'icon' => 'fa-file-medical-alt']);
pharma_erp_toolbar([['href' => pharma_erp_url('medical/'), 'label' => 'Retour', 'icon' => 'fa-arrow-left', 'class' => 'btn-pharma-outline']]);
if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="pharma-pro-panel">
            <div class="pharma-pro-panel-header">Lier patient HIS</div>
            <div class="pharma-pro-panel-body">
                <form method="post" class="mb-3">
                    <input type="hidden" name="search_patient" value="1">
                    <label class="form-label">Rechercher patient</label>
                    <div class="input-group">
                        <input name="patient_search" class="form-control" placeholder="Nom, prénom ou téléphone" required>
                        <button class="btn btn-pharma-outline" type="submit"><i class="fas fa-search"></i></button>
                    </div>
                </form>
                <?php if (!empty($searchResults)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($searchResults as $p): ?>
                    <div class="list-group-item px-0">
                        <strong><?= htmlspecialchars(($p['prenom'] ?? '') . ' ' . ($p['nom'] ?? '')) ?></strong>
                        <br><small><?= htmlspecialchars($p['telephone'] ?? '') ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="pharma-pro-panel">
            <div class="pharma-pro-panel-header">Ordonnance</div>
            <div class="pharma-pro-panel-body">
                <form method="post" id="rxForm">
                    <input type="hidden" name="create_rx" value="1">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Patient HIS (ID)</label>
                            <select name="his_patient_id" class="form-select">
                                <option value="">— Optionnel —</option>
                                <?php foreach ($searchResults as $p): ?>
                                <option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars(($p['prenom'] ?? '') . ' ' . ($p['nom'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Prescripteur</label>
                            <input name="prescriber_name" class="form-control" placeholder="Dr. …">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div id="rxLines">
                        <div class="row g-2 mb-2 rx-line">
                            <div class="col-md-6">
                                <select name="line_product_id[]" class="form-select form-select-sm">
                                    <option value="">— Médicament libre —</option>
                                    <?php foreach ($products as $prod): ?>
                                    <option value="<?= (int) $prod['id'] ?>"><?= htmlspecialchars($prod['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4"><input name="line_name[]" class="form-control form-control-sm" placeholder="Nom médicament"></div>
                            <div class="col-md-2"><input name="line_qty[]" type="number" class="form-control form-control-sm" min="1" value="1"></div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-pharma-outline mb-3" onclick="addRxLine()"><i class="fas fa-plus me-1"></i> Ligne</button>
                    <div><button type="submit" class="btn btn-pharma-primary"><i class="fas fa-save me-1"></i> Enregistrer</button></div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
function addRxLine() {
    const tpl = document.querySelector('.rx-line');
    if (!tpl) return;
    document.getElementById('rxLines').appendChild(tpl.cloneNode(true));
}
</script>
<?php pharma_erp_page_end(); ?>
