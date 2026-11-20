<?php

use App\Models\Product;
use App\Services\BlackFridaySpecialService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $result = app(BlackFridaySpecialService::class)->apply();
        Log::info('Black Friday specials applied', $result);
    }

    public function down(): void
    {
        $specials = app(BlackFridaySpecialService::class);
        $ends = $specials->endsAt()->toDateTimeString();

        foreach ($specials->offers() as $offer) {
            $sale = number_format((float) $offer['sale_price'], 2, '.', '');
            foreach ($offer['skus'] ?? [] as $sku) {
                Product::query()
                    ->whereRaw('UPPER(sku) = ?', [strtoupper((string) $sku)])
                    ->where('sale_price', $sale)
                    ->update([
                        'sale_price' => null,
                        'sale_starts_at' => null,
                        'sale_ends_at' => null,
                        'promo_label' => null,
                    ]);
            }
        }

        Log::info('Black Friday specials rolled back', ['ends_at' => $ends]);
    }
};
