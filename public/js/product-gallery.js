(function () {
    const main = document.getElementById('productMainImage');
    const thumbs = document.querySelectorAll('.product-thumb');
    const zoom = document.getElementById('productZoomButton');
    const dialog = document.getElementById('productLightbox');
    const dialogImage = document.getElementById('productLightboxImage');
    const closeButton = document.getElementById('productLightboxClose');

    function show(src, alt) {
        if (!main) return;
        main.src = src;
        if (alt) main.alt = alt;
    }

    if (main && thumbs.length) {
        thumbs.forEach(function (thumb) {
            thumb.addEventListener('click', function () {
                show(thumb.currentSrc || thumb.src, thumb.alt);
                thumbs.forEach(function (item) { item.classList.remove('active'); });
                thumb.classList.add('active');
            });
        });
    }

    function openLightbox() {
        if (!dialog || !dialogImage || !main) return;
        dialogImage.src = main.currentSrc || main.src;
        dialogImage.alt = main.alt || 'Product image';
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', 'open');
        }
    }

    if (zoom) {
        zoom.addEventListener('click', openLightbox);
    }

    if (closeButton && dialog) {
        closeButton.addEventListener('click', function () {
            if (typeof dialog.close === 'function') {
                dialog.close();
            } else {
                dialog.removeAttribute('open');
            }
        });
    }

    if (dialog) {
        dialog.addEventListener('click', function (event) {
            if (event.target === dialog && typeof dialog.close === 'function') {
                dialog.close();
            }
        });
    }
})();
