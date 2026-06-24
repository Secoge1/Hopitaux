<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_layout.php';
require_once __DIR__ . '/../includes/app_parametres_layout.php';
require_once __DIR__ . '/../includes/platform_brand.php';

app_parametres_require_admin();
extract(app_prepare_context());

// Traitement des formulaires
$message = '';
$messageType = '';

if ($_POST) {
    try {
        if (isset($_POST['action'])) {
            require_once __DIR__ . '/../config/SystemParameters.php';
            $systemParams = SystemParameters::getInstance();

            switch ($_POST['action']) {
                case 'update_identity':
                    $systemParams->update('nom_etablissement', $_POST['nom_etablissement'], 'Nom de l\'établissement');
                    $systemParams->update('adresse', $_POST['adresse'], 'Adresse de l\'établissement');
                    $systemParams->update('telephone', $_POST['telephone'], 'Téléphone de l\'établissement');
                    $systemParams->update('email', $_POST['email'], 'Email de l\'établissement');
                    $systemParams->update('ville', $_POST['ville'], 'Ville de l\'établissement');
                    $message = "Identité de l'établissement mise à jour avec succès !";
                    $messageType = 'success';
                    break;
                    
                case 'update_currency':
                    $code = strtoupper(trim((string) ($_POST['devise_code'] ?? 'XOF')));
                    $systemParams->update('devise_code', $code, 'Code de la devise');
                    $systemParams->update('devise_symbole', $_POST['devise_symbole'], 'Symbole de la devise');
                    $systemParams->update('devise_decimaux', $_POST['devise_decimaux'], 'Nombre de décimales');
                    $conversion = isset($_POST['devise_conversion_actif']) ? '1' : '0';
                    $systemParams->update('devise_conversion_actif', $conversion, 'Conversion automatique depuis FCFA');
                    $message = "Configuration de la devise mise à jour !";
                    $messageType = 'success';
                    break;
                    
                case 'update_system':
                    $systemParams->update('langue', $_POST['langue'], 'Langue du système');
                    $systemParams->update('theme', $_POST['theme'], 'Thème de l\'interface');
                    $systemParams->update('timezone', $_POST['timezone'], 'Fuseau horaire');
                    $sonores = isset($_POST['notifications_sonores_actif']) ? '1' : '0';
                    $systemParams->update('notifications_sonores_actif', $sonores, 'Alertes sonores (patients & communication)');
                    $message = "Paramètres système mis à jour !";
                    $messageType = 'success';
                    break;

                case 'update_patients_settings':
                    $suppression = isset($_POST['patients_suppression_actif']) ? '1' : '0';
                    $systemParams->update('patients_suppression_actif', $suppression, 'Autoriser la suppression des patients');
                    $message = $suppression === '1'
                        ? 'La suppression des patients est activée.'
                        : 'La suppression des patients est désactivée.';
                    $messageType = 'success';
                    break;

                case 'update_medecins_settings':
                    $ajout = isset($_POST['secretaire_medecins_ajout_actif']) ? '1' : '0';
                    $systemParams->update('secretaire_medecins_ajout_actif', $ajout, 'Autoriser le secrétaire à ajouter des professionnels');
                    $message = $ajout === '1'
                        ? 'Le secrétaire peut désormais ajouter des professionnels dans le module Médecins.'
                        : 'Le secrétaire ne peut plus ajouter de professionnels.';
                    $messageType = 'success';
                    break;

                case 'update_thermal_printer':
                    $actif = isset($_POST['thermal_printer_actif']) ? '1' : '0';
                    $systemParams->update('thermal_printer_actif', $actif, 'Imprimante thermique active');
                    $systemParams->update('thermal_printer_ip', trim((string) ($_POST['thermal_printer_ip'] ?? '')), 'IP imprimante thermique');
                    $systemParams->update('thermal_printer_port', (string) max(1, (int) ($_POST['thermal_printer_port'] ?? 9100)), 'Port imprimante thermique');
                    $systemParams->update('thermal_printer_width_mm', (string) ($_POST['thermal_printer_width_mm'] ?? '80'), 'Largeur papier thermique');
                    $systemParams->update('thermal_printer_model', trim((string) ($_POST['thermal_printer_model'] ?? 'Xprinter XP-80TS')), 'Modèle imprimante thermique');
                    $message = 'Imprimante thermique configurée.';
                    $messageType = 'success';
                    break;
                    
                case 'upload_logo':
                    // Gérer l'upload du logo
                    require_once __DIR__ . '/../config/SystemParameters.php';
                    $systemParams = SystemParameters::getInstance();
                    
                    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                        $result = $systemParams->uploadLogo($_FILES['logo']);
                        if ($result['success']) {
                            $message = $result['message'];
                            $messageType = 'success';
                        } else {
                            $message = $result['message'];
                            $messageType = 'danger';
                        }
                    } else {
                        $message = "Erreur lors de l'upload du logo";
                        $messageType = 'danger';
                    }
                    break;
                    
                case 'remove_logo':
                    // Supprimer le logo
                    require_once __DIR__ . '/../config/SystemParameters.php';
                    $systemParams = SystemParameters::getInstance();
                    
                    if ($systemParams->removeLogo()) {
                        $message = "Logo supprimé avec succès";
                        $messageType = 'success';
                    } else {
                        $message = "Erreur lors de la suppression du logo";
                        $messageType = 'danger';
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $message = "Erreur : " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Paramètres de l'établissement connecté (isolés par tenant_id)
try {
    require_once __DIR__ . '/../config/SystemParameters.php';
    $systemParams = SystemParameters::getInstance();
    $nom_etablissement = $systemParams->get('nom_etablissement', 'Clinique et Hôpital');
    $adresse = $systemParams->get('adresse', '');
    $telephone = $systemParams->get('telephone', '');
    $email = $systemParams->get('email', '');
    $ville = $systemParams->get('ville', '');
    $devise_code = $systemParams->get('devise_code', 'XOF');
    $devise_symbole = $systemParams->get('devise_symbole', 'FCFA');
    $devise_decimaux = $systemParams->get('devise_decimaux', 0);
    $devise_conversion_actif = $systemParams->get('devise_conversion_actif', '0') === '1';
    require_once __DIR__ . '/../config/CurrencyConfig.php';
    $currencyPresets = CurrencyConfig::CURRENCY_PRESETS;
    $currencyPreview = $systemParams->formatCurrency(125000);
    $langue = $systemParams->get('langue', 'fr');
    $theme = $systemParams->get('theme', 'default');
    $timezone = $systemParams->get('timezone', 'Africa/Abidjan');
    $notifications_sonores_actif = $systemParams->get('notifications_sonores_actif', '1') === '1';
    $patients_suppression_actif = $systemParams->get('patients_suppression_actif', '1') === '1';
    $secretaire_medecins_ajout_actif = $systemParams->get('secretaire_medecins_ajout_actif', '0') === '1';
    $thermal_printer_actif = $systemParams->get('thermal_printer_actif', '1') === '1';
    $thermal_printer_ip = $systemParams->get('thermal_printer_ip', '');
    $thermal_printer_port = $systemParams->get('thermal_printer_port', '9100');
    $thermal_printer_width_mm = $systemParams->get('thermal_printer_width_mm', '80');
    $thermal_printer_model = $systemParams->get('thermal_printer_model', 'Xprinter XP-80TS');
} catch (Exception $e) {
    $message = "Erreur de base de données : " . $e->getMessage();
    $messageType = 'danger';
    $currencyPresets = class_exists('CurrencyConfig') ? CurrencyConfig::CURRENCY_PRESETS : [];
}

app_head('Paramètres', ['assets/css/app-parametres.css'], 'app-parametres-page');
app_layout_start(['active' => 'parametres', 'skip_page_header' => true]);
app_parametres_shell_start('general', 'Paramètres du système', 'Identité, devise, logo et configuration générale');
app_parametres_alert($message, $messageType);

require_once __DIR__ . '/../includes/tenant_permissions.php';
$tenantIdParam = (int) $auth->getTenantId();
$permsCustom = $tenantIdParam > 0 && TenantPermissions::tenantHasCustomizations($tenantIdParam);
?>

<div class="param-section mb-4">
    <div class="param-card border-primary">
        <div class="param-card-head param-card-head--blue"><i class="fas fa-user-shield"></i> Utilisateurs &amp; droits d'accès</div>
        <div class="param-card-body">
            <p class="text-muted small mb-3">
                En tant qu'administrateur de l'établissement, vous gérez les <strong>comptes utilisateurs</strong>
                (activer / désactiver) et les <strong>modules accessibles par profil</strong>
                (médecin, secrétaire, infirmier…).
                <?php if ($permsCustom): ?>
                <span class="badge bg-primary ms-1">Droits personnalisés</span>
                <?php else: ?>
                <span class="badge bg-secondary ms-1">Profils par défaut</span>
                <?php endif; ?>
            </p>
            <div class="param-shortcuts">
                <a href="<?= app_url('parametres/utilisateurs.php') ?>" class="param-shortcut"><i class="fas fa-users"></i>Comptes utilisateurs</a>
                <a href="<?= app_url('parametres/droits_acces.php') ?>" class="param-shortcut"><i class="fas fa-shield-alt"></i>Droits par profil</a>
                <a href="<?= app_url('parametres/import_donnees.php') ?>" class="param-shortcut"><i class="fas fa-file-import"></i>Import de données</a>
                <a href="<?= app_url('parametres/guide_utilisateurs.php') ?>" class="param-shortcut"><i class="fas fa-book"></i>Guide des rôles</a>
            </div>
            <hr class="my-4">
            <h6 class="mb-2"><i class="fas fa-user-injured me-1 text-primary"></i> Module patients</h6>
            <p class="text-muted small mb-3">
                Contrôlez si les utilisateurs peuvent supprimer des dossiers patients (archivage ou suppression définitive).
            </p>
            <form method="POST" class="mb-0">
                <input type="hidden" name="action" value="update_patients_settings">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="patients_suppression_actif" name="patients_suppression_actif" value="1"
                        <?= !empty($patients_suppression_actif) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="patients_suppression_actif">
                        Autoriser la suppression des patients
                    </label>
                </div>
                <p class="text-muted small mb-3">
                    <?php if (!empty($patients_suppression_actif)): ?>
                    <span class="badge bg-success">Activé</span> — Les boutons « Supprimer » sont visibles dans le module patients.
                    <?php else: ?>
                    <span class="badge bg-secondary">Désactivé</span> — Aucun utilisateur ne peut supprimer un patient. La restauration des patients déjà archivés reste possible.
                    <?php endif; ?>
                </p>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>Enregistrer</button>
            </form>
            <hr class="my-4">
            <h6 class="mb-2"><i class="fas fa-user-md me-1 text-primary"></i> Module médecins</h6>
            <p class="text-muted small mb-3">
                Autorisez ou non le <strong>secrétaire</strong> à créer de nouvelles fiches professionnelles (médecin, infirmier, laborantin…).
            </p>
            <form method="POST" class="mb-0">
                <input type="hidden" name="action" value="update_medecins_settings">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="secretaire_medecins_ajout_actif" name="secretaire_medecins_ajout_actif" value="1"
                        <?= !empty($secretaire_medecins_ajout_actif) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="secretaire_medecins_ajout_actif">
                        Autoriser le secrétaire à ajouter des professionnels
                    </label>
                </div>
                <p class="text-muted small mb-3">
                    <?php if (!empty($secretaire_medecins_ajout_actif)): ?>
                    <span class="badge bg-success">Activé</span> — Le secrétaire accède au module Médecins et peut créer des fiches. La modification et la suppression restent réservées à l'administrateur.
                    <?php else: ?>
                    <span class="badge bg-secondary">Désactivé</span> — Seul l'administrateur peut ajouter des professionnels.
                    <?php endif; ?>
                </p>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>Enregistrer</button>
            </form>
        </div>
    </div>
</div>

<div class="param-grid-2">
    <div class="param-section">
        <div class="param-card">
            <div class="param-card-head"><i class="fas fa-hospital"></i> Identité de l'établissement</div>
            <div class="param-card-body">
                <p class="text-muted small mb-3">
                    <i class="fas fa-lock me-1"></i>
                    Le nom et le logo de votre établissement ne peuvent être modifiés que par un <strong>administrateur</strong>.
                    La marque plateforme <strong><?= htmlspecialchars(function_exists('platform_name') ? platform_name() : 'Se.Santé') ?></strong> est gérée séparément
                    <?php if (function_exists('saas_is_platform_admin') && saas_is_platform_admin()): ?>
                    — <a href="<?= htmlspecialchars(app_url('admin_platform/branding.php')) ?>">Modifier le nom et le logo du site</a>.
                    <?php else: ?>.
                    <?php endif; ?>
                </p>
                <form method="POST">
                    <input type="hidden" name="action" value="update_identity">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nom_etablissement" class="form-label">Nom de l'établissement *</label>
                            <input type="text" class="form-control" id="nom_etablissement" name="nom_etablissement"
                                   value="<?= htmlspecialchars($nom_etablissement) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="ville" class="form-label">Ville</label>
                            <input type="text" class="form-control" id="ville" name="ville"
                                   value="<?= htmlspecialchars($ville) ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="adresse" class="form-label">Adresse complète</label>
                        <textarea class="form-control" id="adresse" name="adresse" rows="2"><?= htmlspecialchars($adresse) ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="telephone" class="form-label">Téléphone</label>
                            <input type="tel" class="form-control" id="telephone" name="telephone"
                                   value="<?= htmlspecialchars($telephone) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= htmlspecialchars($email) ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Enregistrer</button>
                </form>
            </div>
        </div>
    </div>

    <div class="param-section">
        <div class="param-card">
            <div class="param-card-head param-card-head--teal"><i class="fas fa-money-bill"></i> Devise</div>
            <div class="param-card-body">
                <p class="text-muted small mb-3"><?= htmlspecialchars(CurrencyConfig::getCurrencyHelpText()) ?></p>
                <form method="POST" id="currency-form">
                    <input type="hidden" name="action" value="update_currency">
                    <div class="mb-3">
                        <label for="devise_preset" class="form-label">Devise prédéfinie</label>
                        <select class="form-select" id="devise_preset">
                            <?php foreach ($currencyPresets as $presetCode => $preset): ?>
                            <option value="<?= htmlspecialchars($presetCode) ?>"
                                <?= strtoupper($devise_code) === $presetCode ? 'selected' : '' ?>>
                                <?= htmlspecialchars($preset['name'] . ' (' . $presetCode . ')') ?>
                            </option>
                            <?php endforeach; ?>
                            <option value="custom" <?= !isset($currencyPresets[strtoupper($devise_code)]) ? 'selected' : '' ?>>Personnalisée</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="devise_code" class="form-label">Code ISO</label>
                        <input type="text" class="form-control" id="devise_code" name="devise_code"
                               value="<?= htmlspecialchars($devise_code) ?>" required maxlength="8">
                        <small class="text-muted">Ex. : XOF, EUR, USD — stockage toujours en FCFA (XOF)</small>
                    </div>
                    <div class="mb-3">
                        <label for="devise_symbole" class="form-label">Symbole affiché</label>
                        <input type="text" class="form-control" id="devise_symbole" name="devise_symbole"
                               value="<?= htmlspecialchars($devise_symbole) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="devise_decimaux" class="form-label">Décimales</label>
                        <select class="form-select" id="devise_decimaux" name="devise_decimaux">
                            <option value="0" <?= (int) $devise_decimaux === 0 ? 'selected' : '' ?>>0 (FCFA)</option>
                            <option value="2" <?= (int) $devise_decimaux === 2 ? 'selected' : '' ?>>2 (EUR, USD…)</option>
                        </select>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="devise_conversion_actif"
                               name="devise_conversion_actif" value="1"
                               <?= $devise_conversion_actif ? 'checked' : '' ?>>
                        <label class="form-check-label" for="devise_conversion_actif">
                            Convertir les montants FCFA en devise d'affichage
                        </label>
                        <div class="form-text">Désactivé : les montants restent affichés en FCFA même si le symbole change.</div>
                    </div>
                    <div class="alert alert-light border mb-3">
                        <strong>Aperçu :</strong> 125&nbsp;000 FCFA stockés → <span id="currency-preview"><?= htmlspecialchars($currencyPreview) ?></span>
                    </div>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save me-2"></i>Enregistrer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="param-section">
    <div class="param-card">
        <div class="param-card-head"><i class="fas fa-cog"></i> Paramètres système</div>
        <div class="param-card-body">
            <form method="POST">
                <input type="hidden" name="action" value="update_system">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="langue" class="form-label">Langue</label>
                        <select class="form-select" id="langue" name="langue">
                            <option value="fr" <?= $langue == 'fr' ? 'selected' : '' ?>>Français</option>
                            <option value="en" <?= $langue == 'en' ? 'selected' : '' ?>>English</option>
                            <option value="es" <?= $langue == 'es' ? 'selected' : '' ?>>Español</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="theme" class="form-label">Thème</label>
                        <select class="form-select" id="theme" name="theme">
                            <option value="default" <?= $theme == 'default' ? 'selected' : '' ?>>Défaut</option>
                            <option value="dark" <?= $theme == 'dark' ? 'selected' : '' ?>>Sombre</option>
                            <option value="light" <?= $theme == 'light' ? 'selected' : '' ?>>Clair</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="timezone" class="form-label">Fuseau horaire</label>
                        <select class="form-select" id="timezone" name="timezone">
                            <option value="Africa/Abidjan" <?= $timezone == 'Africa/Abidjan' ? 'selected' : '' ?>>Afrique de l'Ouest</option>
                            <option value="Europe/Paris" <?= $timezone == 'Europe/Paris' ? 'selected' : '' ?>>Europe</option>
                            <option value="America/New_York" <?= $timezone == 'America/New_York' ? 'selected' : '' ?>>Amérique</option>
                        </select>
                    </div>
                </div>
                <hr class="my-3">
                <h6 class="mb-2"><i class="fas fa-volume-up me-1 text-primary"></i> Alertes sonores</h6>
                <p class="text-muted small mb-3">
                    Son distinct lors d'une <strong>assignation patient → médecin</strong> ou d'un <strong>nouveau message</strong> reçu (module Communication).
                </p>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="notifications_sonores_actif" name="notifications_sonores_actif" value="1"
                        <?= !empty($notifications_sonores_actif) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="notifications_sonores_actif">
                        Activer les notifications sonores
                    </label>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Enregistrer</button>
            </form>
        </div>
    </div>
</div>

<div class="param-section">
    <div class="param-card">
        <div class="param-card-head"><i class="fas fa-receipt"></i> Imprimante thermique — tickets patients</div>
        <div class="param-card-body">
            <p class="text-muted small mb-3">
                Compatible <strong>Xprinter XP-80TS</strong> et imprimantes ESC/POS 80 mm (USB + réseau).
                Branchez l'imprimante sur le réseau local et renseignez son <strong>adresse IP</strong> (port 9100 par défaut).
            </p>
            <form method="POST" class="mb-3">
                <input type="hidden" name="action" value="update_thermal_printer">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="thermal_printer_actif" name="thermal_printer_actif" value="1"
                        <?= !empty($thermal_printer_actif) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="thermal_printer_actif">Activer l'impression thermique automatique</label>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="thermal_printer_ip">Adresse IP réseau</label>
                        <input type="text" class="form-control" id="thermal_printer_ip" name="thermal_printer_ip"
                               placeholder="ex. 192.168.1.100" value="<?= htmlspecialchars((string) ($thermal_printer_ip ?? '')) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="thermal_printer_port">Port</label>
                        <input type="number" class="form-control" id="thermal_printer_port" name="thermal_printer_port"
                               value="<?= htmlspecialchars((string) ($thermal_printer_port ?? '9100')) ?>" min="1" max="65535">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="thermal_printer_width_mm">Largeur papier</label>
                        <select class="form-select" id="thermal_printer_width_mm" name="thermal_printer_width_mm">
                            <option value="80" <?= ($thermal_printer_width_mm ?? '80') == '80' ? 'selected' : '' ?>>80 mm (XP-80TS)</option>
                            <option value="58" <?= ($thermal_printer_width_mm ?? '') == '58' ? 'selected' : '' ?>>58 mm</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="thermal_printer_model">Modèle</label>
                        <input type="text" class="form-control" id="thermal_printer_model" name="thermal_printer_model"
                               value="<?= htmlspecialchars((string) ($thermal_printer_model ?? 'Xprinter XP-80TS')) ?>">
                    </div>
                </div>
                <div class="mt-3 d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Enregistrer</button>
                    <button type="button" class="btn btn-outline-dark" id="btnTestThermalPrinter">
                        <i class="fas fa-print me-1"></i>Imprimer page de test
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('btnTestThermalPrinter')?.addEventListener('click', function () {
    var btn = this;
    btn.disabled = true;
    fetch('<?= app_url('parametres/test_imprimante_thermique.php') ?>', { method: 'POST' })
        .then(function (r) { return r.json(); })
        .then(function (d) { alert(d.message || (d.success ? 'OK' : 'Erreur')); })
        .catch(function () { alert('Erreur réseau'); })
        .finally(function () { btn.disabled = false; });
});
</script>

<div class="param-section">
    <div class="param-card">
        <div class="param-card-head param-card-head--amber"><i class="fas fa-image"></i> Logo de l'établissement <span class="badge bg-secondary ms-1">Admin uniquement</span></div>
        <div class="param-card-body">
            <?php
            require_once __DIR__ . '/../config/SystemParameters.php';
            $systemParams = SystemParameters::getInstance();
            $logoPath = $systemParams->getLogoPath();
            ?>
            <div class="mb-3">
                <label class="form-label">Logo actuel</label>
                <?php if ($logoPath && file_exists($logoPath) && is_readable($logoPath)):
                    $storedRel = (string) $systemParams->get('logo_clinique');
                    $storedRel = str_replace('\\', '/', $storedRel);
                    $v = (int) @filemtime($logoPath);
                    if ($v < 1) $v = time();
                    $imgSrc = ($storedRel !== '' && strpos($storedRel, '..') === false && strpos($storedRel, 'uploads/logos/') === 0)
                        ? app_url($storedRel) . '?t=' . $v
                        : app_url('display_logo.php') . '?t=' . $v;
                ?>
                <div class="app-logo-adaptive-frame mb-3">
                    <img src="<?= htmlspecialchars($imgSrc) ?>" alt="Logo actuel" class="param-logo-preview">
                </div>
                <?php else: ?>
                <div class="alert alert-info mb-3">Aucun logo configuré. Uploadez une image ci-dessous.</div>
                <?php endif; ?>
            </div>

            <?php if ($logoPath && file_exists($logoPath)): ?>
            <form method="POST" class="mb-3 d-inline">
                <input type="hidden" name="action" value="remove_logo">
                <button type="submit" class="btn btn-outline-danger btn-sm"
                        onclick="return confirm('Supprimer le logo ?')">
                    <i class="fas fa-trash me-1"></i>Supprimer
                </button>
            </form>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_logo">
                <div class="mb-3">
                    <label for="logo" class="form-label">Nouveau logo</label>
                    <input type="file" class="form-control" id="logo" name="logo" accept="image/jpeg,image/png" required>
                    <small class="text-muted">JPG ou PNG, max 2 Mo — tous formats acceptés (carré, horizontal, vertical). Le système adapte l'affichage sans déformation.</small>
                </div>
                <div class="mb-3" id="file-preview" style="display:none">
                    <div id="preview-image" class="mb-2"></div>
                    <div id="preview-info" class="small text-muted"></div>
                </div>
                <button type="submit" class="btn btn-warning"><i class="fas fa-upload me-2"></i>Uploader</button>
            </form>
            <div class="param-info-box mt-3">
                <h6><i class="fas fa-info-circle me-1"></i> Utilisation</h6>
                <p class="mb-0">Le logo apparaît dans la barre latérale, les PDF, factures et documents. Les logos larges (ex. texte + symbole) sont affichés en entier grâce au redimensionnement proportionnel.</p>
            </div>
        </div>
    </div>
</div>

<div class="param-grid-2">
    <div class="param-section">
        <div class="param-card">
            <div class="param-card-head param-card-head--slate"><i class="fas fa-info-circle"></i> Informations système</div>
            <div class="param-card-body">
                <?php
                $mysqlVersion = 'indisponible';
                if (isset($pdo) && $pdo instanceof PDO) {
                    try { $mysqlVersion = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION); } catch (Exception $e) {}
                }
                ?>
                <ul class="list-unstyled mb-0 small">
                    <li class="mb-2"><strong>Application</strong><br><span class="text-muted"><?= htmlspecialchars(getNomEtablissement()) ?> v1.0</span></li>
                    <li class="mb-2"><strong>MySQL</strong><br><span class="text-muted"><?= htmlspecialchars($mysqlVersion) ?></span></li>
                    <li class="mb-2"><strong>PHP</strong><br><span class="text-muted"><?= PHP_VERSION ?></span></li>
                    <li><strong>Statut</strong><br><span class="badge bg-success">Opérationnel</span></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="param-section">
        <div class="param-card">
            <div class="param-card-head param-card-head--violet"><i class="fas fa-tags"></i> Tarification</div>
            <div class="param-card-body">
                <p class="text-muted small">Consultations, laboratoire, soins facturables et hospitalisation.</p>
                <div class="param-shortcuts">
                    <a href="<?= app_url('parametres/tarifs.php') ?>" class="param-shortcut"><i class="fas fa-money-bill-wave"></i>Tarifs</a>
                    <a href="<?= app_url('parametres/tarifs_laboratoire.php') ?>" class="param-shortcut"><i class="fas fa-flask"></i>Tarifs labo</a>
                    <a href="<?= app_url('parametres/soins.php') ?>" class="param-shortcut"><i class="fas fa-hand-holding-medical"></i>Soins</a>
                    <a href="<?= app_url('consultations/gestion_tarifs.php') ?>" class="param-shortcut"><i class="fas fa-bed"></i>Hospitalisation</a>
                </div>
            </div>
        </div>
    </div>
    <div class="param-section">
        <div class="param-card">
            <div class="param-card-head param-card-head--blue"><i class="fas fa-book"></i> Guide utilisateur</div>
            <div class="param-card-body">
                <p class="text-muted small mb-3">
                    Documentation complète en PDF pour les nouveaux utilisateurs : rôles, modules, rattachement des comptes et parcours métier.
                </p>
                <div class="param-shortcuts">
                    <a href="<?= app_url('parametres/guide_utilisateurs.php') ?>" class="param-shortcut"><i class="fas fa-book-open"></i>Consulter le guide</a>
                    <a href="<?= app_url('parametres/generer_guide_pdf.php') ?>" class="param-shortcut" target="_blank" rel="noopener"><i class="fas fa-file-pdf"></i>Télécharger PDF</a>
                </div>
            </div>
        </div>
    </div>
    <div class="param-section">
        <div class="param-card">
            <div class="param-card-head param-card-head--violet"><i class="fas fa-robot"></i> Intelligence artificielle</div>
            <div class="param-card-body">
                <?php
                require_once __DIR__ . '/../includes/PlatformAIConfig.php';
                require_once __DIR__ . '/../includes/MistralAIService.php';
                $mistralStatus = MistralAIService::getInstance();
                ?>
                <p class="text-muted small mb-2">
                    Mistral est configuré au niveau <strong>plateforme</strong> et partagé par tous les établissements.
                </p>
                <p class="small mb-3">
                    Statut :
                    <?php if ($mistralStatus->isActive()): ?>
                    <span class="badge bg-success">Actif</span>
                    <?php else: ?>
                    <span class="badge bg-secondary">Inactif / local uniquement</span>
                    <?php endif; ?>
                </p>
                <a href="<?= app_url('parametres/ia.php') ?>" class="param-shortcut"><i class="fas fa-info-circle"></i>Voir le statut IA</a>
                <?php if (function_exists('saas_is_platform_admin') && saas_is_platform_admin()): ?>
                <a href="<?= app_url('admin_platform/ia.php') ?>" class="param-shortcut"><i class="fas fa-cog"></i>Configurer (admin plateforme)</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('logo')?.addEventListener('change', function (e) {
    var file = e.target.files[0];
    var preview = document.getElementById('file-preview');
    var previewImage = document.getElementById('preview-image');
    var previewInfo = document.getElementById('preview-info');
    if (!file || !preview) return;
    previewInfo.textContent = file.name + ' — ' + (file.size / 1024).toFixed(2) + ' KB';
    if (file.type.startsWith('image/')) {
        var reader = new FileReader();
        reader.onload = function (ev) {
            previewImage.innerHTML = '<div class="app-logo-adaptive-frame"><img src="' + ev.target.result + '" class="param-logo-preview" alt="Aperçu"></div>';
        };
        reader.readAsDataURL(file);
    }
    preview.style.display = 'block';
});

(function () {
    var presets = <?= json_encode($currencyPresets ?? [], JSON_UNESCAPED_UNICODE) ?>;
    var presetSelect = document.getElementById('devise_preset');
    var codeInput = document.getElementById('devise_code');
    var symbolInput = document.getElementById('devise_symbole');
    var decimalsSelect = document.getElementById('devise_decimaux');
    var conversionCheck = document.getElementById('devise_conversion_actif');
    var previewEl = document.getElementById('currency-preview');
    if (!presetSelect || !codeInput) return;

    function applyPreset(code) {
        if (!presets[code]) return;
        codeInput.value = code;
        symbolInput.value = presets[code].symbol;
        decimalsSelect.value = String(presets[code].decimals);
        if (code === 'XOF') {
            conversionCheck.checked = false;
            conversionCheck.disabled = true;
        } else {
            conversionCheck.disabled = false;
        }
        updatePreview();
    }

    function updatePreview() {
        if (!previewEl || typeof appFormatMoney !== 'function') return;
        var base = 125000;
        var c = window.APP_CURRENCY || {};
        window.APP_CURRENCY = {
            code: codeInput.value.toUpperCase(),
            symbol: symbolInput.value,
            decimals: parseInt(decimalsSelect.value, 10) || 0,
            conversion: conversionCheck.checked && codeInput.value.toUpperCase() !== 'XOF',
            base: 'XOF',
            rateFromBase: (c.rateFromBase && codeInput.value.toUpperCase() === c.code) ? c.rateFromBase : (<?= json_encode(CurrencyConfig::EXCHANGE_RATES) ?>[codeInput.value.toUpperCase()] || 1)
        };
        previewEl.textContent = appFormatMoney(base);
    }

    presetSelect.addEventListener('change', function () {
        if (this.value === 'custom') return;
        applyPreset(this.value);
    });
    [codeInput, symbolInput, decimalsSelect, conversionCheck].forEach(function (el) {
        el.addEventListener('input', updatePreview);
        el.addEventListener('change', updatePreview);
    });
    if (codeInput.value.toUpperCase() === 'XOF') {
        conversionCheck.disabled = true;
    }
})();
</script>

<?php
app_parametres_shell_end();
app_layout_end(['minimal_scripts' => true]);
