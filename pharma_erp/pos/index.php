<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';

extract(pharma_erp_context());

require_once __DIR__ . '/../../models/pharma_erp/PePharmacy.php';

$pharmacyModel = new PePharmacy();
$pharmacy = $pharmacyModel->getDefault();
$registerId = $pharmacy ? $pharmacyModel->getDefaultRegisterId((int) $pharmacy['id']) : null;
$depositId = $pharmacy ? $pharmacyModel->getDefaultDepositId((int) $pharmacy['id']) : null;

pharma_erp_page_start([
    'active' => 'pos',
    'title' => 'Point de vente',
    'subtitle' => $pharmacy ? $pharmacy['name'] : 'Caisse',
    'icon' => 'fa-cash-register',
    'body_class' => 'pharma-pro-pos-page',
]);

if (!$pharmacy || !$registerId || !$depositId): ?>
<div class="alert alert-warning">Configuration officine incomplète. Contactez l'administrateur.</div>
<?php pharma_erp_page_end(); exit; endif; ?>

<div class="pharma-pro-pos" id="pharmaPos"
     data-api-search="<?= htmlspecialchars(pharma_erp_url('api/search.php')) ?>"
     data-api-sale="<?= htmlspecialchars(pharma_erp_url('api/sale.php')) ?>"
     data-ticket-base="<?= htmlspecialchars(pharma_erp_url('sales/ticket.php?id=')) ?>"
     data-pharmacy-id="<?= (int) $pharmacy['id'] ?>"
     data-register-id="<?= (int) $registerId ?>"
     data-deposit-id="<?= (int) $depositId ?>">

    <div>
        <div class="pharma-pro-pos-search mb-3">
            <input type="text" id="posSearch" class="form-control form-control-lg" placeholder="Scanner code-barres ou rechercher un produit…" autofocus autocomplete="off">
        </div>
        <div id="posSearchResults" class="pharma-pro-panel" style="display:none;">
            <div class="pharma-pro-panel-body" id="posSearchResultsBody"></div>
        </div>
        <div class="pharma-pro-panel mt-3">
            <div class="pharma-pro-panel-header"><i class="fas fa-keyboard"></i> Raccourcis</div>
            <div class="pharma-pro-panel-body small text-muted">
                <kbd>Entrée</kbd> Rechercher · <kbd>F2</kbd> Encaisser · <kbd>Échap</kbd> Vider panier
            </div>
        </div>
    </div>

    <div class="pharma-pro-pos-cart">
        <div class="pharma-pro-pos-cart-header">
            <i class="fas fa-shopping-basket me-2"></i> Panier <span id="posCartCount" class="badge bg-primary ms-1">0</span>
        </div>
        <div class="pharma-pro-pos-lines" id="posCartLines"></div>
        <div class="pharma-pro-pos-total">
            <div class="d-flex justify-content-between mb-2">
                <span>Sous-total</span>
                <span id="posSubtotal">0 FCFA</span>
            </div>
            <div class="d-flex justify-content-between mb-3">
                <strong>Total TTC</strong>
                <span class="pharma-pro-pos-total-value" id="posTotal">0 FCFA</span>
            </div>
            <div class="mb-3">
                <label class="form-label small">Code promo</label>
                <input type="text" id="posPromoCode" class="form-control form-control-sm" placeholder="PROMO2026" autocomplete="off">
            </div>
            <div class="mb-3">
                <label class="form-label small">Fidélité (téléphone)</label>
                <input type="text" id="posLoyaltyPhone" class="form-control form-control-sm" placeholder="+223…" autocomplete="off">
            </div>
            <div class="mb-3">
                <label class="form-label small">Mode de paiement</label>
                <select id="posPaymentMethod" class="form-select form-select-sm">
                    <option value="cash">Espèces</option>
                    <option value="mobile_money">Mobile Money</option>
                    <option value="bank">Banque</option>
                    <option value="card">Carte</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label small">Montant reçu</label>
                <input type="number" id="posAmountPaid" class="form-control" min="0" step="1">
            </div>
            <button type="button" class="btn btn-pharma-secondary w-100 btn-lg" id="posCheckoutBtn">
                <i class="fas fa-check-circle me-1"></i> Encaisser (F2)
            </button>
            <button type="button" class="btn btn-pharma-outline w-100 mt-2 btn-sm" id="posClearBtn">Vider le panier</button>
        </div>
    </div>
</div>

<?php
$posJs = 'assets/js/pharma-erp/pos.js';
pharma_erp_page_end(['extra_js' => $posJs]);
?>
