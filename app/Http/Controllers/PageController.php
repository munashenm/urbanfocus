<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PageController extends Controller
{
    public function about(): View
    {
        $featuredCategories = collect();
        $featuredBrands = collect();

        if (Schema::hasTable('categories')) {
            $featuredCategories = Category::query()
                ->where('is_active', true)
                ->whereNull('parent_id')
                ->visibleInCatalog()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->take(8)
                ->get();
        }

        if (Schema::hasTable('brands')) {
            $featuredBrands = Brand::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->take(12)
                ->get();
        }

        return view('pages.about', [
            'pageSeo' => config('page_seo.about', []),
            'featuredCategories' => $featuredCategories,
            'featuredBrands' => $featuredBrands,
        ]);
    }

    public function shipping(): View
    {
        return $this->render('pages.shipping', 'shipping');
    }

    public function returns(): View
    {
        return $this->render('pages.returns', 'returns');
    }

    public function faq(): View
    {
        return $this->render('pages.faq', 'faq');
    }

    public function warranty(): View
    {
        return $this->render('pages.warranty', 'warranty');
    }

    public function popia(): View
    {
        return $this->render('pages.popia', 'popia');
    }

    public function careers(): View
    {
        return $this->render('pages.careers', 'careers');
    }

    public function privacy(): View
    {
        return $this->render('pages.privacy', 'privacy');
    }

    public function terms(): View
    {
        return $this->render('pages.terms', 'terms');
    }

    protected function render(string $view, string $key): View
    {
        return view($view, [
            'pageSeo' => config("page_seo.{$key}", []),
        ]);
    }
}
