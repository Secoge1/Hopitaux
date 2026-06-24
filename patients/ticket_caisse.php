<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('patients'));

require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../models/Medecin.php';
require_once __DIR__ . '/../models/TarifConsultation.php';
require_once __DIR__ . '/../models/Analyse.php';
require_once __DIR__ . '/../includes/staff_scope.php';

if (!StaffScope::canRegisterConsultationFromPatients()) {
    $_SESSION['flash_message'] = 'Vous n\'êtes pas autorisé à émettre un ticket caisse.';
    $_SESSION['flash_type'] = 'danger';
    header('Location: index.php');
    exit;
}

$canAnalyse = StaffScope::canRegisterAnalyseFromPatients();
$patientModel = new Patient();
$medecinModel = new Medecin();
$tarifModel = new TarifConsultation();
$analyseModel = new Analyse();

$tarifs = $tarifModel->getAll('actif');
$defaultTarif = $tarifModel->getByTypeAndSpecialite('consultation_simple', null);
$defaultPrixConsultation = $defaultTarif ? (float) $defaultTarif['prix'] : 0.0;

$typesAnalyses = $analyseModel->getTypesAnalyses();
$prixParType = $analyseModel->getPrixParType();
$firstTypeAnalyse = array_key_first($typesAnalyses) ?: 'sang';
$defaultPrixAnalyse = (float) ($prixParType[$firstTypeAnalyse] ?? 0);

$patientId = isset($_REQUEST['patient_id']) ? (int) $_REQUEST['patient_id'] : 0;
$patient = $patientId ? $patientModel->getById($patientId) : null;

if ($patientId && !$patient) {
    header('Location: index.php');
    exit;
}

$allowedTypes = ['consultation', 'analyse'];
$selectedType = trim((string) ($_GET['type'] ?? ''));
if (!in_array($selectedType, $allowedTypes, true)) {
    $selectedType = '';
}
if ($selectedType === 'analyse' && !$canAnalyse) {
    $selectedType = '';
}

$error = trim((string) ($_GET['error'] ?? ''));
$medecins = $medecinModel->listForAssignment();
$ctx = StaffScope::context();
$canAssign = StaffScope::canAssignPatientMedecin();
$isMedecin = ($ctx['role'] ?? '') === 'medecin';
$defaultMedecinId = $patient ? (int) ($patient['medecin_referent_id'] ?? 0) : 0;
if ($isMedecin && !empty($ctx['medecin_id'])) {
    $defaultMedecinId = (int) $ctx['medecin_id'];
}
$defaultMedecinSpecialite = '';
$self = null;
if ($isMedecin && !empty($ctx['medecin_id'])) {
    foreach ($medecins as $m) {
        if ((int) $m['id'] === (int) $ctx['medecin_id']) {
            $self = $m;
            $defaultMedecinSpecialite = trim((string) ($m['specialite'] ?? ''));
            break;
        }
    }
}

$backUrl = $patientId
    ? app_url('patients/voir.php?id=' . $patientId)
    : app_url('patients/index.php');

app_module_page_start([
    'active'    => 'patients',
    'title'     => 'Ticket caisse',
    'subtitle'  => $patient ? htmlspecialchars($patient['prenom'] . ' ' . $patient['nom']) : 'Sélection du type de prestation',
    'icon'      => 'fa-receipt',
    'extra_css' => ['assets/css/app-patients.css'],
]);
app_module_back_toolbar($backUrl);
app_module_flash();
?>
<style>
.tc-type-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
.tc-type-card {
    border: 2px solid #dee2e6; border-radius: 12px; padding: 1.25rem; text-align: center;
    text-decoration: none; color: inherit; background: #fff; transition: all .2s ease;
}
.tc-type-card:hover { border-color: #0d6efd; box-shadow: 0 4px 14px rgba(13,110,253,.15); color: inherit; }
.tc-type-card.is-active { border-color: #0d6efd; background: #f0f6ff; }
.tc-type-card i { font-size: 2rem; margin-bottom: .5rem; color: #0d6efd; }
.tc-type-card--lab i { color: #dc3545; }
.tc-type-card--lab.is-active { border-color: #dc3545; background: #fff5f5; }
.tc-panel { display: none; }
.tc-panel.is-visible { display: block; }
</style>

<?php if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($patient): ?>
<div class="card app-mod-form-card mb-4">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <i class="fas fa-user-injured text-primary fa-lg"></i>
            <div>
                <strong><?= htmlspecialchars($patient['prenom'] . ' ' . $patient['nom']) ?></strong>
                <span class="text-muted">— <?= htmlspecialchars($patient['numero_dossier']) ?></span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card app-mod-form-card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>Type de prestation</h5>
    </div>
    <div class="card-body">
        <p class="text-muted mb-3">Choisissez la nature du ticket à émettre pour la caisse.</p>
        <div class="tc-type-grid">
            <a href="ticket_caisse.php?patient_id=<?= (int) $patientId ?>&type=consultation"
               class="tc-type-card <?= $selectedType === 'consultation' ? 'is-active' : '' ?>">
                <i class="fas fa-stethoscope d-block"></i>
                <strong>Consultation</strong>
                <div class="small text-muted mt-1">Ticket médecin / consultation</div>
            </a>
            <?php if ($canAnalyse): ?>
            <a href="ticket_caisse.php?patient_id=<?= (int) $patientId ?>&type=analyse"
               class="tc-type-card tc-type-card--lab <?= $selectedType === 'analyse' ? 'is-active' : '' ?>">
                <i class="fas fa-flask d-block"></i>
                <strong>Analyse laboratoire</strong>
                <div class="small text-muted mt-1">Ticket examen / laboratoire</div>
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Volet consultation -->
<div class="tc-panel <?= $selectedType === 'consultation' ? 'is-visible' : '' ?>" id="panel-consultation">
    <div class="card app-mod-form-card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-user-md me-2"></i>Consultation — informations ticket</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="enregistrer_consultation.php" class="row g-3">
                <?php if ($patient): ?>
                <input type="hidden" name="patient_id" value="<?= (int) $patient['id'] ?>">
                <?php else: ?>
                <div class="col-12">
                    <label for="patient_id_consult" class="form-label">Patient *</label>
                    <input type="number" class="form-control" name="patient_id" id="patient_id_consult" required>
                </div>
                <?php endif; ?>

                <div class="col-md-6">
                    <label for="medecin_id" class="form-label">Médecin assigné *</label>
                    <?php if ($isMedecin && !empty($ctx['medecin_id'])): ?>
                    <input type="hidden" name="medecin_id" value="<?= (int) $ctx['medecin_id'] ?>">
                    <div class="form-control-plaintext"><?= htmlspecialchars(medecin_profil_format_name($self)) ?></div>
                    <?php else: ?>
                    <select class="form-select" id="medecin_id" name="medecin_id" required>
                        <option value="">Choisir un médecin…</option>
                        <?php foreach ($medecins as $m): ?>
                        <option value="<?= (int) $m['id'] ?>"
                                data-specialite="<?= htmlspecialchars((string) ($m['specialite'] ?? ''), ENT_QUOTES) ?>"
                                <?= (int) $m['id'] === $defaultMedecinId ? 'selected' : '' ?>>
                            <?= htmlspecialchars(medecin_profil_format_name($m)) ?>
                            <?= $m['specialite'] ? ' — ' . htmlspecialchars($m['specialite']) : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label for="symptomes" class="form-label">Motif / symptômes (optionnel)</label>
                    <input type="text" class="form-control" id="symptomes" name="symptomes" placeholder="Ex. fièvre, contrôle…">
                </div>

                <div class="col-md-6">
                    <label for="type_consultation" class="form-label">Type de consultation *</label>
                    <select class="form-select" id="type_consultation" name="type_consultation" required>
                        <option value="consultation_simple">Consultation simple</option>
                        <option value="consultation_specialisee">Consultation spécialisée</option>
                        <option value="urgence">Urgence</option>
                        <option value="controle">Contrôle</option>
                        <option value="__autre__">Autre (préciser)</option>
                    </select>
                    <div class="mt-2" id="wrap_type_consultation_autre" style="display: none;">
                        <label for="type_consultation_autre" class="form-label">Précisez le type *</label>
                        <input type="text" class="form-control" id="type_consultation_autre" name="type_consultation_autre"
                               placeholder="Ex. Téléconsultation, bilan…" maxlength="120" autocomplete="off">
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="prix_consultation" class="form-label">Prix consultation (<?= htmlspecialchars(function_exists('app_currency_label') ? app_currency_label() : 'FCFA') ?>) *</label>
                    <input type="number" class="form-control" id="prix_consultation" name="prix_consultation"
                           step="1" min="0" value="<?= htmlspecialchars((string) $defaultPrixConsultation) ?>" required>
                </div>

                <?php if ($canAssign): ?>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="assign_referent" id="assign_referent" value="1" checked>
                        <label class="form-check-label" for="assign_referent">Définir ce médecin comme <strong>médecin référent</strong></label>
                    </div>
                </div>
                <?php endif; ?>

                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="imprimer_ticket" id="imprimer_ticket" value="1" checked>
                        <label class="form-check-label" for="imprimer_ticket">Imprimer le <strong>ticket caisse</strong></label>
                    </div>
                </div>

                <div class="col-12 d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check me-1"></i>Enregistrer et imprimer</button>
                    <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-outline-secondary">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Volet analyse -->
<?php if ($canAnalyse): ?>
<div class="tc-panel <?= $selectedType === 'analyse' ? 'is-visible' : '' ?>" id="panel-analyse">
    <div class="card app-mod-form-card">
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0"><i class="fas fa-flask me-2"></i>Analyse laboratoire — informations ticket</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="enregistrer_analyse.php" class="row g-3">
                <?php if ($patient): ?>
                <input type="hidden" name="patient_id" value="<?= (int) $patient['id'] ?>">
                <?php else: ?>
                <div class="col-12">
                    <label for="patient_id_analyse" class="form-label">Patient *</label>
                    <input type="number" class="form-control" name="patient_id" id="patient_id_analyse" required>
                </div>
                <?php endif; ?>

                <div class="col-md-6">
                    <label for="medecin_id_analyse" class="form-label">Médecin prescripteur *</label>
                    <?php if ($isMedecin && !empty($ctx['medecin_id'])): ?>
                    <input type="hidden" name="medecin_id" value="<?= (int) $ctx['medecin_id'] ?>">
                    <div class="form-control-plaintext"><?= htmlspecialchars(medecin_profil_format_name($self)) ?></div>
                    <?php else: ?>
                    <select class="form-select" id="medecin_id_analyse" name="medecin_id" required>
                        <option value="">Choisir un médecin…</option>
                        <?php foreach ($medecins as $m): ?>
                        <option value="<?= (int) $m['id'] ?>" <?= (int) $m['id'] === $defaultMedecinId ? 'selected' : '' ?>>
                            <?= htmlspecialchars(medecin_profil_format_name($m)) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label for="type_analyse" class="form-label">Type d'analyse *</label>
                    <select class="form-select" id="type_analyse" name="type_analyse" required>
                        <?php foreach ($typesAnalyses as $code => $libelle): ?>
                        <option value="<?= htmlspecialchars($code) ?>" <?= $code === $firstTypeAnalyse ? 'selected' : '' ?>>
                            <?= htmlspecialchars($libelle) ?>
                        </option>
                        <?php endforeach; ?>
                        <option value="__autre__">Autre (préciser)</option>
                    </select>
                    <div class="mt-2" id="wrap_type_analyse_autre" style="display: none;">
                        <label for="type_analyse_autre" class="form-label">Précisez le type *</label>
                        <input type="text" class="form-control" id="type_analyse_autre" name="type_analyse_autre"
                               placeholder="Ex. sérologie, bilan hormonal…" maxlength="120" autocomplete="off">
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="prix_analyse" class="form-label">Prix analyse (<?= htmlspecialchars(function_exists('app_currency_label') ? app_currency_label() : 'FCFA') ?>) *</label>
                    <input type="number" class="form-control" id="prix_analyse" name="prix_analyse"
                           step="1" min="0" value="<?= htmlspecialchars((string) $defaultPrixAnalyse) ?>" required>
                </div>

                <div class="col-md-6">
                    <label for="description_analyse" class="form-label">Motif / indication (optionnel)</label>
                    <input type="text" class="form-control" id="description_analyse" name="description" placeholder="Ex. bilan, contrôle glycémie…">
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="imprimer_ticket" value="1" checked>
                        <label class="form-check-label">Imprimer le <strong>ticket caisse</strong></label>
                    </div>
                </div>

                <div class="col-12 d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-danger"><i class="fas fa-check me-1"></i>Enregistrer et imprimer</button>
                    <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-outline-secondary">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    const tarifs = <?= json_encode($tarifs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const prixParType = <?= json_encode($prixParType, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const defaultSpecialite = <?= json_encode($defaultMedecinSpecialite, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

    const typeSelect = document.getElementById('type_consultation');
    const prixConsult = document.getElementById('prix_consultation');
    const medecinSelect = document.getElementById('medecin_id');
    const wrapTypeConsultAutre = document.getElementById('wrap_type_consultation_autre');
    const typeConsultAutreInput = document.getElementById('type_consultation_autre');

    function toggleTypeConsultationAutre() {
        const isAutre = typeSelect && typeSelect.value === '__autre__';
        if (wrapTypeConsultAutre) {
            wrapTypeConsultAutre.style.display = isAutre ? 'block' : 'none';
        }
        if (typeConsultAutreInput) {
            typeConsultAutreInput.required = !!isAutre;
            if (!isAutre) typeConsultAutreInput.value = '';
        }
    }

    function resolveTarif(type, specialite) {
        const matches = tarifs.filter(function (t) { return t.type_consultation === type && t.statut === 'actif'; });
        if (!matches.length) return null;
        if (specialite) {
            const bySpec = matches.find(function (t) {
                return (t.specialite || '').toLowerCase() === specialite.toLowerCase();
            });
            if (bySpec) return bySpec;
        }
        return matches.find(function (t) { return !t.specialite; }) || matches[0];
    }

    function applyTarifConsultation() {
        if (!typeSelect || !prixConsult) return;
        toggleTypeConsultationAutre();
        if (typeSelect.value === '__autre__') return;
        let specialite = defaultSpecialite;
        if (medecinSelect && medecinSelect.selectedOptions.length) {
            specialite = medecinSelect.selectedOptions[0].dataset.specialite || '';
        }
        const tarif = resolveTarif(typeSelect.value, specialite);
        if (tarif && parseFloat(tarif.prix) > 0) {
            prixConsult.value = parseFloat(tarif.prix).toFixed(0);
        }
    }

    typeSelect?.addEventListener('change', applyTarifConsultation);
    medecinSelect?.addEventListener('change', applyTarifConsultation);
    applyTarifConsultation();

    const typeAnalyse = document.getElementById('type_analyse');
    const prixAnalyse = document.getElementById('prix_analyse');
    const wrapTypeAnalyseAutre = document.getElementById('wrap_type_analyse_autre');
    const typeAnalyseAutreInput = document.getElementById('type_analyse_autre');

    function toggleTypeAnalyseAutre() {
        const isAutre = typeAnalyse && typeAnalyse.value === '__autre__';
        if (wrapTypeAnalyseAutre) {
            wrapTypeAnalyseAutre.style.display = isAutre ? 'block' : 'none';
        }
        if (typeAnalyseAutreInput) {
            typeAnalyseAutreInput.required = !!isAutre;
            if (!isAutre) typeAnalyseAutreInput.value = '';
        }
    }

    function applyTarifAnalyse() {
        if (!typeAnalyse || !prixAnalyse) return;
        toggleTypeAnalyseAutre();
        if (typeAnalyse.value === '__autre__') return;
        const p = prixParType[typeAnalyse.value];
        if (p !== undefined && parseFloat(p) > 0) {
            prixAnalyse.value = parseFloat(p).toFixed(0);
        }
    }
    typeAnalyse?.addEventListener('change', applyTarifAnalyse);
    applyTarifAnalyse();

    document.querySelectorAll('#panel-consultation form, #panel-analyse form').forEach(function (form) {
        form.addEventListener('submit', function () {
            const btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Enregistrement…';
            }
        });
    });
})();
</script>

<?php app_module_page_end(); ?>
