<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Str;

/**
 * Shared out-of-stock listing rules for every supplier (Syntech, Esquire,
 * Pinnacle, Astrum, DCC, and any later source). Customer-facing copy must
 * never name a supplier.
 */
class CatalogOosPolicy
{
    public const DESCRIPTION_MIN = 200;

    public const WORDING = [
        1 => [
            'key' => 1,
            'label' => 'Currently out of stock',
            'message' => 'This item is currently out of stock. Request a quote or ask for an ETA and our sales team will check when it can be available.',
            'button' => 'Request a quote / Ask for ETA',
        ],
        2 => [
            'key' => 2,
            'label' => 'Temporarily unavailable',
            'message' => 'Temporarily unavailable. Leave your details and we will confirm an estimated arrival time or a quote.',
            'button' => 'Ask for an ETA',
        ],
        3 => [
            'key' => 3,
            'label' => 'Out of stock right now',
            'message' => 'Out of stock right now — we can still help. Request a quote and we will come back with availability and pricing.',
            'button' => 'Request a quote',
        ],
    ];

    public static function wordingOption(): int
    {
        $option = (int) config('catalog.oos_wording_option', 1);

        return isset(self::WORDING[$option]) ? $option : 1;
    }

    public static function wording(): array
    {
        return self::WORDING[self::wordingOption()];
    }

    public static function customerMessage(): string
    {
        return self::wording()['message'];
    }

    public static function customerLabel(): string
    {
        return self::wording()['label'];
    }

    public static function ctaLabel(): string
    {
        return self::wording()['button'];
    }

    public static function strippedDescription(?string $html, ?string $short = null): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $html)) ?? '');
        if (mb_strlen($text) >= self::DESCRIPTION_MIN) {
            return $text;
        }

        $shortText = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $short)) ?? '');

        return $shortText !== '' ? $shortText : $text;
    }

    public static function descriptionLength(?string $html, ?string $short = null): int
    {
        return mb_strlen(self::strippedDescription($html, $short));
    }

    public static function hasRealImage($product): bool
    {
        if ($product instanceof Product) {
            $url = $product->primary_image_url;

            return is_string($url) && $url !== '' && ! self::isPlaceholder($url);
        }

        $path = (string) (data_get($product, 'image')
            ?? data_get($product, 'image_path')
            ?? data_get($product, 'primary_image_url')
            ?? '');

        return $path !== '' && ! self::isPlaceholder($path);
    }

    public static function isPlaceholder(string $path): bool
    {
        $lower = strtolower($path);
        if (function_exists('is_catalog_placeholder_path') && is_catalog_placeholder_path($path)) {
            return true;
        }

        return str_contains($lower, 'placeholder') || str_ends_with($lower, '/placeholder');
    }

    public static function hasCost($product): bool
    {
        $cost = data_get($product, 'cost_price');
        if ($cost === null || $cost === '') {
            $cost = data_get($product, 'cost_excl');
        }

        return is_numeric($cost) && (float) $cost > 0;
    }

    public static function hasUsableName($product): bool
    {
        $name = trim((string) data_get($product, 'name', ''));

        return mb_strlen($name) >= 3;
    }

    /**
     * Quality bar used to show a published out-of-stock product in the shop
     * and Google feed (feed already includes all active products): name,
     * 200+ character description, a real picture, and a cost.
     */
    public static function isEligible($product): bool
    {
        if (! self::hasUsableName($product) || ! self::hasCost($product) || ! self::hasRealImage($product)) {
            return false;
        }

        $html = data_get($product, 'description');
        $short = data_get($product, 'short_description');

        return self::descriptionLength($html, $short) >= self::DESCRIPTION_MIN;
    }

    public static function isOutOfStock($product): bool
    {
        if (data_get($product, 'manage_stock')) {
            return (int) data_get($product, 'stock_quantity', 0) <= 0;
        }

        return ! (bool) data_get($product, 'in_stock');
    }

    /**
     * New import-new rows stay hidden drafts when they have stock (existing
     * publish flow). Eligible, zero-stock rows may be published as visible
     * out-of-stock so they can appear in the shop and the merchant feed.
     */
    public static function shouldPublishNewAsVisibleOos($product): bool
    {
        return self::isOutOfStock($product) && self::isEligible($product);
    }

    public static function quotePrefillMessage($product, int $quantity = 1): string
    {
        $name = trim((string) data_get($product, 'name', 'this product'));
        $sku = trim((string) data_get($product, 'sku', ''));
        $skuBit = $sku !== '' ? " (SKU {$sku})" : '';
        $qty = max(1, $quantity);

        return "Please quote or give an ETA for {$qty} × {$name}{$skuBit}.";
    }

    public static function mailtoHref($product, int $quantity = 1): string
    {
        $to = (string) config('app.email', 'sales@urbanfocus.co.za');
        $name = trim((string) data_get($product, 'name', 'this product'));
        $sku = trim((string) data_get($product, 'sku', ''));
        $subject = 'Quote / ETA request: '.($sku !== '' ? $sku.' — ' : '').Str::limit($name, 80, '');
        $body = self::quotePrefillMessage($product, $quantity)
            ."\n\nName:\nEmail:\nPhone:\nQuantity: {$quantity}\n";

        return 'mailto:'.rawurlencode($to)
            .'?subject='.rawurlencode($subject)
            .'&body='.rawurlencode($body);
    }
}
