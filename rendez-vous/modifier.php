<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('rdv'));

require_once __DIR__ . '/../models/RendezVous.php';
require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../models/Medecin.php';

$rdvModel = new RendezVous();
$patientModel = new Patient();
$medecinModel = new Medecin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: index.php");
    exit();
}

$rdv = $rdvModel->getById($id);
if (!$rdv) {
    header("Location: index.php");
    exit();
}
if (($rdv['statut'] ?? '') === 'supprime') {
    header("Location: index.php");
    exit();
}

$message = '';
$error = '';

// Récupérer la liste des patients et médecins
$patients = $patientModel->getAll(1, 1000);
$medecins = $medecinModel->getAll(1, 1000);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'patient_id' => $_POST['patient_id'],
            'medecin_id' => $_POST['medecin_id'],
            'date_rdv' => $_POST['date_rdv'],
            'heure_rdv' => $_POST['heure_rdv'],
            'motif' => $_POST['motif'] ?: null,
            'notes' => $_POST['notes'] ?: null,
            'statut' => $_POST['statut']
        ];
        
        // Vérifier la disponibilité du créneau (en excluant le RDV actuel)
        if ($rdvModel->checkAvailability($data['medecin_id'], $data['date_rdv'], $data['heure_rdv'], $id)) {
            if ($rdvModel->update($id, $data)) {
                $message = "Rendez-vous modifié avec succès !";
                $rdv = $rdvModel->getById($id); // Recharger les données
            } else {
                $error = "Erreur lors de la modification du rendez-vous.";
            }
        } else {
            $error = "Ce créneau n'est pas disponible pour ce médecin.";
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

app_module_page_start([
    'active'   => 'rdv',
    'title'    => 'Modifier un Rendez-vous',
    'subtitle' => date('d/m/Y', strtotime($rdv['date_rdv'])) . ' à ' . $rdv['heure_rdv'],
    'icon'     => 'fa-edit',
]);
app_module_back_toolbar(app_url('rendez-vous/index.php'));
app_module_flash();
?>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card app-mod-form-card">
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-md-6">
                        <label for="patient_id" class="form-label">Patient *</label>
                        <select class="form-select" id="patient_id" name="patient_id" required>
                            <option value="">Choisir un patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>" <?php echo $rdv['patient_id'] == $patient['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($patient['numero_dossier'] . ' - ' . $patient['prenom'] . ' ' . $patient['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="medecin_id" class="form-label">Médecin *</label>
                        <select class="form-select" id="medecin_id" name="medecin_id" required>
                            <option value="">Choisir un médecin</option>
                            <?php foreach ($medecins as $medecin): ?>
                                <option value="<?php echo $medecin['id']; ?>" <?php echo $rdv['medecin_id'] == $medecin['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(medecin_profil_option_label($medecin)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="date_rdv" class="form-label">Date du rendez-vous *</label>
                        <input type="date" class="form-control" id="date_rdv" name="date_rdv" 
                               value="<?php echo $rdv['date_rdv']; ?>" min="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="heure_rdv" class="form-label">Heure du rendez-vous *</label>
                        <select class="form-select" id="heure_rdv" name="heure_rdv" required>
                            <option value="">Choisir une heure</option>
                            <?php 
                            for ($h = 8; $h <= 18; $h++) {
                                for ($m = 0; $m < 60; $m += 30) {
                                    $heure = sprintf('%02d:%02d', $h, $m);
                                    $selected = $rdv['heure_rdv'] === $heure ? 'selected' : '';
                                    echo "<option value=\"$heure\" $selected>$heure</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="statut" class="form-label">Statut *</label>
                        <select class="form-select" id="statut" name="statut" required>
                            <option value="planifie" <?php echo $rdv['statut'] === 'planifie' ? 'selected' : ''; ?>>Planifié</option>
                            <option value="confirme" <?php echo $rdv['statut'] === 'confirme' ? 'selected' : ''; ?>>Confirmé</option>
                            <option value="annule" <?php echo $rdv['statut'] === 'annule' ? 'selected' : ''; ?>>Annulé</option>
                            <option value="termine" <?php echo $rdv['statut'] === 'termine' ? 'selected' : ''; ?>>Terminé</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="motif" class="form-label">Motif de consultation</label>
                        <input type="text" class="form-control" id="motif" name="motif" 
                               value="<?php echo htmlspecialchars($rdv['motif'] ?? ''); ?>" 
                               placeholder="Ex: Consultation de routine">
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label">Notes additionnelles</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                  placeholder="Informations complémentaires..."><?php echo htmlspecialchars($rdv['notes'] ?? ''); ?></textarea>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save me-2"></i>Enregistrer les modifications
                        </button>
                        <a href="voir.php?id=<?php echo $rdv['id']; ?>" class="btn btn-secondary ms-2">Annuler</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Informations actuelles -->
        <div class="card mt-4">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informations actuelles</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>Patient actuel :</strong><br>
                        <span class="text-primary"><?php echo htmlspecialchars($rdv['patient_prenom'] . ' ' . $rdv['patient_nom']); ?></span>
                    </div>
                    <div class="col-md-3">
                        <strong>Médecin actuel :</strong><br>
                        <span class="text-success"><?php echo htmlspecialchars(medecin_profil_format_joined($rdv)); ?></span>
                    </div>
                    <div class="col-md-3">
                        <strong>Date actuelle :</strong><br>
                        <span class="text-info"><?php echo date('d/m/Y', strtotime($rdv['date_rdv'])); ?></span>
                    </div>
                    <div class="col-md-3">
                        <strong>Heure actuelle :</strong><br>
                        <span class="text-info"><?php echo $rdv['heure_rdv']; ?></span>
                    </div>
                </div>
            </div>
        </div>

<?php app_module_page_end();





