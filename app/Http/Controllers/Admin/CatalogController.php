<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Setting;
use App\Services\CategoryConsolidationService;
use App\Services\CategoryMergeService;
use App\Services\GoogleMerchantService;
use App\Services\ProductCleanupService;
use App\Services\ProductExportService;
use App\Services\ProductImportService;
use App\Services\ProductSeoService;
use App\Services\TargetRangeCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogController extends Controller
{
    public function index(Request $request, GoogleMerchantService $merchant, ProductCleanupService $cleanup, CategoryConsolidationService $consolidation): View
    {
        try {
            $this->forgetStaleRouteCache();

            $apiKey = Setting::get('api_key') ?: config('app.api_key');
            $feedStats = $this->emptyFeedStats();
            $nonItPreview = [
                'terms_loaded' => 0,
                'it_heads_loaded' => 0,
                'total_products' => (int) Product::query()->count(),
                'excluded_categories' => [],
                'products_to_delete' => 0,
                'categories_to_delete' => 0,
                'sample_products' => [],
            ];
            $categoryConsolidationPreview = [
                'products_to_move' => 0,
                'empty_categories' => 0,
                'sample_moves' => [],
            ];
            $ineligibleSample = [];
            $importPricing = [
                'markup_percent' => (float) config('pricing.markup_percent', 15),
                'round_to' => (int) config('pricing.round_to', 50),
                'round_mode' => (string) config('pricing.round_mode', 'up'),
                'low_cost_threshold' => (float) config('pricing.low_cost_threshold', 20),
                'example' => ['cost' => 100, 'retail' => 150],
                'low_cost_example' => ['cost' => 4, 'retail' => 5.6],
            ];

            try {
                $feedStats['total_active'] = (int) Product::query()->where('is_active', true)->count();
            } catch (\Throwable $e) {
                report($e);
            }

            try {
                $importPricing = app(ProductImportService::class)->pricingPolicy();
            } catch (\Throwable $e) {
                report($e);
            }

            if ($request->boolean('stats')) {
                @set_time_limit(120);

                try {
                    $feedStats = $merchant->feedStats();
                } catch (\Throwable $e) {
                    report($e);
                }

                try {
                    $nonItPreview = $cleanup->previewNonItCleanup();
                } catch (\Throwable $e) {
                    report($e);
                }

                try {
                    $categoryConsolidationPreview = $consolidation->preview();
                } catch (\Throwable $e) {
                    report($e);
                }

                try {
                    $ineligibleSample = $merchant->ineligibleProducts(10);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            $merchantIssueLabels = Product::googleMerchantIssueLabels();
            $targetRangeCount = $this->targetRangeCount();

            $feeds = [
                ['name' => 'Google Merchant Center', 'url' => url('/feeds/google.xml'), 'format' => 'XML'],
                ['name' => 'Bob Shop product feed (XML)', 'url' => url('/feeds/bobshop.xml'), 'format' => 'XML'],
                ['name' => 'Bob Shop BulkLoad CSV', 'url' => url('/feeds/bobshop.csv'), 'format' => 'CSV'],
                ['name' => 'PriceCheck comparison CSV', 'url' => url('/feeds/pricecheck.csv'), 'format' => 'CSV'],
                ['name' => 'PriceCheck product feed (XML)', 'url' => url('/feeds/pricecheck.xml'), 'format' => 'XML'],
                ['name' => 'XML Sitemap', 'url' => url('/sitemap.xml'), 'format' => 'XML'],
            ];

            $apiEndpoints = [
                ['method' => 'GET', 'path' => '/api/products', 'description' => 'List products (paginated)'],
                ['method' => 'GET', 'path' => '/api/products/{slug|sku|id}', 'description' => 'Single product'],
            ];

            return view('admin.catalog.index', compact('apiKey', 'feeds', 'apiEndpoints', 'feedStats', 'nonItPreview', 'categoryConsolidationPreview', 'merchantIssueLabels', 'ineligibleSample', 'importPricing', 'targetRangeCount'));
        } catch (\Throwable $e) {
            report($e);

            $count = 0;
            try {
                $count = $this->targetRangeCount();
            } catch (\Throwable) {
            }

            return view('admin.catalog.simple', [
                'error' => $e->getMessage(),
                'targetRangeCount' => $count,
            ]);
        }
    }

    public function import(Request $request, ProductImportService $importService): RedirectResponse
    {
        $request->validate([
            'csv_file' => 'required|file|max:20480',
        ]);

        $extension = strtolower($request->file('csv_file')->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'txt', ''], true)) {
            return back()->with('error', 'Please upload a .csv file.');
        }

        try {
            $result = $importService->import($request->file('csv_file'));
        } catch (\Throwable $e) {
            return back()->with('error', 'Import failed: '.$e->getMessage());
        }

        $message = $this->formatImportMessage($result);

        if (! empty($result['errors'])) {
            return back()->with('warning', $message);
        }

        return back()->with('success', $message);
    }

    public function importPreview(Request $request, ProductImportService $importService): RedirectResponse
    {
        $request->validate([
            'csv_file' => 'required|file|max:20480',
        ]);

        $extension = strtolower($request->file('csv_file')->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'txt', ''], true)) {
            return back()->with('error', 'Please upload a .csv file.');
        }

        try {
            $preview = $importService->preview($request->file('csv_file'));
        } catch (\Throwable $e) {
            return back()->with('error', 'Preview failed: '.$e->getMessage());
        }

        return back()->with('import_preview', $preview);
    }

    /** @param array<string, mixed> $result */
    protected function formatImportMessage(array $result): string
    {
        $message = "Imported {$result['imported']} new, updated {$result['updated']}.";

        if ($result['skipped'] > 0) {
            $message .= " Skipped {$result['skipped']} empty rows.";
        }

        if (($result['skippedNoImage'] ?? 0) > 0) {
            $message .= " Skipped {$result['skippedNoImage']} without images.";
        }

        if (($result['skippedNoPrice'] ?? 0) > 0) {
            $message .= " Skipped {$result['skippedNoPrice']} without cost/price.";
        }

        if (($result['skippedImageFailed'] ?? 0) > 0) {
            $message .= " Skipped {$result['skippedImageFailed']} with failed image downloads.";
        }

        if (($result['skippedNonIt'] ?? 0) > 0) {
            $message .= " Skipped {$result['skippedNonIt']} non-IT rows.";
        }

        if (! empty($result['errors'])) {
            return $message.' Errors: '.implode(' | ', array_slice($result['errors'], 0, 8));
        }

        return $message;
    }

    public function clearProducts(Request $request, ProductCleanupService $cleanup): RedirectResponse
    {
        $request->validate([
            'confirm_phrase' => 'required|in:DELETE ALL PRODUCTS',
        ]);

        $result = $cleanup->deleteAll();
        \Illuminate\Support\Facades\Cache::forget('sitemap.xml');

        return back()->with('success', "Deleted {$result['deleted']} products. You can now import a fresh CSV.");
    }

    public function removeNonIt(ProductCleanupService $cleanup): RedirectResponse
    {
        try {
            @set_time_limit(0);

            $result = $cleanup->removeNonItProducts();

            $message = "Removed {$result['products_deleted']} non-IT product(s) and {$result['categories_deleted']} non-IT categor(ies). {$result['images_removed']} image(s) deleted.";

            if (! empty($result['errors'])) {
                return back()->with('warning', $message.' Some items failed: '.implode(' | ', array_slice($result['errors'], 0, 5)));
            }

            return back()->with('success', $message);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Cleanup failed: '.$e->getMessage());
        }
    }

    public function export(ProductExportService $exportService): StreamedResponse
    {
        return $exportService->exportUrbanFocus();
    }

    public function exportWooCommerce(ProductExportService $exportService): StreamedResponse
    {
        return $exportService->exportWooCommerce();
    }

    public function regenerateApiKey(): RedirectResponse
    {
        $key = 'uf_'.Str::random(40);
        Setting::set('api_key', $key, 'api');

        return back()->with('success', 'API key regenerated. Update any integrations using the new key.');
    }

    public function exportIneligible(GoogleMerchantService $merchant): StreamedResponse
    {
        return $merchant->exportIneligibleCsv();
    }

    public function bulkFixMerchant(Request $request, GoogleMerchantService $merchant): RedirectResponse
    {
        $request->validate([
            'action' => 'required|in:fill_descriptions,fill_sku,normalize_gtin,fill_brand',
        ]);

        $count = match ($request->action) {
            'fill_descriptions' => $merchant->bulkFillDescriptions(),
            'fill_sku' => $merchant->bulkFillSkuFromId(),
            'normalize_gtin' => $merchant->bulkNormalizeGtin(),
            'fill_brand' => $merchant->bulkFillBrandFromName(),
        };

        $label = match ($request->action) {
            'fill_descriptions' => 'descriptions',
            'fill_sku' => 'SKUs',
            'normalize_gtin' => 'GTIN/barcode values',
            'fill_brand' => 'brands',
        };

        if ($count === 0) {
            return back()->with('warning', "No products needed {$label} updates.");
        }

        return back()->with('success', "Updated {$count} product {$label} for Google Merchant eligibility.");
    }

    public function consolidateCategories(CategoryConsolidationService $consolidation): RedirectResponse
    {
        $result = $consolidation->consolidate();

        return back()->with(
            'success',
            "Moved {$result['moved']} products into the canonical category tree and deactivated {$result['deactivated']} empty import categories."
        );
    }

    public function optimizeSeo(ProductSeoService $seo): RedirectResponse
    {
        try {
            @set_time_limit(0);

            $categoryStats = $seo->assignProductCategories();
            $stats = $seo->optimizeCatalog();

            return back()->with(
                'success',
                "Catalog updated: {$categoryStats['categorized']} products assigned to categories; {$stats['meta_updated']} meta fields updated; {$stats['images_updated']} image alt tags updated ({$stats['processed']} products processed)."
            );
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'SEO optimization failed: '.$e->getMessage());
        }
    }

    public function assignCategories(ProductSeoService $seo): RedirectResponse
    {
        try {
            @set_time_limit(0);

            $stats = $seo->assignProductCategories();

            return back()->with(
                'success',
                "Assigned {$stats['categorized']} products to categories ({$stats['processed']} processed, {$stats['skipped']} unchanged)."
            );
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Category assignment failed: '.$e->getMessage());
        }
    }

    public function syncTargetRangePreview(TargetRangeCatalogService $catalog): RedirectResponse
    {
        try {
            @set_time_limit(120);
            $result = $catalog->sync(dryRun: true);

            return back()->with('target_range_preview', $result);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Target-range preview failed: '.$e->getMessage());
        }
    }

    public function syncTargetRange(TargetRangeCatalogService $catalog): RedirectResponse
    {
        try {
            @set_time_limit(0);

            $result = $catalog->sync();
            $message = "Created {$result['created']} target-range product(s). Already on the store: {$result['skipped']}. Photos attached: {$result['imaged']}. Errors: {$result['errors']}.";

            if ($result['errors'] > 0) {
                return back()->with('warning', $message);
            }

            return back()->with('success', $message);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Target-range sync failed: '.$e->getMessage());
        }
    }

    public function mergeCategories(CategoryMergeService $merge): RedirectResponse
    {
        try {
            @set_time_limit(0);

            $result = $merge->merge(backup: true);

            return back()->with(
                'success',
                "Category merge complete: {$result['reorganize']['moved']} products remapped, {$result['assign']['categorized']} heuristically assigned, {$result['reorganize']['redirects']} redirects created, {$result['reorganize']['deactivated']} legacy categories deactivated, {$result['legacy_products_remaining']} products still on legacy categories."
            );
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Category merge failed: '.$e->getMessage());
        }
    }

    protected function targetRangeCount(): int
    {
        $path = (string) (config('catalog.target_range_path') ?: database_path('data/target-range-products.json'));
        if (! is_readable($path)) {
            return 0;
        }

        try {
            $decoded = json_decode((string) file_get_contents($path), true);

            return is_array($decoded) ? count($decoded) : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return array{total_active: int, eligible: int, ineligible: int, issues: array<string, int>, feed_url: string} */
    protected function emptyFeedStats(): array
    {
        return [
            'total_active' => 0,
            'eligible' => 0,
            'ineligible' => 0,
            'issues' => [
                'no_image' => 0,
                'no_description' => 0,
                'no_price' => 0,
                'no_brand' => 0,
                'no_identifier' => 0,
            ],
            'feed_url' => Route::has('feeds.google') ? route('feeds.google') : url('/feeds/google.xml'),
        ];
    }

    protected function forgetStaleRouteCache(): void
    {
        if (Route::has('admin.catalog.sync-target-range')) {
            return;
        }

        foreach (glob(base_path('bootstrap/cache/routes*.php')) ?: [] as $file) {
            @unlink($file);
        }

        foreach (glob(storage_path('framework/views/*.php')) ?: [] as $file) {
            if (basename($file) !== '.gitignore') {
                @unlink($file);
            }
        }
    }
}
