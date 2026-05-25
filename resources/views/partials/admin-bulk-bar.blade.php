<div class="bulk-action-bar d-none align-items-center gap-3 mb-3 px-3 py-2 bg-light border rounded" id="bulk-action-bar">
    <span class="small mb-0"><strong id="bulk-selected-count">0</strong> selected on this page</span>
    <button type="submit" class="btn btn-sm btn-danger" id="bulk-delete-btn">{{ $deleteLabel ?? 'Delete selected' }}</button>
</div>

<script>
(function () {
    const bar = document.getElementById('bulk-action-bar');
    const countEl = document.getElementById('bulk-selected-count');
    const selectAll = document.getElementById('bulk-select-all');
    const boxes = () => Array.from(document.querySelectorAll('.bulk-select'));

    function updateBar() {
        const checked = boxes().filter((cb) => cb.checked);
        if (countEl) countEl.textContent = String(checked.length);
        if (bar) bar.classList.toggle('d-none', checked.length === 0);
        if (bar) bar.classList.toggle('d-flex', checked.length > 0);
        if (selectAll) {
            const all = boxes();
            selectAll.checked = all.length > 0 && checked.length === all.length;
            selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
        }
    }

    selectAll?.addEventListener('change', () => {
        boxes().forEach((cb) => { cb.checked = selectAll.checked; });
        updateBar();
    });

    boxes().forEach((cb) => cb.addEventListener('change', updateBar));
    updateBar();
})();
</script>
