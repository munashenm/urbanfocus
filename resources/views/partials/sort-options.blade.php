@php
    $sortValue = $currentSort ?? app(\App\Services\CatalogBrowseService::class)->requestedSort(request());
@endphp
@if(request('q'))
    <option value="relevance" @selected($sortValue === 'relevance')>Best match</option>
@endif
<option value="recommended" @selected($sortValue === 'recommended')>Recommended</option>
<option value="newest" @selected($sortValue === 'newest')>Newest</option>
<option value="popular" @selected($sortValue === 'popular')>Popular</option>
<option value="price_asc" @selected($sortValue === 'price_asc')>Price: Low to High</option>
<option value="price_desc" @selected($sortValue === 'price_desc')>Price: High to Low</option>
<option value="name" @selected($sortValue === 'name')>Name</option>
