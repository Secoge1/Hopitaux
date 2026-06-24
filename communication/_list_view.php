<?php
/** Fragment liste communication */

app_mod_stats([
    ['value' => $stats['messages_recus'] ?? 0, 'label' => 'Messages reçus', 'icon' => 'fa-inbox'],
    ['value' => $stats['messages_envoyes'] ?? 0, 'label' => 'Messages envoyés', 'icon' => 'fa-paper-plane', 'mod' => 'teal'],
    ['value' => $stats['messages_non_lus'] ?? 0, 'label' => 'Non lus', 'icon' => 'fa-envelope-open', 'mod' => 'amber'],
    ['value' => $stats['annonces_actives'] ?? 0, 'label' => 'Annonces actives', 'icon' => 'fa-bullhorn'],
]);
?>

<div class="row">
    <div class="col-md-8 mb-4">
        <div class="app-mod-table-wrap">
            <div class="px-3 py-2 border-bottom bg-light">
                <ul class="nav nav-pills gap-1 mb-0">
                    <li class="nav-item">
                        <a class="nav-link <?= $type === 'received' ? 'active' : '' ?>" href="?type=received">
                            <i class="fas fa-inbox me-1"></i>Reçus
                            <?php if (($stats['messages_non_lus'] ?? 0) > 0): ?>
                            <span class="badge bg-danger ms-1"><?= (int) $stats['messages_non_lus'] ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $type === 'sent' ? 'active' : '' ?>" href="?type=sent">
                            <i class="fas fa-paper-plane me-1"></i>Envoyés
                        </a>
                    </li>
                </ul>
            </div>
            <?php if (empty($messages)): ?>
            <div class="app-mod-empty border-0">
                <i class="fas fa-envelope-open d-block"></i>
                <h5 class="mb-2">Aucun message</h5>
                <p class="mb-3">Votre boîte est vide pour le moment.</p>
                <a href="nouveau_message.php" class="btn btn-primary btn-sm">Nouveau message</a>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 mod-list-table">
                    <thead>
                        <tr>
                            <th>Sujet</th>
                            <th class="d-none d-md-table-cell"><?= $type === 'received' ? 'De' : 'À' ?></th>
                            <th class="d-none d-lg-table-cell">Date</th>
                            <th class="text-end mod-actions-cell">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($messages as $msg):
                        $mid = (int) $msg['id'];
                        $isUnread = empty($msg['lu']);
                        $isUrgent = ($msg['priorite'] ?? '') === 'urgente';
                        $rowClass = trim(($isUnread ? 'table-light fw-semibold' : '') . ($isUrgent ? ' border-start border-danger border-3' : ''));
                        $preview = substr($msg['message'], 0, 150);
                        if (strlen($msg['message']) > 150) {
                            $preview .= '…';
                        }
                        $contactName = $type === 'received'
                            ? ($msg['expediteur_nom'] ?? 'Système')
                            : ($msg['destinataire_nom'] ?? ($msg['destinataire_role'] ?? 'Tous'));
                    ?>
                        <tr class="<?= $rowClass ?>">
                            <td>
                                <?php if ($isUnread): ?><span class="badge bg-primary me-1">Nouveau</span><?php endif; ?>
                                <?php if ($isUrgent): ?><span class="badge bg-danger me-1">Urgent</span><?php endif; ?>
                                <div><?= htmlspecialchars($msg['sujet']) ?></div>
                                <div class="mod-meta d-md-none"><?= htmlspecialchars($contactName) ?> · <?= date('d/m/Y H:i', strtotime($msg['date_creation'])) ?></div>
                                <div class="text-muted small mt-1"><?= nl2br(htmlspecialchars($preview)) ?></div>
                            </td>
                            <td class="d-none d-md-table-cell"><?= htmlspecialchars($contactName) ?></td>
                            <td class="d-none d-lg-table-cell text-muted small"><?= date('d/m/Y H:i', strtotime($msg['date_creation'])) ?></td>
                            <td class="text-end mod-actions-cell">
                                <?php app_mod_actions_dropdown([
                                    ['href' => 'voir_message.php?id=' . $mid, 'label' => 'Lire', 'icon' => 'fa-eye'],
                                    ['href' => 'nouveau_message.php?repondre=' . $mid, 'label' => 'Répondre', 'icon' => 'fa-reply'],
                                    ['divider' => true],
                                    [
                                        'href' => 'index.php?type=' . urlencode($type) . '&delete=1&id=' . $mid,
                                        'label' => 'Supprimer',
                                        'icon' => 'fa-trash',
                                        'class' => 'text-danger',
                                        'onclick' => "return confirm('Êtes-vous sûr de vouloir supprimer ce message ?')",
                                    ],
                                ]); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-md-4 mb-4">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-bullhorn me-2"></i>Annonces</h5>
            </div>
            <div class="card-body">
                <?php if (empty($annonces)): ?>
                <p class="text-muted text-center mb-0">Aucune annonce</p>
                <?php else: ?>
                <?php foreach ($annonces as $annonce): ?>
                <div class="annonce-card card mb-3 annonce-card-<?= htmlspecialchars($annonce['type']) ?>">
                    <div class="card-body">
                        <h6 class="card-title">
                            <span class="badge bg-<?=
                                $annonce['type'] === 'urgence' ? 'danger' :
                                ($annonce['type'] === 'alerte' ? 'warning' : 'info')
                            ?> me-2"><?= ucfirst($annonce['type']) ?></span>
                            <?= htmlspecialchars($annonce['titre']) ?>
                        </h6>
                        <p class="card-text small"><?= nl2br(htmlspecialchars(substr($annonce['contenu'], 0, 100))) ?>…</p>
                        <small class="text-muted">
                            <i class="fas fa-user me-1"></i><?= htmlspecialchars($annonce['auteur'] ?? 'Système') ?>
                            — <?= date('d/m/Y', strtotime($annonce['date_debut'])) ?>
                        </small>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
