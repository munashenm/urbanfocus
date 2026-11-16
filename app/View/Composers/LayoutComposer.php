<?php

namespace App\View\Composers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\SearchService;
use App\Support\StorefrontCache;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class LayoutComposer
{
    public function __construct(protected SearchService $search) {}

    protected function blackFridayActive(): bool
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'sale_starts_at')) {
            return false;
        }

        return (bool) StorefrontCache::remember(StorefrontCache::BLACK_FRIDAY_ACTIVE, function () {
            return Product::query()->where('is_active', true)->onSale()->exists();
        }, 60);
    }

    public function compose(View $view): void
    {
        // header and footer both use this composer; share one payload per request.
        $sharedKey = 'storefront.layout_composer.payload';

        try {
            if (app()->bound($sharedKey)) {
                $payload = app($sharedKey);
            } else {
                $payload = StorefrontCache::remember(StorefrontCache::NAV, function () {
                    $brands = collect();
                    try {
                        $brands = Schema::hasTable('brands')
                            ? Brand::where('is_active', true)->orderBy('sort_order')->take(12)->get()
                            : collect();
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
                $payload['blackFridayActive'] = $this->blackFridayActive();
                app()->instance($sharedKey, $payload);
            }

            $view->with($payload);
        } catch (\Throwable) {
            $view->with('navCategories', collect());
            $view->with('megaMenuCategories', []);
            $view->with('navBrands', collect());
            $view->with('blackFridayActive', false);
        }
    }
}
