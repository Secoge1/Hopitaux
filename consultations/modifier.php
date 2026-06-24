<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('consultations'));

require_once __DIR__ . '/../models/Consultation.php';
require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../models/Medecin.php';
require_once __DIR__ . '/../models/TarifConsultation.php';
require_once __DIR__ . '/../models/CategorieHospitalisation.php';
require_once __DIR__ . '/../models/SoinsConsultation.php';

$consultationModel = new Consultation();
$patientModel = new Patient();
$medecinModel = new Medecin();
$tarifModel = new TarifConsultation();
$categorieModel = new CategorieHospitalisation();
$soinsModel = new SoinsConsultation();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: index.php");
    exit();
}

$consultation = $consultationModel->getById($id);
if (!$consultation) {
    echo "<!DOCTYPE html><html><head><title>Erreur</title></head><body>";
    echo "<h1>Erreur : Consultation non trouvée</h1>";
    echo "<p>La consultation avec l'ID $id n'existe pas.</p>";
    echo "<a href='index.php'>Retour à la liste</a>";
    echo "</body></html>";
    exit();
}

$message = '';
$error = '';

// Récupérer la liste des patients et médecins
$patients = $patientModel->getAll(1, 1000);
$medecins = $medecinModel->getAll(1, 1000);
$tarifs = $tarifModel->getAll(); // Tous les tarifs
$categories = $categorieModel->getAll(); // Toutes les catégories d'hospitalisation
$soins = $soinsModel->getAll('actif'); // Tous les soins actifs

// Récupérer les soins existants de cette consultation
$soins_consultation_existants = $consultationModel->getConsultationSoins($id);

// Récupérer les données d'hospitalisation existantes
$hospitalisation_existante = $consultationModel->getConsultationHospitalisation($id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $date_consultation = $_POST['date_consultation'];
        
        $data = [
            'patient_id' => $_POST['patient_id'],
            'medecin_id' => $_POST['medecin_id'],
            'date_consultation' => $date_consultation,
            'symptomes' => $_POST['symptomes'] ?: null,
            'diagnostic' => $_POST['diagnostic'] ?: null,
            'traitement' => $_POST['traitement'] ?: null,
            'ordonnance' => $_POST['ordonnance'] ?: null,
            'statut' => $_POST['statut'],
            'prix_consultation' => $_POST['prix_consultation'] ?: 0.00,
            'type_consultation' => $_POST['type_consultation'] ?: 'consultation_simple',
            'hospitalisation_requise' => isset($_POST['hospitalisation_requise']) ? 1 : 0
        ];

        // Ajouter les données des soins si présentes
        if (isset($_POST['soins_data']) && !empty($_POST['soins_data'])) {
            $data['soins_data'] = $_POST['soins_data'];
        }

        // Ajouter les données d'hospitalisation si présentes
        if (isset($_POST['hospitalisation_requise']) && !empty($_POST['categorie_hospitalisation'])) {
            $categorie_id = $_POST['categorie_hospitalisation'];
            $duree = $_POST['duree_hospitalisation'] ?? 1;
            $notes = $_POST['notes_hospitalisation'] ?? null;
            
            // Récupérer le prix par jour de la catégorie
            $categorie = null;
            foreach ($categories as $cat) {
                if ($cat['id'] == $categorie_id) {
                    $categorie = $cat;
                    break;
                }
            }
            
            if ($categorie) {
                $prix_jour = $categorie['prix_jour'];
                $prix_total = $prix_jour * $duree;
                
                $data['hospitalisation_data'] = [
                    'categorie_id' => $categorie_id,
                    'duree' => $duree,
                    'prix_jour' => $prix_jour,
                    'prix_total' => $prix_total,
                    'notes' => $notes
                ];
            }
        }

        if ($consultationModel->update($id, $data)) {
            $message = "Consultation mise à jour avec succès !";
            // Recharger les données
            $consultation = $consultationModel->getById($id);
        } else {
            $error = "Erreur lors de la mise à jour de la consultation.";
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Extraire la date et l'heure pour le champ datetime-local
$date_consultation = date('Y-m-d\TH:i', strtotime($consultation['date_consultation']));

app_module_page_start([
    'active'   => 'consultations',
    'title'    => 'Modifier la Consultation',
    'subtitle' => 'Mise à jour des informations',
    'icon'     => 'fa-stethoscope',
]);
app_module_back_toolbar(app_url('consultations/index.php'), 'Retour à la liste', [
    ['href' => app_url('consultations/voir.php?id=' . $consultation['id']), 'label' => 'Voir', 'icon' => 'fa-eye', 'class' => 'btn-info'],
]);
app_module_flash();
?>
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header bg-warning text-white">
                <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Modifier la Consultation #<?php echo $consultation['id']; ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-md-6">
                        <label for="patient_id" class="form-label">Patient *</label>
                        <select class="form-select" id="patient_id" name="patient_id" required>
                            <option value="">Choisir un patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>" <?php echo $patient['id'] == $consultation['patient_id'] ? 'selected' : ''; ?>>
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
                                <option value="<?php echo $medecin['id']; ?>" <?php echo $medecin['id'] == $consultation['medecin_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(medecin_profil_option_label($medecin)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="date_consultation" class="form-label">Date et heure de consultation *</label>
                        <input type="datetime-local" class="form-control" id="date_consultation" name="date_consultation"
                               value="<?php echo htmlspecialchars($date_consultation); ?>" required>
                        <div class="form-text">Vous pouvez sélectionner une date passée ou future</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Fuseau horaire</label>
                        <div class="form-control-plaintext">
                            <i class="fas fa-clock me-2"></i>
                            <span id="timezone-display"><?php echo date_default_timezone_get(); ?></span>
                            <small class="text-muted ms-2"><?php echo date('H:i'); ?></small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label for="statut" class="form-label">Statut *</label>
                        <select class="form-select" id="statut" name="statut" required>
                            <option value="planifiee" <?php echo $consultation['statut'] === 'planifiee' ? 'selected' : ''; ?>>Planifiée</option>
                            <option value="en_cours" <?php echo $consultation['statut'] === 'en_cours' ? 'selected' : ''; ?>>En cours</option>
                            <option value="terminee" <?php echo $consultation['statut'] === 'terminee' ? 'selected' : ''; ?>>Terminée</option>
                            <option value="annulee" <?php echo $consultation['statut'] === 'annulee' ? 'selected' : ''; ?>>Annulée</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="type_consultation" class="form-label">Type de consultation *</label>
                        <select class="form-select" id="type_consultation" name="type_consultation" required>
                            <option value="consultation_simple" <?php echo ($consultation['type_consultation'] ?? '') === 'consultation_simple' ? 'selected' : ''; ?>>Consultation simple</option>
                            <option value="consultation_specialisee" <?php echo ($consultation['type_consultation'] ?? '') === 'consultation_specialisee' ? 'selected' : ''; ?>>Consultation spécialisée</option>
                            <option value="urgence" <?php echo ($consultation['type_consultation'] ?? '') === 'urgence' ? 'selected' : ''; ?>>Urgence</option>
                            <option value="controle" <?php echo ($consultation['type_consultation'] ?? '') === 'controle' ? 'selected' : ''; ?>>Contrôle</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="prix_consultation" class="form-label">Prix de la consultation (FCFA) *</label>
                        <input type="number" class="form-control" id="prix_consultation" name="prix_consultation" 
                               step="0.01" min="0" value="<?php echo htmlspecialchars($consultation['prix_consultation'] ?? '5000.00'); ?>" required>
                        <div class="form-text">Le prix sera automatiquement calculé selon le type de consultation</div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="hospitalisation_requise" name="hospitalisation_requise" 
                                   <?php echo ($consultation['hospitalisation_requise'] ?? 0) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="hospitalisation_requise">
                                Hospitalisation requise
                            </label>
                        </div>
                        <div class="form-text">Cochez si le patient nécessite une hospitalisation</div>
                    </div>

                    <!-- Section Hospitalisation (affichée conditionnellement) -->
                    <div class="col-12" id="hospitalisation-section" style="display: <?php echo ($consultation['hospitalisation_requise'] ?? 0) ? 'block' : 'none'; ?>;">
                        <div class="card mt-3">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0"><i class="fas fa-bed me-2"></i>Détails de l'Hospitalisation</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="categorie_hospitalisation" class="form-label">Catégorie d'hospitalisation</label>
                                        <select class="form-select" id="categorie_hospitalisation" name="categorie_hospitalisation">
                                            <option value="">Sélectionner une catégorie</option>
                                            <?php foreach ($categories as $categorie): ?>
                                                <option value="<?php echo $categorie['id']; ?>" 
                                                        data-prix="<?php echo $categorie['prix_jour']; ?>"
                                                        <?php echo (!empty($hospitalisation_existante) && $hospitalisation_existante[0]['categorie_hospitalisation_id'] == $categorie['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($categorie['nom']); ?> - 
                                                    <?php echo number_format($categorie['prix_jour'], 0, ',', ' '); ?> FCFA/jour
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="duree_hospitalisation" class="form-label">Durée prévue (jours)</label>
                                        <input type="number" class="form-control" id="duree_hospitalisation" name="duree_hospitalisation" 
                                               min="1" value="<?php echo !empty($hospitalisation_existante) ? $hospitalisation_existante[0]['duree_jours'] : '1'; ?>">
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <label for="notes_hospitalisation" class="form-label">Notes sur l'hospitalisation</label>
                                        <textarea class="form-control" id="notes_hospitalisation" name="notes_hospitalisation" rows="2"
                                                  placeholder="Notes spécifiques sur l'hospitalisation..."><?php echo !empty($hospitalisation_existante) ? htmlspecialchars($hospitalisation_existante[0]['notes'] ?? '') : ''; ?></textarea>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <div class="alert alert-info">
                                            <strong>Prix total estimé :</strong> <span id="prix-total-hospitalisation">0 FCFA</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section Soins -->
                    <div class="col-12">
                        <div class="card mt-3">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0"><i class="fas fa-stethoscope me-2"></i>Soins de Consultation</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="soin_id" class="form-label">Ajouter un soin</label>
                                        <select class="form-select" id="soin_id" name="soin_id">
                                            <option value="">Sélectionner un soin...</option>
                                            <?php foreach ($soins as $soin): ?>
                                                <option value="<?php echo $soin['id']; ?>" 
                                                        data-prix="<?php echo number_format($soin['prix'], 2, '.', ''); ?>"
                                                        data-type="<?php echo $soin['type_soin']; ?>">
                                                    <?php echo htmlspecialchars($soin['nom']); ?> - 
                                                    <?php echo number_format($soin['prix'], 0, ',', ' '); ?> FCFA
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="quantite_soin" class="form-label">Quantité</label>
                                        <input type="number" class="form-control" id="quantite_soin" name="quantite_soin" 
                                               min="1" value="1" onchange="updatePrixSoin()">
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end">
                                        <button type="button" class="btn btn-success w-100" onclick="addSoinToList()">
                                            <i class="fas fa-plus me-2"></i>Ajouter
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Liste des soins existants et ajoutés -->
                                <div id="soins-list" class="mt-3" style="display: <?php echo !empty($soins_consultation_existants) ? 'block' : 'none'; ?>;">
                                    <h6>Soins de la consultation :</h6>
                                    <div id="soins-items">
                                        <?php foreach ($soins_consultation_existants as $soin): ?>
                                            <div class="alert alert-success d-flex justify-content-between align-items-center mb-2">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($soin['nom']); ?></strong><br>
                                                    <small class="text-muted"><?php echo number_format($soin['prix_unitaire'], 0, ',', ' '); ?> FCFA × <?php echo $soin['quantite']; ?> = <?php echo number_format($soin['prix_total'], 0, ',', ' '); ?> FCFA</small>
                                                </div>
                                                <div>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeSoinFromExisting(<?php echo $soin['soin_id']; ?>)" title="Retirer">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="mt-2">
                                        <strong>Total des soins : <span id="total-soins" class="text-success">
                                            <?php 
                                            $total_existant = 0;
                                            foreach ($soins_consultation_existants as $soin) {
                                                $total_existant += $soin['prix_total'];
                                            }
                                            echo number_format($total_existant, 0, ',', ' ') . ' FCFA';
                                            ?>
                                        </span></strong>
                                    </div>
                                </div>
                                
                                <!-- Champs cachés pour envoyer les données des soins -->
                                <input type="hidden" id="soins-data" name="soins_data" value="<?php echo htmlspecialchars(json_encode($soins_consultation_existants)); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <label for="symptomes" class="form-label">Symptômes</label>
                        <textarea class="form-control" id="symptomes" name="symptomes" rows="3"
                                  placeholder="Décrivez les symptômes du patient..."><?php echo htmlspecialchars($consultation['symptomes'] ?? ''); ?></textarea>
                    </div>

                    <div class="col-12">
                        <label for="diagnostic" class="form-label">Diagnostic</label>
                        <textarea class="form-control" id="diagnostic" name="diagnostic" rows="3"
                                  placeholder="Diagnostic établi par le médecin..."><?php echo htmlspecialchars($consultation['diagnostic'] ?? ''); ?></textarea>
                    </div>

                    <div class="col-12">
                        <label for="traitement" class="form-label">Traitement prescrit</label>
                        <textarea class="form-control" id="traitement" name="traitement" rows="3"
                                  placeholder="Traitement recommandé..."><?php echo htmlspecialchars($consultation['traitement'] ?? ''); ?></textarea>
                    </div>

                    <div class="col-12">
                        <label for="ordonnance" class="form-label">Ordonnance</label>
                        <textarea class="form-control" id="ordonnance" name="ordonnance" rows="4"
                                  placeholder="Médicaments prescrits, posologie, durée..."><?php echo htmlspecialchars($consultation['ordonnance'] ?? ''); ?></textarea>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save me-2"></i>Mettre à jour
                        </button>
                        <a href="voir.php?id=<?php echo $consultation['id']; ?>" class="btn btn-info ms-2">
                            <i class="fas fa-eye me-2"></i>Voir
                        </a>
                        <a href="index.php" class="btn btn-secondary ms-2">Annuler</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Informations sur la consultation -->
        <div class="card mt-4">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informations sur la Consultation</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>ID :</strong><br>
                        <span class="badge bg-secondary"><?php echo $consultation['id']; ?></span>
                    </div>
                    <div class="col-md-3">
                        <strong>Date de création :</strong><br>
                        <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($consultation['date_creation'])); ?></small>
                    </div>
                    <div class="col-md-3">
                        <strong>Dernière modification :</strong><br>
                        <small class="text-muted">
                            <?php echo isset($consultation['date_modification']) && $consultation['date_modification'] ? date('d/m/Y H:i', strtotime($consultation['date_modification'])) : 'Jamais modifiée'; ?>
                        </small>
                    </div>
                    <div class="col-md-3">
                        <strong>Statut actuel :</strong><br>
                        <span class="badge bg-<?php echo $consultation['statut'] === 'terminee' ? 'success' : ($consultation['statut'] === 'en_cours' ? 'warning' : ($consultation['statut'] === 'annulee' ? 'danger' : 'info')); ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $consultation['statut'])); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Informations sur le patient -->
        <div class="card mt-4">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0"><i class="fas fa-user me-2"></i>Informations du Patient</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <strong>Nom complet :</strong><br>
                        <span class="text-primary"><?php echo htmlspecialchars($consultation['patient_prenom'] . ' ' . $consultation['patient_nom']); ?></span>
                    </div>
                    <div class="col-md-4">
                        <strong><?= htmlspecialchars(medecin_profil_attribution_label_from_row($consultation)) ?> :</strong><br>
                        <span class="text-success"><?php echo htmlspecialchars(medecin_profil_format_joined($consultation)); ?></span>
                    </div>
                    <div class="col-md-4">
                        <strong>Spécialité :</strong><br>
                        <span class="text-info"><?php echo htmlspecialchars($consultation['medecin_specialite']); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php ob_start(); ?>
<script>
        // Gestion de l'hospitalisation
        document.addEventListener('DOMContentLoaded', function() {
            const hospitalisationCheckbox = document.getElementById('hospitalisation_requise');
            const hospitalisationSection = document.getElementById('hospitalisation-section');
            const categorieSelect = document.getElementById('categorie_hospitalisation');
            const dureeInput = document.getElementById('duree_hospitalisation');
            const prixTotalSpan = document.getElementById('prix-total-hospitalisation');

            hospitalisationCheckbox.addEventListener('change', function() {
                hospitalisationSection.style.display = this.checked ? 'block' : 'none';
            });

            function calculerPrixHospitalisation() {
                const categorie = categorieSelect.options[categorieSelect.selectedIndex];
                const prixJour = parseFloat(categorie?.dataset?.prix || 0);
                const duree = parseInt(dureeInput.value || '1', 10);
                const prixTotal = prixJour * duree;
                prixTotalSpan.textContent = prixTotal.toLocaleString('fr-FR') + ' FCFA';
            }

            categorieSelect.addEventListener('change', calculerPrixHospitalisation);
            dureeInput.addEventListener('input', calculerPrixHospitalisation);
            
            // Gestion des soins - adapter les données existantes au format attendu par JavaScript
            let soinsSelectionnes = <?php 
                $soins_formatted = [];
                foreach ($soins_consultation_existants as $soin) {
                    $soins_formatted[] = [
                        'id' => $soin['soin_id'],
                        'nom' => $soin['nom'],
                        'prix' => floatval($soin['prix_unitaire']),
                        'quantite' => intval($soin['quantite']),
                        'total' => floatval($soin['prix_total'])
                    ];
                }
                echo json_encode($soins_formatted);
            ?>;
            
            // Fonction pour mettre à jour le prix d'un soin
            window.updatePrixSoin = function() {
                const soinSelect = document.getElementById('soin_id');
                const quantiteInput = document.getElementById('quantite_soin');
                
                if (soinSelect.selectedOptions.length > 0 && soinSelect.value) {
                    const selectedOption = soinSelect.selectedOptions[0];
                    const prixStr = selectedOption.dataset.prix;
                    const prix = parseFloat(prixStr);
                    const quantite = parseInt(quantiteInput.value) || 1;
                    const total = prix * quantite;
                    
                    if (!isNaN(prix) && !isNaN(total) && prix > 0) {
                        const button = document.querySelector('button[onclick="addSoinToList()"]');
                        button.innerHTML = `<i class="fas fa-plus me-2"></i>Ajouter (${total.toLocaleString('fr-FR')} FCFA)`;
                    } else {
                        const button = document.querySelector('button[onclick="addSoinToList()"]');
                        button.innerHTML = `<i class="fas fa-plus me-2"></i>Ajouter`;
                    }
                } else {
                    const button = document.querySelector('button[onclick="addSoinToList()"]');
                    button.innerHTML = `<i class="fas fa-plus me-2"></i>Ajouter`;
                }
            };
            
            // Fonction pour ajouter un soin à la liste
            window.addSoinToList = function() {
                const soinSelect = document.getElementById('soin_id');
                const quantiteInput = document.getElementById('quantite_soin');
                
                if (!soinSelect.value) {
                    alert('Veuillez sélectionner un soin');
                    return;
                }
                
                const selectedOption = soinSelect.selectedOptions[0];
                const soinId = soinSelect.value;
                const nom = selectedOption.textContent.split(' - ')[0];
                const prixStr = selectedOption.dataset.prix;
                const prix = parseFloat(prixStr);
                const quantite = parseInt(quantiteInput.value) || 1;
                
                if (isNaN(prix) || prix <= 0) {
                    alert('Erreur : Prix du soin invalide');
                    return;
                }
                
                const total = prix * quantite;
                
                // Vérifier si le soin n'est pas déjà dans la liste
                const existingIndex = soinsSelectionnes.findIndex(s => s.id === soinId);
                if (existingIndex !== -1) {
                    soinsSelectionnes[existingIndex].quantite += quantite;
                    soinsSelectionnes[existingIndex].total = soinsSelectionnes[existingIndex].prix * soinsSelectionnes[existingIndex].quantite;
                } else {
                    soinsSelectionnes.push({
                        id: soinId,
                        nom: nom,
                        prix: prix,
                        quantite: quantite,
                        total: total
                    });
                }
                
                updateSoinsList();
                
                // Réinitialiser le formulaire
                soinSelect.value = '';
                quantiteInput.value = 1;
                updatePrixSoin();
            };
            
            // Fonction pour mettre à jour la liste des soins
            function updateSoinsList() {
                const container = document.getElementById('soins-items');
                const totalSpan = document.getElementById('total-soins');
                const listDiv = document.getElementById('soins-list');
                const soinsDataInput = document.getElementById('soins-data');
                
                if (soinsSelectionnes.length === 0) {
                    listDiv.style.display = 'none';
                    soinsDataInput.value = '';
                    return;
                }
                
                listDiv.style.display = 'block';
                container.innerHTML = '';
                
                let totalGeneral = 0;
                
                soinsSelectionnes.forEach((soin, index) => {
                    totalGeneral += soin.total;
                    
                    const soinDiv = document.createElement('div');
                    soinDiv.className = 'alert alert-success d-flex justify-content-between align-items-center mb-2';
                    soinDiv.innerHTML = `
                        <div>
                            <strong>${soin.nom}</strong><br>
                            <small class="text-muted">${soin.prix.toLocaleString('fr-FR')} FCFA × ${soin.quantite} = ${soin.total.toLocaleString('fr-FR')} FCFA</small>
                        </div>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeSoin(${index})" title="Retirer">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    `;
                    container.appendChild(soinDiv);
                });
                
                totalSpan.textContent = totalGeneral.toLocaleString('fr-FR') + ' FCFA';
                soinsDataInput.value = JSON.stringify(soinsSelectionnes);
            }
            
            // Fonction pour retirer un soin
            window.removeSoin = function(index) {
                soinsSelectionnes.splice(index, 1);
                updateSoinsList();
            };
            
            // Fonction pour retirer un soin existant
            window.removeSoinFromExisting = function(soinId) {
                soinsSelectionnes = soinsSelectionnes.filter(s => s.id != soinId);
                updateSoinsList();
            };
            
            // Initialiser le prix du soin
            document.getElementById('soin_id').addEventListener('change', updatePrixSoin);
            updatePrixSoin();
        });
    </script>
<?php
$GLOBALS['app_page_scripts'] = ob_get_clean();
app_module_page_end();
