<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_layout.php';
require_once __DIR__ . '/../includes/app_platform_layout.php';
require_once __DIR__ . '/../includes/platform_brand.php';
require_once __DIR__ . '/../includes/PlatformBranding.php';
require_once __DIR__ . '/_handlers.php';

app_platform_require_admin();
$postResult = admin_platform_handle_post();
extract(app_prepare_platform_context());
extract($postResult);

$platformName = platform_name();
$logoPath = platform_logo_path();
$logoUrl = platform_logo_url();
$hasCustomLogo = PlatformBranding::getCustomLogoPath() !== null;
$defaultLogo = defined('PLATFORM_LOGO') ? (string) PLATFORM_LOGO : 'assets/images/brand/sesante-logo.png';

app_head('Marque plateforme', ['assets/css/app-platform.css'], 'app-platform-page');
app_layout_start(['active' => 'platform', 'skip_page_header' => true]);
app_platform_shell_start(
    'branding',
    'Marque & logo du système',
    'Nom et logo affichés sur le site public, la page de connexion et la documentation'
);
app_platform_alert($message, $messageType);
?>

<div class="platform-grid-2">
    <div class="platform-card">
        <div class="platform-card-head">
            <span><i class="fas fa-signature"></i>Nom du système</span>
        </div>
        <div class="platform-card-body">
            <p class="text-muted small mb-3">
                Ce nom apparaît sur la page d'accueil, la connexion, les tarifs et la documentation publique.
                Il est distinct du nom de chaque établissement abonné.
            </p>
            <form method="POST">
                <input type="hidden" name="update_platform_branding" value="1">
                <div class="mb-3">
                    <label for="platform_name" class="form-label">Nom de la plateforme *</label>
                    <input type="text" class="form-control" id="platform_name" name="platform_name"
                           value="<?= htmlspecialchars($platformName) ?>" maxlength="120" required>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i>Enregistrer le nom
                </button>
            </form>
        </div>
    </div>

    <div class="platform-card">
        <div class="platform-card-head">
            <span><i class="fas fa-image"></i>Logo du site</span>
        </div>
        <div class="platform-card-body">
            <div class="platform-brand-preview mb-3">
                <div class="platform-brand-preview-box">
                    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= htmlspecialchars($platformName) ?>" class="platform-brand-preview-img">
                </div>
                <div class="small text-muted mt-2">
                    <?php if ($hasCustomLogo): ?>
                    Logo personnalisé — <code><?= htmlspecialchars($logoPath) ?></code>
                    <?php else: ?>
                    Logo par défaut — <code><?= htmlspecialchars($defaultLogo) ?></code>
                    <?php endif; ?>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data" class="mb-3">
                <input type="hidden" name="upload_platform_logo" value="1">
                <div class="mb-3">
                    <label for="platform_logo" class="form-label">Nouveau logo</label>
                    <input type="file" class="form-control" id="platform_logo" name="platform_logo" accept="image/jpeg,image/png" required>
                    <small class="text-muted">JPG ou PNG, max 2 Mo — carré, horizontal ou vertical (affichage adaptatif)</small>
                </div>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-upload me-1"></i>Uploader le logo
                </button>
            </form>

            <?php if ($hasCustomLogo): ?>
            <form method="POST" onsubmit="return confirm('Réinitialiser le logo personnalisé ?');">
                <input type="hidden" name="remove_platform_logo" value="1">
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="fas fa-undo me-1"></i>Réinitialiser au logo par défaut
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="platform-card">
    <div class="platform-card-head">
        <span><i class="fas fa-eye"></i>Aperçu des emplacements</span>
    </div>
    <div class="platform-card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="platform-brand-slot">
                    <div class="platform-brand-slot-label">Navigation publique</div>
                    <?= platform_brand_html('nav') ?>
                </div>
            </div>
            <div class="col-md-4">
                <div class="platform-brand-slot platform-brand-slot--login">
                    <div class="platform-brand-slot-label">Page de connexion</div>
                    <?= platform_brand_html('login') ?>
                </div>
            </div>
            <div class="col-md-4">
                <div class="platform-brand-slot platform-brand-slot--footer">
                    <div class="platform-brand-slot-label">Pied de page</div>
                    <?= platform_brand_html('footer') ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
app_platform_shell_end();
app_layout_end(['minimal_scripts' => true]);
