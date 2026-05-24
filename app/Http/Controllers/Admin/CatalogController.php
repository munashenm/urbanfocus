<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\GoogleMerchantService;
use App\Services\ProductExportService;
use App\Services\ProductImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogController extends Controller
{
    public function index(GoogleMerchantService $merchant): View
    {
        $apiKey = Setting::get('api_key') ?: config('app.api_key');
        $feedStats = $merchant->feedStats();

        $feeds = [
            ['name' => 'Google Merchant Center', 'url' => route('feeds.google'), 'format' => 'XML'],
            ['name' => 'PriceCheck / Bob Shop', 'url' => route('feeds.pricecheck'), 'format' => 'CSV'],
            ['name' => 'XML Sitemap', 'url' => route('sitemap'), 'format' => 'XML'],
        ];

        $apiEndpoints = [
            ['method' => 'GET', 'path' => '/api/products', 'description' => 'List products (paginated)'],
            ['method' => 'GET', 'path' => '/api/products/{slug|sku|id}', 'description' => 'Single product'],
        ];

        return view('admin.catalog.index', compact('apiKey', 'feeds', 'apiEndpoints', 'feedStats'));
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

        $message = "Imported {$result['imported']} new, updated {$result['updated']}.";

        if ($result['skipped'] > 0) {
            $message .= " Skipped {$result['skipped']} empty rows.";
        }

        if (! empty($result['errors'])) {
            return back()->with('warning', $message.' Errors: '.implode(' | ', array_slice($result['errors'], 0, 8)));
        }

        return back()->with('success', $message);
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
}
