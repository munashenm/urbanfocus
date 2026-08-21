<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CompareService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CompareController extends Controller
{
    public function __construct(protected CompareService $compare) {}

    public function index(): View
    {
        $products = $this->compare->products();

        return view('compare.index', [
            'products' => $products,
            'specRows' => $this->compare->specRows(),
            'remaining' => $this->compare->remaining(),
        ]);
    }

    public function add(Product $product): RedirectResponse
    {
        abort_unless($product->is_active, 404);

        if (! $this->compare->add($product->id)) {
            return back()->with('warning', 'You can compare up to '.CompareService::MAX_ITEMS.' products at a time.');
        }

        return back()->with('success', $product->name.' added to compare.');
    }

    public function remove(Product $product): RedirectResponse
    {
        $this->compare->remove($product->id);

        return back()->with('success', 'Item removed from compare.');
    }

    public function toggle(Product $product): RedirectResponse
    {
        abort_unless($product->is_active, 404);

        if ($this->compare->has($product->id)) {
            $this->compare->remove($product->id);

            return back()->with('success', $product->name.' removed from compare.');
        }

        if (! $this->compare->add($product->id)) {
            return back()->with('warning', 'You can compare up to '.CompareService::MAX_ITEMS.' products at a time.');
        }

        return back()->with('success', $product->name.' added to compare.');
    }

    public function clear(): RedirectResponse
    {
        $this->compare->clear();

        return redirect()->route('compare.index')->with('success', 'Comparison cleared.');
    }
}
