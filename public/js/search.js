(function () {
    const input = document.getElementById('searchInput');
    const box = document.getElementById('searchSuggestions');
    if (!input || !box) return;

    const url = input.dataset.suggestUrl;
    const placeholder = input.dataset.placeholderImg || '/images/product-placeholder.svg';
    let timer = null;
    let items = [];
    let idx = -1;
    let lastData = null;

    function hide() {
        box.classList.add('d-none');
        box.innerHTML = '';
        items = [];
        idx = -1;
        lastData = null;
    }

    function render(data) {
        lastData = data;
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
            brands.forEach(function (b, i) {
                html += '<a href="' + b.url + '" class="search-item search-item--meta" data-idx="' + i + '"><span><strong>' + b.name + '</strong><small>Brand</small></span></a>';
            });
        }

        if (categories.length) {
            html += '<div class="search-group-label">Categories</div>';
            categories.forEach(function (c) {
                html += '<a href="' + c.url + '" class="search-item search-item--meta"><span><strong>' + c.name + '</strong><small>Category</small></span></a>';
            });
        }

        if (products.length) {
            html += '<div class="search-group-label">Products</div>';
            products.forEach(function (item, i) {
                const img = item.image
                    ? '<img src="' + item.image + '" alt="" width="44" height="44" loading="lazy">'
                    : '<img src="' + placeholder + '" alt="" width="44" height="44" loading="lazy" class="search-placeholder-img">';
                const stock = item.in_stock ? '<span class="text-success">In stock</span>' : '<span class="text-danger">Out of stock</span>';
                html += '<a href="' + item.url + '" class="search-item' + (i === idx ? ' active' : '') + '" data-idx="' + i + '">' +
                    img +
                    '<span><strong>' + item.name + '</strong>' +
                    '<small>' + (item.brand ? item.brand + ' · ' : '') + item.price + (item.sku ? ' · ' + item.sku : '') + ' · ' + stock + '</small></span></a>';
            });
        }

        html += '<a href="' + (input.form ? input.form.action : '/shop') + '?q=' + encodeURIComponent(input.value) + '" class="search-view-all">View all results for "' + input.value + '"</a>';
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
})();
