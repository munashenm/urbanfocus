@php
    $media = $article->featuredImage();
    $loading = $loading ?? 'lazy';
    $fetchpriority = $fetchpriority ?? null;
    $class = $class ?? '';
@endphp
<picture>
    @if(!empty($media['webp']) && ($media['jpg'] ?? null) !== $media['webp'])
        <source type="image/webp" srcset="{{ $media['webp'] }}">
    @endif
    <img
        src="{{ $media['url'] }}"
        alt="{{ $media['alt'] }}"
        width="{{ $media['width'] }}"
        height="{{ $media['height'] }}"
        sizes="{{ $sizes ?? '(max-width: 768px) 100vw, (max-width: 1200px) 50vw, 640px' }}"
        loading="{{ $loading }}"
        decoding="async"
        @if($fetchpriority) fetchpriority="{{ $fetchpriority }}" @endif
        @if($class !== '') class="{{ $class }}" @endif
    >
</picture>
