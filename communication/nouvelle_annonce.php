<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('communication'));

// Seuls les admins peuvent créer des annonces
if (!$auth->estAdmin()) {
    header("Location: index.php");
    exit;
}

require_once __DIR__ . '/../models/Communication.php';

$commModel = new Communication();

$user_id = $utilisateur['id'];

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Convertir datetime-local en format MySQL
        $date_debut = $_POST['date_debut'] ? str_replace('T', ' ', $_POST['date_debut']) . ':00' : date('Y-m-d H:i:s');
        $date_fin = $_POST['date_fin'] ? str_replace('T', ' ', $_POST['date_fin']) . ':00' : null;
        
        $data = [
            'titre' => $_POST['titre'],
            'contenu' => $_POST['contenu'],
            'type' => $_POST['type'] ?? 'information',
            'destinataires' => $_POST['destinataires'] ?? 'tous',
            'date_debut' => $date_debut,
            'date_fin' => $date_fin,
            'actif' => 1,
            'cree_par' => $user_id
        ];

        if ($commModel->createAnnonce($data)) {
            header("Location: index.php?success=1");
            exit;
        } else {
            $error = "Erreur lors de la création de l'annonce.";
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}
?>
<?php
app_module_page_start([
    'active'   => 'communication',
    'title'    => 'Nouvelle Annonce',
    'subtitle' => 'Publication interne',
    'icon'     => 'fa-comments',
]);
app_module_back_toolbar(app_url('communication/index.php'), 'Retour à la liste');
app_module_flash();
?>
<div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-bullhorn me-2"></i>Créer une Annonce</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-12">
                        <label for="titre" class="form-label">Titre de l'Annonce *</label>
                        <input type="text" class="form-control" id="titre" name="titre" required placeholder="Ex: Réunion du personnel, Nouvelle procédure, etc.">
                    </div>

                    <div class="col-md-6">
                        <label for="type" class="form-label">Type d'Annonce</label>
                        <select class="form-select" id="type" name="type">
                            <option value="information" selected>Information</option>
                            <option value="alerte">Alerte</option>
                            <option value="urgence">Urgence</option>
                            <option value="general">Général</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="destinataires" class="form-label">Destinataires</label>
                        <select class="form-select" id="destinataires" name="destinataires">
                            <option value="tous" selected>Tous les utilisateurs</option>
                            <option value="admins">Administrateurs</option>
                            <option value="medecins">Médecins</option>
                            <option value="secretaires">Secrétaires</option>
                            <option value="infirmiers">Infirmiers</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="date_debut" class="form-label">Date de Début</label>
                        <input type="datetime-local" class="form-control" id="date_debut" name="date_debut" value="<?php echo date('Y-m-d\TH:i'); ?>">
                        <small class="text-muted">Date à partir de laquelle l'annonce sera visible</small>
                    </div>

                    <div class="col-md-6">
                        <label for="date_fin" class="form-label">Date de Fin</label>
                        <input type="datetime-local" class="form-control" id="date_fin" name="date_fin">
                        <small class="text-muted">Date jusqu'à laquelle l'annonce sera visible (optionnel)</small>
                    </div>

                    <div class="col-12">
                        <label for="contenu" class="form-label">Contenu de l'Annonce *</label>
                        <textarea class="form-control" id="contenu" name="contenu" rows="10" required placeholder="Rédigez votre annonce ici..."></textarea>
                        <small class="text-muted">Vous pouvez utiliser du texte simple. Les sauts de ligne seront préservés.</small>
                    </div>

                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> Cette annonce sera visible par tous les utilisateurs (ou le rôle sélectionné) dans la section annonces.
                        </div>
                    </div>

                    <div class="col-12">
                        <hr>
                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Annuler
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Publier l'Annonce
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php
ob_start();
?>
<script>
        // Pré-remplir la date de fin avec une semaine par défaut
        document.addEventListener('DOMContentLoaded', function() {
            const dateDebut = document.getElementById('date_debut');
            const dateFin = document.getElementById('date_fin');
            
            if (dateDebut && dateFin && !dateFin.value) {
                dateDebut.addEventListener('change', function() {
                    if (this.value && !dateFin.value) {
                        const date = new Date(this.value);
                        date.setDate(date.getDate() + 7); // Ajouter 7 jours
                        const dateStr = date.toISOString().slice(0, 16);
                        dateFin.value = dateStr;
                    }
                });
            }
        });
    </script>
<?php
$GLOBALS['app_page_scripts'] = ob_get_clean();
app_module_page_end();
