<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('personnel'));

require_once __DIR__ . '/../models/Personnel.php';

$personnelModel = new Personnel();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$id) {
    header('Location: index.php');
    exit;
}

$joursOrdre = [
    'lundi' => 1, 'mardi' => 2, 'mercredi' => 3, 'jeudi' => 4,
    'vendredi' => 5, 'samedi' => 6, 'dimanche' => 7,
];
$joursLabels = [
    'lundi' => 'Lundi', 'mardi' => 'Mardi', 'mercredi' => 'Mercredi',
    'jeudi' => 'Jeudi', 'vendredi' => 'Vendredi', 'samedi' => 'Samedi', 'dimanche' => 'Dimanche',
];

try {
    $personnel = $personnelModel->getById($id);
    if (!$personnel) {
        header('Location: index.php');
        exit;
    }
    $horaires = $personnelModel->getHoraires($id);
    $conges = $personnelModel->getConges($id);
} catch (Exception $e) {
    die('Erreur: ' . htmlspecialchars($e->getMessage()));
}

usort($horaires, static function ($a, $b) use ($joursOrdre) {
    $ja = strtolower((string) ($a['jour_semaine'] ?? ''));
    $jb = strtolower((string) ($b['jour_semaine'] ?? ''));
    return ($joursOrdre[$ja] ?? 99) <=> ($joursOrdre[$jb] ?? 99);
});

$congesEnAttente = 0;
$joursCongesApprouvesAnnee = 0;
$anneeCourante = (int) date('Y');
foreach ($conges as $c) {
    if (($c['statut'] ?? '') === 'en_attente') {
        $congesEnAttente++;
    }
    if (($c['statut'] ?? '') === 'approuve' && isset($c['date_debut']) && (int) date('Y', strtotime($c['date_debut'])) === $anneeCourante) {
        $joursCongesApprouvesAnnee += (int) ($c['nombre_jours'] ?? 0);
    }
}

$anciennete = null;
if (!empty($personnel['date_embauche'])) {
    try {
        $emb = new DateTime($personnel['date_embauche']);
        $anciennete = $emb->diff(new DateTime());
    } catch (Exception $e) {
        $anciennete = null;
    }
}

$photoUrl = null;
if (!empty($personnel['photo']) && is_string($personnel['photo'])) {
    $rel = trim($personnel['photo']);
    if ($rel !== '' && strpos($rel, '..') === false) {
        $rel = ltrim(str_replace('\\', '/', $rel), '/');
        $full = dirname(__DIR__) . '/' . $rel;
        if (is_readable($full)) {
            $photoUrl = '../' . $rel;
        }
    }
}

$initiales = strtoupper(mb_substr((string) ($personnel['prenom'] ?? ''), 0, 1, 'UTF-8') . mb_substr((string) ($personnel['nom'] ?? ''), 0, 1, 'UTF-8'));
if (function_exists('mb_substr') === false) {
    $initiales = strtoupper(substr((string) ($personnel['prenom'] ?? ''), 0, 1) . substr((string) ($personnel['nom'] ?? ''), 0, 1));
}

$sexeLabel = [
    'M' => 'Homme', 'F' => 'Femme', 'm' => 'Homme', 'f' => 'Femme',
    'homme' => 'Homme', 'femme' => 'Femme',
];
$succesModif = isset($_GET['success']) && $_GET['success'] === '1';

?>
<?php
app_module_page_start([
    'active'   => 'personnel',
    'title'    => $personnel['prenom'] . ' ' . $personnel['nom'],
    'subtitle' => 'Fiche du personnel',
    'icon'     => 'fa-user-tie',
]);
app_module_back_toolbar(app_url('personnel/index.php'), 'Retour à la liste', [
    ['href' => app_url('personnel/modifier.php?id=' . $id), 'label' => 'Modifier', 'icon' => 'fa-edit', 'class' => 'btn-outline-primary'],
    ['href' => app_url('personnel/conges.php?id=' . $id), 'label' => 'Congés', 'icon' => 'fa-calendar-alt', 'class' => 'btn-outline-warning'],
    ['href' => app_url('personnel/horaires.php?id=' . $id), 'label' => 'Horaires', 'icon' => 'fa-clock', 'class' => 'btn-outline-info']
]);
app_module_flash();
?>
<style>
        :root {
            --personnel-gradient: linear-gradient(135deg, #4f46e5 0%, #7c3aed 55%, #a855f7 100%);
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }
        
        .wrap { max-width: 1320px; margin: 0 auto; padding: 1rem 1rem 2.5rem; }
        .hero {
            background: var(--personnel-gradient);
            color: #fff;
            border-radius: 20px;
            padding: 1.75rem 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 12px 40px rgba(79, 70, 229, 0.35);
        }
        .hero-avatar {
            width: 88px; height: 88px; border-radius: 20px;
            background: rgba(255,255,255,0.2);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.75rem; font-weight: 700;
            border: 3px solid rgba(255,255,255,0.45);
            overflow: hidden; flex-shrink: 0;
        }
        .hero-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .stat-mini {
            background: #fff;
            border-radius: 14px;
            padding: 1rem 1.1rem;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(0,0,0,0.04);
            height: 100%;
        }
        .stat-mini h4 { font-size: 1.35rem; font-weight: 700; margin: 0; color: #1e293b; }
        .stat-mini p { margin: 0.25rem 0 0; font-size: 0.8rem; color: #64748b; font-weight: 500; }
        .card-pro { border: none; border-radius: 16px; box-shadow: var(--card-shadow); margin-bottom: 1.25rem; }
        .card-pro .card-header {
            border: none;
            border-radius: 16px 16px 0 0 !important;
            font-weight: 600;
            padding: 0.9rem 1.25rem;
        }
        .dl-row { display: grid; grid-template-columns: minmax(140px, 38%) 1fr; gap: 0.35rem 1rem; padding: 0.55rem 0; border-bottom: 1px solid #f1f5f9; font-size: 0.95rem; }
        .dl-row:last-child { border-bottom: none; }
        .dl-row dt { color: #64748b; font-weight: 500; margin: 0; }
        .dl-row dd { margin: 0; color: #0f172a; }
        .table-h { font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em; color: #64748b; }
        .badge-soft { font-weight: 600; }
    </style>

<div class="wrap">
        <?php if ($succesModif): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
                <i class="fas fa-check-circle me-2"></i>Les informations ont été enregistrées avec succès.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="hero">
            <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center gap-3 justify-content-between">
                <div class="d-flex gap-3 align-items-center">
                    <div class="hero-avatar">
                        <?php if ($photoUrl): ?>
                            <img src="<?php echo htmlspecialchars($photoUrl); ?>" alt="">
                        <?php else: ?>
                            <?php echo htmlspecialchars($initiales); ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <nav class="small opacity-75 mb-1">
                            <a href="index.php" class="text-white text-decoration-none">Personnel</a>
                            <span class="mx-1">/</span>
                            <span>Fiche</span>
                        </nav>
                        <h1 class="h3 mb-1 fw-bold"><?php echo htmlspecialchars($personnel['prenom'] . ' ' . $personnel['nom']); ?></h1>
                        <p class="mb-0 opacity-90">
                            <span class="badge bg-white text-primary me-1"><?php echo htmlspecialchars($personnel['numero_employe']); ?></span>
                            <?php echo htmlspecialchars($personnel['poste'] ?? ''); ?>
                            <?php if (!empty($personnel['departement'])): ?>
                                <span class="opacity-75"> · </span><?php echo htmlspecialchars($personnel['departement']); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="../index.php" class="btn btn-light btn-sm"><i class="fas fa-home me-1"></i>Accueil</a>
                    <a href="index.php" class="btn btn-outline-light btn-sm"><i class="fas fa-list me-1"></i>Liste</a>
                    <a href="modifier.php?id=<?php echo (int) $personnel['id']; ?>" class="btn btn-warning btn-sm text-dark fw-semibold">
                        <i class="fas fa-edit me-1"></i>Modifier
                    </a>
                    <a href="horaires.php?id=<?php echo (int) $personnel['id']; ?>" class="btn btn-info btn-sm text-white">
                        <i class="fas fa-clock me-1"></i>Horaires
                    </a>
                    <a href="conge_ajouter.php?id=<?php echo (int) $personnel['id']; ?>" class="btn btn-light btn-sm">
                        <i class="fas fa-calendar-plus me-1"></i>Congé
                    </a>
                    <?php if ($auth->estAdmin()): ?>
                    <a href="supprimer.php?id=<?php echo (int) $personnel['id']; ?>" class="btn btn-outline-light btn-sm border-white text-white" title="Désactiver le compte">
                        <i class="fas fa-user-slash me-1"></i>Désactiver
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="stat-mini">
                    <h4>
                        <span class="badge bg-<?php echo ($personnel['statut'] ?? '') === 'actif' ? 'success' : 'secondary'; ?> badge-soft">
                            <?php echo htmlspecialchars(ucfirst($personnel['statut'] ?? '—')); ?>
                        </span>
                    </h4>
                    <p>Statut</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-mini">
                    <h4><?php
                        if ($anciennete) {
                            if ($anciennete->y > 0) {
                                echo $anciennete->y . ' an' . ($anciennete->y > 1 ? 's' : '');
                            } elseif ($anciennete->m > 0) {
                                echo $anciennete->m . ' mois';
                            } else {
                                echo $anciennete->d . ' j.';
                            }
                        } else {
                            echo '—';
                        }
?></h4>
                    <p>Ancienneté<?php
                        echo $anciennete && $anciennete->y > 0 && ($anciennete->m > 0 || $anciennete->d > 0)
                            ? ' · ' . $anciennete->m . ' m. ' . $anciennete->d . ' j.'
                            : '';
?></p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-mini">
                    <h4><?php echo count($horaires) ? count($horaires) . ' j.' : '0'; ?></h4>
                    <p>Jours planifiés (horaires)</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-mini">
                    <h4 class="text-<?php echo $congesEnAttente > 0 ? 'warning' : 'dark'; ?>"><?php echo (int) $congesEnAttente; ?></h4>
                    <p>Congés en attente · <?php echo (int) $joursCongesApprouvesAnnee; ?> j. approuvés (<?php echo $anneeCourante; ?>)</p>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card card-pro">
                    <div class="card-header bg-primary text-white">
                        <i class="fas fa-id-card me-2"></i>Identité & contrat
                    </div>
                    <div class="card-body p-0 px-3 py-2">
                        <dl class="mb-0">
                            <div class="dl-row"><dt>Numéro employé</dt><dd><span class="badge bg-info text-dark"><?php echo htmlspecialchars($personnel['numero_employe']); ?></span></dd></div>
                            <div class="dl-row"><dt>Date de naissance</dt><dd><?php echo !empty($personnel['date_naissance']) ? date('d/m/Y', strtotime($personnel['date_naissance'])) : '—'; ?><?php echo isset($personnel['age']) && $personnel['age'] !== null ? ' <span class="text-muted">(' . (int) $personnel['age'] . ' ans)</span>' : ''; ?></dd></div>
                            <div class="dl-row"><dt>Sexe</dt><dd><?php
                                $sx = $personnel['sexe'] ?? '';
echo htmlspecialchars($sexeLabel[$sx] ?? ($sx !== '' ? $sx : '—'));
?></dd></div>
                            <div class="dl-row"><dt>Poste</dt><dd><?php echo htmlspecialchars($personnel['poste'] ?? '—'); ?></dd></div>
                            <div class="dl-row"><dt>Département</dt><dd><?php echo htmlspecialchars($personnel['departement'] ?? '—'); ?></dd></div>
                            <div class="dl-row"><dt>Contrat</dt><dd><?php echo htmlspecialchars($personnel['type_contrat'] ?? '—'); ?></dd></div>
                            <div class="dl-row"><dt>Date d'embauche</dt><dd><?php echo !empty($personnel['date_embauche']) ? date('d/m/Y', strtotime($personnel['date_embauche'])) : '—'; ?></dd></div>
                            <div class="dl-row"><dt>Salaire</dt><dd><?php echo isset($personnel['salaire']) && $personnel['salaire'] !== '' && $personnel['salaire'] !== null ? number_format((float) $personnel['salaire'], 0, ',', ' ') . ' FCFA' : '—'; ?></dd></div>
                            <div class="dl-row"><dt>Pays</dt><dd><?php echo htmlspecialchars($personnel['pays'] ?? '—'); ?></dd></div>
                        </dl>
                    </div>
                </div>

                <div class="card card-pro">
                    <div class="card-header" style="background: linear-gradient(90deg, #0ea5e9, #0284c7); color: #fff;">
                        <i class="fas fa-address-book me-2"></i>Contact
                    </div>
                    <div class="card-body p-0 px-3 py-2">
                        <dl class="mb-0">
                            <div class="dl-row"><dt>Téléphone</dt><dd><?php
                                $tel = trim((string) ($personnel['telephone'] ?? ''));
if ($tel !== '') {
    echo '<a href="tel:' . htmlspecialchars(preg_replace('/\s+/', '', $tel)) . '">' . htmlspecialchars($tel) . '</a>';
} else {
    echo '—';
}
?></dd></div>
                            <div class="dl-row"><dt>Email</dt><dd><?php
                                $em = trim((string) ($personnel['email'] ?? ''));
if ($em !== '') {
    echo '<a href="mailto:' . htmlspecialchars($em) . '">' . htmlspecialchars($em) . '</a>';
} else {
    echo '—';
}
?></dd></div>
                            <div class="dl-row"><dt>Adresse</dt><dd><?php
                                $adr = array_filter([
    $personnel['adresse'] ?? '',
    trim(($personnel['code_postal'] ?? '') . ' ' . ($personnel['ville'] ?? '')),
]);
echo !empty($adr) ? nl2br(htmlspecialchars(implode("\n", array_map('trim', $adr)))) : '—';
?></dd></div>
                        </dl>
                    </div>
                </div>

                <?php if (!empty($personnel['notes'])): ?>
                <div class="card card-pro">
                    <div class="card-header bg-secondary text-white">
                        <i class="fas fa-sticky-note me-2"></i>Notes internes
                    </div>
                    <div class="card-body">
                        <div class="text-muted small" style="white-space: pre-wrap;"><?php echo htmlspecialchars($personnel['notes']); ?></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-4">
                <div class="card card-pro mb-3">
                    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span><i class="fas fa-clock me-2"></i>Horaires</span>
                        <a href="horaires.php?id=<?php echo (int) $personnel['id']; ?>" class="btn btn-sm btn-light text-info fw-semibold">
                            <?php echo empty($horaires) ? 'Définir' : 'Modifier'; ?>
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (isset($_GET['horaires']) && $_GET['horaires'] === '1'): ?>
                            <div class="alert alert-success py-2 px-3 mb-0 rounded-0 border-0 small">
                                <i class="fas fa-check-circle me-1"></i>Horaires enregistrés.
                            </div>
                        <?php endif; ?>
                        <?php if (empty($horaires)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="fas fa-calendar-week fa-2x mb-2 opacity-50"></i>
                                <p class="mb-2 small">Aucun horaire défini.</p>
                                <a href="horaires.php?id=<?php echo (int) $personnel['id']; ?>" class="btn btn-sm btn-primary">Configurer</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0 small">
                                    <thead class="table-light">
                                        <tr class="table-h">
                                            <th>Jour</th>
                                            <th>Plage</th>
                                            <th>Pause</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($horaires as $horaire): ?>
                                            <?php
                                            $jk = strtolower((string) ($horaire['jour_semaine'] ?? ''));
                                            $jLabel = $joursLabels[$jk] ?? ucfirst($horaire['jour_semaine'] ?? '');
                                            ?>
                                            <tr>
                                                <td class="fw-semibold"><?php echo htmlspecialchars($jLabel); ?></td>
                                                <td><?php echo date('H:i', strtotime($horaire['heure_debut'])); ?> – <?php echo date('H:i', strtotime($horaire['heure_fin'])); ?></td>
                                                <td class="text-muted">
                                                    <?php if (!empty($horaire['pause_debut']) && !empty($horaire['pause_fin'])): ?>
                                                        <?php echo date('H:i', strtotime($horaire['pause_debut'])); ?>–<?php echo date('H:i', strtotime($horaire['pause_fin'])); ?>
                                                    <?php else: ?>
                                                        —
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card card-pro">
                    <div class="card-header text-dark d-flex justify-content-between align-items-center flex-wrap gap-2" style="background: linear-gradient(90deg, #fde68a, #fcd34d);">
                        <span><i class="fas fa-umbrella-beach me-2"></i>Congés</span>
                        <a href="conge_ajouter.php?id=<?php echo (int) $personnel['id']; ?>" class="btn btn-sm btn-dark">
                            <i class="fas fa-plus me-1"></i>Ajouter
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($conges)): ?>
                            <div class="p-4 text-center text-muted">
                                <p class="mb-2 small">Aucun congé enregistré.</p>
                                <a href="conge_ajouter.php?id=<?php echo (int) $personnel['id']; ?>" class="btn btn-sm btn-outline-warning">Déclarer un congé</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive" style="max-height: 340px;">
                                <table class="table table-sm table-hover mb-0 small">
                                    <thead class="table-light sticky-top">
                                        <tr class="table-h">
                                            <th>Type</th>
                                            <th>Période</th>
                                            <th class="text-center">J.</th>
                                            <th>État</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($conges as $conge): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $conge['type_conge'] ?? ''))); ?></td>
                                                <td>
                                                    <?php echo date('d/m/y', strtotime($conge['date_debut'])); ?> →
                                                    <?php echo date('d/m/y', strtotime($conge['date_fin'])); ?>
                                                </td>
                                                <td class="text-center"><?php echo (int) ($conge['nombre_jours'] ?? 0); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php
                                                    echo ($conge['statut'] ?? '') === 'approuve' ? 'success' : (($conge['statut'] ?? '') === 'refuse' ? 'danger' : 'warning');
                                                    ?>">
                                                        <?php echo htmlspecialchars(ucfirst($conge['statut'] ?? '')); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="p-2 border-top bg-light text-center">
                                <a href="conges.php?id=<?php echo (int) $personnel['id']; ?>" class="btn btn-sm btn-outline-secondary w-100">
                                    <i class="fas fa-list me-1"></i>Tous les congés
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php app_module_page_end(); ?>
