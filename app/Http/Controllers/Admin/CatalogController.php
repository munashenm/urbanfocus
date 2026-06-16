<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Setting;
use App\Services\CategoryConsolidationService;
use App\Services\GoogleMerchantService;
use App\Services\ProductCleanupService;
use App\Services\ProductExportService;
use App\Services\ProductImportService;
use App\Services\ProductSeoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogController extends Controller
{
    public function index(GoogleMerchantService $merchant, ProductCleanupService $cleanup, CategoryConsolidationService $consolidation): View
    {
        $apiKey = Setting::get('api_key') ?: config('app.api_key');
        $feedStats = $merchant->feedStats();
        $nonItPreview = $cleanup->previewNonItCleanup();
        $categoryConsolidationPreview = $consolidation->preview();
        $merchantIssueLabels = Product::googleMerchantIssueLabels();
        $ineligibleSample = $merchant->ineligibleProducts(10);
        $importPricing = app(ProductImportService::class)->pricingPolicy();

        $feeds = [
            ['name' => 'Google Merchant Center', 'url' => route('feeds.google'), 'format' => 'XML'],
            ['name' => 'Bob Shop product feed (XML)', 'url' => route('feeds.bobshop'), 'format' => 'XML'],
            ['name' => 'Bob Shop BulkLoad CSV', 'url' => route('feeds.bobshop.csv'), 'format' => 'CSV'],
            ['name' => 'PriceCheck comparison CSV', 'url' => route('feeds.pricecheck'), 'format' => 'CSV'],
            ['name' => 'PriceCheck product feed (XML)', 'url' => route('feeds.pricecheck.xml'), 'format' => 'XML'],
            ['name' => 'XML Sitemap', 'url' => route('sitemap'), 'format' => 'XML'],
        ];

        $apiEndpoints = [
            ['method' => 'GET', 'path' => '/api/products', 'description' => 'List products (paginated)'],
            ['method' => 'GET', 'path' => '/api/products/{slug|sku|id}', 'description' => 'Single product'],
        ];

        return view('admin.catalog.index', compact('apiKey', 'feeds', 'apiEndpoints', 'feedStats', 'nonItPreview', 'categoryConsolidationPreview', 'merchantIssueLabels', 'ineligibleSample', 'importPricing'));
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
}
