<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_layout.php';
require_once __DIR__ . '/../includes/app_platform_layout.php';
require_once __DIR__ . '/../includes/PlatformAIConfig.php';
require_once __DIR__ . '/../includes/MistralAIService.php';

app_platform_require_admin();
extract(app_prepare_platform_context());

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_ia') {
    try {
        PlatformAIConfig::saveMany([
            PlatformAIConfig::KEY_ACTIVE => isset($_POST['ia_mistral_actif']) ? '1' : '0',
            PlatformAIConfig::KEY_CONSULTATIONS => isset($_POST['ia_mistral_consultations']) ? '1' : '0',
            PlatformAIConfig::KEY_LABORATOIRE => isset($_POST['ia_mistral_laboratoire']) ? '1' : '0',
            PlatformAIConfig::KEY_MODEL => trim($_POST['ia_mistral_model'] ?? 'mistral-small-latest'),
            PlatformAIConfig::KEY_TIMEOUT => (string) max(5, min(60, (int) ($_POST['ia_mistral_timeout'] ?? 25))),
        ]);

        $newKey = trim($_POST['ia_mistral_api_key'] ?? '');
        if ($newKey !== '') {
            PlatformAIConfig::save(PlatformAIConfig::KEY_API, $newKey, 'Clé API Mistral (plateforme)');
        }

        $message = 'Configuration IA Mistral enregistrée pour toute la plateforme.';
        $messageType = 'success';
    } catch (Throwable $e) {
        $message = 'Erreur : ' . $e->getMessage();
        $messageType = 'danger';
    }
}

$ia_mistral_actif = PlatformAIConfig::get(PlatformAIConfig::KEY_ACTIVE, '0') === '1';
$ia_mistral_consultations = PlatformAIConfig::get(PlatformAIConfig::KEY_CONSULTATIONS, '1') === '1';
$ia_mistral_laboratoire = PlatformAIConfig::get(PlatformAIConfig::KEY_LABORATOIRE, '1') === '1';
$ia_mistral_model = PlatformAIConfig::get(PlatformAIConfig::KEY_MODEL, 'mistral-small-latest');
$ia_mistral_timeout = (int) PlatformAIConfig::get(PlatformAIConfig::KEY_TIMEOUT, '25');
$hasApiKey = PlatformAIConfig::hasApiKey();
$mistral = MistralAIService::getInstance();

app_head('IA Mistral', ['assets/css/app-platform.css'], 'app-platform-page');
app_layout_start(['active' => 'platform', 'skip_page_header' => true]);
app_platform_shell_start(
    'ia',
    'Intelligence artificielle Mistral',
    'Configuration globale — partagée par tous les établissements (tenants)'
);
app_platform_alert($message, $messageType);
?>

<div class="platform-card mb-4">
    <div class="platform-card-head">
        <span><i class="fas fa-robot"></i> Mistral AI — plateforme</span>
    </div>
    <div class="platform-card-body">
        <div class="alert alert-info small mb-4">
            <i class="fas fa-globe me-2"></i>
            Cette configuration s'applique à <strong>tous les établissements</strong> abonnés.
            Les administrateurs d'établissement ne peuvent pas modifier la clé API ni activer/désactiver le service.
        </div>

        <form method="POST" autocomplete="off">
            <input type="hidden" name="action" value="update_ia">

            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="ia_mistral_actif" name="ia_mistral_actif"
                       <?= $ia_mistral_actif ? 'checked' : '' ?>>
                <label class="form-check-label" for="ia_mistral_actif">Activer Mistral AI (tous les tenants)</label>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="ia_mistral_consultations"
                               name="ia_mistral_consultations" <?= $ia_mistral_consultations ? 'checked' : '' ?>>
                        <label class="form-check-label" for="ia_mistral_consultations">Consultations (diagnostics)</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="ia_mistral_laboratoire"
                               name="ia_mistral_laboratoire" <?= $ia_mistral_laboratoire ? 'checked' : '' ?>>
                        <label class="form-check-label" for="ia_mistral_laboratoire">Laboratoire (analyses)</label>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label for="ia_mistral_api_key" class="form-label">Clé API Mistral</label>
                <input type="password" class="form-control" id="ia_mistral_api_key" name="ia_mistral_api_key"
                       placeholder="<?= $hasApiKey ? '•••••••••••••••• (laisser vide pour conserver)' : 'Collez votre clé API' ?>"
                       autocomplete="new-password">
                <?php if ($hasApiKey): ?>
                <small class="text-success"><i class="fas fa-check-circle me-1"></i>Clé configurée (globale)</small>
                <?php else: ?>
                <small class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Aucune clé — l'IA locale seule sera utilisée</small>
                <?php endif; ?>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="ia_mistral_model" class="form-label">Modèle</label>
                    <select class="form-select" id="ia_mistral_model" name="ia_mistral_model">
                        <?php
                        $models = [
                            'mistral-small-latest' => 'mistral-small-latest (rapide)',
                            'mistral-medium-latest' => 'mistral-medium-latest (équilibré)',
                            'mistral-large-latest' => 'mistral-large-latest (précis)',
                            'open-mistral-nemo' => 'open-mistral-nemo',
                        ];
                        foreach ($models as $value => $label):
                        ?>
                        <option value="<?= htmlspecialchars($value) ?>" <?= $ia_mistral_model === $value ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="ia_mistral_timeout" class="form-label">Délai max. (secondes)</label>
                    <input type="number" class="form-control" id="ia_mistral_timeout" name="ia_mistral_timeout"
                           min="5" max="60" value="<?= (int) $ia_mistral_timeout ?>">
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-2"></i>Enregistrer pour toute la plateforme
            </button>
        </form>
    </div>
</div>

<div class="platform-card">
    <div class="platform-card-head">
        <span><i class="fas fa-info-circle"></i> État du service</span>
    </div>
    <div class="platform-card-body">
        <ul class="list-unstyled mb-0 small">
            <li class="mb-2">
                <strong>Statut global</strong><br>
                <?php if ($mistral->isActive()): ?>
                <span class="badge bg-success">Actif pour tous les tenants</span>
                <?php else: ?>
                <span class="badge bg-secondary">Inactif</span>
                <?php endif; ?>
            </li>
            <li class="mb-2"><strong>Consultations</strong><br>
                <span class="text-muted"><?= $mistral->isEnabledForConsultations() ? 'Mistral activé' : 'Base locale uniquement' ?></span>
            </li>
            <li><strong>Laboratoire</strong><br>
                <span class="text-muted"><?= $mistral->isEnabledForLaboratoire() ? 'Mistral activé' : 'Base locale uniquement' ?></span>
            </li>
        </ul>
    </div>
</div>

<?php
app_platform_shell_end();
app_layout_end(['minimal_scripts' => true]);
