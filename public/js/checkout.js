(function () {
    const dataEl = document.getElementById('checkout-data');
    if (!dataEl) return;

    const data = JSON.parse(dataEl.textContent);
    const subtotal = data.subtotal;
    const vatRate = data.vatRate;
    const flatRate = data.flatRate;
    const freeThreshold = data.freeThreshold;
    const pricesIncludeVat = data.pricesIncludeVat !== false;

    const discountRow = document.getElementById('checkout-discount-row');
    const discountValue = document.getElementById('checkout-discount');
    const shippingValue = document.getElementById('checkout-shipping');
    const shippingNote = document.getElementById('checkout-shipping-note');
    const vatValue = document.getElementById('checkout-vat');
    const totalValue = document.getElementById('checkout-total');
    const couponInput = document.querySelector('[name="coupon_code"]');
    const couponFeedback = document.getElementById('coupon-feedback');
    const applyBtn = document.getElementById('apply-coupon-btn');
    const submitBtn = document.querySelector('form[action*="checkout"] button[type="submit"]');

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

    function calculateTaxAndTotal(discountedSubtotal, ship) {
        if (pricesIncludeVat) {
            const total = discountedSubtotal + ship;
            const vat = Math.round(total * (vatRate / (100 + vatRate)) * 100) / 100;
            return { vat: vat, total: total };
        }

        const taxable = discountedSubtotal + ship;
        const vat = Math.round(taxable * (vatRate / 100) * 100) / 100;
        return { vat: vat, total: taxable + vat };
    }

    function updateTotals() {
        const method = selectedShippingMethod();
        const discountedSubtotal = Math.max(0, subtotal - discountAmount);
        const ship = shippingCost(method, discountedSubtotal);
        const amounts = calculateTaxAndTotal(discountedSubtotal, ship);

        if (discountAmount > 0) {
            discountRow.classList.remove('d-none');
            discountValue.textContent = '−' + formatMoney(discountAmount);
        } else {
            discountRow.classList.add('d-none');
        }

        shippingValue.textContent = ship > 0 ? formatMoney(ship) : 'Free';
        shippingNote.classList.toggle('d-none', method !== 'manual_quote');
        vatValue.textContent = formatMoney(amounts.vat);
        totalValue.textContent = formatMoney(amounts.total);
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

    function resetSubmitButton() {
        if (!submitBtn) return;
        submitBtn.disabled = false;
        submitBtn.removeAttribute('aria-busy');
        submitBtn.textContent = submitBtn.getAttribute('data-label') || 'Continue to secure payment';
    }

    if (submitBtn) {
        const form = submitBtn.closest('form');
        form?.addEventListener('submit', function (event) {
            if (form.dataset.checkoutSubmitting === '1') {
                event.preventDefault();
                return;
            }

            form.dataset.checkoutSubmitting = '1';
            submitBtn.setAttribute('aria-busy', 'true');
            submitBtn.textContent = 'Taking you to payment…';

            // Chrome cancels the POST if the clicked submit button is disabled
            // in the same turn as the submit event. Disable on the next tick.
            setTimeout(function () {
                submitBtn.disabled = true;
            }, 0);

            setTimeout(function () {
                if (document.visibilityState === 'visible' && form.dataset.checkoutSubmitting === '1') {
                    form.dataset.checkoutSubmitting = '';
                    resetSubmitButton();
                }
            }, 25000);
        });
    }

    window.addEventListener('pageshow', function (event) {
        const form = submitBtn ? submitBtn.closest('form') : null;
        if (event.persisted || (form && form.dataset.checkoutSubmitting === '1' && document.visibilityState === 'visible')) {
            if (form) {
                form.dataset.checkoutSubmitting = '';
            }
            resetSubmitButton();
        }
    });

    updateTotals();
})();
