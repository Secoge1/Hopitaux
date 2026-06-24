<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_layout.php';
require_once __DIR__ . '/../includes/app_parametres_layout.php';
require_once __DIR__ . '/../includes/app_module_list.php';
require_once __DIR__ . '/../includes/roles.php';
require_once __DIR__ . '/../includes/staff_link.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Utilisateur.php';

/** @return ?int */
function utilisateurs_parse_staff_link_id(): ?int
{
    $raw = $_POST['staff_link_id'] ?? '';
    if ($raw === '' || $raw === '0') {
        return null;
    }
    $id = (int) $raw;
    return $id > 0 ? $id : null;
}

function utilisateurs_user_needs_link(array $user): bool
{
    return StaffLink::roleNeedsLink($user['role'] ?? '');
}

function utilisateurs_user_is_linked(int $userId, array $staffLinksByUser): bool
{
    return !empty($staffLinksByUser[$userId]['label']);
}

/** @return list<array<string, mixed>> */
function utilisateurs_filter_list(array $users, array $staffLinksByUser, array $filters): array
{
    return array_values(array_filter($users, static function ($u) use ($staffLinksByUser, $filters) {
        $id = (int) $u['id'];
        if ($filters['q'] !== '') {
            $q = mb_strtolower($filters['q']);
            $hay = mb_strtolower(
                ($u['nom_utilisateur'] ?? '') . ' '
                . ($u['email'] ?? '') . ' '
                . ($staffLinksByUser[$id]['label'] ?? '')
            );
            if (mb_strpos($hay, $q) === false) {
                return false;
            }
        }
        if ($filters['role'] !== '' && ($u['role'] ?? '') !== $filters['role']) {
            return false;
        }
        if ($filters['statut'] !== '' && ($u['statut'] ?? '') !== $filters['statut']) {
            return false;
        }
        if ($filters['liaison'] === 'non_rattache') {
            if (!utilisateurs_user_needs_link($u) || utilisateurs_user_is_linked($id, $staffLinksByUser)) {
                return false;
            }
        }
        if ($filters['liaison'] === 'rattache') {
            if (!utilisateurs_user_needs_link($u) || !utilisateurs_user_is_linked($id, $staffLinksByUser)) {
                return false;
            }
        }
        return true;
    }));
}

function utilisateurs_count_non_rattache(array $users, array $staffLinksByUser): int
{
    $n = 0;
    foreach ($users as $u) {
        $id = (int) $u['id'];
        if (utilisateurs_user_needs_link($u) && !utilisateurs_user_is_linked($id, $staffLinksByUser)) {
            $n++;
        }
    }
    return $n;
}

/** @return array{liaison: string, role: string, statut: string, q: string} */
function utilisateurs_parse_filters(): array
{
    $liaison = $_GET['liaison'] ?? '';
    if (!in_array($liaison, ['', 'non_rattache', 'rattache'], true)) {
        $liaison = '';
    }
    $role = $_GET['role'] ?? '';
    if ($role !== '' && !app_role_is_valid($role)) {
        $role = '';
    }
    $statut = $_GET['statut'] ?? '';
    if (!in_array($statut, ['', 'actif', 'inactif'], true)) {
        $statut = '';
    }
    return [
        'liaison' => $liaison,
        'role'    => $role,
        'statut'  => $statut,
        'q'       => trim($_GET['q'] ?? ''),
    ];
}

app_parametres_require_admin();
extract(app_prepare_context());

$database = new Database();
$db = $database->getConnection();
$utilisateurModel = new Utilisateur($db);

$message = '';
$message_type = '';

// Traitement des actions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'creer':
                $nom_utilisateur = trim($_POST['nom_utilisateur']);
                $email = trim($_POST['email']);
                $mot_de_passe = $_POST['mot_de_passe'];
                $role = $_POST['role'];
                $statut = $_POST['statut'];
                
                if (empty($nom_utilisateur) || empty($email) || empty($mot_de_passe)) {
                    $message = "Tous les champs sont obligatoires.";
                    $message_type = "danger";
                } elseif ($utilisateurModel->emailExiste($email)) {
                    $message = "Cette adresse email est déjà utilisée.";
                    $message_type = "danger";
                } elseif ($utilisateurModel->nomUtilisateurExiste($nom_utilisateur)) {
                    $message = "Ce nom d'utilisateur est déjà utilisé.";
                    $message_type = "danger";
                } elseif (!app_role_is_valid($role)) {
                    $message = "Rôle invalide.";
                    $message_type = "danger";
                } else {
                    $newId = $utilisateurModel->creer($nom_utilisateur, $email, $mot_de_passe, $role, $statut);
                    if ($newId) {
                        $linkResult = StaffLink::syncForUser((int) $newId, $role, utilisateurs_parse_staff_link_id());
                        $message = "Utilisateur créé avec succès.";
                        if (!$linkResult['ok']) {
                            $message .= ' ' . $linkResult['message'];
                            $message_type = 'warning';
                        } elseif (StaffLink::roleNeedsLink($role) && utilisateurs_parse_staff_link_id() === null) {
                            $message .= ' Pensez à rattacher une fiche médecin ou personnel pour limiter l\'accès aux données.';
                            $message_type = 'warning';
                        } else {
                            $message_type = 'success';
                        }
                    } else {
                        $message = "Erreur lors de la création de l'utilisateur.";
                        $message_type = "danger";
                    }
                }
                break;
                
            case 'modifier':
                $id = $_POST['id'];
                $nom_utilisateur = trim($_POST['nom_utilisateur']);
                $email = trim($_POST['email']);
                $role = $_POST['role'];
                $statut = $_POST['statut'];
                
                if (empty($nom_utilisateur) || empty($email)) {
                    $message = "Tous les champs sont obligatoires.";
                    $message_type = "danger";
                } elseif ($utilisateurModel->emailExiste($email, $id)) {
                    $message = "Cette adresse email est déjà utilisée.";
                    $message_type = "danger";
                } elseif ($utilisateurModel->nomUtilisateurExiste($nom_utilisateur, $id)) {
                    $message = "Ce nom d'utilisateur est déjà utilisé.";
                    $message_type = "danger";
                } elseif (!app_role_is_valid($role)) {
                    $message = "Rôle invalide.";
                    $message_type = "danger";
                } else {
                    if ($utilisateurModel->modifier($id, $nom_utilisateur, $email, $role, $statut)) {
                        $linkResult = StaffLink::syncForUser((int) $id, $role, utilisateurs_parse_staff_link_id());
                        $message = "Utilisateur modifié avec succès.";
                        if (!$linkResult['ok']) {
                            $message .= ' ' . $linkResult['message'];
                            $message_type = 'warning';
                        } elseif (StaffLink::roleNeedsLink($role) && utilisateurs_parse_staff_link_id() === null) {
                            $message .= ' Aucune fiche métier liée : l\'utilisateur ne verra pas ses dossiers.';
                            $message_type = 'warning';
                        } else {
                            $message_type = 'success';
                        }
                    } else {
                        $message = "Erreur lors de la modification de l'utilisateur.";
                        $message_type = "danger";
                    }
                }
                break;
                
            case 'supprimer':
                $id = $_POST['id'];
                
                // Empêcher la suppression de son propre compte
                if ($id == $auth->getUserId()) {
                    $message = "Vous ne pouvez pas supprimer votre propre compte.";
                    $message_type = "danger";
                } else {
                    if ($utilisateurModel->supprimer($id)) {
                        $message = "Utilisateur supprimé avec succès.";
                        $message_type = "success";
                    } else {
                        $message = "Erreur lors de la suppression de l'utilisateur.";
                        $message_type = "danger";
                    }
                }
                break;
                
            case 'changer_mot_de_passe':
                $id = $_POST['id'];
                $nouveau_mot_de_passe = $_POST['nouveau_mot_de_passe'];
                
                if (empty($nouveau_mot_de_passe)) {
                    $message = "Le nouveau mot de passe est obligatoire.";
                    $message_type = "danger";
                } else {
                    if ($utilisateurModel->changerMotDePasse($id, $nouveau_mot_de_passe)) {
                        $message = "Mot de passe modifié avec succès.";
                        $message_type = "success";
                    } else {
                        $message = "Erreur lors de la modification du mot de passe.";
                        $message_type = "danger";
                    }
                }
                break;

            case 'toggle_statut':
                $id = (int) ($_POST['id'] ?? 0);
                if ($id <= 0) {
                    $message = 'Utilisateur invalide.';
                    $message_type = 'danger';
                    break;
                }
                if ($id === (int) $auth->getUserId()) {
                    $message = 'Vous ne pouvez pas désactiver votre propre compte.';
                    $message_type = 'danger';
                    break;
                }
                $target = $utilisateurModel->getById($id);
                if (!$target) {
                    $message = 'Utilisateur introuvable.';
                    $message_type = 'danger';
                    break;
                }
                $newStatut = ($target['statut'] ?? '') === 'actif' ? 'inactif' : 'actif';
                if ($utilisateurModel->modifier(
                    $id,
                    $target['nom_utilisateur'],
                    $target['email'],
                    $target['role'],
                    $newStatut
                )) {
                    $message = $newStatut === 'actif'
                        ? 'Accès réactivé pour « ' . $target['nom_utilisateur'] . ' ».'
                        : 'Accès suspendu pour « ' . $target['nom_utilisateur'] . ' » (connexion impossible).';
                    $message_type = 'success';
                    require_once __DIR__ . '/../config/SystemLogs.php';
                    (new SystemLogs())->addLog(
                        'user_access_toggle',
                        ($newStatut === 'actif' ? 'Réactivation' : 'Suspension') . ' compte #' . $id,
                        (int) $auth->getUserId()
                    );
                } else {
                    $message = 'Erreur lors de la modification du statut.';
                    $message_type = 'danger';
                }
                break;
        }
    }
}

// Récupérer les données
$userFilters = utilisateurs_parse_filters();
$utilisateursAll = $utilisateurModel->getAll();
$stats = $utilisateurModel->getStats();
$userIds = array_map(static fn ($u) => (int) $u['id'], $utilisateursAll);
$staffLinksByUser = StaffLink::getLinksForUsers($userIds);
$countNonRattache = utilisateurs_count_non_rattache($utilisateursAll, $staffLinksByUser);
$utilisateurs = utilisateurs_filter_list($utilisateursAll, $staffLinksByUser, $userFilters);
$hasActiveFilters = $userFilters['liaison'] !== '' || $userFilters['role'] !== ''
    || $userFilters['statut'] !== '' || $userFilters['q'] !== '';
$staffMedecins = StaffLink::listMedecinsForSelect();
require_once __DIR__ . '/../includes/medecin_profil.php';

$actionsHtml = '<a href="' . htmlspecialchars(app_url('parametres/droits_acces.php')) . '" class="btn btn-outline-primary btn-sm">'
    . '<i class="fas fa-shield-alt me-1"></i>Droits d\'accès</a> '
    . '<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#creerUtilisateurModal">'
    . '<i class="fas fa-plus me-1"></i>Nouvel utilisateur</button>';

app_head('Utilisateurs', ['assets/css/app-parametres.css', 'assets/css/app-module.css'], 'app-parametres-page');
app_layout_start(['active' => 'parametres', 'skip_page_header' => true]);
app_parametres_shell_start('utilisateurs', 'Gestion des utilisateurs', 'Administration des comptes et des rôles', $actionsHtml);
app_parametres_alert($message, $message_type);
?>

        <!-- Statistiques -->
        <div class="param-stats">
            <?php
            $userStatItems = [
                ['val' => $stats['total'] ?? 0,       'label' => 'Total',       'href' => ''],
                ['val' => $stats['actifs'] ?? 0,      'label' => 'Actifs',      'href' => '?statut=actif'],
                ['val' => $stats['admins'] ?? 0,      'label' => 'Admins',      'href' => '?role=admin'],
                ['val' => $stats['medecins'] ?? 0,    'label' => 'Médecins',    'href' => '?role=medecin'],
                ['val' => $stats['secretaires'] ?? 0, 'label' => 'Secrétaires', 'href' => '?role=secretaire'],
                ['val' => $stats['infirmiers'] ?? 0,  'label' => 'Infirmiers',  'href' => '?role=infirmier'],
                ['val' => $countNonRattache,          'label' => 'Non rattachés', 'href' => '?liaison=non_rattache', 'alert' => true],
            ];
            foreach ($userStatItems as $item):
                $isActiveStat = ($item['href'] === '?liaison=non_rattache' && $userFilters['liaison'] === 'non_rattache')
                    || ($item['href'] === ('?role=' . ($userFilters['role'] ?? '')) && $userFilters['role'] !== '' && $item['href'] !== '')
                    || ($item['href'] === '?statut=actif' && $userFilters['statut'] === 'actif');
                $statClass = 'param-stat' . (!empty($item['alert']) && (int) $item['val'] > 0 ? ' param-stat--alert' : '');
                if (!empty($item['alert']) && (int) $item['val'] === 0) {
                    continue;
                }
                ?>
            <?php if (!empty($item['href'])): ?>
            <a href="<?= htmlspecialchars($item['href']) ?>" class="<?= $statClass ?> param-stat-link<?= $isActiveStat ? ' is-active' : '' ?>">
                <div class="param-stat-val"><?= (int) $item['val'] ?></div>
                <div class="param-stat-label"><?= htmlspecialchars($item['label']) ?></div>
            </a>
            <?php else: ?>
            <div class="<?= $statClass ?>">
                <div class="param-stat-val"><?= (int) $item['val'] ?></div>
                <div class="param-stat-label"><?= htmlspecialchars($item['label']) ?></div>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <!-- Liste des utilisateurs -->
        <div class="param-card param-users-table-wrap">
            <div class="param-card-head d-flex flex-wrap align-items-center justify-content-between gap-2">
                <span><i class="fas fa-list"></i> Liste des utilisateurs</span>
                <?php if ($hasActiveFilters): ?>
                    <span class="badge bg-secondary"><?= count($utilisateurs) ?> / <?= count($utilisateursAll) ?> affiché(s)</span>
                <?php endif; ?>
            </div>
            <div class="param-card-body p-0">
                <div class="param-users-filters px-3 pt-3">
                    <form method="get" class="filter-section mb-0">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label small mb-1" for="filtre_q">Recherche</label>
                                <input type="search" class="form-control form-control-sm" id="filtre_q" name="q"
                                       value="<?= htmlspecialchars($userFilters['q']) ?>"
                                       placeholder="Nom, email, fiche…">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1" for="filtre_liaison">Fiche métier</label>
                                <select class="form-select form-select-sm" id="filtre_liaison" name="liaison">
                                    <option value=""<?= $userFilters['liaison'] === '' ? ' selected' : '' ?>>Toutes</option>
                                    <option value="non_rattache"<?= $userFilters['liaison'] === 'non_rattache' ? ' selected' : '' ?>>Non rattachés</option>
                                    <option value="rattache"<?= $userFilters['liaison'] === 'rattache' ? ' selected' : '' ?>>Rattachés</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1" for="filtre_role">Rôle</label>
                                <select class="form-select form-select-sm" id="filtre_role" name="role">
                                    <option value="">Tous les rôles</option>
                                    <?php app_roles_select_options($userFilters['role']); ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1" for="filtre_statut">Statut</label>
                                <select class="form-select form-select-sm" id="filtre_statut" name="statut">
                                    <option value=""<?= $userFilters['statut'] === '' ? ' selected' : '' ?>>Tous</option>
                                    <option value="actif"<?= $userFilters['statut'] === 'actif' ? ' selected' : '' ?>>Actif</option>
                                    <option value="inactif"<?= $userFilters['statut'] === 'inactif' ? ' selected' : '' ?>>Inactif</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex flex-wrap gap-2">
                                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter me-1"></i>Filtrer</button>
                                <?php if ($hasActiveFilters): ?>
                                <a href="<?= htmlspecialchars(app_url('parametres/utilisateurs.php')) ?>" class="btn btn-outline-secondary btn-sm">Réinitialiser</a>
                                <?php endif; ?>
                                <?php if ($countNonRattache > 0 && $userFilters['liaison'] !== 'non_rattache'): ?>
                                <a href="?liaison=non_rattache" class="btn btn-outline-warning btn-sm">
                                    <i class="fas fa-unlink me-1"></i>Non rattachés (<?= (int) $countNonRattache ?>)
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>

                <?php if (empty($utilisateursAll)): ?>
                    <div class="text-center py-4 px-3">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Aucun utilisateur trouvé</h5>
                        <p class="text-muted">Commencez par créer votre premier utilisateur.</p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#creerUtilisateurModal">
                            <i class="fas fa-plus me-2"></i>Nouvel utilisateur
                        </button>
                    </div>
                <?php elseif (empty($utilisateurs)): ?>
                    <div class="text-center py-4 px-3">
                        <i class="fas fa-filter fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Aucun résultat pour ces filtres</h5>
                        <p class="text-muted">Modifiez les critères ou réinitialisez la liste.</p>
                        <a href="<?= htmlspecialchars(app_url('parametres/utilisateurs.php')) ?>" class="btn btn-outline-secondary btn-sm">Réinitialiser les filtres</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover param-users-table mb-0">
                            <thead>
                                <tr>
                                    <th>Utilisateur</th>
                                    <th>Email</th>
                                    <th>Rôle</th>
                                    <th>Fiche métier</th>
                                    <th>Statut</th>
                                    <th>Date création</th>
                                    <th class="param-col-actions text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($utilisateurs as $utilisateur): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($utilisateur['nom_utilisateur']) ?></strong>
                                            <?php if ($utilisateur['id'] == $auth->getUserId()): ?>
                                                <br><small class="text-muted">(Vous)</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($utilisateur['email']) ?></td>
                                        <td>
                                            <span class="role-badge role-<?= htmlspecialchars($utilisateur['role']) ?>">
                                                <?= htmlspecialchars(app_role_label($utilisateur['role'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $link = $staffLinksByUser[(int) $utilisateur['id']] ?? ['label' => null];
                                            if (!empty($link['label'])): ?>
                                                <small class="text-success"><i class="fas fa-link me-1"></i><?= htmlspecialchars($link['label']) ?></small>
                                            <?php elseif (StaffLink::roleNeedsLink($utilisateur['role'])): ?>
                                                <small class="text-warning" title="Créez d'abord une fiche dans le module Médecins, puis rattachez ici.">
                                                    <i class="fas fa-unlink me-1"></i>Non rattaché
                                                </small>
                                            <?php else: ?>
                                                <small class="text-muted">—</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= htmlspecialchars($utilisateur['statut']) ?>">
                                                <?= ucfirst(htmlspecialchars($utilisateur['statut'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?= date('d/m/Y H:i', strtotime($utilisateur['date_creation'])) ?>
                                            </small>
                                        </td>
                                        <td class="param-col-actions mod-actions-cell text-end">
                                            <?php
                                            $uid = (int) $utilisateur['id'];
                                            $userNameJson = json_encode($utilisateur['nom_utilisateur'], JSON_UNESCAPED_UNICODE);
                                            $userActions = [
                                                [
                                                    'button' => true,
                                                    'label' => 'Voir le profil',
                                                    'icon' => 'fa-eye',
                                                    'tone' => 'primary',
                                                    'onclick' => 'voirUtilisateur(' . $uid . ')',
                                                ],
                                                [
                                                    'button' => true,
                                                    'label' => 'Modifier',
                                                    'icon' => 'fa-edit',
                                                    'tone' => 'warning',
                                                    'onclick' => 'modifierUtilisateur(' . $uid . ')',
                                                ],
                                                [
                                                    'button' => true,
                                                    'label' => 'Mot de passe',
                                                    'icon' => 'fa-key',
                                                    'tone' => 'neutral',
                                                    'onclick' => 'changerMotDePasse(' . $uid . ')',
                                                ],
                                            ];
                                            if ($uid !== (int) $auth->getUserId()) {
                                                $userActions[] = ['divider' => true];
                                                $userActions[] = [
                                                    'button' => true,
                                                    'label' => ($utilisateur['statut'] ?? '') === 'actif' ? 'Suspendre l\'accès' : 'Réactiver l\'accès',
                                                    'icon' => ($utilisateur['statut'] ?? '') === 'actif' ? 'fa-user-slash' : 'fa-user-check',
                                                    'tone' => ($utilisateur['statut'] ?? '') === 'actif' ? 'warning' : 'success',
                                                    'onclick' => 'toggleAccesUtilisateur(' . $uid . ', ' . json_encode($utilisateur['nom_utilisateur'], JSON_UNESCAPED_UNICODE) . ', ' . json_encode($utilisateur['statut'] ?? 'actif', JSON_UNESCAPED_UNICODE) . ')',
                                                ];
                                                $userActions[] = ['divider' => true];
                                                $userActions[] = [
                                                    'button' => true,
                                                    'label' => 'Supprimer',
                                                    'icon' => 'fa-trash',
                                                    'tone' => 'danger',
                                                    'onclick' => 'supprimerUtilisateur(' . $uid . ', ' . $userNameJson . ')',
                                                ];
                                            }
                                            app_mod_actions_dropdown($userActions);
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

<?php app_parametres_shell_end(); ?>

    <!-- Modal Créer Utilisateur -->
    <div class="modal fade param-modal" id="creerUtilisateurModal" tabindex="-1" aria-labelledby="creerUtilisateurModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header param-modal-header">
                    <h5 class="modal-title" id="creerUtilisateurModalLabel"><i class="fas fa-plus me-2"></i>Nouvel utilisateur</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="creer">
                        
                        <div class="mb-3">
                            <label for="nom_utilisateur" class="form-label">Nom d'utilisateur *</label>
                            <input type="text" class="form-control" id="nom_utilisateur" name="nom_utilisateur" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="mot_de_passe" class="form-label">Mot de passe *</label>
                            <input type="password" class="form-control" id="mot_de_passe" name="mot_de_passe" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="role" class="form-label">Profil / rôle *</label>
                                    <select class="form-select" id="role" name="role" required>
                                        <?php app_roles_select_options('', true); ?>
                                    </select>
                                    <div class="form-text">Les modules accessibles dépendent du profil — <a href="<?= htmlspecialchars(app_url('parametres/droits_acces.php')) ?>">personnaliser les droits</a>.</div>
                                    <div id="creer_role_preview" class="param-role-preview small mt-2 d-none" aria-live="polite"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="statut" class="form-label">Statut</label>
                                    <select class="form-select" id="statut" name="statut">
                                        <option value="actif">Actif</option>
                                        <option value="inactif">Inactif</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-0 staff-link-block" id="creer_staff_link_block" hidden>
                            <label for="creer_staff_link_id" class="form-label" id="creer_staff_link_label">Fiche métier</label>
                            <select class="form-select" id="creer_staff_link_id" name="staff_link_id">
                                <option value="">— Aucune liaison —</option>
                            </select>
                            <div class="form-text">Lie le compte à une fiche pour que l'utilisateur ne voie que ses patients, consultations ou analyses.</div>
                            <div class="alert alert-warning py-2 px-3 mt-2 mb-0 small d-none" id="creer_staff_link_hint" role="alert"></div>
                        </div>
                    </div>
                    <div class="modal-footer param-modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Créer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Modifier Utilisateur -->
    <div class="modal fade param-modal" id="modifierUtilisateurModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header param-modal-header param-modal-header--amber">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Modifier l'utilisateur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="modifier">
                        <input type="hidden" name="id" id="modifier_id">
                        
                        <div class="mb-3">
                            <label for="modifier_nom_utilisateur" class="form-label">Nom d'utilisateur *</label>
                            <input type="text" class="form-control" id="modifier_nom_utilisateur" name="nom_utilisateur" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="modifier_email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="modifier_email" name="email" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="modifier_role" class="form-label">Profil / rôle *</label>
                                    <select class="form-select" id="modifier_role" name="role" required>
                                        <?php app_roles_select_options(); ?>
                                    </select>
                                    <div id="modifier_role_preview" class="param-role-preview small mt-2 d-none" aria-live="polite"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="modifier_statut" class="form-label">Statut</label>
                                    <select class="form-select" id="modifier_statut" name="statut">
                                        <option value="actif">Actif</option>
                                        <option value="inactif">Inactif</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-0 staff-link-block" id="modifier_staff_link_block" hidden>
                            <label for="modifier_staff_link_id" class="form-label" id="modifier_staff_link_label">Fiche métier</label>
                            <select class="form-select" id="modifier_staff_link_id" name="staff_link_id">
                                <option value="">— Aucune liaison —</option>
                            </select>
                            <div class="form-text">Obligatoire pour médecins, infirmiers, laborantins et techniciens afin de filtrer leurs données.</div>
                            <div class="alert alert-warning py-2 px-3 mt-2 mb-0 small d-none" id="modifier_staff_link_hint" role="alert"></div>
                        </div>
                    </div>
                    <div class="modal-footer param-modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-warning"><i class="fas fa-save me-1"></i>Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Changer Mot de Passe -->
    <div class="modal fade param-modal" id="changerMotDePasseModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header param-modal-header">
                    <h5 class="modal-title"><i class="fas fa-key me-2"></i>Changer le mot de passe</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="changer_mot_de_passe">
                        <input type="hidden" name="id" id="changer_mot_de_passe_id">
                        
                        <div class="mb-3">
                            <label for="nouveau_mot_de_passe" class="form-label">Nouveau mot de passe *</label>
                            <input type="password" class="form-control" id="nouveau_mot_de_passe" name="nouveau_mot_de_passe" required>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Le mot de passe sera immédiatement mis à jour pour cet utilisateur.
                        </div>
                    </div>
                    <div class="modal-footer param-modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-key me-1"></i>Mettre à jour</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Voir Utilisateur -->
    <div class="modal fade param-modal" id="voirUtilisateurModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header param-modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-circle me-2"></i>Profil utilisateur</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="voir_id">
                    
                    <!-- En-tête du profil -->
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <div class="bg-info rounded-circle d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                        <i class="fas fa-user fa-2x text-white"></i>
                                    </div>
                                </div>
                                <div>
                                    <h4 id="voir_nom_utilisateur" class="mb-1"></h4>
                                    <p class="text-muted mb-2" id="voir_email"></p>
                                    <div class="d-flex gap-2">
                                        <span id="voir_role_badge" class="role-badge"></span>
                                        <span id="voir_statut_badge" class="status-badge"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="text-muted">
                                <small>ID: <span id="voir_id_display"></span></small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Informations principales -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informations Générales</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-muted">Nom d'utilisateur</label>
                                        <p class="form-control-plaintext border-bottom pb-2" id="voir_nom_utilisateur_detail"></p>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-muted">Email</label>
                                        <p class="form-control-plaintext border-bottom pb-2" id="voir_email_detail"></p>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-muted">Rôle</label>
                                        <div class="pt-2">
                                            <span id="voir_role_badge_detail" class="role-badge"></span>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-muted">Statut</label>
                                        <div class="pt-2">
                                            <span id="voir_statut_badge_detail" class="status-badge"></span>
                                        </div>
                                    </div>
                                    <div class="mb-0" id="voir_staff_link_row" hidden>
                                        <label class="form-label fw-bold text-muted">Fiche métier liée</label>
                                        <p class="form-control-plaintext border-bottom pb-2" id="voir_staff_link_detail"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-clock me-2"></i>Informations Temporelles</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-muted">Date de création</label>
                                        <p class="form-control-plaintext border-bottom pb-2" id="voir_date_creation"></p>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-muted">Dernière modification</label>
                                        <p class="form-control-plaintext border-bottom pb-2" id="voir_date_modification"></p>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-muted">Dernière connexion</label>
                                        <p class="form-control-plaintext border-bottom pb-2" id="voir_derniere_connexion"></p>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-muted">Nombre de connexions</label>
                                        <p class="form-control-plaintext border-bottom pb-2" id="voir_nombre_connexions"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Permissions et accès -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Permissions et Accès</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6 class="text-primary">Modules d'accès</h6>
                                            <div id="voir_permissions" class="mt-2">
                                                <!-- Les permissions seront affichées ici -->
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="text-primary">Actions autorisées</h6>
                                            <div id="voir_actions" class="mt-2">
                                                <!-- Les actions seront affichées ici -->
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Statistiques d'activité -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Activité Récente</h6>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Fonctionnalité en développement :</strong> Les statistiques d'activité détaillées seront bientôt disponibles.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer param-modal-footer param-modal-footer--split">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Fermer
                    </button>
                    <div class="param-modal-actions">
                        <button type="button" class="btn btn-warning" id="btnModifierFromView">
                            <i class="fas fa-edit me-1"></i>Modifier
                        </button>
                        <button type="button" class="btn btn-outline-primary" id="btnPasswordFromView">
                            <i class="fas fa-key me-1"></i>Mot de passe
                        </button>
                        <button type="button" class="btn btn-success" id="btnExportFromView">
                            <i class="fas fa-download me-1"></i>Exporter
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php
ob_start();
?>
    <script>
        (function () {
            const UTILISATEUR_API = <?= json_encode(app_url('parametres/get_utilisateur.php')) ?>;
            const EXPORT_PROFIL_URL = <?= json_encode(app_url('parametres/export_utilisateur.php')) ?>;
            const ROLE_PROFILES = <?= json_encode(app_role_profiles_for_ui((int) $auth->getTenantId()), JSON_UNESCAPED_UNICODE) ?>;
            const ROLE_LABELS = <?= json_encode(APP_ROLE_LABELS, JSON_UNESCAPED_UNICODE) ?>;
            const STAFF_LINK_TYPES = <?= json_encode(array_combine(app_role_keys(), array_map('app_role_staff_link_type', app_role_keys())), JSON_UNESCAPED_UNICODE) ?>;
            const STAFF_LINK_LABELS = <?= json_encode(array_combine(app_role_keys(), array_map('app_role_staff_link_label', app_role_keys())), JSON_UNESCAPED_UNICODE) ?>;
            const STAFF_MEDECINS = <?= json_encode($staffMedecins, JSON_UNESCAPED_UNICODE) ?>;
            const STAFF_PROFIL_FOR_ROLE = <?= json_encode(array_combine(app_role_keys(), array_map('medecin_profil_types_for_role', app_role_keys())), JSON_UNESCAPED_UNICODE) ?>;
            const STAFF_MODULE_URL = <?= json_encode(app_url('medecins/ajouter.php'), JSON_UNESCAPED_UNICODE) ?>;

            function fichesPourRole(role) {
                const types = STAFF_PROFIL_FOR_ROLE[role] || [];
                if (!types.length) {
                    return STAFF_MEDECINS;
                }
                return STAFF_MEDECINS.filter(function (item) {
                    const t = item.type_profil || 'medecin';
                    return types.indexOf(t) !== -1 || t === 'medecin' && types.indexOf('medecin') !== -1;
                });
            }

            function remplirSelectStaffLink(selectEl, linkType, selectedId, currentUserId, role) {
                if (!selectEl) {
                    return;
                }
                const list = linkType === 'medecin' ? fichesPourRole(role || '') : [];
                selectEl.innerHTML = '<option value="">— Aucune liaison —</option>';
                if (list.length === 0) {
                    const emptyOpt = document.createElement('option');
                    emptyOpt.value = '';
                    emptyOpt.disabled = true;
                    emptyOpt.textContent = '— Aucune fiche disponible —';
                    selectEl.appendChild(emptyOpt);
                    return;
                }
                list.forEach(function (item) {
                    const opt = document.createElement('option');
                    opt.value = String(item.id);
                    let text = item.label;
                    if (item.linked_user_id && item.linked_user_id !== currentUserId) {
                        text += ' (déjà lié)';
                    }
                    opt.textContent = text;
                    if (String(item.id) === String(selectedId)) {
                        opt.selected = true;
                    }
                    selectEl.appendChild(opt);
                });
            }

            function majStaffLinkHint(prefix, linkType, role) {
                const hint = document.getElementById(prefix + '_staff_link_hint');
                if (!hint) {
                    return;
                }
                if (!linkType) {
                    hint.classList.add('d-none');
                    hint.innerHTML = '';
                    return;
                }
                const list = fichesPourRole(role || '');
                if (list.length === 0) {
                    hint.classList.remove('d-none');
                    hint.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>'
                        + 'Aucune fiche dans le module <strong>Médecins</strong>. '
                        + '<a href="' + STAFF_MODULE_URL + '" class="alert-link">Créer un professionnel</a> '
                        + '(infirmier, laborantin, médecin…), puis rattachez le compte ici.';
                    return;
                }
                hint.classList.add('d-none');
                hint.innerHTML = '';
            }

            function majBlocStaffLink(prefix, role, selectedId, currentUserId) {
                const block = document.getElementById(prefix + '_staff_link_block');
                const select = document.getElementById(prefix + '_staff_link_id');
                const label = document.getElementById(prefix + '_staff_link_label');
                const linkType = STAFF_LINK_TYPES[role] || null;
                if (!block || !select) {
                    return;
                }
                if (!linkType) {
                    block.hidden = true;
                    select.value = '';
                    majStaffLinkHint(prefix, null);
                    return;
                }
                block.hidden = false;
                if (label) {
                    label.textContent = STAFF_LINK_LABELS[role] || 'Fiche métier';
                }
                remplirSelectStaffLink(select, linkType, selectedId, currentUserId || 0, role);
                majStaffLinkHint(prefix, linkType, role);
            }

            function majApercuRole(selectEl, previewId) {
                const preview = document.getElementById(previewId);
                if (!preview || !selectEl) {
                    return;
                }
                const role = selectEl.value;
                if (!role || !ROLE_PROFILES[role]) {
                    preview.classList.add('d-none');
                    preview.innerHTML = '';
                    return;
                }
                const perms = ROLE_PROFILES[role].permissions || [];
                preview.classList.remove('d-none');
                preview.innerHTML = '<strong>Modules :</strong> ' + perms.map(function (p) {
                    return '<span class="badge bg-light text-dark border me-1 mb-1">' + p + '</span>';
                }).join('');
            }

            const roleCreer = document.getElementById('role');
            const roleModifier = document.getElementById('modifier_role');
            roleCreer.addEventListener('change', function () {
                majBlocStaffLink('creer', this.value, '', 0);
                majApercuRole(this, 'creer_role_preview');
            });
            roleModifier.addEventListener('change', function () {
                const uid = parseInt(document.getElementById('modifier_id').value, 10) || 0;
                majBlocStaffLink('modifier', this.value, '', uid);
                majApercuRole(this, 'modifier_role_preview');
            });

            function modalEstOuvert(id) {
                const el = document.getElementById(id);
                return el && el.classList.contains('show');
            }

            function fermerModal(id) {
                const el = document.getElementById(id);
                if (!el || !modalEstOuvert(id)) {
                    return Promise.resolve();
                }
                const instance = bootstrap.Modal.getInstance(el);
                if (!instance) {
                    return Promise.resolve();
                }
                return new Promise(function (resolve) {
                    el.addEventListener('hidden.bs.modal', resolve, { once: true });
                    instance.hide();
                });
            }

            function ouvrirModal(id) {
                const el = document.getElementById(id);
                if (!el) {
                    return;
                }
                if (el.parentElement !== document.body) {
                    document.body.appendChild(el);
                }
                bootstrap.Modal.getOrCreateInstance(el).show();
            }

            function getViewUserId() {
                return document.getElementById('voir_id').value;
            }

            window.modifierUtilisateur = function (id) {
                if (!id) {
                    alert('Utilisateur introuvable.');
                    return;
                }
                fermerModal('voirUtilisateurModal').then(function () {
                    fetch(UTILISATEUR_API + '?id=' + encodeURIComponent(id))
                        .then(function (r) {
                            if (!r.ok) {
                                throw new Error('HTTP ' + r.status);
                            }
                            return r.json();
                        })
                        .then(function (data) {
                            if (!data.success) {
                                alert(data.message || 'Erreur lors de la récupération des données utilisateur');
                                return;
                            }
                            const user = data.utilisateur;
                            document.getElementById('modifier_id').value = user.id;
                            document.getElementById('modifier_nom_utilisateur').value = user.nom_utilisateur;
                            document.getElementById('modifier_email').value = user.email;
                            document.getElementById('modifier_role').value = user.role;
                            document.getElementById('modifier_statut').value = user.statut;
                            const staffLink = user.staff_link || {};
                            majBlocStaffLink('modifier', user.role, staffLink.id || '', user.id);
                            ouvrirModal('modifierUtilisateurModal');
                        })
                        .catch(function (err) {
                            console.error(err);
                            alert('Erreur lors de la récupération des données utilisateur');
                        });
                });
            };

            window.changerMotDePasse = function (id) {
                if (!id) {
                    alert('Utilisateur introuvable.');
                    return;
                }
                fermerModal('voirUtilisateurModal').then(function () {
                    document.getElementById('changer_mot_de_passe_id').value = id;
                    document.getElementById('nouveau_mot_de_passe').value = '';
                    ouvrirModal('changerMotDePasseModal');
                });
            };

            window.supprimerUtilisateur = function (id, nom) {
                if (!confirm('Êtes-vous sûr de vouloir supprimer l\'utilisateur « ' + nom + ' » ?')) {
                    return;
                }
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="supprimer"><input type="hidden" name="id" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            };

            window.toggleAccesUtilisateur = function (id, nom, statut) {
                const suspendre = statut === 'actif';
                const msg = suspendre
                    ? 'Suspendre l\'accès de « ' + nom + ' » ?\n\nL\'utilisateur ne pourra plus se connecter.'
                    : 'Réactiver l\'accès de « ' + nom + ' » ?';
                if (!confirm(msg)) {
                    return;
                }
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="toggle_statut"><input type="hidden" name="id" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            };

            window.voirUtilisateur = function (id) {
                ouvrirModal('voirUtilisateurModal');
                fetch(UTILISATEUR_API + '?id=' + encodeURIComponent(id))
                    .then(function (r) {
                        if (!r.ok) {
                            throw new Error('HTTP ' + r.status);
                        }
                        return r.json();
                    })
                    .then(function (data) {
                        if (!data.success) {
                            alert('Erreur : ' + (data.message || 'données indisponibles'));
                            fermerModal('voirUtilisateurModal');
                            return;
                        }
                        const user = data.utilisateur;
                        document.getElementById('voir_id').value = user.id;
                        document.getElementById('voir_id_display').textContent = user.id;
                        document.getElementById('voir_nom_utilisateur').textContent = user.nom_utilisateur;
                        document.getElementById('voir_email').textContent = user.email;
                        document.getElementById('voir_nom_utilisateur_detail').textContent = user.nom_utilisateur;
                        document.getElementById('voir_email_detail').textContent = user.email;
                        document.getElementById('voir_date_creation').textContent = new Date(user.date_creation).toLocaleString('fr-FR');
                        document.getElementById('voir_date_modification').textContent = user.date_modification
                            ? new Date(user.date_modification).toLocaleString('fr-FR') : 'Non modifié';
                        if (user.stats) {
                            document.getElementById('voir_nombre_connexions').textContent = user.stats.connexions || 0;
                            document.getElementById('voir_derniere_connexion').textContent = user.stats.derniere_connexion
                                ? new Date(user.stats.derniere_connexion).toLocaleString('fr-FR') : 'Jamais connecté';
                        } else {
                            document.getElementById('voir_nombre_connexions').textContent = '0';
                            document.getElementById('voir_derniere_connexion').textContent = 'Jamais connecté';
                        }
                        ['voir_role_badge', 'voir_role_badge_detail'].forEach(function (bid) {
                            const b = document.getElementById(bid);
                            b.className = 'role-badge role-' + user.role;
                            b.textContent = ROLE_LABELS[user.role] || user.role;
                        });
                        ['voir_statut_badge', 'voir_statut_badge_detail'].forEach(function (bid) {
                            const b = document.getElementById(bid);
                            b.className = 'status-badge status-' + user.statut;
                            b.textContent = user.statut.charAt(0).toUpperCase() + user.statut.slice(1);
                        });
                        afficherPermissions(user.role);
                        const staffRow = document.getElementById('voir_staff_link_row');
                        const staffDetail = document.getElementById('voir_staff_link_detail');
                        const sl = user.staff_link || {};
                        if (STAFF_LINK_TYPES[user.role] && staffRow && staffDetail) {
                            staffRow.hidden = false;
                            staffDetail.textContent = sl.label || 'Non rattaché — accès aux données limité';
                            staffDetail.className = 'form-control-plaintext border-bottom pb-2 ' + (sl.label ? 'text-success' : 'text-warning');
                        } else if (staffRow) {
                            staffRow.hidden = true;
                        }
                    })
                    .catch(function (err) {
                        console.error(err);
                        alert('Erreur lors de la récupération des données utilisateur');
                        fermerModal('voirUtilisateurModal');
                    });
            };

            function afficherPermissions(role) {
                const permissionsContainer = document.getElementById('voir_permissions');
                const actionsContainer = document.getElementById('voir_actions');
                const profile = ROLE_PROFILES[role] || {
                    permissions: ['Aucune permission définie'],
                    actions: ['Aucune action autorisée']
                };

                permissionsContainer.innerHTML = profile.permissions.map(function (perm) {
                    return '<div class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>' + perm + '</div>';
                }).join('') + '<p class="small text-muted mt-3 mb-0"><a href="<?= htmlspecialchars(app_url('parametres/droits_acces.php')) ?>?role=' + encodeURIComponent(role) + '"><i class="fas fa-shield-alt me-1"></i>Modifier les droits du profil « ' + (ROLE_LABELS[role] || role) + ' »</a></p>';

                actionsContainer.innerHTML = profile.actions.map(function (action) {
                    return '<div class="mb-2"><i class="fas fa-arrow-right text-primary me-2"></i>' + action + '</div>';
                }).join('');
            }

            window.exporterProfil = function (id) {
                window.open(EXPORT_PROFIL_URL + '?id=' + encodeURIComponent(id), '_blank');
            };

            document.getElementById('btnModifierFromView').addEventListener('click', function () {
                modifierUtilisateur(getViewUserId());
            });
            document.getElementById('btnPasswordFromView').addEventListener('click', function () {
                changerMotDePasse(getViewUserId());
            });
            document.getElementById('btnExportFromView').addEventListener('click', function () {
                exporterProfil(getViewUserId());
            });

            document.querySelectorAll('.param-modal').forEach(function (modalEl) {
                modalEl.addEventListener('show.bs.modal', function () {
                    if (modalEl.parentElement !== document.body) {
                        document.body.appendChild(modalEl);
                    }
                });
            });
        })();
    </script>
<?php
$GLOBALS['app_page_scripts'] = ob_get_clean();
app_layout_end(['minimal_scripts' => true]);
?>

