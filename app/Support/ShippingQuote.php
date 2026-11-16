<?php

namespace App\Support;

/**
 * Courier fee (incl. VAT) for a single-item purchase, used by the Google Merchant
 * feed and kept in lockstep with checkout: R0 when the selling price is strictly
 * above the free threshold, otherwise the paid flat rate.
 *
 * Approved policy (Nov 2026): paid rate R150, threshold R1,500. R201 courier
 * cost is already built into selling prices above R1,500, so those items ship free.
 */
class ShippingQuote
{
    public const DEFAULT_PAID_RATE = 150.00;

    public const DEFAULT_FREE_THRESHOLD = 1500.00;

    /**
     * @param  float  $sellingPrice  Customer-facing price incl. VAT (effective / sale price)
     * @param  float|null  $paidRate  Override for tests; null reads config / default
     * @param  float|null  $threshold  Override for tests; null reads config / default
     */
    public static function charge(float $sellingPrice, ?float $paidRate = null, ?float $threshold = null): float
    {
        $paidRate = $paidRate ?? static::paidRate();
        $threshold = $threshold ?? static::freeThreshold();

        return $sellingPrice > $threshold ? 0.0 : $paidRate;
    }

    public static function paidRate(): float
    {
        if (function_exists('config')) {
            $configured = config('google-merchant.shipping.paid_price');
            if ($configured !== null && $configured !== '') {
                return (float) $configured;
            }

            $flat = config('shipping.flat_rate');
            if ($flat !== null && $flat !== '') {
                return (float) $flat;
            }
        }

        return self::DEFAULT_PAID_RATE;
    }

    public static function freeThreshold(): float
    {
        if (function_exists('config')) {
            $configured = config('google-merchant.shipping.free_threshold');
            if ($configured !== null && $configured !== '') {
                return (float) $configured;
            }

            $threshold = config('shipping.free_threshold');
            if ($threshold !== null && $threshold !== '') {
                return (float) $threshold;
            }
        }

        return self::DEFAULT_FREE_THRESHOLD;
    }
}
