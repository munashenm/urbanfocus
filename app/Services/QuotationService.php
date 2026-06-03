<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Quote;
use Illuminate\Support\Collection;

class QuotationService
{
    public function nextNumber(): string
    {
        $prefix = config('quotation.number_prefix', 'UF-Q');
        $year = now()->format('Y');
        $pattern = $prefix.'-'.$year.'-%';

        $last = Quotation::where('quotation_number', 'like', $pattern)
            ->orderByDesc('id')
            ->value('quotation_number');

        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return sprintf('%s-%s-%05d', $prefix, $year, $seq);
    }

    public function defaultValidUntil(): \Carbon\Carbon
    {
        return now()->addDays((int) config('quotation.default_validity_days', 14))->startOfDay();
    }

    public function defaultTerms(): string
    {
        return (string) config('quotation.default_terms', '');
    }

    /**
     * @param  array<int, array{product_id?:int|null,description:string,sku?:string|null,quantity:int|float,unit_price:float|int|string}>  $lines
     */
    public function syncItems(Quotation $quotation, array $lines): void
    {
        $quotation->items()->delete();

        foreach (array_values($lines) as $index => $line) {
            $qty = max(1, (int) ($line['quantity'] ?? 1));
            $unit = round((float) ($line['unit_price'] ?? 0), 2);
            $lineTotal = round($qty * $unit, 2);

            $quotation->items()->create([
                'product_id' => $line['product_id'] ?? null,
                'description' => $line['description'],
                'sku' => $line['sku'] ?? null,
                'quantity' => $qty,
                'unit_price' => $unit,
                'line_total' => $lineTotal,
                'sort_order' => $index,
            ]);
        }

        $quotation->load('items');
        $this->recalculateTotals($quotation);
    }

    public function recalculateTotals(Quotation $quotation): void
    {
        $quotation->loadMissing('items');

        $linesTotal = round((float) $quotation->items->sum('line_total'), 2);
        $discount = round((float) ($quotation->discount_amount ?? 0), 2);
        $taxableInc = max(0, $linesTotal - $discount);

        $vatRate = (float) config('app.vat_rate', 15);

        if (config('app.prices_include_vat', true)) {
            $taxAmount = round($taxableInc * ($vatRate / (100 + $vatRate)), 2);
            $subtotalEx = round($taxableInc - $taxAmount, 2);
            $total = $taxableInc;
        } else {
            $subtotalEx = $taxableInc;
            $taxAmount = round($subtotalEx * ($vatRate / 100), 2);
            $total = round($subtotalEx + $taxAmount, 2);
        }

        $quotation->update([
            'subtotal' => $subtotalEx,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ]);
    }

    public function documentData(Quotation $quotation): array
    {
        $quotation->loadMissing('items', 'creator');

        return [
            'quotation' => $quotation,
            'title' => 'Quotation',
            'vatRate' => (float) config('app.vat_rate', 15),
            'pricesIncludeVat' => (bool) config('app.prices_include_vat', true),
            'seller' => [
                'name' => config('app.name', 'Urban Focus'),
                'email' => config('business.email'),
                'phone' => config('business.phone'),
                'vat_number' => config('business.vat_number'),
                'company_reg' => config('business.company_reg'),
                'address' => config('business.address'),
                'website' => config('business.website'),
            ],
        ];
    }

    public function prefillFromEnquiry(Quote $quote): array
    {
        $data = [
            'customer_name' => $quote->name,
            'customer_company' => $quote->company,
            'customer_email' => $quote->email,
            'customer_phone' => $quote->phone,
            'internal_notes' => trim(($quote->admin_notes ?? '')."\n\nFrom enquiry #".$quote->id.' ('.$quote->typeLabel().')'),
            'source_quote_id' => $quote->id,
            'items' => [],
        ];

        if ($quote->product) {
            $product = $quote->product;
            $data['items'][] = $this->lineFromProduct($product, 1);
        }

        return $data;
    }

    public function lineFromProduct(Product $product, int $quantity = 1): array
    {
        return [
            'product_id' => $product->id,
            'description' => $product->name,
            'sku' => $product->sku,
            'quantity' => $quantity,
            'unit_price' => $product->effective_price,
        ];
    }

    /**
     * @return Collection<int, array{id:int,name:string,sku:?string,price:float}>
     */
    public function searchProducts(string $query, int $limit = 15): Collection
    {
        $query = trim($query);
        if ($query === '') {
            return collect();
        }

        return Product::query()
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', '%'.$query.'%')
                    ->orWhere('sku', 'like', '%'.$query.'%');
            })
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'sku', 'price', 'sale_price'])
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'price' => $p->effective_price,
            ]);
    }
}
