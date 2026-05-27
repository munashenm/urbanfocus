@if(!empty($brands) && $brands->count())
<div class="section-brand-strip" aria-label="Featured brands in this category">
    @include('partials.brand-logos', ['brands' => $brands])
</div>
@endif
