<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_layout.php';
require_once __DIR__ . '/../includes/app_parametres_layout.php';
require_once __DIR__ . '/../includes/user_guide_content.php';

app_parametres_require_user();
extract(app_prepare_context());

$meta = user_guide_meta();
$chapters = user_guide_onboarding();
$roleRows = user_guide_roles_table_rows();
$isAdmin = $auth->estAdmin();

$pdfUrl = app_url('parametres/generer_guide_pdf.php');
$docPublicUrl = app_url('documentation.php');

$actionsHtml = '<a href="' . htmlspecialchars($pdfUrl) . '" class="btn btn-danger btn-sm" target="_blank" rel="noopener">'
    . '<i class="fas fa-file-pdf me-1"></i>Télécharger le PDF</a>';

app_head('Guide utilisateur', ['assets/css/app-parametres.css'], 'app-parametres-page');
app_layout_start(['active' => $isAdmin ? 'parametres' : 'guide', 'skip_page_header' => true]);
app_parametres_shell_start(
    'guide',
    'Guide utilisateur',
    'Documentation complète pour démarrer sur ' . $meta['platform'],
    $actionsHtml
);
?>

<div class="param-card mb-4">
    <div class="param-card-body">
        <div class="row align-items-center g-3">
            <div class="col-md-8">
                <h2 class="h5 mb-2"><i class="fas fa-book-open text-primary me-2"></i>Bienvenue sur <?= htmlspecialchars($meta['etablissement']) ?></h2>
                <p class="text-muted mb-2">
                    Ce guide explique les rôles, les modules, le rattachement des comptes et les parcours métier.
                    Idéal pour les nouveaux arrivants et comme référence PDF hors ligne.
                </p>
                <p class="small text-muted mb-0">
                    <i class="fas fa-calendar me-1"></i>Mise à jour : <?= htmlspecialchars($meta['date']) ?>
                    · Version <?= htmlspecialchars($meta['version']) ?>
                    · Devise : <?= htmlspecialchars($meta['devise']) ?>
                </p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="<?= htmlspecialchars($pdfUrl) ?>" class="btn btn-danger btn-lg w-100 w-md-auto" target="_blank" rel="noopener">
                    <i class="fas fa-file-pdf me-2"></i>Télécharger le guide PDF
                </a>
                <a href="<?= htmlspecialchars($docPublicUrl) ?>" class="btn btn-outline-secondary btn-sm mt-2 w-100 w-md-auto" target="_blank" rel="noopener">
                    <i class="fas fa-external-link-alt me-1"></i>Documentation publique
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="alert alert-info text-center mb-0 h-100">
            <div class="display-6 mb-0"><?= count($chapters) ?></div>
            <small>Chapitres</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="alert alert-success text-center mb-0 h-100">
            <div class="display-6 mb-0"><?= (int) doc_module_count() ?></div>
            <small>Modules documentés</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="alert alert-primary text-center mb-0 h-100">
            <div class="display-6 mb-0"><?= count($roleRows) ?></div>
            <small>Rôles métier</small>
        </div>
    </div>
</div>

<div class="param-card mb-4">
    <div class="param-card-head param-card-head--blue"><i class="fas fa-users"></i> Rôles et modules</div>
    <div class="param-card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Rôle</th>
                        <th>Description</th>
                        <th>Modules accessibles</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roleRows as $row): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['role']) ?></strong></td>
                        <td class="small"><?= htmlspecialchars($row['access']) ?></td>
                        <td class="small text-muted"><?= htmlspecialchars($row['modules']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="param-card">
    <div class="param-card-head param-card-head--violet"><i class="fas fa-list-ol"></i> Sommaire du guide PDF</div>
    <div class="param-card-body">
        <div class="guide-chapters" id="guideChapters">
            <?php foreach ($chapters as $i => $chapter): ?>
            <details class="guide-chapter"<?= $i === 0 ? ' open' : '' ?>>
                <summary id="ch-head-<?= $i ?>"><?= htmlspecialchars($chapter['title']) ?></summary>
                <div class="guide-chapter-body small" id="ch-body-<?= $i ?>">
                        <?php foreach ($chapter['blocks'] as $block): ?>
                            <?php if (($block['type'] ?? '') === 'p'): ?>
                                <p><?= htmlspecialchars($block['text'] ?? '') ?></p>
                            <?php elseif (($block['type'] ?? '') === 'ul'): ?>
                                <ul>
                                    <?php foreach ($block['items'] ?? [] as $item): ?>
                                        <li><?= htmlspecialchars((string) $item) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php elseif (($block['type'] ?? '') === 'roles_table'): ?>
                                <p class="text-muted">Voir le tableau des rôles ci-dessus.</p>
                            <?php elseif (($block['type'] ?? '') === 'modules_detail'): ?>
                                <?php foreach (doc_module_groups() as $group): ?>
                                    <p class="fw-semibold mb-1"><?= htmlspecialchars($group['title']) ?></p>
                                    <ul class="mb-3">
                                        <?php foreach ($group['items'] as $item): ?>
                                            <li><strong><?= htmlspecialchars($item['name']) ?> :</strong>
                                                <?= htmlspecialchars($item['desc']) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endforeach; ?>
                            <?php elseif (($block['type'] ?? '') === 'workflows'): ?>
                                <?php foreach (doc_workflows() as $wf): ?>
                                    <p class="fw-semibold mb-1"><?= htmlspecialchars($wf['title']) ?></p>
                                    <ol class="mb-3">
                                        <?php foreach ($wf['steps'] as $step): ?>
                                            <li><?= htmlspecialchars($step) ?></li>
                                        <?php endforeach; ?>
                                    </ol>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                </div>
            </details>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-4">
            <a href="<?= htmlspecialchars($pdfUrl) ?>" class="btn btn-danger" target="_blank" rel="noopener">
                <i class="fas fa-download me-2"></i>Télécharger la version PDF complète
            </a>
        </div>
    </div>
</div>

<?php
ob_start();
?>
<script>
(function () {
    var chapters = document.querySelectorAll('#guideChapters .guide-chapter');
    if (!chapters.length) return;
    chapters.forEach(function (details) {
        details.addEventListener('toggle', function () {
            if (!details.open) return;
            chapters.forEach(function (other) {
                if (other !== details) other.removeAttribute('open');
            });
        });
    });
})();
</script>
<?php
$GLOBALS['app_page_scripts'] = ($GLOBALS['app_page_scripts'] ?? '') . ob_get_clean();

app_parametres_shell_end();
app_layout_end();
?>
