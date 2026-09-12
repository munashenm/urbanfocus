@php
    $priority = config('homepage.category_priority', []);
    $directoryCategories = $categories->sortBy(function ($category) use ($priority) {
        $index = array_search($category->slug, $priority, true);

        return $index === false ? 1000 + (int) $category->sort_order : $index;
    })->values();
@endphp
<section id="shop-category-directory" class="shop-category-directory mb-4" aria-labelledby="shop-category-directory-title">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div>
            <h2 id="shop-category-directory-title" class="h4 fw-bold mb-1">Browse all categories</h2>
            <p class="small text-muted mb-0">Every parent category and subcategory in the Urban Focus catalogue.</p>
        </div>
    </div>
    <div class="row g-3 g-lg-4">
        @foreach($directoryCategories as $category)
            <div class="col-6 col-md-4 col-lg-3 shop-category-column">
                <h3 class="h6 fw-bold mb-2">
                    <a href="{{ $category->url() }}">{{ $category->name }}</a>
                </h3>
                @if($category->children->count())
                    <ul class="list-unstyled shop-category-children mb-0">
                        @foreach($category->children as $child)
                            <li><a href="{{ $child->url() }}">{{ $child->name }}</a></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    </div>
</section>
