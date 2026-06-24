<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('communication'));

require_once __DIR__ . '/../models/Communication.php';
require_once __DIR__ . '/../models/Utilisateur.php';
require_once __DIR__ . '/../config/database.php';

$commModel = new Communication();

$user_id = $utilisateur['id'];

$message = '';
$error = '';

// Gérer la réponse à un message
$messageOriginal = null;
$repondreId = isset($_GET['repondre']) ? (int)$_GET['repondre'] : 0;
if ($repondreId > 0) {
    try {
        $messageOriginal = $commModel->getMessageById($repondreId, $user_id);
        if (!$messageOriginal || $messageOriginal['expediteur_id'] == $user_id) {
            // Si le message n'existe pas ou si l'utilisateur est l'expéditeur, on ne peut pas répondre
            $messageOriginal = null;
        }
    } catch (Exception $e) {
        $messageOriginal = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'expediteur_id' => $user_id,
            'destinataire_id' => $_POST['destinataire_id'] ?: null,
            'destinataire_role' => $_POST['destinataire_role'] ?: null,
            'sujet' => $_POST['sujet'],
            'message' => $_POST['message'],
            'priorite' => $_POST['priorite'] ?? 'normale'
        ];

        if ($commModel->createMessage($data)) {
            header("Location: index.php?success=1");
            exit;
        } else {
            $error = "Erreur lors de l'envoi du message.";
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Utilisateurs actifs du tenant courant uniquement
try {
    $database = new Database();
    $utilisateurModel = new Utilisateur($database->getConnection());
    $users = $utilisateurModel->listActifsForTenant();
} catch (Exception $e) {
    $users = [];
}
?>
<?php
app_module_page_start([
    'active'   => 'communication',
    'title'    => 'Nouveau Message',
    'subtitle' => 'Communication interne',
    'icon'     => 'fa-comments',
]);
app_module_back_toolbar(app_url('communication/index.php'), 'Retour à la liste');
app_module_flash();
?>
<div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-comments me-2"></i>Composer un Message</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <?php if ($messageOriginal): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Réponse à:</strong> <?php echo htmlspecialchars($messageOriginal['sujet']); ?>
                        </div>
                        <input type="hidden" name="destinataire_id" value="<?php echo htmlspecialchars($messageOriginal['expediteur_id']); ?>">
                        <div class="col-12">
                            <label class="form-label">Destinataire</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($messageOriginal['expediteur_nom'] ?? 'Système'); ?>" readonly>
                        </div>
                    <?php else: ?>
                        <div class="col-md-6">
                            <label for="destinataire_id" class="form-label">Destinataire (Utilisateur spécifique)</label>
                            <select class="form-select" id="destinataire_id" name="destinataire_id">
                                <option value="">Choisir un utilisateur...</option>
                                <?php foreach ($users as $user): ?>
                                    <?php if ($user['id'] != $user_id): ?>
                                        <option value="<?php echo $user['id']; ?>">
                                            <?php echo htmlspecialchars($user['nom_utilisateur']); ?> 
                                            (<?php echo ucfirst($user['role']); ?>)
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="destinataire_role" class="form-label">OU Destinataire (Par Rôle)</label>
                            <select class="form-select" id="destinataire_role" name="destinataire_role">
                                <option value="">Choisir un rôle...</option>
                                <?php
                                require_once __DIR__ . '/../includes/roles.php';
                                foreach (APP_ROLE_LABELS as $roleSlug => $roleLabel):
                                ?>
                                <option value="<?= htmlspecialchars($roleSlug) ?>"><?= htmlspecialchars($roleLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Si vous choisissez un rôle, tous les utilisateurs de ce rôle recevront le message</small>
                        </div>
                    <?php endif; ?>

                    <div class="col-12">
                        <label for="sujet" class="form-label">Sujet *</label>
                        <input type="text" class="form-control" id="sujet" name="sujet" 
                               value="<?php echo $messageOriginal ? 'Re: ' . htmlspecialchars($messageOriginal['sujet']) : ''; ?>" 
                               required>
                    </div>

                    <div class="col-md-6">
                        <label for="priorite" class="form-label">Priorité</label>
                        <select class="form-select" id="priorite" name="priorite">
                            <option value="basse">Basse</option>
                            <option value="normale" selected>Normale</option>
                            <option value="haute">Haute</option>
                            <option value="urgente">Urgente</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label for="message" class="form-label">Message *</label>
                        <textarea class="form-control" id="message" name="message" rows="8" required><?php 
                            if ($messageOriginal) {
                                echo "\n\n--- Message original ---\n";
                                echo "De: " . htmlspecialchars($messageOriginal['expediteur_nom'] ?? 'Système') . "\n";
                                echo "Date: " . date('d/m/Y H:i', strtotime($messageOriginal['date_creation'])) . "\n";
                                echo "Sujet: " . htmlspecialchars($messageOriginal['sujet']) . "\n\n";
                                echo htmlspecialchars($messageOriginal['message']);
                            }
                        ?></textarea>
                    </div>

                    <div class="col-12">
                        <hr>
                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Annuler
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Envoyer
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php app_module_page_end(); ?>
