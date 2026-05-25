<?php

namespace App\Services;

use App\Models\Product;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GoogleMerchantService
{
    public function feedStats(): array
    {
        $products = Product::with(['category', 'images'])
            ->where('is_active', true)
            ->get();

        $issueCounts = [
            'no_image' => 0,
            'no_description' => 0,
            'no_price' => 0,
            'no_brand' => 0,
            'no_identifier' => 0,
        ];

        $eligible = 0;

        foreach ($products as $product) {
            $issues = $product->googleMerchantIssues();

            if ($issues === []) {
                $eligible++;
            }

            foreach ($issues as $issue) {
                if (isset($issueCounts[$issue])) {
                    $issueCounts[$issue]++;
                }
            }
        }

        return [
            'total_active' => $products->count(),
            'eligible' => $eligible,
            'ineligible' => $products->count() - $eligible,
            'issues' => $issueCounts,
            'feed_url' => route('feeds.google'),
        ];
    }

    /** @return list<array{sku: ?string, name: string, issues: list<string>}> */
    public function ineligibleProducts(int $limit = 100): array
    {
        $results = [];

        Product::with('images')
            ->where('is_active', true)
            ->orderBy('name')
            ->chunk(200, function ($products) use (&$results, $limit) {
                foreach ($products as $product) {
                    $issues = $product->googleMerchantIssues();

                    if ($issues === []) {
                        continue;
                    }

                    $results[] = [
                        'sku' => $product->sku,
                        'name' => $product->name,
                        'issues' => $issues,
                    ];

                    if (count($results) >= $limit) {
                        return false;
                    }
                }
            });

        return $results;
    }

    public function exportIneligibleCsv(): StreamedResponse
    {
        $labels = Product::googleMerchantIssueLabels();

        return response()->streamDownload(function () use ($labels) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['SKU', 'Name', 'Issues']);

            Product::with('images')
                ->where('is_active', true)
                ->orderBy('name')
                ->chunk(200, function ($products) use ($handle, $labels) {
                    foreach ($products as $product) {
                        $issues = $product->googleMerchantIssues();

                        if ($issues === []) {
                            continue;
                        }

                        $issueText = implode('; ', array_map(
                            fn (string $key) => $labels[$key] ?? $key,
                            $issues
                        ));

                        fputcsv($handle, [$product->sku, $product->name, $issueText]);
                    }
                });

            fclose($handle);
        }, 'merchant-ineligible-'.date('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function bulkFillDescriptions(): int
    {
        $updated = 0;

        Product::query()
            ->where('is_active', true)
            ->chunkById(200, function ($products) use (&$updated) {
                foreach ($products as $product) {
                    if (! in_array('no_description', $product->googleMerchantIssues(), true)) {
                        continue;
                    }

                    $product->update([
                        'short_description' => $product->name,
                    ]);
                    $updated++;
                }
            });

        return $updated;
    }

    public function bulkFillSkuFromId(): int
    {
        $updated = 0;

        Product::query()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('sku')->orWhere('sku', '');
            })
            ->chunkById(200, function ($products) use (&$updated) {
                foreach ($products as $product) {
                    if (! in_array('no_identifier', $product->googleMerchantIssues(), true)) {
                        continue;
                    }

                    $product->update([
                        'sku' => 'UF-'.$product->id,
                    ]);
                    $updated++;
                }
            });

        return $updated;
    }

    public function bulkNormalizeGtin(): int
    {
        $updated = 0;

        Product::query()
            ->where('is_active', true)
            ->whereNotNull('barcode')
            ->where('barcode', '!=', '')
            ->chunkById(200, function ($products) use (&$updated) {
                foreach ($products as $product) {
                    $normalized = preg_replace('/\D/', '', (string) $product->barcode);

                    if ($normalized === '' || $normalized === (string) $product->barcode) {
                        continue;
                    }

                    $product->update(['barcode' => $normalized]);
                    $updated++;
                }
            });

        return $updated;
    }

    public function bulkFillBrandFromName(): int
    {
        $updated = 0;

        Product::query()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('brand')->orWhere('brand', '');
            })
            ->chunkById(200, function ($products) use (&$updated) {
                foreach ($products as $product) {
                    if (! in_array('no_brand', $product->googleMerchantIssues(), true)) {
                        continue;
                    }

                    $brand = trim(strtok($product->name, ' '));

                    if ($brand === '') {
                        continue;
                    }

                    $product->update(['brand' => $brand]);
                    $updated++;
                }
            });

        return $updated;
    }
}
