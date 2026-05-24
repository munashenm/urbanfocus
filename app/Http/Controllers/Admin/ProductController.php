<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(protected ImageService $images) {}

    public function index(Request $request): View
    {
        $query = Product::with('category')->latest();

        if ($search = $request->get('q')) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%");
        }

        $products = $query->paginate(20);

        return view('admin.products.index', compact('products'));
    }

    public function create(): View
    {
        $categories = Category::orderBy('name')->get();

        return view('admin.products.form', ['product' => new Product, 'categories' => $categories]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateProduct($request);
        $validated = $this->normalizeProductFields($validated);
        $validated['slug'] = $this->uniqueSlug($validated['slug'] ?? null, $validated['name']);

        try {
            $product = Product::create($validated);
            $this->handleImages($request, $product);
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'Could not save product. '.$e->getMessage());
        }

        return redirect()->route('admin.products.index')->with('success', 'Product created.');
    }

    public function edit(Product $product): View
    {
        $product->load('images');
        $categories = Category::orderBy('name')->get();

        return view('admin.products.form', compact('product', 'categories'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $this->validateProduct($request, $product->id);
        $validated = $this->normalizeProductFields($validated);
        $validated['slug'] = $this->uniqueSlug($validated['slug'] ?? null, $validated['name'], $product->id);

        try {
            $product->update($validated);
            $this->handleImages($request, $product);
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'Could not save product. '.$e->getMessage());
        }

        return redirect()->route('admin.products.index')->with('success', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        foreach ($product->images as $image) {
            $this->images->delete($image->path);
        }

        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'Product deleted.');
    }

    protected function validateProduct(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'sku' => 'nullable|string|max:100|unique:products,sku,'.$id,
            'model_number' => 'nullable|string|max:100',
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:products,slug,'.$id,
            'short_description' => 'nullable|string',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'manage_stock' => 'boolean',
            'in_stock' => 'boolean',
            'brand' => 'nullable|string|max:100',
            'barcode' => 'nullable|string|max:100',
            'google_product_category' => 'nullable|string|max:255',
            'weight' => 'nullable|numeric|min:0',
            'dimensions' => 'nullable|string|max:100',
            'warranty_months' => 'nullable|integer|min:0|max:120',
            'delivery_days' => 'nullable|integer|min:1|max:60',
            'specifications' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'meta_keywords' => 'nullable|string|max:255',
            'is_featured' => 'boolean',
            'is_deal' => 'boolean',
            'deal_label' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'images.*' => 'nullable|image|max:5120',
        ]);
    }

    protected function normalizeProductFields(array $data): array
    {
        if (isset($data['specifications']) && is_string($data['specifications'])) {
            $raw = trim($data['specifications']);
            if ($raw === '') {
                $data['specifications'] = null;
            } elseif ($decoded = json_decode($raw, true)) {
                $data['specifications'] = $decoded;
            } else {
                $specs = [];
                foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
                    if (str_contains($line, ':')) {
                        [$key, $value] = array_map('trim', explode(':', $line, 2));
                        if ($key !== '') {
                            $specs[$key] = $value;
                        }
                    }
                }
                $data['specifications'] = $specs ?: null;
            }
        }

        return $data;
    }

    protected function uniqueSlug(?string $slug, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug ?: $name) ?: 'product';
        $candidate = $base;
        $suffix = 1;

        while (Product::where('slug', $candidate)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $candidate = $base.'-'.$suffix++;
        }

        return $candidate;
    }

    protected function handleImages(Request $request, Product $product): void
    {
        if (! $request->hasFile('images')) {
            return;
        }

        $sortOrder = $product->images()->max('sort_order') ?? 0;

        foreach ($request->file('images') as $index => $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $path = $this->images->storeProductImage($file, $product->id);
            ProductImage::create([
                'product_id' => $product->id,
                'path' => $path,
                'alt_text' => $product->name,
                'sort_order' => ++$sortOrder,
                'is_primary' => $product->images()->count() === 0 && $index === 0,
            ]);
        }
    }
}
