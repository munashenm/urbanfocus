<?php

namespace App\View\Composers;

use App\Models\Brand;
use App\Models\Category;
use App\Services\SearchService;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class LayoutComposer
{
    public function __construct(protected SearchService $search) {}

    public function compose(View $view): void
    {
        try {
            $view->with('navCategories', Category::where('is_active', true)
                ->whereNull('parent_id')
                ->visibleInCatalog()
                ->orderBy('sort_order')
                ->take(12)
                ->get());

            $view->with('megaMenuCategories', $this->search->megaMenuCategories());

            $view->with('navBrands', Schema::hasTable('brands')
                ? Brand::where('is_active', true)->orderBy('sort_order')->take(12)->get()
                : collect());
        } catch (\Throwable) {
            $view->with('navCategories', collect());
            $view->with('megaMenuCategories', []);
            $view->with('navBrands', collect());
        }
    }
}
