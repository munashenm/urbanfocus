(function () {
    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function initSearch(inputId, boxId) {
        const input = document.getElementById(inputId);
        const box = document.getElementById(boxId);
        if (!input || !box) return;

        const url = input.dataset.suggestUrl;
        const placeholder = input.dataset.placeholderImg || '/images/product-placeholder.svg';
        let timer = null;
        let items = [];
        let idx = -1;

        function hide() {
            box.classList.add('d-none');
            box.innerHTML = '';
            items = [];
            idx = -1;
        }

        function render(data) {
            const products = data.products || [];
            const brands = data.brands || [];
            const categories = data.categories || [];

            if (!products.length && !brands.length && !categories.length) {
                hide();
                return;
            }

            let html = '';

            if (brands.length) {
                html += '<div class="search-group-label">Brands</div>';
                brands.forEach(function (b) {
                    html += '<a href="' + escapeHtml(b.url) + '" class="search-item search-item--meta"><span><strong>' + escapeHtml(b.name) + '</strong><small>Brand</small></span></a>';
                });
            }

            if (categories.length) {
                html += '<div class="search-group-label">Categories</div>';
                categories.forEach(function (c) {
                    html += '<a href="' + escapeHtml(c.url) + '" class="search-item search-item--meta"><span><strong>' + escapeHtml(c.name) + '</strong><small>Category</small></span></a>';
                });
            }

            if (products.length) {
                html += '<div class="search-group-label">Products</div>';
                products.forEach(function (item, i) {
                    const img = item.image
                        ? '<img src="' + escapeHtml(item.image) + '" alt="" width="44" height="44" loading="lazy">'
                        : '<img src="' + escapeHtml(placeholder) + '" alt="" width="44" height="44" loading="lazy" class="search-placeholder-img">';
                    const stock = item.in_stock ? '<span class="text-success">In stock</span>' : '<span class="text-danger">Out of stock</span>';
                    html += '<a href="' + escapeHtml(item.url) + '" class="search-item' + (i === idx ? ' active' : '') + '">' +
                        img +
                        '<span><strong>' + escapeHtml(item.name) + '</strong>' +
                        '<small>' + (item.brand ? escapeHtml(item.brand) + ' · ' : '') + escapeHtml(item.price) + (item.sku ? ' · ' + escapeHtml(item.sku) : '') + ' · ' + stock + '</small></span></a>';
                });
            }

            html += '<a href="' + (input.form ? input.form.action : '/shop') + '?q=' + encodeURIComponent(input.value) + '" class="search-view-all">View all results for "' + escapeHtml(input.value) + '"</a>';
            box.innerHTML = html;
            box.classList.remove('d-none');
            items = Array.from(box.querySelectorAll('.search-item'));
        }

        function fetchSuggest(q) {
            fetch(url + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) { render(data); })
                .catch(function () { hide(); });
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            const q = input.value.trim();
            if (q.length < 2) { hide(); return; }
            timer = setTimeout(function () { fetchSuggest(q); }, 200);
        });

        input.addEventListener('keydown', function (e) {
            if (!items.length) return;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                idx = Math.min(idx + 1, items.length - 1);
                items.forEach(function (el, i) { el.classList.toggle('active', i === idx); });
                items[idx]?.scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                idx = Math.max(idx - 1, 0);
                items.forEach(function (el, i) { el.classList.toggle('active', i === idx); });
            } else if (e.key === 'Enter' && idx >= 0 && items[idx]) {
                e.preventDefault();
                window.location.href = items[idx].href;
            } else if (e.key === 'Escape') {
                hide();
            }
        });

        document.addEventListener('click', function (e) {
            if (!box.contains(e.target) && e.target !== input) hide();
        });
    }

    initSearch('searchInput', 'searchSuggestions');
    initSearch('mobileSearchInput', 'mobileSearchSuggestions');
})();
