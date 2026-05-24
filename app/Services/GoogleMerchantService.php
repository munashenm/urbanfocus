<?php

namespace App\Services;

use App\Models\Product;

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
}
