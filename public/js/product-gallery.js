(function () {
    const main = document.getElementById('productMainImage');
    const thumbs = document.querySelectorAll('.product-thumb');
    if (!main || !thumbs.length) return;

    thumbs.forEach(function (thumb) {
        thumb.addEventListener('click', function () {
            main.src = thumb.src.replace(/w=\d+/, '').replace(/h=\d+/, '') || thumb.src;
            thumbs.forEach(function (t) { t.classList.remove('active'); });
            thumb.classList.add('active');
        });
    });
})();
