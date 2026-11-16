<?php

namespace App\Support;

/**
 * Customer-facing warranty text. When the period is unknown (null, empty or 0),
 * say "Manufacturer warranty applies" — never invent 12 months or 1 year.
 * Explicit warranty_months values keep the existing wording.
 */
class WarrantyCopy
{
    public const UNKNOWN_LABEL = 'Manufacturer warranty applies';

    public static function periodIsKnown(mixed $months): bool
    {
        if ($months === null || $months === '') {
            return false;
        }

        return (int) $months > 0;
    }

    /**
     * Same wording as today's storefront for a known period:
     * 12+ months -> "N year manufacturer warranty"; otherwise "N month manufacturer warranty".
     */
    public static function label(mixed $months): string
    {
        if (! self::periodIsKnown($months)) {
            return self::UNKNOWN_LABEL;
        }
        $months = (int) $months;

        return $months >= 12
            ? (int) ($months / 12).' year manufacturer warranty'
            : $months.' month manufacturer warranty';
    }

    /**
     * Bob Shop warranty type. Unknown period is still a manufacturer warranty
     * (no invented duration). 0 / not offered stays NOT_OFFERED.
     */
    public static function bobShopType(mixed $months): string
    {
        if ($months === null || $months === '') {
            return 'MANUFACTURER';
        }

        return (int) $months > 0 ? 'MANUFACTURER' : 'NOT_OFFERED';
    }

    /**
     * When the period is unknown, replace generic or stored Warranty spec values
     * so the spec table and JSON-LD cannot claim 12 months / 1 year.
     * Explicit periods leave a richer stored Warranty line in place.
     *
     * @param  array<string, mixed>  $specs
     * @return array<string, mixed>
     */
    public static function sanitizeSpecs(array $specs, mixed $months): array
    {
        if (self::periodIsKnown($months)) {
            return $specs;
        }
        foreach ($specs as $key => $value) {
            if (! is_string($key) || ! preg_match('/warranty/i', $key)) {
                continue;
            }
            $specs[$key] = self::UNKNOWN_LABEL;
        }

        return $specs;
    }

    /**
     * Ensure JSON-LD additionalProperty includes the customer-facing warranty text.
     *
     * @param  list<array<string, mixed>>  $properties
     * @return list<array<string, mixed>>
     */
    public static function withSchemaProperty(array $properties, mixed $months): array
    {
        $label = self::label($months);
        $found = false;
        foreach ($properties as $i => $prop) {
            $name = strtolower(trim((string) ($prop['name'] ?? '')));
            if ($name === 'warranty' || $name === 'warranty period') {
                $properties[$i]['value'] = $label;
                $found = true;
            }
        }
        if (! $found) {
            $properties[] = [
                '@type' => 'PropertyValue',
                'name' => 'Warranty',
                'value' => $label,
            ];
        }

        return $properties;
    }
}
