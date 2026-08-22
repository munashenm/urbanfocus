@php
    $shareUrl = route('blog.show', $article);
    $shareText = $article->title;
@endphp
<div class="blog-share d-flex flex-wrap align-items-center gap-2 my-4 py-3 border-top border-bottom">
    <span class="small fw-semibold text-muted me-1">Share:</span>
    <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"
       href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($shareUrl) }}" aria-label="Share on Facebook">Facebook</a>
    <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"
       href="https://twitter.com/intent/tweet?url={{ urlencode($shareUrl) }}&text={{ urlencode($shareText) }}" aria-label="Share on X">X</a>
    <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"
       href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode($shareUrl) }}" aria-label="Share on LinkedIn">LinkedIn</a>
    <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"
       href="mailto:?subject={{ urlencode($shareText) }}&body={{ urlencode($shareUrl) }}" aria-label="Share by email">Email</a>
    <button type="button" class="btn btn-sm btn-outline-secondary" data-copy-link="{{ $shareUrl }}">Copy link</button>
</div>
<script>
document.querySelectorAll('[data-copy-link]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var url = btn.getAttribute('data-copy-link');
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(function () {
                var original = btn.textContent;
                btn.textContent = 'Copied!';
                setTimeout(function () { btn.textContent = original; }, 1500);
            });
        }
    });
});
</script>
