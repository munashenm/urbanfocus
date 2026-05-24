<?php

namespace App\View\Composers;

use App\Models\Category;
use Illuminate\View\View;

class LayoutComposer
{
    public function compose(View $view): void
    {
        $view->with('navCategories', Category::where('is_active', true)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->take(8)
            ->get());
    }
}
