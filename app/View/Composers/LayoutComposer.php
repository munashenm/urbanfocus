<?php

namespace App\View\Composers;

use App\Models\Brand;
use App\Models\Category;
use App\Services\SearchService;
use App\Support\StorefrontCache;
use Illuminate\View\View;

class LayoutComposer
{
    public function __construct(protected SearchService $search) {}

    public function compose(View $view): void
    {
        try {
            $data = StorefrontCache::remember(StorefrontCache::NAV, function () {
                $brands = collect();
                try {
                    $brands = Brand::where('is_active', true)->orderBy('sort_order')->take(12)->get();
                } catch (\Throwable) {
                    $brands = collect();
                }

                return [
                    'navCategories' => Category::where('is_active', true)
                        ->whereNull('parent_id')
                        ->visibleInCatalog()
                        ->orderBy('sort_order')
                        ->take(12)
                        ->get(),
                    'megaMenuCategories' => $this->search->megaMenuCategories(),
                    'navBrands' => $brands,
                ];
            });

            $view->with($data);
        } catch (\Throwable) {
            $view->with('navCategories', collect());
            $view->with('megaMenuCategories', []);
            $view->with('navBrands', collect());
        }
    }
}
