<?php

namespace App\Services;

use App\Mail\BackInStockNotification;
use App\Models\Product;
use App\Models\StockAlert;
use Illuminate\Support\Facades\Mail;

class StockAlertService
{
    public function subscribe(Product $product, string $email, ?string $name = null): StockAlert
    {
        return StockAlert::firstOrCreate(
            ['product_id' => $product->id, 'email' => $email],
            ['name' => $name]
        );
    }

    public function notifyWaitlist(Product $product): int
    {
        $alerts = StockAlert::where('product_id', $product->id)
            ->whereNull('notified_at')
            ->get();

        $sent = 0;
        foreach ($alerts as $alert) {
            try {
                Mail::to($alert->email)->send(new BackInStockNotification($product, $alert));
                $alert->update(['notified_at' => now()]);
                $sent++;
            } catch (\Throwable) {
                // continue with other recipients
            }
        }

        return $sent;
    }
}
