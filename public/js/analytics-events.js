(function () {
    window.dataLayer = window.dataLayer || [];

    function payload(extra) {
        extra = extra || {};
        Object.keys(extra).forEach(function (key) {
            if (extra[key] === null || extra[key] === undefined || extra[key] === '') {
                delete extra[key];
            }
        });
        return extra;
    }

    function track(name, params) {
        var data = payload(params);
        window.dataLayer.push(Object.assign({ event: name }, data));
        if (typeof gtag === 'function') {
            gtag('event', name, data);
        }
    }

    window.ufTrack = track;

    document.addEventListener('click', function (event) {
        var el = event.target.closest('[data-analytics-event], a[href^="tel:"], a[href^="mailto:"], a[href*="wa.me"], a[href*="whatsapp"]');
        if (!el) {
            return;
        }

        var name = el.getAttribute('data-analytics-event');
        if (!name) {
            var href = (el.getAttribute('href') || '').toLowerCase();
            if (href.indexOf('tel:') === 0) {
                name = 'phone_click';
            } else if (href.indexOf('mailto:') === 0) {
                name = 'email_click';
            } else if (href.indexOf('wa.me') !== -1 || href.indexOf('whatsapp') !== -1) {
                name = 'whatsapp_click';
            }
        }

        if (name) {
            track(name, {});
        }
    });

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (form.getAttribute('data-analytics-submit')) {
            track(form.getAttribute('data-analytics-submit'), {});
            return;
        }

        if (form.id === 'searchForm' || form.id === 'mobileSearchForm' || (form.getAttribute('role') === 'search' && form.querySelector('[name="q"]'))) {
            var q = form.querySelector('[name="q"]');
            track('search', { search_term: q ? String(q.value || '').slice(0, 80) : '' });
            return;
        }

        if ((form.getAttribute('action') || '').indexOf('/cart/') !== -1) {
            track('add_to_cart', window.ufAnalyticsItem || {});
            return;
        }

        if (form.id === 'checkout-form') {
            track('begin_checkout', window.ufCheckoutValue || {});
        }
    });

    if (window.ufAnalyticsItem && window.ufAnalyticsViewItem) {
        track('view_item', { items: [window.ufAnalyticsItem], value: window.ufAnalyticsItem.price || undefined, currency: 'ZAR' });
    }

    if (window.ufPurchase) {
        track('purchase', window.ufPurchase);
    }
})();
