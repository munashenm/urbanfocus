<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\LogsAdminActivity;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    use LogsAdminActivity;

    public function __construct(protected ImageService $images) {}

    public function index(Request $request): View
    {
        $query = Product::with(['category', 'images'])->latest();

        if ($request->get('status') === 'archived') {
            $query->onlyTrashed();
        } else {
            $query->whereNull('products.deleted_at');

            if ($status = $request->get('status')) {
                $query->publicationStatus($status);
            }
        }

        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        if ($categoryId = $request->get('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($brand = $request->get('brand')) {
            $query->where('brand', $brand);
        }

        if ($issue = $request->get('merchant_issue')) {
            $query->where('is_active', true)->merchantIssue($issue);
        }

        $products = $query->paginate(20)->withQueryString();
        $merchantIssueLabels = Product::googleMerchantIssueLabels();
        $categories = Category::orderBy('name')->get(['id', 'name']);
        $brands = Brand::where('is_active', true)->orderBy('name')->pluck('name');

        return view('admin.products.index', compact('products', 'merchantIssueLabels', 'categories', 'brands'));
    }

    public function create(): View
    {
        return view('admin.products.form', $this->formData(new Product([
            'manage_stock' => true,
            'in_stock' => true,
            'stock_quantity' => 0,
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateProduct($request);
        $validated['category_id'] = $this->resolveCategoryIdFromRequest($request);
        $validated = $this->normalizeProductFields($validated);
        $publicationStatus = $validated['publication_status'] ?? 'draft';
        unset($validated['publication_status']);
        $validated['slug'] = $this->uniqueSlug($validated['slug'] ?? null, $validated['name']);

        try {
            $product = Product::create($validated);
            $product->applyPublicationStatus($publicationStatus);
            $this->handleImages($request, $product);
            $this->audit('products.create', $product);
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'Could not save product. '.$e->getMessage());
        }

        return redirect()->route('admin.products.edit', $product)->with('success', 'Product created.');
    }

    public function edit(Product $product): View
    {
        $product->load('images');

        return view('admin.products.form', $this->formData($product));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $this->validateProduct($request, $product->id);
        $validated['category_id'] = $this->resolveCategoryIdFromRequest($request);
        $validated = $this->normalizeProductFields($validated);
        $publicationStatus = $validated['publication_status'] ?? $product->publicationStatus();
        unset($validated['publication_status']);
        $validated['slug'] = $this->uniqueSlug($validated['slug'] ?? null, $validated['name'], $product->id);

        try {
            $product->update($validated);
            $product->applyPublicationStatus($publicationStatus);
            $this->handleImages($request, $product);
            $this->audit('products.update', $product);
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'Could not save product. '.$e->getMessage());
        }

        return redirect()->route('admin.products.edit', $product)->with('success', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->deleteProduct($product);
        $this->audit('products.delete', $product);

        return redirect()->route('admin.products.index')->with('success', 'Product deleted.');
    }

    public function duplicate(Product $product): RedirectResponse
    {
        $product->load('images');

        $copy = $product->replicate(['views', 'woocommerce_id', 'deleted_at']);
        $copy->name = $product->name.' (Copy)';
        $copy->sku = $product->sku
            ? Str::limit($product->sku, 90, '').'-'.Str::upper(Str::random(4))
            : null;
        $copy->slug = $this->uniqueSlug(null, $copy->name);
        $copy->is_active = false;
        $copy->save();

        foreach ($product->images as $image) {
            $newPath = $this->copyImagePath($image->path, $copy->id);
            if ($newPath) {
                ProductImage::create([
                    'product_id' => $copy->id,
                    'path' => $newPath,
                    'alt_text' => $image->alt_text ?: $copy->name,
                    'sort_order' => $image->sort_order,
                    'is_primary' => $image->is_primary,
                ]);
            }
        }

        $this->audit('products.duplicate', $copy, ['source_id' => $product->id]);

        return redirect()->route('admin.products.edit', $copy)->with('success', 'Product duplicated as a draft.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:products,id',
        ]);

        $deleted = 0;

        Product::withTrashed()
            ->whereIn('id', $validated['ids'])
            ->with('images')
            ->each(function (Product $product) use (&$deleted) {
                $this->deleteProduct($product);
                $deleted++;
            });

        $this->audit('products.bulk_delete', null, ['count' => $deleted]);

        return redirect()
            ->route('admin.products.index', $request->only(['q', 'merchant_issue', 'status', 'category_id', 'brand', 'page']))
            ->with('success', "{$deleted} product(s) deleted.");
    }

    public function bulkUpdate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:products,id',
            'action' => 'required|in:publish,draft,archive,delete',
        ]);

        $products = Product::withTrashed()->whereIn('id', $validated['ids'])->get();
        $count = 0;

        foreach ($products as $product) {
            match ($validated['action']) {
                'publish' => $product->applyPublicationStatus('published'),
                'draft' => $product->applyPublicationStatus('draft'),
                'archive' => $product->applyPublicationStatus('archived'),
                'delete' => $this->deleteProduct($product),
            };
            $count++;
        }

        $this->audit('products.bulk_update', null, ['action' => $validated['action'], 'count' => $count]);

        return back()->with('success', "{$count} product(s) updated.");
    }

    public function destroyImage(Product $product, ProductImage $image): RedirectResponse
    {
        abort_unless($image->product_id === $product->id, 404);

        $this->images->delete($image->path);
        $image->delete();

        if (! $product->images()->where('is_primary', true)->exists()) {
            $product->images()->orderBy('sort_order')->first()?->update(['is_primary' => true]);
        }

        return back()->with('success', 'Image removed.');
    }

    public function setPrimaryImage(Product $product, ProductImage $image): RedirectResponse
    {
        abort_unless($image->product_id === $product->id, 404);

        $product->images()->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);

        return back()->with('success', 'Primary image updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        $filename = 'products-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($request) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'SKU', 'Name', 'Brand', 'Category', 'Regular Price', 'Sale Price',
                'Stock Qty', 'In Stock', 'Status', 'Featured', 'Deal', 'Google Category',
            ]);

            $query = Product::with('category')->latest();
            if ($request->get('status') === 'archived') {
                $query->onlyTrashed();
            } elseif ($status = $request->get('status')) {
                $query->publicationStatus($status);
            }

            $query->chunk(200, function ($products) use ($handle) {
                foreach ($products as $product) {
                    fputcsv($handle, [
                        $product->sku,
                        $product->name,
                        $product->brand,
                        $product->category?->name,
                        $product->price,
                        $product->sale_price,
                        $product->stock_quantity,
                        $product->in_stock ? 'yes' : 'no',
                        $product->publicationStatus(),
                        $product->is_featured ? 'yes' : 'no',
                        $product->is_deal ? 'yes' : 'no',
                        $product->google_product_category,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    protected function formData(Product $product): array
    {
        $product->loadMissing('category.parent');

        $parentCategories = Category::topLevel()->active()->orderBy('sort_order')->get();
        $childrenByParent = Category::query()
            ->whereNotNull('parent_id')
            ->active()
            ->orderBy('sort_order')
            ->get()
            ->groupBy('parent_id');

        $selectedParentId = old('parent_category_id');
        $selectedChildId = old('category_id', $product->category_id);

        if ($selectedParentId === null && $product->category) {
            if ($product->category->parent_id) {
                $selectedParentId = $product->category->parent_id;
            } else {
                $selectedParentId = $product->category_id;
                $selectedChildId = null;
            }
        }

        return [
            'product' => $product,
            'parentCategories' => $parentCategories,
            'childrenByParent' => $childrenByParent,
            'selectedParentId' => $selectedParentId,
            'selectedChildId' => $selectedChildId,
            'brands' => Brand::where('is_active', true)->orderBy('name')->pluck('name'),
            'publicationStatuses' => Product::publicationStatuses(),
            'pricesIncludeVat' => (bool) config('app.prices_include_vat', true),
            'vatRate' => (float) config('app.vat_rate', 15),
        ];
    }

    protected function deleteProduct(Product $product): void
    {
        foreach ($product->images as $image) {
            $this->images->delete($image->path);
        }

        $product->delete();
    }

    protected function validateProduct(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'parent_category_id' => 'nullable|exists:categories,id',
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
            'publication_status' => 'required|in:draft,published,archived',
            'images.*' => 'nullable|image|max:5120',
            'image_urls' => 'nullable|string',
            'remove_image_ids' => 'nullable|array',
            'remove_image_ids.*' => 'integer|exists:product_images,id',
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

        unset($data['image_urls'], $data['remove_image_ids'], $data['parent_category_id']);

        return $data;
    }

    protected function resolveCategoryIdFromRequest(Request $request): ?int
    {
        if ($request->filled('category_id')) {
            return (int) $request->input('category_id');
        }

        if ($request->filled('parent_category_id')) {
            return (int) $request->input('parent_category_id');
        }

        return null;
    }

    protected function uniqueSlug(?string $slug, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug ?: $name) ?: 'product';
        $candidate = $base;
        $suffix = 1;

        while (Product::withTrashed()->where('slug', $candidate)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $candidate = $base.'-'.$suffix++;
        }

        return $candidate;
    }

    protected function handleImages(Request $request, Product $product): void
    {
        if ($request->filled('remove_image_ids')) {
            ProductImage::query()
                ->where('product_id', $product->id)
                ->whereIn('id', $request->input('remove_image_ids', []))
                ->each(function (ProductImage $image) {
                    $this->images->delete($image->path);
                    $image->delete();
                });
        }

        $sortOrder = (int) ($product->images()->max('sort_order') ?? 0);

        if ($request->hasFile('images')) {
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

        if ($request->filled('image_urls')) {
            foreach (preg_split('/\r\n|\r|\n/', $request->input('image_urls')) as $url) {
                $url = trim($url);
                if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
                    continue;
                }

                $path = $this->images->storeProductImageFromUrl($url, $product->id);
                if ($path) {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'path' => $path,
                        'alt_text' => $product->name,
                        'sort_order' => ++$sortOrder,
                        'is_primary' => $product->images()->count() === 0,
                    ]);
                }
            }
        }
    }

    protected function copyImagePath(string $path, int $productId): ?string
    {
        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
        $newPath = 'products/'.$productId.'/'.Str::uuid().'.'.$extension;

        Storage::disk('public')->copy($path, $newPath);
        $contents = Storage::disk('public')->get($newPath);
        $target = public_path('storage/'.$newPath);
        $directory = dirname($target);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        file_put_contents($target, $contents);

        return $newPath;
    }
}
