<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('rdv'));

require_once __DIR__ . '/../models/RendezVous.php';
require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../models/Medecin.php';
require_once __DIR__ . '/../includes/RendezVousIntelligence.php';

$rdvModel = new RendezVous();
$patientModel = new Patient();
$medecinModel = new Medecin();

$message = '';
$error = '';

// Récupérer l'ID du patient depuis l'URL si disponible
$selectedPatientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : '';

// Récupérer la liste des patients et médecins
$patients = $patientModel->getAll(1, 1000); // Tous les patients
$medecins = $medecinModel->getAll(1, 1000); // Tous les médecins

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
        
        // Vérifier la disponibilité du créneau
        if ($rdvModel->checkAvailability($data['medecin_id'], $data['date_rdv'], $data['heure_rdv'])) {
            if ($rdvModel->create($data)) {
                $message = "Rendez-vous créé avec succès !";
            } else {
                $error = "Erreur lors de la création du rendez-vous.";
            }
        } else {
            $error = "Ce créneau n'est pas disponible pour ce médecin.";
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

$subtitle = 'Planification d\'un nouveau rendez-vous';
if ($selectedPatientId && isset($_GET['patient_name'])) {
    $subtitle = 'Patient : ' . $_GET['patient_name'];
    if (isset($_GET['patient_dossier'])) {
        $subtitle .= ' (Dossier: ' . $_GET['patient_dossier'] . ')';
    }
}

app_module_page_start([
    'active'   => 'rdv',
    'title'    => 'Nouveau Rendez-vous',
    'subtitle' => $subtitle,
    'icon'     => 'fa-calendar-plus',
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
                                <option value="<?php echo $patient['id']; ?>" <?php echo $selectedPatientId == $patient['id'] ? 'selected' : ''; ?>>
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
                                <option value="<?php echo $medecin['id']; ?>">
                                    <?php echo htmlspecialchars(medecin_profil_option_label($medecin)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="date_rdv" class="form-label">Date du rendez-vous *</label>
                        <input type="date" class="form-control" id="date_rdv" name="date_rdv" 
                               min="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="heure_rdv" class="form-label">Heure du rendez-vous *</label>
                        <select class="form-select" id="heure_rdv" name="heure_rdv" required>
                            <option value="">Choisir une heure</option>
                            <?php 
                            for ($h = 8; $h <= 18; $h++) {
                                for ($m = 0; $m < 60; $m += 30) {
                                    $heure = sprintf('%02d:%02d', $h, $m);
                                    echo "<option value=\"$heure\">$heure</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="statut" class="form-label">Statut *</label>
                        <select class="form-select" id="statut" name="statut" required>
                            <option value="planifie">Planifié</option>
                            <option value="confirme">Confirmé</option>
                            <option value="annule">Annulé</option>
                            <option value="termine">Terminé</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="motif" class="form-label">Motif de consultation</label>
                        <input type="text" class="form-control" id="motif" name="motif" 
                               placeholder="Ex: Consultation de routine">
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label">Notes additionnelles</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                  placeholder="Informations complémentaires..."></textarea>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save me-2"></i>Créer le rendez-vous
                        </button>
                        <a href="index.php" class="btn btn-secondary ms-2">Annuler</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Aide pour la planification -->
        <div class="card mt-4">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Conseils pour la planification</h6>
            </div>
            <div class="card-body">
                <ul class="mb-0">
                    <li>Les créneaux sont disponibles de 8h00 à 18h00 par intervalles de 30 minutes</li>
                    <li>Vérifiez la disponibilité du médecin avant de confirmer</li>
                    <li>Les rendez-vous ne peuvent pas être planifiés dans le passé</li>
                    <li>Privilégiez les créneaux du matin pour les consultations de routine</li>
                </ul>
            </div>
        </div>

<?php app_module_page_end();
