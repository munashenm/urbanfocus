@if(count($tocItems) >= 2)
<nav class="blog-toc mb-4" aria-label="Table of contents">
    <p class="fw-bold small text-uppercase text-muted mb-2">On this page</p>
    <ol class="mb-0 ps-3">
        @foreach($tocItems as $item)
            <li class="{{ $item['level'] === 3 ? 'ms-3' : '' }}">
                <a href="#{{ $item['id'] }}">{{ $item['text'] }}</a>
            </li>
        @endforeach
    </ol>
</nav>
@endif
