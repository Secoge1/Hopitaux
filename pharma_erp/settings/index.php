<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';

extract(pharma_erp_context());

require_once __DIR__ . '/../../models/pharma_erp/PePharmacy.php';

$model = new PePharmacy();
$pharmacies = $model->getAll();
$default = $model->getDefault();

pharma_erp_page_start([
    'active' => 'settings',
    'title' => 'Paramètres PharmaPro',
    'subtitle' => 'Configuration de l\'officine',
    'icon' => 'fa-sliders',
]);
?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="pharma-pro-panel">
            <div class="pharma-pro-panel-header"><i class="fas fa-store"></i> Officines</div>
            <div class="pharma-pro-panel-body">
                <?php foreach ($pharmacies as $ph): ?>
                <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                    <div>
                        <strong><?= htmlspecialchars($ph['name']) ?></strong>
                        <br><small class="text-muted"><?= htmlspecialchars($ph['code']) ?> — <?= htmlspecialchars($ph['city'] ?? '—') ?></small>
                    </div>
                    <?php if ($ph['is_default']): ?>
                        <span class="pe-badge pe-badge--active">Par défaut</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <a href="<?= htmlspecialchars(pharma_erp_url('settings/officines.php')) ?>" class="btn btn-pharma-outline btn-sm">
                    <i class="fas fa-plus me-1"></i> Gérer les officines
                </a>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="pharma-pro-panel">
            <div class="pharma-pro-panel-header"><i class="fas fa-info-circle"></i> Module</div>
            <div class="pharma-pro-panel-body">
                <p><strong>PharmaPro ERP</strong> est un module premium activé par l'administrateur plateforme.</p>
                <ul class="small text-muted">
                    <li>Feature : <code>pharma_erp_suite</code></li>
                    <li>Tables dédiées : préfixe <code>pe_</code></li>
                    <li>Indépendant du module HIS <code>pharmacie/</code></li>
                </ul>
                <?php if ($default): ?>
                <p class="mb-0">Officine active : <strong><?= htmlspecialchars($default['name']) ?></strong></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="pharma-pro-panel mt-4">
            <div class="pharma-pro-panel-header"><i class="fas fa-puzzle-piece"></i> Modules livrés</div>
            <div class="pharma-pro-panel-body">
                <div class="row g-2 small">
                    <div class="col-6"><span class="pe-badge pe-badge--active">POS</span> Point de vente</div>
                    <div class="col-6"><span class="pe-badge pe-badge--active">Stock</span> Lots & inventaires</div>
                    <div class="col-6"><span class="pe-badge pe-badge--active">Achats</span> Commandes & réceptions</div>
                    <div class="col-6"><span class="pe-badge pe-badge--active">SYSCOHADA</span> Comptabilité auto</div>
                    <div class="col-6"><span class="pe-badge pe-badge--active">RH</span> Paie & congés</div>
                    <div class="col-6"><span class="pe-badge pe-badge--active">Pont HIS</span> Sync Finances</div>
                    <div class="col-6"><span class="pe-badge pe-badge--active">API REST</span> Mobile caisse</div>
                    <div class="col-6"><span class="pe-badge pe-badge--active">Promotions</span> Codes promo & fidélité</div>
                    <div class="col-6"><span class="pe-badge pe-badge--active">Médical</span> Ordonnances & patients HIS</div>
                    <div class="col-6"><span class="pe-badge pe-badge--active">Banque/TVA</span> Trésorerie & déclarations</div>
                    <div class="col-6"><span class="pe-badge pe-badge--active">PWA</span> Installation écran d'accueil</div>
                    <div class="col-6"><span class="pe-badge pe-badge--active">Tickets</span> Impression thermique POS</div>
                    <div class="col-6"><span class="pe-badge pe-badge--active">Clients</span> CRM & fidélité</div>
                    <div class="col-6"><span class="pe-badge pe-badge--active">Retours</span> Remboursements vente</div>
                    <div class="col-6"><span class="pe-badge pe-badge--active">Factures</span> Dettes fournisseurs</div>
                    <div class="col-6"><span class="pe-badge pe-badge--active">Multi-sites</span> Plusieurs officines</div>
                    <div class="col-6"><span class="pe-badge pe-badge--active">Immobilisations</span> Amortissement linéaire</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php pharma_erp_page_end(); ?>
