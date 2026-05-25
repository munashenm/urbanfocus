@if(empty($embedded))
<aside class="col-lg-3">
@endif
    <div class="filter-sidebar">
        <div class="d-flex justify-content-between align-items-center mb-3 d-lg-none">
            <h6 class="fw-bold mb-0">Filters</h6>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#shopFilters">Toggle</button>
        </div>
        <div class="collapse d-lg-block" id="shopFilters">
            <h6 class="fw-bold mb-3 d-none d-lg-block">Filters</h6>
            <form method="GET" action="{{ $filterAction ?? route('shop.index') }}">
                @foreach(request()->except(['category', 'brand', 'in_stock', 'deals', 'price_min', 'price_max', 'sort', 'page']) as $key => $val)
                    @if(is_string($val))<input type="hidden" name="{{ $key }}" value="{{ $val }}">@endif
                @endforeach
                @if(!empty($showCategoryFilter) && $categories->count())
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Category</label>
                    <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->slug }}" @selected(request('category') === $cat->slug)>{{ $cat->name }}</option>
                            @foreach($cat->children ?? [] as $child)
                                <option value="{{ $child->slug }}" @selected(request('category') === $child->slug)>— {{ $child->name }}</option>
                            @endforeach
                        @endforeach
                    </select>
                </div>
                @endif
                @if(!empty($showBrandFilter ?? true) && $brands->count())
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Brand</label>
                    <select name="brand" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Brands</option>
                        @foreach($brands as $b)
                            <option value="{{ $b }}" @selected(request('brand') === $b)>{{ $b }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Price (ZAR)</label>
                    <div class="row g-2">
                        <div class="col-6"><input type="number" name="price_min" class="form-control form-control-sm" placeholder="Min" value="{{ request('price_min') }}" min="0"></div>
                        <div class="col-6"><input type="number" name="price_max" class="form-control form-control-sm" placeholder="Max" value="{{ request('price_max') }}" min="0"></div>
                    </div>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="in_stock" value="1" id="inStock" @checked(request('in_stock'))>
                    <label class="form-check-label small" for="inStock">In stock only</label>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="deals" value="1" id="dealsOnly" @checked(request('deals'))>
                    <label class="form-check-label small" for="dealsOnly">Deals only</label>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Sort by</label>
                    <select name="sort" class="form-select form-select-sm">
                        <option value="newest" @selected(request('sort', 'newest') === 'newest')>Newest</option>
                        <option value="popular" @selected(request('sort') === 'popular')>Popular</option>
                        <option value="price_asc" @selected(request('sort') === 'price_asc')>Price: Low to High</option>
                        <option value="price_desc" @selected(request('sort') === 'price_desc')>Price: High to Low</option>
                        <option value="name" @selected(request('sort') === 'name')>Name</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-sm w-100">Apply Filters</button>
            </form>
        </div>
    </div>
@if(empty($embedded))
</aside>
@endif
