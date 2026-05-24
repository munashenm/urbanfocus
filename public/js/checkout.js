(function () {
    const dataEl = document.getElementById('checkout-data');
    if (!dataEl) return;

    const data = JSON.parse(dataEl.textContent);
    const subtotal = data.subtotal;
    const vatRate = data.vatRate;
    const flatRate = data.flatRate;
    const freeThreshold = data.freeThreshold;

    const discountRow = document.getElementById('checkout-discount-row');
    const discountValue = document.getElementById('checkout-discount');
    const shippingRow = document.getElementById('checkout-shipping-row');
    const shippingValue = document.getElementById('checkout-shipping');
    const shippingNote = document.getElementById('checkout-shipping-note');
    const vatValue = document.getElementById('checkout-vat');
    const totalValue = document.getElementById('checkout-total');
    const couponInput = document.querySelector('[name="coupon_code"]');
    const couponFeedback = document.getElementById('coupon-feedback');
    const applyBtn = document.getElementById('apply-coupon-btn');

    let discountAmount = 0;

    function formatMoney(amount) {
        return 'R ' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function selectedShippingMethod() {
        const selected = document.querySelector('[name="shipping_method"]:checked');
        return selected ? selected.value : 'courier';
    }

    function shippingCost(method, discountedSubtotal) {
        if (method === 'manual_quote' || method === 'collection') {
            return 0;
        }

        return discountedSubtotal >= freeThreshold ? 0 : flatRate;
    }

    function updateTotals() {
        const method = selectedShippingMethod();
        const discountedSubtotal = Math.max(0, subtotal - discountAmount);
        const ship = shippingCost(method, discountedSubtotal);
        const taxable = discountedSubtotal + ship;
        const vat = Math.round(taxable * (vatRate / 100) * 100) / 100;
        const total = discountedSubtotal + ship + vat;

        if (discountAmount > 0) {
            discountRow.classList.remove('d-none');
            discountValue.textContent = '−' + formatMoney(discountAmount);
        } else {
            discountRow.classList.add('d-none');
        }

        shippingValue.textContent = ship > 0 ? formatMoney(ship) : 'Free';
        shippingNote.classList.toggle('d-none', method !== 'manual_quote');
        vatValue.textContent = formatMoney(vat);
        totalValue.textContent = formatMoney(total);
    }

    document.querySelectorAll('[name="shipping_method"]').forEach(function (input) {
        input.addEventListener('change', updateTotals);
    });

    if (applyBtn && couponInput) {
        applyBtn.addEventListener('click', function () {
            const code = couponInput.value.trim();
            couponFeedback.textContent = '';
            couponFeedback.className = 'small mt-2';

            if (!code) {
                discountAmount = 0;
                updateTotals();
                return;
            }

            applyBtn.disabled = true;

            fetch(data.validateCouponUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': data.csrfToken,
                },
                body: JSON.stringify({ coupon_code: code }),
            })
                .then(function (response) {
                    return response.json().then(function (body) {
                        return { ok: response.ok, body: body };
                    });
                })
                .then(function (result) {
                    if (result.ok && result.body.valid) {
                        discountAmount = result.body.discount;
                        couponFeedback.textContent = 'Coupon applied: ' + result.body.code;
                        couponFeedback.classList.add('text-success');
                    } else {
                        discountAmount = 0;
                        couponFeedback.textContent = result.body.message || 'Invalid coupon code.';
                        couponFeedback.classList.add('text-danger');
                    }
                    updateTotals();
                })
                .catch(function () {
                    discountAmount = 0;
                    couponFeedback.textContent = 'Could not validate coupon. Please try again.';
                    couponFeedback.classList.add('text-danger');
                    updateTotals();
                })
                .finally(function () {
                    applyBtn.disabled = false;
                });
        });
    }

    updateTotals();
})();
