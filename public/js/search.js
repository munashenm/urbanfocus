(function () {
    const input = document.getElementById('searchInput');
    const box = document.getElementById('searchSuggestions');
    if (!input || !box) return;

    const url = input.dataset.suggestUrl;
    let timer = null;
    let idx = -1;

    function hide() {
        box.classList.add('d-none');
        box.innerHTML = '';
        idx = -1;
    }

    function render(items) {
        if (!items.length) {
            hide();
            return;
        }
        box.innerHTML = items.map(function (item, i) {
            const img = item.image
                ? '<img src="' + item.image + '" alt="" width="40" height="40" loading="lazy">'
                : '<span class="search-no-img"></span>';
            return '<a href="' + item.url + '" class="search-item' + (i === idx ? ' active' : '') + '" data-idx="' + i + '">' +
                img +
                '<span><strong>' + item.name + '</strong>' +
                '<small>' + (item.brand ? item.brand + ' · ' : '') + item.price + (item.sku ? ' · ' + item.sku : '') + '</small></span></a>';
        }).join('') + '<a href="' + (input.form ? input.form.action : '/shop') + '?q=' + encodeURIComponent(input.value) + '" class="search-view-all">View all results</a>';
        box.classList.remove('d-none');
    }

    function fetchSuggest(q) {
        fetch(url + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) { render(data.results || []); })
            .catch(function () { hide(); });
    }

    input.addEventListener('input', function () {
        clearTimeout(timer);
        const q = input.value.trim();
        if (q.length < 2) { hide(); return; }
        timer = setTimeout(function () { fetchSuggest(q); }, 250);
    });

    input.addEventListener('keydown', function (e) {
        const items = box.querySelectorAll('.search-item');
        if (!items.length) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            idx = Math.min(idx + 1, items.length - 1);
            render(Array.from(items).map(function (el) { return { url: el.href }; }));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            idx = Math.max(idx - 1, 0);
        } else if (e.key === 'Escape') {
            hide();
        }
    });

    document.addEventListener('click', function (e) {
        if (!box.contains(e.target) && e.target !== input) hide();
    });
})();
