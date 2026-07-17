(function () {
    'use strict';

    const pos = document.getElementById('pharmaPos');
    if (!pos) return;

    const apiSearch = pos.dataset.apiSearch;
    const apiSale = pos.dataset.apiSale;
    const ticketBase = pos.dataset.ticketBase || 'sales/ticket.php?id=';
    const pharmacyId = parseInt(pos.dataset.pharmacyId, 10);
    const registerId = parseInt(pos.dataset.registerId, 10);
    const depositId = parseInt(pos.dataset.depositId, 10);

    const searchInput = document.getElementById('posSearch');
    const searchResults = document.getElementById('posSearchResults');
    const searchResultsBody = document.getElementById('posSearchResultsBody');
    const cartLines = document.getElementById('posCartLines');
    const cartCount = document.getElementById('posCartCount');
    const subtotalEl = document.getElementById('posSubtotal');
    const totalEl = document.getElementById('posTotal');
    const amountPaidEl = document.getElementById('posAmountPaid');
    const paymentMethodEl = document.getElementById('posPaymentMethod');
    const promoCodeEl = document.getElementById('posPromoCode');
    const loyaltyPhoneEl = document.getElementById('posLoyaltyPhone');
    const checkoutBtn = document.getElementById('posCheckoutBtn');
    const clearBtn = document.getElementById('posClearBtn');

    let cart = [];
    let searchTimer = null;

    function formatMoney(n) {
        return window.PharmaPro ? PharmaPro.formatMoney(n) : new Intl.NumberFormat('fr-FR').format(n) + ' FCFA';
    }

    function cartSubtotal() {
        return cart.reduce(function (sum, item) {
            return sum + item.unit_price * item.quantity;
        }, 0);
    }

    function renderCart() {
        if (cart.length === 0) {
            cartLines.innerHTML = '<div class="text-center text-muted py-5">Panier vide — scannez un produit</div>';
        } else {
            cartLines.innerHTML = cart.map(function (item, idx) {
                return '<div class="pharma-pro-pos-line">' +
                    '<div><strong>' + escapeHtml(item.name) + '</strong><br>' +
                    '<small class="text-muted">' + formatMoney(item.unit_price) + ' × </small>' +
                    '<input type="number" min="1" value="' + item.quantity + '" data-idx="' + idx + '" class="form-control form-control-sm d-inline-block pos-qty-input" style="width:70px">' +
                    '</div>' +
                    '<div class="text-end">' +
                    '<div>' + formatMoney(item.unit_price * item.quantity) + '</div>' +
                    '<button type="button" class="btn btn-link btn-sm text-danger p-0 pos-remove" data-idx="' + idx + '"><i class="fas fa-times"></i></button>' +
                    '</div></div>';
            }).join('');
        }

        const sub = cartSubtotal();
        cartCount.textContent = cart.reduce(function (s, i) { return s + i.quantity; }, 0);
        subtotalEl.textContent = formatMoney(sub);
        totalEl.textContent = formatMoney(sub);
        if (!amountPaidEl.dataset.manual) {
            amountPaidEl.value = Math.ceil(sub);
        }
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function addToCart(product) {
        const stock = parseInt(product.stock_available || product.stock_total || 0, 10);
        const existing = cart.find(function (i) { return i.product_id === parseInt(product.id, 10); });
        if (existing) {
            if (stock > 0 && existing.quantity >= stock) {
                alert('Stock insuffisant (' + stock + ' disponible)');
                return;
            }
            existing.quantity += 1;
        } else {
            if (stock <= 0) {
                if (!confirm('Stock à zéro. Ajouter quand même ?')) return;
            }
            cart.push({
                product_id: parseInt(product.id, 10),
                name: product.name,
                unit_price: parseFloat(product.sale_price),
                quantity: 1,
            });
        }
        renderCart();
        searchInput.value = '';
        searchResults.style.display = 'none';
        searchInput.focus();
    }

    function doSearch(q) {
        if (q.length < 1) {
            searchResults.style.display = 'none';
            return;
        }
        fetch(apiSearch + '?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.exact && data.results && data.results[0]) {
                    addToCart(data.results[0]);
                    return;
                }
                if (!data.results || !data.results.length) {
                    searchResultsBody.innerHTML = '<p class="text-muted mb-0">Aucun produit trouvé</p>';
                    searchResults.style.display = '';
                    return;
                }
                searchResultsBody.innerHTML = data.results.map(function (p) {
                    return '<button type="button" class="btn btn-pharma-outline w-100 mb-2 text-start pos-pick" data-product=\'' +
                        JSON.stringify(p).replace(/'/g, '&#39;') + '\'>' +
                        '<strong>' + escapeHtml(p.name) + '</strong> — ' + formatMoney(parseFloat(p.sale_price)) +
                        ' <small class="text-muted">(' + escapeHtml(p.sku) + ')</small></button>';
                }).join('');
                searchResults.style.display = '';
            })
            .catch(function () {
                searchResultsBody.innerHTML = '<p class="text-danger mb-0">Erreur recherche</p>';
                searchResults.style.display = '';
            });
    }

    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { doSearch(searchInput.value.trim()); }, 250);
    });

    searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            doSearch(searchInput.value.trim());
        }
    });

    document.addEventListener('click', function (e) {
        const pick = e.target.closest('.pos-pick');
        if (pick) {
            try {
                addToCart(JSON.parse(pick.dataset.product));
            } catch (err) {}
            return;
        }
        const remove = e.target.closest('.pos-remove');
        if (remove) {
            cart.splice(parseInt(remove.dataset.idx, 10), 1);
            renderCart();
            return;
        }
        const qtyInput = e.target.closest('.pos-qty-input');
        if (qtyInput && e.type === 'change') {
            const idx = parseInt(qtyInput.dataset.idx, 10);
            cart[idx].quantity = Math.max(1, parseInt(qtyInput.value, 10) || 1);
            renderCart();
        }
    });

    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('pos-qty-input')) {
            const idx = parseInt(e.target.dataset.idx, 10);
            cart[idx].quantity = Math.max(1, parseInt(e.target.value, 10) || 1);
            renderCart();
        }
    });

    amountPaidEl.addEventListener('input', function () {
        amountPaidEl.dataset.manual = '1';
    });

    clearBtn.addEventListener('click', function () {
        cart = [];
        amountPaidEl.dataset.manual = '';
        renderCart();
    });

    function checkout() {
        if (cart.length === 0) {
            alert('Panier vide');
            return;
        }
        checkoutBtn.disabled = true;
        fetch(apiSale, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                pharmacy_id: pharmacyId,
                register_id: registerId,
                deposit_id: depositId,
                lines: cart.map(function (i) {
                    return { product_id: i.product_id, quantity: i.quantity, unit_price: i.unit_price };
                }),
                payment_method: paymentMethodEl.value,
                amount_paid: parseFloat(amountPaidEl.value) || cartSubtotal(),
                promo_code: promoCodeEl ? promoCodeEl.value.trim() : '',
                loyalty_phone: loyaltyPhoneEl ? loyaltyPhoneEl.value.trim() : '',
            }),
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                checkoutBtn.disabled = false;
                if (data.error) {
                    alert(data.error);
                    return;
                }
                const sale = data.sale;
                const change = sale.change_amount || 0;
                let msg = 'Vente ' + sale.sale_number + ' — ' + formatMoney(sale.total_ttc);
                if (change > 0) msg += '\nMonnaie : ' + formatMoney(change);
                if (window.PharmaPro) {
                    PharmaPro.toast(msg.replace('\n', ' · '), 'success');
                } else {
                    alert(msg);
                }
                cart = [];
                amountPaidEl.dataset.manual = '';
                if (promoCodeEl) promoCodeEl.value = '';
                if (loyaltyPhoneEl) loyaltyPhoneEl.value = '';
                renderCart();
                if (sale && sale.id) {
                    window.open(ticketBase + sale.id, '_blank', 'noopener');
                }
                searchInput.focus();
            })
            .catch(function () {
                checkoutBtn.disabled = false;
                alert('Erreur lors de la vente');
            });
    }

    checkoutBtn.addEventListener('click', checkout);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'F2') {
            e.preventDefault();
            checkout();
        }
        if (e.key === 'Escape') {
            cart = [];
            renderCart();
        }
    });

    renderCart();
})();
