<?php
/**
 * Statut IA Mistral (lecture seule) — configuration réservée à l'admin plateforme.
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_layout.php';
require_once __DIR__ . '/../includes/app_parametres_layout.php';
require_once __DIR__ . '/../includes/PlatformAIConfig.php';
require_once __DIR__ . '/../includes/MistralAIService.php';

if (function_exists('saas_is_platform_admin') && saas_is_platform_admin()) {
    header('Location: ' . app_url('admin_platform/ia.php'));
    exit;
}

app_parametres_require_admin();
extract(app_prepare_context());

$mistral = MistralAIService::getInstance();

app_head('Intelligence artificielle', ['assets/css/app-parametres.css'], 'app-parametres-page');
app_layout_start(['active' => 'parametres', 'skip_page_header' => true]);
app_parametres_shell_start('ia', 'Intelligence artificielle', 'Service Mistral géré au niveau plateforme');
?>

<div class="param-section">
    <div class="param-card">
        <div class="param-card-head param-card-head--violet"><i class="fas fa-robot"></i> Mistral AI</div>
        <div class="param-card-body">
            <div class="alert alert-info small">
                <i class="fas fa-lock me-2"></i>
                La clé API et l'activation de Mistral sont configurées par
                <strong>l'administrateur principal de la plateforme</strong> et s'appliquent à tous les établissements.
            </div>

            <ul class="list-unstyled mb-0 small">
                <li class="mb-3">
                    <strong>Statut pour votre établissement</strong><br>
                    <?php if ($mistral->isActive()): ?>
                    <span class="badge bg-success">Mistral actif</span>
                    <?php else: ?>
                    <span class="badge bg-secondary">Mistral inactif — suggestions locales uniquement</span>
                    <?php endif; ?>
                </li>
                <li class="mb-2">
                    <strong>Consultations</strong><br>
                    <span class="text-muted"><?= $mistral->isEnabledForConsultations() ? 'Enrichissement Mistral disponible' : 'Non disponible' ?></span>
                </li>
                <li class="mb-2">
                    <strong>Laboratoire</strong><br>
                    <span class="text-muted"><?= $mistral->isEnabledForLaboratoire() ? 'Enrichissement Mistral disponible' : 'Non disponible' ?></span>
                </li>
                <li>
                    <strong>Clé API</strong><br>
                    <span class="text-muted"><?= PlatformAIConfig::hasApiKey() ? 'Configurée (plateforme)' : 'Non configurée' ?></span>
                </li>
            </ul>

            <p class="text-muted small mt-4 mb-0">
                Utilisation : <strong>Consultations → Ajouter</strong> et <strong>Laboratoire → Ajouter</strong>.
                Les suggestions restent indicatives et doivent être validées par le personnel médical.
            </p>
        </div>
    </div>
</div>

<?php
app_parametres_shell_end();
app_layout_end(['minimal_scripts' => true]);
