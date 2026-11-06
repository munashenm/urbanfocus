@if($categories->count())
<div class="filter-sidebar mb-3">
    <p class="fw-bold mb-3">{{ $title ?? 'Categories' }}</p>
    <ul class="category-subnav-list list-unstyled mb-0">
        @foreach($categories as $item)
            <li>
                <a href="{{ $item->url() }}" class="category-subnav-link">{{ $item->name }}</a>
            </li>
        @endforeach
    </ul>
</div>
@endif
